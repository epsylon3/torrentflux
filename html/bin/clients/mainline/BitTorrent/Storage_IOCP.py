# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Greg Hazel

import os
import sys
import ctypes
import win32file
from bisect import bisect_right
from BTL.translation import _

from BTL import BTFailure
from BTL.defer import Deferred, ThreadedDeferred, Failure, wrap_task
from BTL.yielddefer import launch_coroutine
from BitTorrent.platform import get_allocated_regions
from BTL.sparse_set import SparseSet
from BTL.DictWithLists import DictWithLists, DictWithSets
from BitTorrent.Storage_base import make_file_sparse, bad_libc_workaround, is_open_for_write
from BitTorrent.Storage_base import open_sparse_file as open_sparse_file_base
from BitTorrent.Storage_base import UnregisteredFileException

# not needed, but it raises errors for platforms that don't support iocp
from twisted.internet.iocpreactor import _iocp
from twisted.internet.iocpreactor.proactor import Proactor
from twisted.internet import reactor
assert isinstance(reactor, Proactor), "You imported twisted.internet.reactor before RawServer_twisted!"


class OverlappedOp:

    def initiateOp(self, handle, seekpos, buffer):
        assert len(buffer) > 0
        assert seekpos >= 0
        df = Deferred()
        try:
            self.op(handle, seekpos, buffer,
                    self.ovDone, (handle, buffer))
        except:
            df.errback(Failure())
        else:
            self.df = df
        return df

    def op(self, *a, **kw):
        raise NotImplementedError

    def ovDone(self, ret, bytes, (handle, buffer)):
        df = self.df
        del self.df 
        if ret or not bytes:
            try:
                raise ctypes.WinError()
            except:
                df.errback(Failure())                
        else:
            self.opComplete(df, bytes, buffer)

    def opComplete(self, df, bytes, buffer):    
        raise NotImplementedError


class ReadFileOp(OverlappedOp):

    op = reactor.issueReadFile

    def opComplete(self, df, bytes, buffer):    
        df.callback(buffer[:bytes])


class WriteFileOp(OverlappedOp):

    op = reactor.issueWriteFile

    def opComplete(self, df, bytes, buffer):    
        df.callback(bytes)


class IOCPFile(object):

    # standard block size by default
    buffer_size = 16384
    
    def __init__(self, handle):
        from twisted.internet import reactor
        self.reactor = reactor
        self.handle = handle
        self.osfhandle = win32file._get_osfhandle(self.handle.fileno())
        self.mode = self.handle.mode
        # CloseHandle automatically calls CancelIo
        self.close = self.handle.close
        self.fileno = self.handle.fileno
        self.read_op = ReadFileOp()
        self.write_op = WriteFileOp()
        self.readbuf = self.reactor.AllocateReadBuffer(self.buffer_size)

    def seek(self, offset):
        self.seekpos = offset
    
    def write(self, data):
        return self.write_op.initiateOp(self.osfhandle, self.seekpos, data)

    def read(self, bytes):
        if bytes == self.buffer_size:
            readbuf = self.readbuf
        else:            
            # hmmmm, slow. but, readfile tries to fill the buffer,
            # so maybe this is better than reading too much all the time.
            readbuf = self.reactor.AllocateReadBuffer(bytes)        
        return self.read_op.initiateOp(self.osfhandle, self.seekpos, readbuf)


def open_sparse_file(path, mode, length=0, overlapped=True):
    return IOCPFile(open_sparse_file_base(path, mode, length, overlapped))


