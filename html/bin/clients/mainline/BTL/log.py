from logging import *

import time, sys
import socket
import datetime
import logging
import logging.handlers
from BTL.reactor_magic import reactor
from BTL.defer import Deferred
from BTL.btl_string import printable

from BTL import twisted_logger

# convenience re-export so that they can be used without import logging.
DEBUG = DEBUG
INFO = INFO
WARNING = WARNING
ERROR = ERROR
CRITICAL = CRITICAL
getLogger = getLogger

# Not used at the moment but can be changed later
SYSLOG_HOST                 = 'localhost'
SYSLOG_PORT                 = 514

class BTLFormatter(logging.Formatter):

    def __init__(self, *a, **k):
        self.use_localtime = False
        if k.has_key('use_localtime'):
            self.use_localtime = k['use_localtime']
            del k['use_localtime']
        logging.Formatter.__init__(self, *a, **k)


    def formatTime(self, record, datefmt=None):
        ct = self.converter(record.created)
        try:
            if self.use_localtime:
                dt = datetime.datetime.fromtimestamp(record.created)
            else:
                dt = datetime.datetime.utcfromtimestamp(record.created)
            if datefmt:
                s = dt.strftime(datefmt)
            else:
                s = dt.isoformat()
        except:
            s = "Interpretter Shutdown"
        return s

class RateLimitedLogger:
    """Logger that tosses log entries whenever the logged
       entries per second exceeds the specified rate limit by
       max_burst log entries."""
    def __init__(self, logger, rate_limit, max_burst, log_all_level = CRITICAL ):
        """@param logger: logging.Logger object that this class wraps.
           @param rate_limit: maximum number of log entries per second.
           @param max_burst: maximum number of log entries that can be printed
              in a burst. max_burst is the sigma in a (sigma,rho) token bucket.
           @param log_all_level: log all entries with level >= log_all_level.
              Such entries are still counted against the rate limit.
              """
        self.logger = logger
        self.rate_limit = rate_limit
        self.max_burst = max_burst
        self.logged_discard = False   # logged that we are dropping entries?
        self.tokens = self.max_burst
        self.log_all_above_level = log_all_level
        reactor.callLater(1,self._increment_tokens)
        reactor.callLater(5,self._log_clear)

    def _increment_tokens(self):
        self.tokens += self.rate_limit
        if self.tokens >= self.max_burst:
            self.tokens = self.max_burst
        reactor.callLater(1, self._increment_tokens)

    def _log_clear(self):
        self.logged_discard = False

    def setLevel(self, level):
        return self.logger.setLevel(level)

    def _discarded(self, level):
        self.tokens -= 1
        if self.tokens < 0:
            self.tokens = 0
            if level >= self.log_all_above_level:
                return False
            elif not self.logged_discard:
                self.logger.error( "Discarding '%s' logger entries because they are arriving "
                                   "too fast.  Will not log this error again for 5 "
                                   "seconds." % self.logger.name )
                self.logged_discard = True
            return True   # true = discarded
        return False      # false = not discarded

    def debug(self, msg, *args, **kwargs):
        if not self._discarded(level = DEBUG):
            self.logger.debug(msg,*args, **kwargs)

    def info(self, msg, *args, **kwargs):
        if not self._discarded(level = INFO):
            self.logger.info(msg, *args, **kwargs)

    def warning(self, msg, *args, **kwargs):
        if not self._discarded(level = WARNING):
            self.logger.warning(msg, *args, **kwargs)

    def error(self, msg, *args, **kwargs):
        if not self._discarded(level = ERROR):
            self.logger.error(msg, *args, **kwargs)

    def exception(self, msg, *args):
        if not self._discarded(level = EXCEPTION):
            self.logger.exception(msg, *args)

    def critical(self, msg, *args, **kwargs):
        if not self._discarded(level = CRITICAL):
            self.logger.critical(msg, *args, **kwargs)

    def log(self, level, msg, *args, **kwargs):
        if not self._discarded():
            self.logger.log(level, msg, *args, **kwargs)

    def findCaller(self):
        return self.logger.findCaller()

    def makeRecord(self, name, level, fn, lno, msg, args, exc_info):
        self.logger.makeRecord(name, level, fn, lno, msg, args, exc_info)

    def addHandler(self, hdlr):
        self.logger.addHandler(hdlr)

    def removeHandler(self, hdlr):
        self.logger.removeHandler(hdlr)

    def callHandlers(self, record):
        self.logger.callHandlers(record)

    def getEffectiveLevel(self):
        return self.logger.getEffectiveLevel()


