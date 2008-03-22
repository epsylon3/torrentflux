# -*- coding: UTF-8 -*-
# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

version = '5.2.0'

URL = 'http://www.bittorrent.com/'
DONATE_URL = URL + 'donate.html?client=%(client)s'
FAQ_URL = URL + 'FAQ.html?client=%(client)s'
SEARCH_URL = 'http://www.bittorrent.com/search_result.html?client=%(client)s&search=%(search)s'
#LOCALE_URL = URL + 'translations/'

# Moved to BTL.  Needed for get_language <- BTL.write_language_file
#LOCALE_URL = 'http://translations.bittorrent.com/'

NAG_FREQUENCY = 3
PORT_RANGE = 5

import sys
assert sys.version_info >= (2, 3, 0), "Python %s or newer required" % '2.3.0'
import os
import logging
import logging.handlers
from StringIO import StringIO

from BTL import BTFailure, InfoHashType
from BTL import atexit_threads

# failure due to user error.  Should output differently (e.g., not outputting
# a backtrace).
class UserFailure(BTFailure):
    pass

branch = None
p = os.path.realpath(os.path.split(sys.argv[0])[0])
if (os.path.exists(os.path.join(p, '.cdv')) or
    os.path.exists(os.path.join(p, '.svn'))):
    branch = os.path.split(p)[1]
del p


from BitTorrent.platform import get_temp_subdir, get_dot_dir, is_frozen_exe


if os.name == 'posix':
    if os.uname()[0] == "Darwin":
        from BTL.translation import _

if "-u" in sys.argv or "--use_factory_defaults" in sys.argv:
    logroot = get_temp_subdir()
else:
    #logroot = get_home_dir()
    logroot = get_dot_dir()

if is_frozen_exe:
    if logroot is None:
        logroot = os.path.splitdrive(sys.executable)[0]
        if logroot[-1] != os.sep:
            logroot += os.sep
    logname = os.path.split(sys.executable)[1]
else:
    logname = os.path.split(os.path.abspath(sys.argv[0]))[1]
logname = os.path.splitext(logname)[0] + '.log'
if logroot != '' and not os.path.exists(logroot):
    os.makedirs(logroot)
logpath = os.path.join(logroot, logname)


# becuase I'm generous.
STDERR = logging.CRITICAL + 10
logging.addLevelName(STDERR, 'STDERR')

# define a Handler which writes INFO messages or higher to the sys.stderr
console = logging.StreamHandler()
console.setLevel(logging.DEBUG)
# set a format which is simpler for console use
#formatter = logging.Formatter(u'%(name)-12s: %(levelname)-8s %(message)s')
formatter = logging.Formatter(u'%(message)s')
# tell the handler to use this format
console.setFormatter(formatter)
# add the handler to the root logger
logging.getLogger('').addHandler(console)

bt_log_fmt = logging.Formatter(u'[' + unicode(version) + u' %(asctime)s] %(levelname)-8s: %(message)s',
                               datefmt=u'%Y-%m-%d %H:%M:%S')

stderr_console = None
old_stderr = sys.stderr

def inject_main_logfile():
    # the main log file. log every kind of message, format properly,
    # rotate the log. someday - SocketHandler

    mainlog = logging.handlers.RotatingFileHandler(filename=logpath,
        mode='a', maxBytes=2**20, backupCount=1)
    mainlog.setFormatter(bt_log_fmt)
    mainlog.setLevel(logging.DEBUG)
    logger = logging.getLogger('')
    logging.getLogger('').addHandler(mainlog)
    logging.getLogger('').removeHandler(console)
    atexit_threads.register(lambda : logging.getLogger('').removeHandler(mainlog))

    global stderr_console
    if not is_frozen_exe:
        # write all stderr messages to stderr (unformatted)
        # as well as the main log (formatted)
        stderr_console = logging.StreamHandler(old_stderr)
        stderr_console.setLevel(STDERR)
        stderr_console.setFormatter(logging.Formatter(u'%(message)s'))
        logging.getLogger('').addHandler(stderr_console)

root_logger = logging.getLogger('')

class StderrProxy(StringIO):

    # whew. ugly. is there a simpler way to write this?
    # the goal is to stop every '\n' and flush to the log
    # otherwise keep buffering.
    def write(self, text, *args):
        lines = text.split('\n')
        for t in lines[:-1]:
            if len(t) > 0:
                StringIO.write(self, t)
            try:
                # the docs don't say it, but logging.log is new in 2.4
                #logging.log(STDERR, self.getvalue())
                root_logger.log(STDERR, self.getvalue())
            except:
                # logging failed. throwing a traceback would recurse
                pass
            self.truncate(0)
        if len(lines[-1]) > 0:
            StringIO.write(self, lines[-1])

sys.stderr = StderrProxy()
def reset_stderr():
    sys.stderr = old_stderr
atexit_threads.register(reset_stderr)