class FilePool(object):

    def __init__(self, doneflag, add_task, external_add_task, max_files_open, num_disk_threads):
        self.add_task = add_task
        self.file_to_torrent = {}
        self.waiting_ops = []

        self.active_file_to_handles = DictWithSets()
        self.open_file_to_handles = DictWithLists()

        self.set_max_files_open(max_files_open)

    def close_all(self):
        df = Deferred()
        self._close_all(df)
        return df

    def _close_all(self, df):
        failures = {}

        while len(self.open_file_to_handles) > 0:
            filename, handle = self.open_file_to_handles.popitem()
            try:
                handle.close()
            except:
                failures[self.file_to_torrent[filename]] = Failure()

        for torrent, failure in failures.iteritems():
            torrent.got_exception(failure)

        if self.get_open_file_count() > 0:
            # it would be nice to wait on the deferred for the outstanding ops
            self.add_task(0.5, self._close_all, df)
        else:
            df.callback(True)

    def close_files(self, file_set):
        df = Deferred()
        self._close_files(df, file_set)
        return df

    def _close_files(self, df, file_set):
        failure = None
        done = False

        filenames = self.open_file_to_handles.keys()
        for filename in filenames:
            if filename not in file_set:
                continue
            handles = self.open_file_to_handles.poprow(filename)
            for handle in handles:
                try:
                    handle.close()
                except:
                    failure = Failure()

        done = True
        for filename in file_set.iterkeys():
            if filename in self.active_file_to_handles:
                done = False
                break

        if failure is not None:
            df.errback(failure)

        if not done:
            # it would be nice to wait on the deferred for the outstanding ops
            self.add_task(0.5, self._close_files, df, file_set)
        else:
            df.callback(True)

    def set_max_files_open(self, max_files_open):
        if max_files_open <= 0:
            max_files_open = 1e100
        self.max_files_open = max_files_open
        self.close_all()

    def add_files(self, files, torrent):
        for filename in files:
            if filename in self.file_to_torrent:
                raise BTFailure(_("File %s belongs to another running torrent")
                                % filename)
        for filename in files:
            self.file_to_torrent[filename] = torrent

    def remove_files(self, files):
        for filename in files:
            del self.file_to_torrent[filename]

    def _ensure_exists(self, filename, length=0):
        if not os.path.exists(filename):
            f = os.path.split(filename)[0]
            if f != '' and not os.path.exists(f):
                os.makedirs(f)
            f = file(filename, 'wb')
            make_file_sparse(filename, f, length)
            f.close()

    def get_open_file_count(self):
        t = self.open_file_to_handles.total_length()
        t += self.active_file_to_handles.total_length()
        return t

    def free_handle_notify(self):
        if self.waiting_ops:
            args = self.waiting_ops.pop(0)
            self._produce_handle(*args)

    def acquire_handle(self, filename, for_write, length=0):
        df = Deferred()

        if filename not in self.file_to_torrent:
            raise UnregisteredFileException()
        
        if self.active_file_to_handles.total_length() == self.max_files_open:
            self.waiting_ops.append((df, filename, for_write, length))
        else:
            self._produce_handle(df, filename, for_write, length)
            
        return df

    def _produce_handle(self, df, filename, for_write, length):
        if filename in self.open_file_to_handles:
            handle = self.open_file_to_handles.pop_from_row(filename)
            if for_write and not is_open_for_write(handle.mode):
                handle.close()
                handle = open_sparse_file(filename, 'rb+', length=length)
            #elif not for_write and is_open_for_write(handle.mode):
            #    handle.close()
            #    handle = file(filename, 'rb', 0)
        else:
            if self.get_open_file_count() == self.max_files_open:
                oldfname, oldhandle = self.open_file_to_handles.popitem()
                oldhandle.close()
            self._ensure_exists(filename, length)
            if for_write:
                handle = open_sparse_file(filename, 'rb+', length=length)
            else:
                handle = open_sparse_file(filename, 'rb', length=length)

        self.active_file_to_handles.push_to_row(filename, handle)
        df.callback(handle)

    def release_handle(self, filename, handle):
        self.active_file_to_handles.remove_fom_row(filename, handle)
        self.open_file_to_handles.push_to_row(filename, handle)
        self.free_handle_notify()