class StdioPretender:
    """Pretends to be stdout or stderr."""
    # modified from twisted.python.log.StdioOnnaStick
    closed = 0
    softspace = 0
    mode = 'wb'
    name = '<stdio (log)>'

    def __init__(self, capture_name, level ):
        self.level = level
        self.logger = logging.getLogger( capture_name )
        self.buf = ''

    def close(self):
        pass

    def flush(self):
        pass

    def fileno(self):
        return -1

    def read(self):
        raise IOError("can't read from the log!")

    readline = read
    readlines = read
    seek = read
    tell = read

    def write(self, data):
        d = (self.buf + data).split('\n')
        self.buf = d[-1]
        messages = d[0:-1]
        for message in messages:
            self.logger.log( self.level, message )

    def writelines(self, lines):
        for line in lines:
            self.logger.log( self.level, message )


class SysLogHandler(logging.handlers.SysLogHandler):
    # This is a hack to get around log entry size limits imposed by syslog.
    def __init__(self, address=('localhost', logging.handlers.SYSLOG_UDP_PORT),
                 facility=logging.handlers.SysLogHandler.LOG_USER,
                 max_msg_len = 4096, fragment_len = 900, make_printable = True ):
        """@param max_msg_len: maximum message length before truncation.
           @param fragment_len: when message length exceeds 900 it is truncated
                                and broken into multiple consecutive log entries.
           @param make_printable: runs each message through
                                BTL.btl_string.printable in emit.  For example,
                                this is useful if the messages are being sent
                                through a UNIX socket to syslogd and the
                                message might contain non-ascii characters.
        """
        logging.handlers.SysLogHandler.__init__( self, address, facility )
        self.max_msg_len = max_msg_len
        self.fragment_len = fragment_len
        self.make_printable = make_printable

    def emit(self, record):
        """Differs from the override emit in that it
           fragments the message to allow for much longer
           syslog messages."""
        msg = self.format(record)
        if self.make_printable:
            msg = printable(msg)
        msg = msg[:self.max_msg_len]
        i = 0
        while msg:
            remaining = msg[self.fragment_len:]
            if i > 0:
                msg = "(cont.) " + msg[:self.fragment_len]
            else:
                msg = msg[:self.fragment_len]
            msg = self.log_format_string % (self.encodePriority(self.facility,
                                            string.lower(record.levelname)),msg)
            try:
                if self.unixsocket:
                    try:
                        self.socket.send(msg)
                    except socket.error:
                        self._connect_unixsocket(self.address)
                        self.socket.send(msg)
                else:
                    self.socket.sendto(msg, self.address)
            except (KeyboardInterrupt, SystemExit):
                raise
            except:
                self.handleError(record)

            msg = remaining
            i += 1



