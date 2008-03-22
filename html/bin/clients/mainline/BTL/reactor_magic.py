# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import os
import sys
try:
    from resource import setrlimit, getrlimit, RLIMIT_NOFILE
    try:
        setrlimit(RLIMIT_NOFILE, (100000, 100000))
    except ValueError, e:
        # dont be so noisy here
        pass
        # print ">>> unable to setrlimit ", e
except ImportError, e:
    pass

if 'twisted.internet.reactor' in sys.modules:
    print ("twisted.internet.reactor was imported before BTL.reactor_magic!\n"
           "I'll clean it up for you, but don't do that!\n"
           "Existing reference may be for the wrong reactor!\n"
           "!")
    del sys.modules['twisted.internet.reactor']

noSignals = True
is_iocpreactor = False

if os.name == 'nt':
    try:
        from BTL.Luciana import reactor
        noSignals = False
        is_iocpreactor = True
    except:
        pass
else:
    try:
        from twisted.internet import kqreactor
        kqreactor.install()
    except:
        try:
            from BTL import epollreactor
            epollreactor.install()
        except:
            try:
                from twisted.internet import pollreactor
                pollreactor.install()
            except:
                pass

from twisted.internet import reactor

old_run = reactor.run
def run_default(method=None, **kw):
    if method:
        reactor.callLater(0, method)
    return old_run(**kw)
reactor.run = run_default
