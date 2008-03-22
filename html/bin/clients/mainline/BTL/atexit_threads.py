# threads are dumb, this module is smart.
#
# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# by Greg Hazel

import sys
import time
import atexit
import threading

def _get_non_daemons():
    return [d for d in threading.enumerate() if not d.isDaemon() and d != threading.currentThread()]

def register(func, *targs, **kargs):
    def duh():
        nondaemons = _get_non_daemons()
        for th in nondaemons:
            th.join()
        func(*targs, **kargs)
    atexit.register(duh)


def megadeth():
    time.sleep(10)
    try:
        import wx
        wx.Kill(wx.GetProcessId(), wx.SIGKILL)
    except:
        pass

def register_verbose(func, *targs, **kargs):
    def duh():
        nondaemons = _get_non_daemons()
        timeout = 4
        for th in nondaemons:
            start = time.time()
            th.join(timeout)
            timeout = max(0, timeout - (time.time() - start))
            if timeout == 0:
                break

        # kill all the losers
        # remove this when there are no more losers
        t = threading.Thread(target=megadeth)
        t.setDaemon(True)
        t.start()

        if timeout == 0:
            sys.stderr.write("non-daemon threads not shutting down "
                             "in a timely fashion:\n")
            nondaemons = _get_non_daemons()
            for th in nondaemons:
                sys.stderr.write("  %s\n" % th)
            sys.stderr.write("You have no chance to survive make your time.\n")
            for th in nondaemons:
                th.join()

        func(*targs, **kargs)

    atexit.register(duh)

