import logging
from twisted.python import reflect
from twisted.python import log
from BTL import defer # for failure decoration

class LogLogObserver(log.FileLogObserver):
    """Log observer that writes to a python logger."""

    def __init__(self, error_log_level, info_log_level ):
        self.error_log_level = error_log_level
        self.info_log_level = info_log_level

    def emit(self, eventDict):
        system = eventDict['system']
        if system == '-':
            system = 'twisted'
        else:
            system = 'twisted.' + system
        logger = logging.getLogger(system)
        # This next line is obnoxious.   --Dave
        #logger.setLevel(logging.DEBUG)
        edm = eventDict['message'] or ''
        if eventDict['isError'] and eventDict.has_key('failure'):
            if not edm:
                edm = 'Failure:'
            logger.log(self.error_log_level,
                       edm, exc_info=eventDict['failure'].exc_info())
        elif eventDict.has_key('format'):
            text = self._safeFormat(eventDict['format'], eventDict)
            logger.log(self.info_log_level, text)
        else:
            text = ' '.join(map(reflect.safe_str, edm))
            logger.log(self.info_log_level, text)


def start(error_log_level = logging.ERROR,
          info_log_level = logging.INFO ):
    """Writes twisted output to a logger using 'twisted' as the
       logger name (i.e., 'twisted' is passed as name arg to logging.getLogger(name)).
       """
    o = LogLogObserver(error_log_level, info_log_level)

    # We do not use twisted setStdout logging because it is not clear to me
    # how to differentiate twisted-generated log entries and
    # redirected output.  It is possible that all stdout and stderr
    # output has system '-', but from looking at twisted source code
    # there does not appear to be any guarantee that this is the case.
    # A simpler way of handling this is to simply separate
    # stdio and stderr redirection from twisted logging.   --Dave
    log.startLoggingWithObserver(o.emit , setStdout = 0) #setStdout=int(capture_output))

