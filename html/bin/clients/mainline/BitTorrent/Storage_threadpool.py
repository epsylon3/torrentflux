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

import os
import sys
import Queue
from bisect import bisect_right
from BTL.translation import _

from BTL.obsoletepythonsupport import set

from BitTorrent import BTFailure
from BTL.defer import Deferred, ThreadedDeferred, Failure, wrap_task
from BTL.yielddefer import launch_coroutine
from BitTorrent.platform import get_allocated_regions
from BTL.sparse_set import SparseSet
from BTL.DictWithLists import DictWithLists, DictWithSets
import BTL.stackthreading as threading
from BitTorrent.Storage_base import open_sparse_file, make_file_sparse
from BitTorrent.Storage_base import bad_libc_workaround, is_open_for_write
from BitTorrent.Storage_base import UnregisteredFileException


class FilePool(object):

    def __init__(self, doneflag, add_task, external_add_task,
                 max_files_open, num_disk_threads):
        self.doneflag = doneflag
        self.external_add_task = external_add_task
        self.file_to_torrent = {}

        self.free_handle_condition = threading.Condition()
        self.active_file_to_handles = DictWithSets()
        self.open_file_to_handles = DictWithLists()

        self.set_max_files_open(max_files_open)

        self.diskq = Queue.Queue()
        for i in xrange(num_disk_threads):
            t = threading.Thread(target=self._disk_thread,
                                 name="disk_thread-%s" % (i+1))
            t.start()

        self.doneflag.addCallback(self.finalize)

    def finalize(self, r=None):
        # re-queue self so all threads die. we end up with one extra event on
        # the queue, but who cares.
        self._create_op(self.finalize)

    def close_all(self):
        failures = {}
        self.free_handle_condition.acquire()
        while self.get_open_file_count() > 0:
            while len(self.open_file_to_handles) > 0:
                filename, handle = self.open_file_to_handles.popitem()
                try:
                    handle.close()
                except Exception, e:
                    failures[self.file_to_torrent[filename]] = e
                self.free_handle_condition.notify()
            if self.get_open_file_count() > 0:
                self.free_handle_condition.wait(1)
        self.free_handle_condition.release()

        for torrent, e in failures.iteritems():
            torrent.got_exception(e)

    def close_files(self, file_set):
        failures = set()
        self.free_handle_condition.acquire()
        done = False

        while not done:

            filenames = list(self.open_file_to_handles.iterkeys())
            for filename in filenames:
                if filename not in file_set:
                    continue
                handles = self.open_file_to_handles.poprow(filename)
                for handle in handles:
                    try:
                        handle.close()
                    except Exception, e:
                        failures.add(e)
                    self.free_handle_condition.notify()

            done = True
            for filename in file_set.iterkeys():
                if filename in self.active_file_to_handles:
                    done = False
                    break

            if not done:
                self.free_handle_condition.wait(0.5)
        self.free_handle_condition.release()
        if len(failures) > 0:
            raise failures.pop()

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

    def acquire_handle(self, filename, for_write, length=0):
        # this will block until a new file handle can be made
        self.free_handle_condition.acquire()

        if filename not in self.file_to_torrent:
            self.free_handle_condition.release()
            raise UnregisteredFileException()

        while self.active_file_to_handles.total_length() == self.max_files_open:
            self.free_handle_condition.wait()

        if filename in self.open_file_to_handles:
            handle = self.open_file_to_handles.pop_from_row(filename)
            if for_write and not is_open_for_write(handle.mode):
                handle.close()
                handle = open_sparse_file(filename, 'rb+', length=length)
            #elif not for_write and is_open_for_write(handle.mode):
            #    handle.close()
            #    handle = open_sparse_file(filename, 'rb', length=length)
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
        self.free_handle_condition.release()
        return handle

    def release_handle(self, filename, handle):
        self.free_handle_condition.acquire()
        self.active_file_to_handles.remove_fom_row(filename, handle)
        self.open_file_to_handles.push_to_row(filename, handle)
        self.free_handle_condition.notify()
        self.free_handle_condition.release()

    def _create_op(self, _f, *args, **kwargs):
        df = Deferred()
        self.diskq.put((df, _f, args, kwargs))
        return df
    read = _create_op
    write = _create_op

    def _disk_thread(self):
        while not self.doneflag.isSet():
            df, func, args, kwargs = self.diskq.get(True)
            try:
                v = func(*args, **kwargs)
            except:
                self.external_add_task(0, df.errback, Failure())
            else:
                self.external_add_task(0, df.callback, v)


class Storage(object):

    def __init__(self, config, filepool, save_path,
                 files, add_task,
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
            r.append((filename, max(pos, begin) - begin, min(end, stop) - begin))
        return r

    def _read(self, filename, pos, amount):
        begin, end = self.get_byte_range_for_filename(filename)
        length = end - begin
        h = self.filepool.acquire_handle(filename, for_write=False, length=length)
        if h is None:
            return
        try:
            h.seek(pos)
            r = h.read(amount)
        finally:
            self.filepool.release_handle(filename, h)
        return r

    def _batch_read(self, pos, amount):
        dfs = []
        r = []

        # queue all the reads
        for filename, pos, end in self._intervals(pos, amount):
            df = self.filepool.read(self._read, filename, pos, end - pos)
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
            raise BTFailure(_("Short read (%d of %d) - something truncated files?") %
                            (len(r), amount))

        yield r

    def read(self, pos, amount):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._batch_read, pos, amount)
        return df

    def _write(self, filename, pos, s):
        begin, end = self.get_byte_range_for_filename(filename)
        length = end - begin
        h = self.filepool.acquire_handle(filename, for_write=True, length=length)
        if h is None:
            return
        try:
            h.seek(pos)
            h.write(s)
        finally:
            self.filepool.release_handle(filename, h)
        return len(s)

    def _batch_write(self, pos, s):
        dfs = []

        total = 0
        amount = len(s)

        # queue all the writes
        for filename, begin, end in self._intervals(pos, amount):
            length = end - begin
            d = buffer(s, total, length)
            total += length
            df = self.filepool.write(self._write, filename, begin, d)
            dfs.append(df)

        # yield on all the writes - they complete in any order
        exc = None
        for df in dfs:
            yield df
            try:
                df.getResult()
            except:
                exc = exc or sys.exc_info()
        if exc:
            raise exc[0], exc[1], exc[2]

        yield total

    def write(self, pos, s):
        df = launch_coroutine(wrap_task(self.add_task),
                              self._batch_write, pos, s)
        return df

    def close(self):
        if not self.initialized:
            self.startup_df.addCallback(lambda *a : self.filepool.close_files(self.range_by_name))
            return self.startup_df
        self.filepool.close_files(self.range_by_name)

    def downloaded(self, pos, length):
        for filename, begin, end in self._intervals(pos, length):
            self.undownloaded[filename] -= end - begin
