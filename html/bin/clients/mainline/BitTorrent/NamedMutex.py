# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Author: David Harrison

if __name__ == "__main__":
    def _(s):
        return s
else:
    from BTL.translation import _

import os

if os.name == 'nt':
    import win32file
    import win32event
    #import BTL.likewin32api as win32api
    import win32api
    import winerror
    import pywintypes

elif os.name == 'posix':
    import fcntl
    from BitTorrent import platform
    from fcntl import flock


class NamedMutex(object):
    """Reasonably cross-platform, cross-process mutex.

       It does not implement mutual exclusion between threads in the
       same process.  Use threading.Lock or threading.RLock for mutual
       exclusion between threads.
    """
    # In Unix:
    #   Same semantics as directly locking a file: mutual exclusion is
    #   provided when a process can lock the file.  If
    #   the file does not exist, it is created. 
    #
    # In NT:
    #   Uses Windows CreateMutex.
    # obtain_mutex = 1
    # mutex = win32event.CreateMutex(None, obtain_mutex, name)

    def __init__(self, name):
        self._mylock = False
        self._name = name
        self._mutex = None 
        if os.name in ('posix','max'):
            ddir = platform.get_dot_dir()
            self._path = os.path.join( ddir, "mutex", name )
            if not os.path.exists(ddir):
                os.mkdir(ddir, 0700)

    def owner(self):
        return self._mylock
    
    def acquire(self, wait=True):  
        """Acquires mutual exclusion.  Returns true iff acquired."""
        if os.name == 'nt':

            # Gotcha: Not checking self._mylock in windows, because it is
            # possible to acquire the mutex from more than one object with
            # the same name.  This needs some work to make the semantics
            # exactly the same between Windows and Unix.
            obtain_mutex = 1
            self._mutex = win32event.CreateMutex(None,obtain_mutex,self._name)
            if self._mutex is None:
                return False
            lasterror = win32api.GetLastError()
            if lasterror == winerror.ERROR_ALREADY_EXISTS:
                if wait:
                   # mutex exists and has been opened(not created, not locked).
                   r = win32event.WaitForSingleObject(self._mutex, 
                                                      win32event.INFINITE)
                else:
                   r = win32event.WaitForSingleObject(self._mutex, 0)

                # WAIT_OBJECT_0 means the mutex was obtained
                # WAIT_ABANDONED means the mutex was obtained, 
                # and it had previously been abandoned
                if (r != win32event.WAIT_OBJECT_0 and 
                    r != win32event.WAIT_ABANDONED):
                    return False
    
        elif os.name in ('posix','max'):
            if self._mylock: 
                return True
            
            if os.path.exists(self._path) and not os.path.isfile(self._path):
                raise BTFailure( 
                    "Cannot lock file that is not regular file." )

            (dir,name) = os.path.split(self._path)
            if not os.path.exists(dir):
                os.mkdir(dir, 0700)  # mode=0700 = allow user access.

            while True:  # <--- for file deletion race condition (see __del__)

                # UNIX does not support O_TEMPORARY!! Blech. This complicates
                # file deletion (see "while True" above and path checking after
                # "flock").
                #self._mutex = os.open( self._path, os.O_CREAT|os.O_TEMPORARY )
                self._mutex = open( self._path, "w" )
                if wait:
                    flags = fcntl.LOCK_EX
                else:
                    flags = fcntl.LOCK_EX | fcntl.LOCK_NB
                try:
                    flock( self._mutex.fileno(), flags)
                except IOError:
                    return False

                # race condition: __del__ may have deleted the file.
                if not os.path.exists( self._path ):
                    self._mutex.close()
                else:
                    break
    
        else:
            # dangerous, but what should we do if the platform neither
            # supports named mutexes nor file locking?   --Dave
            pass
            
        self._mylock = True
        return True
  
    def release(self):
        # unlock
        assert self._mylock
        if os.name == 'nt':
            win32event.ReleaseMutex(self._mutex)
            # Error code 123? 
            #lasterror = win32api.GetLastError()
            #if lasterror != 0:
            #    raise IOError( _("Could not release mutex %s due to "
            #                     "error windows code %d.") % 
            #                    (self._name,lasterror) )
        elif os.name == 'posix':
            self._mylock = False
            if not os.path.exists(self._path):
                raise IOError( _("Non-existent file: %s") % self._path )
            flock( self._mutex.fileno(), fcntl.LOCK_UN )
            self._mutex.close()

    def __del__(self):

        if os.name == 'nt':
            if self._mutex is not None:
                # Gotchas: Don't close the handle before releasing it or the
                # mutex won't release until the python script exits.  It's 
                # safe to call ReleaseMutex even if the local process hasn't 
                # acquired the mutex.  If this process doesn't have mutex then 
                # the call fails.  Note that in Windows, mutexes are literally 
                # per process.  Multiple mutexes created with the same name
                # from the same process will be treated as one with respect to 
                # release and acquire.
                self.release()

                # windows will destroy the mutex when the last handle to that
                # mutex is closed.
                win32file.CloseHandle(self._mutex)
                del self._mutex
   
        elif os.name == 'posix':
            if self._mylock:
                self.release()

            # relock file non-blocking to see if anyone else was 
            # waiting on it.  (A race condition exists where another process
            # could have just opened but not yet locked the file.  This process
            # then deletes the file.  When the other process resumes, the
            # flock call fails.  This is however not a particularly bad
            # race condition since acquire() simply repeats the open & flock.)
            if os.path.exists(self._path) and self.acquire(False):

                try:
                    os.remove(self._path)
                except:
                    pass

                flock( self._mutex.fileno(), fcntl.LOCK_UN )
                self._mutex.close()

if __name__ == "__main__":
    # perform unit tests.
    n_tests = n_tests_passed = 0
    n_tests += 1
    mutex = NamedMutex("blah")    
    if mutex.acquire() and mutex._mutex is not None and mutex._mylock:
        n_tests_passed += 1
    else:
        print "FAIL! Failed to acquire mutex on a new NamedMutex."

    n_tests += 1
    mutex.release()
    if mutex._mutex is not None and not mutex._mylock:
        n_tests_passed += 1
    else:
        print "FAIL! Did not properly release mutex."

    n_tests += 1
    if mutex.acquire():
        if mutex._mutex is not None and mutex._mylock:
            n_tests_passed += 1
        else: 
            print ( "FAIL! After calling acquire on a released NamedMutex, "
                    "either mutex._mutex is None or mutex._mylock is false." )
    else:
        print "FAIL! Failed to acquire mutex a released NamedMutex."


    # okay.  I should add more tests.

    del mutex

    if n_tests == n_tests_passed:
        print "Passed all %d tests." % n_tests