class Storage(object):

    def __init__(self, config, filepool, save_path, files, add_task,
                 external_add_task, doneflag):
        self.filepool = filepool
        self.config = config
        self.doneflag = doneflag
        self.add_task = add_task
        self.external_add_task = external_add_task
        self.initialize(save_path, files)

    def initialize(self, save_path, files):
        # a list of bytes ranges and filenames for window-based IO
        self.ranges = []
        # a dict of filename-to-ranges for piece priorities and filename lookup
        self.range_by_name = {}
        # a sparse set for smart allocation detection
        self.allocated_regions = SparseSet()

        # dict of filename-to-length on disk (for % complete in the file view)
        self.undownloaded = {}
        self.save_path = save_path

        # Rather implement this as an ugly hack here than change all the
        # individual calls. Affects all torrent instances using this module.
        if self.config['bad_libc_workaround']:
            bad_libc_workaround()

        self.initialized = False
        self.startup_df = ThreadedDeferred(wrap_task(self.external_add_task),
                                           self._build_file_structs,
                                           self.filepool, files)
        return self.startup_df

    def _build_file_structs(self, filepool, files):
        total = 0
        for filename, length in files:
            # we're shutting down, abort.
            if self.doneflag.isSet():
                return False

            self.undownloaded[filename] = length
            if length > 0:
                self.ranges.append((total, total + length, filename))

            self.range_by_name[filename] = (total, total + length)

            if os.path.exists(filename):
                if not os.path.isfile(filename):
                    raise BTFailure(_("File %s already exists, but is not a "
                                      "regular file") % filename)
                l = os.path.getsize(filename)
                if l > length:
                    # This is the truncation Bram was talking about that no one
                    # else thinks is a good idea.
                    #h = file(filename, 'rb+')
                    #make_file_sparse(filename, h, length)
                    #h.truncate(length)
                    #h.close()
                    l = length

                a = get_allocated_regions(filename, begin=0, length=l)
                if a is not None:
                    a.offset(total)
                else:
                    a = SparseSet()
                    if l > 0:
                        a.add(total, total + l)
                self.allocated_regions += a
            total += length
        self.total_length = total
        self.initialized = True
        return True

    def get_byte_range_for_filename(self, filename):
        if filename not in self.range_by_name:
            filename = os.path.normpath(filename)
            filename = os.path.join(self.save_path, filename)
        return self.range_by_name[filename]

    def was_preallocated(self, pos, length):
        return self.allocated_regions.is_range_in(pos, pos+length)

    def get_total_length(self):
        return self.total_length

    def _intervals(self, pos, amount):
        r = []
        stop = pos + amount
        p = max(bisect_right(self.ranges, (pos, 2 ** 500)) - 1, 0)
        for begin, end, filename in self.ranges[p:]:
            if begin >= stop:
                break
            r.append((filename,
                      max(pos, begin) - begin, min(end, stop) - begin))
        return r

    def _file_op(self, filename, pos, param, write):
        begin, end = self.get_byte_range_for_filename(filename)
        length = end - begin
        hdf = self.filepool.acquire_handle(filename, for_write=write,
                                           length=length)
        def op(h):
            h.seek(pos)
            if write:
                odf = h.write(param)
            else:
                odf = h.read(param)
            def like_finally(r):
                self.filepool.release_handle(filename, h)
                return r
            odf.addBoth(like_finally)
            return odf
        hdf.addCallback(op)
        return hdf

    def _batch_read(self, pos, amount):
        dfs = []
        r = []

        # queue all the reads
        for filename, pos, end in self._intervals(pos, amount):
            df = self._file_op(filename, pos, end - pos, write=False)
            dfs.append(df)

        # yield on all the reads in order - they complete in any order
        exc = None
        for df in dfs:
            yield df
            try:
                r.append(df.getResult())
            except:
                exc = exc or sys.exc_info()
        if exc:
            raise exc[0], exc[1], exc[2]

        r = ''.join(r)

        if len(r) != amount:
            raise BTFailure(_("Short read (%d of %d) - "
                              "something truncated files?") %
                            (len(r), amount))

        yield r

    def read(self, pos, amount):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._batch_read, pos, amount)
        return df

    def _batch_write(self, pos, s):
        dfs = []

        total = 0
        amount = len(s)

        # queue all the writes
        for filename, begin, end in self._intervals(pos, amount):
            length = end - begin
            assert length > 0, '%s %s' % (pos, amount)
            d = buffer(s, total, length)
            total += length
            df = self._file_op(filename, begin, d, write=True)
            dfs.append(df)
        assert total == amount, '%s and %s' % (total, amount)

        written = 0            
        # yield on all the writes - they complete in any order
        exc = None
        for df in dfs:
            yield df
            try:
                written += df.getResult()            
            except:
                exc = exc or sys.exc_info()
        if exc:
            raise exc[0], exc[1], exc[2]
        assert total == written, '%s and %s' % (total, written)
        
        yield total

    def write(self, pos, s):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._batch_write, pos, s)
        return df

    def close(self):
        if not self.initialized:
            def post_init(r):
                return self.filepool.close_files(self.range_by_name)
            self.startup_df.addCallback(post_init)
            return self.startup_df
        df = self.filepool.close_files(self.range_by_name)
        return df

    def downloaded(self, pos, length):
        for filename, begin, end in self._intervals(pos, length):
            self.undownloaded[filename] -= end - begin