def injectLogger(use_syslog = True, log_file = None, verbose = False,
                 capture_output = True,
                 twisted_error_log_level = ERROR,
                 twisted_info_log_level = INFO,
                 capture_stderr_log_level = ERROR,
                 capture_stdout_log_level = INFO,
                 capture_stderr_name = 'stderr',
                 capture_stdout_name = 'stdout',
                 log_level = DEBUG,
                 log_twisted = True,
                 use_localtime = False ):
    """
       Installs logging.

       @param use_syslog:    log to syslog.  use_syslog, log_file, and verbose are not
                             mutually exclusive.
       @param log_file:      log to a file.
       @param verbose:       output logs to stdout.  Setting verbose and capture_output
                             to this function does NOT result in an infinite loop.
       @param capture_output: redirects stdout and stderr to the logger. Be careful.  This can
                             create infinite loops with loggers that
                             output to stdout or stderr.
       @param twisted_error_log_level: log level for errors reported
                             by twisted.
       @param twisted_info_log_level: log level for non-errors reported by twisted.
                             If capture_output is set then this is also the log
                             level for anything output to stdout or stderr.
       @param log_level:     only log events that have level >= passed level
                             are logged.  This is achieved by setting the log level in
                             each of the installed handlers.
       @param capture_stderr_log_level: log level for output captured from stdout.
       @param capture_stdout_log_level: log level for output captured from stderr.
       @param capture_stderr_name: log name used for stderr.  'name'
                             refers to the name arg passed to logging.getLogger(name).
       @param capture_stdout_name: log name used for stdout.  Analogous to capture_stderr_name.
    """
    logger = logging.getLogger('')
    logger.setLevel(DEBUG)  # we use log handler levels to control output level.

    formatter = BTLFormatter("%(asctime)s - %(name)s - %(process)d - "
                             "%(levelname)s - %(message)s", use_localtime=use_localtime)

    if log_file is not None:
        lf_handler = logging.handlers.RotatingFileHandler(filename=log_file,
                                                          mode='a',
                                                          maxBytes=2**27,
                                                          backupCount=10)

        lf_handler.setFormatter(formatter)
        lf_handler.setLevel(log_level)
        logger.addHandler(lf_handler)

    if use_syslog:
        sl_handler = SysLogHandler('/dev/log',
                                   facility=SysLogHandler.LOG_LOCAL0)
                                   #address = (SYSLOG_HOST, SYSLOG_PORT))
        # namespace - pid - level - message
        sl_handler.setFormatter(BTLFormatter("%(name)s - %(process)d - "
                                             "%(levelname)s - %(message)s"))
        sl_handler.setLevel(log_level)
        logger.addHandler(sl_handler)

    if verbose:
        # StreamHandler does not capture stdout, it directs output from
        # loggers to stdout.
        so_handler = logging.StreamHandler(sys.stdout)
        so_handler.setFormatter(formatter)
        so_handler.setLevel(log_level)
        logger.addHandler(so_handler)

    if capture_output:
        sys.stdout = StdioPretender( capture_stdout_name, capture_stdout_log_level )
        sys.stderr = StdioPretender( capture_stderr_name, capture_stderr_log_level )

    if log_twisted:
       twisted_logger.start(error_log_level = twisted_error_log_level,
                             info_log_level = twisted_info_log_level)


if __name__ == '__main__':

    from BTL.greenlet_yielddefer import coroutine, like_yield

    @coroutine
    def test_rate_limited_logger():
        injectLogger(verbose = True)
        log = RateLimitedLogger(logging.getLogger("myapp"), 1,1)
        log.info( "should be printed." )
        log.info( "should not be printed" )  # but should log "discard" message.
        log.info( "also should not be printed" )  # should not logging of discard message.
        df = Deferred()
        reactor.callLater(3, df.callback, True)
        like_yield(df)
        log.info( "should also be printed" )
        reactor.stop()

    def test_injectLogger():
        injectLogger(log_file = "your.log", use_syslog=False, verbose=True)
        logger = logging.getLogger("myapp")
        logger.warning("You are awesome")

        print 'stdout!'
        print >>sys.stderr, 'stderr!'
        from twisted.internet import reactor
        from twisted.python import failure
        def foo():
            reactor.stop()
            zuul = dana

        reactor.callLater(0, foo)

    def test_injectLogger2():
        injectLogger(log_file = "your.log", verbose=False, capture_output=True)
	print "hello world"
        def foo():
            reactor.stop()
            zuul = dana

	reactor.callLater(0, foo)

    #test_injectLogger()
    test_injectLogger2()

    #reactor.callLater(0, test_rate_limited_logger)
    reactor.run()


