# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# by David Harrison

import errno
import os
import sys
import errno
import pwd
import grp
import stat
import logging
from twisted.python.util import switchUID
#from twisted.scripts import twistd
#try:
#  from twisted.scripts.twistd import checkPID
#except:
#  from twisted.scripts._twistd_unix import checkPID

from BTL.log import injectLogger, ERROR, INFO, DEBUG
from BTL.platform import app_name
log = logging.getLogger("daemon")

noisy = False
def getuid_from_username(username):
    return pwd.getpwnam(username)[2]

def getgid_from_username(username):
    return pwd.getpwnam(username)[3]

def getgid_from_groupname(groupname):
    return grp.getgrnam(groupname)[2]

def daemon(
    username = None, groupname = None,
    use_syslog = None, log_file = None, logfile = None, # HACK for backwards compat.
    verbose = False,
    capture_output = True,
    twisted_error_log_level = ERROR,
    twisted_info_log_level = INFO,
    capture_stderr_log_level = ERROR,
    capture_stdout_log_level = INFO,
    capture_stderr_name = 'stderr',
    capture_stdout_name = 'stdout',
    log_level = DEBUG,
    log_twisted = True,
    pidfile = None,
    use_localtime=False):
    """When this function returns, you are a daemon.

       If use_syslog or a log_file is specified then this installs a logger.

       Iff capture_output is specified then stdout and stderr
       are also directed to syslog.

       If use_syslog is None then it defaults to True if no log_file
       is provided and the platform is not Darwin.

       The following arguments are passed through to BTL.log.injectLogger.

        use_syslog, log_file, verbose,
        capture_output, twisted_error_log_level,
        twisted_info_log_level, capture_stderr_log_level,
        capture_stdout_log_level, capture_stderr_name,
        capture_stdout_name, log_level, log_twisted

       daemon no longer removes pid file.  Ex: If
       a monitor sees that a pidfile exists and the process is not
       running then the monitor restarts the process.
       If you want the process to REALLY die then the
       pid file should be removed external to the program,
       e.g., by an init.d script that is passed "stop".
    """
    assert log_file is None or logfile is None, "logfile was provided for backwards " \
           "compatibility.  You cannot specify both log_file and logfile."
    if log_file is None:
        log_file = logfile

    try:
        if os.name == 'mac':
            raise NotImplementedError( "Daemonization doesn't work on macs." )

        if noisy:
            print "in daemon"

        uid = os.getuid()
        gid = os.getgid()
        if uid == 0 and username is None:
            raise Exception( "If you start with root privileges you need to "
                "provide a username argument so that daemon() can shed those "
                "privileges before returning." )
        if username:
            uid = getuid_from_username(username)
            if noisy:
                print "setting username to uid of '%s', which is %d." % ( username, uid )
            if uid != os.getuid() and os.getuid() != 0:
                raise Exception( "When specifying a uid other than your own "
                   "you must be running as root for setuid to work. "
                   "Your uid is %d, while the specified user '%s' has uid %d."
                   % ( os.getuid(), username, uid ) )
            gid = getgid_from_username(username) # uses this user's group
        if groupname:
            if noisy:
                print "setting groupname to gid of '%s', which is %d." % (groupname,gid)
            gid = getgid_from_groupname(groupname)

        pid_dir = os.path.split(pidfile)[0]
        if pid_dir and not os.path.exists(pid_dir):
            os.mkdir(pid_dir)
            os.chown(pid_dir,uid,gid)
        checkPID(pidfile)
        if use_syslog is None:
            use_syslog = sys.platform != 'darwin' and not log_file
        if log_file:
            if use_syslog:
                raise Exception( "You have specified both a log_file and "
                    "that the daemon should use_syslog.  Specify one or "
                    "the other." )
            print "Calling injectLogger"
            injectLogger(use_syslog=False, log_file = log_file, log_level = log_level,
                         capture_output = capture_output, verbose = verbose,
                         capture_stdout_name = capture_stdout_name,
                         capture_stderr_name = capture_stderr_name,
                         twisted_info_log_level = twisted_info_log_level,
                         twisted_error_log_level = twisted_error_log_level,
                         capture_stdout_log_level = capture_stdout_log_level,
                         capture_stderr_log_level = capture_stderr_log_level,
                         use_localtime = use_localtime )
        elif use_syslog:
            injectLogger(use_syslog=True, log_level = log_level, verbose = verbose,
                         capture_output = capture_output,
                         capture_stdout_name = capture_stdout_name,
                         capture_stderr_name = capture_stderr_name,
                         twisted_info_log_level = twisted_info_log_level,
                         twisted_error_log_level = twisted_error_log_level,
                         capture_stdout_log_level = capture_stdout_log_level,
                         capture_stderr_log_level = capture_stderr_log_level )
        else:
            raise Exception( "You are attempting to daemonize without a log file,"
                             "and with use_syslog set to false.  A daemon must "
                             "output to syslog, a logfile, or both." )
        if pidfile is None:
            pid_dir = os.path.join("/var/run/", app_name )
            pidfile = os.path.join( pid_dir, app_name + ".pid")
        daemonize()  # forks, moves into its own process group, forks again,
                     # middle process exits with status 0.  Redirects stdout,
                     # stderr to /dev/null.

        # I should now be a daemon.

        open(pidfile,'wb').write(str(os.getpid()))
        if not os.path.exists(pidfile):
            raise Exception( "pidfile %s does not exist" % pidfile )
        os.chmod(pidfile, stat.S_IRUSR|stat.S_IWUSR|stat.S_IROTH|stat.S_IRGRP)

        if os.getuid() == 0:
            if uid is not None or gid is not None:
                switchUID(uid, gid)
        if os.getuid() != uid:
            raise Exception( "failed to setuid to uid %d" % uid )
        if os.getgid() != gid:
            raise Exception( "failed to setgid to gid %d" % gid )
    except:
        log.exception("daemonizing may have failed")
        import traceback
        traceback.print_exc()
        raise

# Copied from twistd.... see daemonize for reason.
def checkPID(pidfile):
    if not pidfile:
        return
    if os.path.exists(pidfile):
        try:
            pid = int(open(pidfile).read())
        except ValueError:
            sys.exit('Pidfile %s contains non-numeric value' % pidfile)
        try:
            os.kill(pid, 0)
        except OSError, why:
            if why[0] == errno.ESRCH:
                # The pid doesnt exists.
                log.warning('Removing stale pidfile %s' % pidfile)
                os.remove(pidfile)
            else:
                sys.exit("Can't check status of PID %s from pidfile %s: %s" %
                         (pid, pidfile, why[1]))
        else:
            sys.exit("""\
Another twistd server is running, PID %s\n
This could either be a previously started instance of your application or a
different application entirely. To start a new one, either run it in some other
directory, or use the --pidfile and --logfile parameters to avoid clashes.
""" %  pid)

# Copied from twistd.  twistd considers this an internal function
# and across versions it got moved.  To prevent future breakage,
# I just assume incorporate daemonize directly.
def daemonize():
    # See http://www.erlenstar.demon.co.uk/unix/faq_toc.html#TOC16
    if os.fork():   # launch child and...
        os._exit(0) # kill off parent
    os.setsid()
    if os.fork():   # launch child and...
        os._exit(0) # kill off parent again.
    os.umask(077)
    null=os.open('/dev/null', os.O_RDWR)
    for i in range(3):
        try:
            os.dup2(null, i)
        except OSError, e:
            if e.errno != errno.EBADF:
                raise
    os.close(null)
