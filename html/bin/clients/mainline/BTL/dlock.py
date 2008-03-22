#!/usr/bin/python
#
# Copyright 2006-2007 BitTorrent, Inc. All Rights Reserved.
#
# Written by Ben Teitelbaum

import os
import sys
import socket

from time import asctime, gmtime, time, sleep

from twisted.internet import reactor, task

class dlock(object):

    def __init__(self, deadlockfile, update_period=300, myhost=None, debug=None):
        if myhost == None: myhost = socket.gethostname()
        self.host = myhost
        self.pid   = os.getpid()
        self.deadlockfile  = deadlockfile
        self.refresher = task.LoopingCall(self.refresh)
        self.update_period = update_period
        self.debug = debug

    # Block until lock is acquired, then refresh the lock file every
    # update_period seconds.
    #
    # Nota Bene: while blocked on acquiring the lock, this sleeps the
    # whole process; once the lock is acquired, an event-driven model
    # (twisted reactor) is presumed.  The intended use (see test at
    # bottom) is to block on acquire before running the Twisted
    # reactor.
    #
    def acquire(self):
        while True:
            while self.islocked():
                if self.debug:
                    lock = self._readlock()
                    print '%s locked by %s' % (self.deadlockfile, self._lockdict2string(lock))
                sleep(self.update_period)
            try:
                # Use link count hack to work around NFS's broken
                # file locking.
                tempfile = '.' + str(self.pid) + self.host + str(time()) + '.tmp'
                lockfile = self.deadlockfile + '.lock'

                # Create temp lock file
                fh = open(tempfile, "w")
                fh.close()

                # Atomicallly create lockfile as a hard link
                try:
                    os.link(tempfile, lockfile)
                except:
                    if self.debug:
                        print "tempfile: " + tempfile
                        print "lockfile: " + lockfile
                    raise

                # Check the number of links
                if os.stat(tempfile)[3] == os.stat(lockfile)[3]:
                    # Hooray, I have the write lock on the deadlock file!
                    self._timestamp_deadlockfile(time())
                    if self.debug:
                        lock = self._readlock()
                        print '%s acquired by %s' % (self.deadlockfile, self._lockdict2string(lock))
                        self.refresher.start(self.update_period)
                    # Release the lock
                    os.unlink(tempfile)
                    os.unlink(lockfile)

                    return self
                else:
                    # Failed to grab write lock on deadlock file, keep looping
                    if self.debug:
                        print '%d failed to grab write lock on deadlock file: %s (will retry)' % (self.pid, self.deadlockfile)
            except:
                if self.debug:
                    print 'File Lock Error: %s@%s could not acquire %s' % (self.pid, self.host, self.deadlockfile)
                raise

    def refresh(self):
        assert self.ownlock()
        # No need to grab a write lock on the deadlock file, since it's not stale
        self._timestamp_deadlockfile(time())

    def _timestamp_deadlockfile(self, ts):
        try:
            fh = open(self.deadlockfile, 'w')
            fh.write(self._lockstr(ts))
            fh.close()
            os.chmod(self.deadlockfile, 0644)
        except:
            if self.debug:
                print 'File Lock Error: %s@%s could not write %s' % (self.pid, self.host, self.deadlockfile)
            raise

    def release(self):
        if self.ownlock():
            try:
                self.refresher.stop()
                self._timestamp_deadlockfile(0)
                if self.debug:
                    print '%s@%s released lock %s' % (self.pid, self.host, self.deadlockfile)
            except:
                if self.debug:
                    print 'File Lock Error: %s@%s could not release %s' % (self.pid, self.host, self.deadlockfile)
                raise
        return self

    def islocked(self):
        try:
            if self._isstale():
                # Lock seems stale, wait for one more update period and check again
                sleep(self.update_period)
                return not self._isstale()
            else:
                return True
        except:
            if self.debug:
                print "islocked exception"
            return False

    def _isstale(self):
        lock = self._readlock()
        if time() - lock['timestamp'] > self.update_period:
            return True
        else:
            return False

    def _readlock(self):
        try:
            lock = {}
            fh   = open(self.deadlockfile)
            data = fh.read().split()
            fh.close()
            assert len(data) == 3
            lock['pid'] = int(data[0])
            lock['host'] = data[1]
            lock['timestamp'] = float(data[2])
            return lock
        except:
            if self.debug:
                print 'File Lock Error: %s@%s reading %s' % (self.pid, self.host, self.deadlockfile)
            raise

    # Public method to read a lockfile.
    @classmethod
    def readlock(cls, lockfile):
        lock = cls(deadlockfile=lockfile, myhost='dummy')
        return lock._readlock()

    def _lockdict2string(self, lock):
        return '%s@%s at %s' % (lock['pid'], lock['host'], asctime(gmtime(lock['timestamp'])))

    def _lockstr(self, ts):
        return '%d %s %f'%(self.pid, self.host, ts)

    def ownlock(self):
        lock = self._readlock()
        return (self.host == lock['host'] and
                self.pid == lock['pid'])

    def __del__(self):
        self.release()


# Tests
#
# Run several in parallel on multiple machines, but have at most one
# whack the deadlock file on initialization.
#
def run_tests(argv=None):
    if argv is None:
        argv = sys.argv

    deadlockfile = './dlock_test'
    l = dlock(deadlockfile, 5, debug=True)

    # Stupid argv handling; just grab first arg and run that test
    if len(argv) > 1:
        if argv[1] == 'none':
            print "Removing deadlock file."
            os.unlink(deadlockfile)
        elif argv[1] == 'old':
            print "Creating stale deadlock file owned by no one."
            fh = open(l.deadlockfile, 'w')
            fh.write('%d %s %f'%(0, 0, 0))
            fh.close()
        elif argv[1] == 'new':
            print "Creating fresh deadlock file owned by no one."
            fh = open(l.deadlockfile, 'w')
            fh.write('%d %s %f'%(0, 0, time()))
            fh.close()
        else:
            print "Un-known arg--starting with old deadlock file."
    else:
            print "Starting with old deadlock file."

    # Tease for a while, then release the lock
    def tease(l, n):
        if n > 0:
            assert l.ownlock()
            print 'I (%d) have the lock--ha, ha ha!'%os.getpid()
            reactor.callLater(1, tease, l, n - 1)
        else:
            l.release()

    # Start teasing once reactor is run
    reactor.callLater(1, tease, l, 20)

    # But first, grab the lock (this blocks)
    l.acquire()

    reactor.run()

if __name__ == "__main__":
    sys.exit(run_tests())
