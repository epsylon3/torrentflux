# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen and Greg Hazel

from __future__ import division
from __future__ import generators

import os
import sys
import struct
import cPickle
import logging
from array import array
from BTL.translation import _

from BTL.obsoletepythonsupport import set
from BTL.sparse_set import SparseSet
from BTL.bitfield import Bitfield
from BTL import defer
from BTL.defer import wrap_task
from BTL.yielddefer import launch_coroutine
from BitTorrent import BTFailure
from BTL.exceptions import str_exc

from BTL.hash import sha

NO_PLACE = -1

ALLOCATED = -1
UNALLOCATED = -2
FASTRESUME_PARTIAL = -3

global_logger = logging.getLogger('StorageWrapper')
#global_logger.setLevel(logging.DEBUG)
#global_logger.addHandler(logging.StreamHandler(sys.stdout))


class DataPig(object):
    def __init__(self, read, add_task):
        self.add_task = add_task
        self.read = read
        self.failed_pieces = {}
        self.download_history = {}

    def got_piece(self, index, begin, length, source):
        if index in self.failed_pieces:
            df = launch_coroutine(wrap_task(self.add_task),
                                  self._got_piece,
                                  index, begin, length, source)
            return df
        self.download_history.setdefault(index, {})
        self.download_history[index][begin] = source

    def _got_piece(self, index, begin, piece, source):
        df = self.read(index, len(piece), offset=begin)
        yield df
        data = df.getResult()
        if data != piece:
            if (index in self.download_history and
                begin in self.download_history[index]):
                d = self.download_history[index][begin]
                self.failed_pieces[index].add(d)
        self.download_history.setdefault(index, {})
        self.download_history[index][begin] = source

    def finished_piece(self, index):
        for d in self.download_history[index].itervalues():
            d.good(index)
        del self.download_history[index]
        if index in self.failed_pieces:
            for d in self.failed_pieces[index]:
                d.bad(index)
            del self.failed_pieces[index]

    def failed_piece(self, index):
        self.failed_pieces[index] = set()
        allsenders = {}
        for d in self.download_history[index].itervalues():
            allsenders[d] = None
        if len(allsenders) == 1:
            culprit = allsenders.keys()[0]
            culprit.bad(index, bump = True)
            del self.failed_pieces[index] # found the culprit already

current_version = 2
resume_prefix = 'BitTorrent resume state file, version '
version_string = resume_prefix + str(current_version)

class StorageWrapper(object):

    READ_AHEAD_BUFFER_SIZE = 2**22 # 4mB

    def __init__(self, storage, rm, config, hashes, piece_size,
                 statusfunc, doneflag, data_flunked,
                 infohash, # needed for partials
                 is_batch, errorfunc, working_path, destination_path, resumefile,
                 add_task, external_add_task):
        assert len(hashes) > 0
        assert piece_size > 0
        self.initialized = False
        self.numpieces = len(hashes)
        self.infohash = infohash
        self.is_batch = is_batch
        self.add_task = add_task
        self.external_add_task = external_add_task
        self.storage = storage
        self.config = config
        self.doneflag = doneflag
        self.hashes = hashes
        self.piece_size = piece_size
        self.data_flunked = data_flunked
        self.errorfunc = errorfunc
        self.statusfunc = statusfunc
        self.total_length = storage.get_total_length()
        # a brief explanation about the mildly confusing amount_ variables:
        #   amount_left: total_length - fully_written_pieces
        #   amount_inactive: amount_left - blocks_written - requests_pending_on_network
        #   amount_left_with_partials (only correct during startup): amount_left + blocks_written
        self.amount_left = self.total_length
        if self.total_length <= piece_size * (self.numpieces - 1):
            raise BTFailure(_("bad data in torrent file - total too small"))
        if self.total_length > piece_size * self.numpieces:
            raise BTFailure(_("bad data in torrent file - total too big"))

        self.have_callbacks = {}

        # a index => df dict for locking pieces
        self.blocking_pieces = {}
        self.have = Bitfield(self.numpieces)
        self.have_set = SparseSet()
        self.checked_pieces = SparseSet()
        self.fastresume = False
        self.fastresume_dirty = False

        self._pieces_in_buf = []
        self._piece_buf = None

        self.partial_mark = None

        if self.numpieces < 32768:
            self.typecode = 'h'
        else:
            self.typecode = 'l'

        self.rm = rm
        self.rm.amount_inactive = self.total_length

        read = lambda i, l, offset : self._storage_read(self.places[i], l,
                                                        offset=offset)
        self.datapig = DataPig(read, self.add_task)

        self.places = array(self.typecode, [NO_PLACE] * self.numpieces)
        check_hashes = self.config['check_hashes']

        self.done_checking_df = defer.Deferred()
        self.lastlen = self._piecelen(self.numpieces - 1)

        global_logger.debug("Loading fastresume...")
        if not check_hashes:
            self.rplaces = array(self.typecode, range(self.numpieces))
            self.places = self.rplaces
            self.amount_left = 0
            self.rm.amount_inactive = self.amount_left
            self.amount_left_with_partials = self.rm.amount_inactive
            self.have.numfalse = 0
            self.have.bits = None
            self.have_set.add(0, self.numpieces)
            self._initialized(True)
        else:
            try:
                result = self.read_fastresume(resumefile, working_path,
                                              destination_path)
                # if resume file doesn't apply to this destination or
                # working path then start over.
                if not result:
                    self.rplaces = array(self.typecode, [UNALLOCATED] * self.numpieces)
                    # full hashcheck
                    df = self.hashcheck_pieces()
                    df.addCallback(self._initialized)
            except:
                # if resumefile is not None:
                #    global_logger.warning("Failed to read fastresume",
                #                          exc_info=sys.exc_info())
                self.rplaces = array(self.typecode, [UNALLOCATED] * self.numpieces)
                # full hashcheck
                df = self.hashcheck_pieces()
                df.addCallback(self._initialized)

    def _initialized(self, v):
        self._pieces_in_buf = []
        self._piece_buf = None
        self.initialized = v
        global_logger.debug('Initialized')
        self.done_checking_df.callback(v)

    ## fastresume
    ############################################################################
    def read_fastresume(self, f, working_path, destination_path):
        version_line = f.readline().strip()
        try:
            resume_version = version_line.split(resume_prefix)[1]
        except Exception, e:
            raise BTFailure(_("Unsupported fastresume file format, "
                              "probably corrupted: %s on (%s)") %
                            (str_exc(e), repr(version_line)))
        global_logger.debug('Reading fastresume v' + resume_version)
        if resume_version == '1':
            return self._read_fastresume_v1(f, working_path, destination_path)
        elif resume_version == '2':
            return self._read_fastresume_v2(f, working_path, destination_path)
        else:
            raise BTFailure(_("Unsupported fastresume file format, "
                              "maybe from another client version?"))

    def _read_fastresume_v1(self, f, working_path, destination_path):
        # skip a bunch of lines
        amount_done = int(f.readline())
        for b, e, filename in self.storage.ranges:
            line = f.readline()

        # now for the good stuff
        r = array(self.typecode)
        r.fromfile(f, self.numpieces)

        self.rplaces = r

        df = self.checkPieces_v1()
        df.addCallback(self._initialized)

    def checkPieces_v1(self):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._checkPieces_v1)
        return df

    def _checkPieces_v1(self):
        partials = {}

        needs_full_hashcheck = False
        for i in xrange(self.numpieces):

            piece_len = self._piecelen(i)

            t = self.rplaces[i]
            if t >= 0:
                self._markgot(t, i)
            elif t in (ALLOCATED, UNALLOCATED):
                pass
            elif t == FASTRESUME_PARTIAL:
                df = self._storage_read(i, piece_len)
                yield df
                try:
                    data = df.getResult()
                except:
                    global_logger.error(_("Bad fastresume info "
                                          "(truncation at piece %d)") % i)
                    needs_full_hashcheck = True
                    i -= 1
                    break
                self._check_partial(i, partials, data)
                self.rplaces[i] = ALLOCATED

                # we're shutting down, abort.
                if self.doneflag.isSet():
                    yield False
            else:
                global_logger.error(_("Bad fastresume info (illegal value at "
                                      "piece %d)") % i)
                needs_full_hashcheck = True
                i -= 1
                break

        if needs_full_hashcheck:
            df = self.hashcheck_pieces(i)
            yield df
            r = df.getResult()
            if r == False:
                yield False

        self._realize_partials(partials)
        yield True

    def _read_fastresume_v2(self, f, working_path, destination_path):
        # The working and destination paths are "save_as" paths meaning
        # that they refer to the entire path for a single-file torrent and the
        # name of the directory containing the files for a batch torrent.

        # Path read from resume should either reside in/at the
        # working_path or the destination_path.

        d = cPickle.loads(f.read())

        try:
            snapshot = d['snapshot']
            work_or_dest = 0
            for filename, s in snapshot.iteritems():
                # all files should reside in either the working path or the
                # destination path.  For batch torrents, the file may have a
                # relative path so compare common path.
                if self.is_batch:
                    commonw = os.path.commonprefix((filename, working_path))
                    commond = os.path.commonprefix((filename, destination_path))
                else:
                    commonw = commond = filename

                # first file determines whether all are in work or dest path.
                if work_or_dest == 0:
                    if commonw == working_path:
                        work_or_dest = -1
                    elif commond == destination_path:
                        work_or_dest = 1
                    else:
                        return False
                elif work_or_dest == -1 and commonw != working_path:
                    return False
                elif work_or_dest == 1 and commond != destination_path:
                    return False

                # this could be a lot smarter, like punching holes in the
                # ranges on failed files in a batch torrent.
                if not os.path.exists(filename):
                    raise ValueError("No such file or directory: %s" % filename)
                if os.path.getsize(filename) < s['size']:
                    raise ValueError("File sizes do not match.")
                if os.path.getmtime(filename) < (s['mtime'] - 5):
                    raise ValueError("File modification times do not match.")

            self.places = array(self.typecode)
            self.places.fromstring(d['places'])
            self.rplaces = array(self.typecode)
            self.rplaces.fromstring(d['rplaces'])
            self.have = d['have']
            self.have_set = d['have_set']
            # We are reading the undownloaded section from the fast resume.
            # We should check whether the file exists.  If it doesn't then
            # we should not read from fastresume.
            self.storage.undownloaded = d['undownloaded']
            self.amount_left = d['amount_left']
            assert self.amount_left >= 0

            self.rm.amount_inactive = self.amount_left

            # all unwritten partials are now inactive
            for k, v in d['unwritten_partials'].iteritems():
                self.rm.add_inactive(k, v)

            # these are equal at startup, because nothing has been requested
            self.amount_left_with_partials = self.rm.amount_inactive

            if self.amount_left_with_partials < 0:
                raise ValueError("Amount left < 0: %d" %
                                 self.amount_left_with_partials)
            if self.amount_left_with_partials > self.total_length:
                raise ValueError("Amount left > total length: %d > %d" %
                                 (self.amount_left_with_partials, self.total_length))

            self._initialized(True)
        except:
            self.amount_left = self.total_length
            self.have = Bitfield(self.numpieces)
            self.have_set = SparseSet()
            self.rm.inactive_requests = {}
            self.rm.active_requests = {}
            self.places = array(self.typecode, [NO_PLACE] * self.numpieces)
            self.rplaces = array(self.typecode, range(self.numpieces))
            raise

        return True


    def write_fastresume(self, resumefile):
        try:
            self._write_fastresume_v2(resumefile)
        except:
            global_logger.exception("write_fastresume failed")

    def _write_fastresume_v2(self, resumefile):
        if not self.initialized:
            return

        global_logger.debug('Writing fast resume: %s' % version_string)
        resumefile.write(version_string + '\n')

        d = {}

        snapshot = {}
        for filename in self.storage.range_by_name.iterkeys():
            if not os.path.exists(filename):
                continue
            s = {}
            s['size'] = os.path.getsize(filename)
            s['mtime'] = os.path.getmtime(filename)
            snapshot[filename] = s
        d['snapshot'] = snapshot

        d['places'] = self.places.tostring()
        d['rplaces'] = self.rplaces.tostring()
        d['have'] = self.have
        d['have_set'] = self.have_set
        d['undownloaded'] = self.storage.undownloaded
        d['amount_left'] = self.amount_left
        d['unwritten_partials'] = self.rm.get_unwritten_requests()

        resumefile.write(cPickle.dumps(d))

        self.fastresume_dirty = False
    ############################################################################

    def _markgot(self, piece, pos):
        if self.have[piece]:
            if piece != pos:
                return
            self.rplaces[self.places[pos]] = ALLOCATED
            self.places[pos] = self.rplaces[pos] = pos
            return
        self.places[piece] = pos
        self.rplaces[pos] = piece
        self.have[piece] = True
        self.have_set.add(piece)
        plen = self._piecelen(piece)
        self.storage.downloaded(self.piece_size * piece, plen)
        self.amount_left -= plen
        assert self.amount_left >= 0
        self.rm.amount_inactive -= plen
        if piece in self.have_callbacks:
            for c in self.have_callbacks.pop(piece):
                c.callback(None)
        assert piece not in self.rm.inactive_requests

    ## hashcheck
    ############################################################################
    def _get_data(self, i):
        if i in self._pieces_in_buf:
            p = i - self._pieces_in_buf[0]
            return buffer(self._piece_buf, p * self.piece_size, self._piecelen(i))
        df = launch_coroutine(wrap_task(self.add_task),
                              self._get_data_gen, i)
        return df

    def _get_data_gen(self, i):
        num_pieces = int(max(1, self.READ_AHEAD_BUFFER_SIZE / self.piece_size))
        if i + num_pieces >= self.numpieces:
            size = self.total_length - (i * self.piece_size)
            num_pieces = self.numpieces - i
        else:
            size = num_pieces * self.piece_size
        self._pieces_in_buf = range(i, i + num_pieces)
        df = self._storage_read(i, size)
        yield df
        try:
            self._piece_buf = df.getResult()
        except BTFailure: # short read
            self._piece_buf = ''
        p = i - self._pieces_in_buf[0]
        yield buffer(self._piece_buf, p * self.piece_size, self._piecelen(i))

    def hashcheck_pieces(self, begin=0, end=None):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._hashcheck_pieces,
                              begin, end)
        return df

    def _hashcheck_pieces(self, begin=0, end=None):
        # we need a full reverse-lookup of hashes for out of order compatability
        targets = {}
        for i in xrange(self.numpieces):
            targets[self.hashes[i]] = i
        partials = {}

        if end is None:
            end = self.numpieces

        global_logger.debug('Hashcheck from %d to %d' % (begin, end))

        # TODO: make this work with more than one running at a time
        for i in xrange(begin, end):

            # we're shutting down, abort.
            if self.doneflag.isSet():
                yield False

            piece_len = self._piecelen(i)
            global_logger.debug( "i=%d, piece_len=%d" % (i,piece_len) )

            if not self._waspre(i, piece_len):
                # hole in the file
                continue

            r = self._get_data(i)
            if isinstance(r, defer.Deferred):
                yield r
                data = r.getResult()
            else:
                data = r

            sh = sha(buffer(data, 0, self.lastlen))
            sp = sh.digest()
            sh.update(buffer(data, self.lastlen))
            s = sh.digest()
            # handle out-of-order pieces
            if s in targets and piece_len == self._piecelen(targets[s]):
                # handle one or more pieces with identical hashes properly
                piece_found = i
                if s != self.hashes[i]:
                    piece_found = targets[s]
                self.checked_pieces.add(piece_found)
                self._markgot(piece_found, i)
            # last piece junk. I'm not even sure this is right.
            elif (not self.have[self.numpieces - 1] and
                  sp == self.hashes[-1] and
                  (i == self.numpieces - 1 or
                   not self._waspre(self.numpieces - 1))):
                self.checked_pieces.add(self.numpieces - 1)
                self._markgot(self.numpieces - 1, i)
            else:
                self._check_partial(i, partials, data)
            self.statusfunc(fractionDone = 1 - self.amount_left /
                            self.total_length)

        global_logger.debug('Hashcheck from %d to %d complete.' % (begin, end))

        self._realize_partials(partials)
        self.fastresume_dirty = True
        yield True

    def hashcheck_piece(self, index, data = None):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._hashcheck_piece,
                              index, data = data)
        return df

    def _hashcheck_piece(self, index, data = None):
        if not data:
            df = self._storage_read(index, self._piecelen(index))
            yield df
            data = df.getResult()
        if sha(data).digest() != self.hashes[index]:
            yield False
        self.checked_pieces.add(index)
        yield True
    ############################################################################

    ## out of order compatability
    ############################################################################
    def _initalloc(self, pos, piece):
        assert self.rplaces[pos] < 0
        assert self.places[piece] == NO_PLACE
        p = self.piece_size * pos
        length = self._piecelen(pos)
        self.places[piece] = pos
        self.rplaces[pos] = piece

    def _move_piece(self, oldpos, newpos):
        assert self.rplaces[newpos] < 0
        assert self.rplaces[oldpos] >= 0
        df = self._storage_read(oldpos, self._piecelen(newpos))
        yield df
        data = df.getResult()
        df = self._storage_write(newpos, data)
        yield df
        df.getResult()
        piece = self.rplaces[oldpos]
        self.places[piece] = newpos
        self.rplaces[oldpos] = ALLOCATED
        self.rplaces[newpos] = piece
        if not self.have[piece]:
            return
        data = buffer(data, 0, self._piecelen(piece))
        if sha(data).digest() != self.hashes[piece]:
            raise BTFailure(_("data corrupted on disk - "
                              "maybe you have two copies running?"))
    ############################################################################

    def get_piece_range_for_filename(self, filename):
        begin, end = self.storage.get_byte_range_for_filename(filename)
        begin = int(begin / self.piece_size)
        end = int(end / self.piece_size)
        return begin, end

    def _waspre(self, piece, piece_len=None):
        if piece_len is None:
            piece_len = self._piecelen(piece)
        return self.storage.was_preallocated(piece * self.piece_size, piece_len)

    def _piecelen(self, piece):
        if piece < self.numpieces - 1:
            return self.piece_size
        else:
            return self.total_length - piece * self.piece_size

    def get_total_length(self):
        """Returns the total length of the torrent in bytes."""
        return self.total_length

    def get_num_pieces(self):
        """Returns the total number of pieces in this torrent."""
        return self.numpieces

    def get_amount_left(self):
        """Returns the number of bytes left to download."""
        return self.amount_left

    def do_I_have_anything(self):
        return self.amount_left < self.total_length

    def get_have_list(self):
        return self.have.tostring()

    def do_I_have(self, index):
        return self.have[index]

    def _block_piece(self, index, df):
        self.blocking_pieces[index] = df
        df.addCallback(lambda x: self.blocking_pieces.pop(index))
        return df

    def write(self, index, begin, piece, source):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._write,
                              index, begin, piece, source)
        return df

    def _write(self, index, begin, piece, source):

        if index in self.blocking_pieces:
            df = self.blocking_pieces[index]
            yield df
            df.getResult()

        if self.places[index] < 0:
            # since old versions of BT wrote out-of-order, we could
            # come across a piece which is misplaced. move it to the
            # correct place.
            if self.rplaces[index] >= 0:
                new_pos = self.rplaces[index]
                df = launch_coroutine(wrap_task(self.add_task),
                                      self._move_piece, index, new_pos)
                yield self._block_piece(index, df)
                df.getResult()

            self._initalloc(index, index)

        df = self.datapig.got_piece(index, begin, piece, source)
        if df is not None:
            yield df
            df.getResult()

        df = self._storage_write(self.places[index], piece, offset=begin)
        yield df
        df.getResult()

        self.rm.request_received(index, begin, len(piece))

        hashcheck = self.rm.is_piece_received(index)
        if hashcheck:
            df = self.hashcheck_piece(self.places[index])
            yield df
            passed = df.getResult()
            self.rm.piece_finished(index)
            length = self._piecelen(index)
            if passed:
                self.have[index] = True
                self.have_set.add(index)
                self.storage.downloaded(index * self.piece_size, length)
                self.amount_left -= length
                assert self.amount_left >= 0

                self.datapig.finished_piece(index)
                if index in self.have_callbacks:
                    for c in self.have_callbacks.pop(index):
                        c.callback(None)
            else:
                self.data_flunked(length, index)
                self.rm.amount_inactive += length

                self.datapig.failed_piece(index)
        self.fastresume_dirty = True
        yield hashcheck

    def get_piece(self, index):
        if not self.have[index]:
            df = defer.Deferred()
            self.have_callbacks.setdefault(index, []).append(df)
            yield df
            df.getResult()
            assert self.have[index]
        df = self.read(index, 0, self._piecelen(index))
        yield df
        r = df.getResult()
        yield r

    def read(self, index, begin, length):
        if not self.have[index]:
            raise IndexError("Do not have piece %d of %d" %
                             (index, self.numpieces))
        df = launch_coroutine(wrap_task(self.add_task),
                              self._read, index, begin, length)
        return df

    def _read(self, index, begin, length):
        if index in self.blocking_pieces:
            df = self.blocking_pieces[index]
            yield df
            df.getResult()

        if index not in self.checked_pieces:
            df = self.hashcheck_piece(self.places[index])
            yield df
            passed = df.getResult()
            if not passed:
                # TODO: this case should cause a total file hash check and
                # reconnect when done.
                raise BTFailure, _("told file complete on start-up, but piece "
                                   "failed hash check")
        if begin + length > self._piecelen(index):
            #yield None
            raise ValueError("incorrect size: (%d + %d ==) %d >= %d" %
                             (begin, length,
                              begin + length, self._piecelen(index)))
        df = self._storage_read(self.places[index], length, offset=begin)
        yield df
        data = df.getResult()
        yield data

    def _storage_read(self, index, amount, offset=0):
        assert index >= 0
        return self.storage.read(index * self.piece_size + offset, amount)

    def _storage_write(self, index, data, offset=0):
        return self.storage.write(index * self.piece_size + offset, data)

    ## partials
    ############################################################################
    def _realize_partials(self, partials):
        self.amount_left_with_partials = self.amount_left
        for piece in partials:
            if self.places[piece] < 0:
                pos = partials[piece][0]
                self.places[piece] = pos
                self.rplaces[pos] = piece

    def _check_partial(self, pos, partials, data):
        index = None
        missing = False
        request_size = self.config['download_chunk_size']
        if self.partial_mark is None:
            i = struct.pack('>i', request_size)
            self.partial_mark = ("BitTorrent - this part has not been " +
                                 "downloaded yet." + self.infohash + i)
        marklen = len(self.partial_mark) + 4
        for i in xrange(0, len(data) - marklen, request_size):
            if data[i:i+marklen-4] == self.partial_mark:
                ind = struct.unpack('>i', data[i+marklen-4:i+marklen])[0]
                if index is None:
                    index = ind
                    parts = []
                if ind >= self.numpieces or ind != index:
                    return
                parts.append(i)
            else:
                missing = True
        if index is not None and missing:
            i += request_size
            if i < len(data):
                parts.append(i)
            partials[index] = (pos, parts)

    def _make_pending(self, index, parts):
        length = self._piecelen(index)
        x = 0
        request_size = self.config['download_chunk_size']
        for x in xrange(0, length, request_size):
            if x not in parts:
                partlen = min(request_size, length - x)
                self.amount_left_with_partials -= partlen
    ############################################################################
