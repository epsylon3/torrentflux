# pick a Storage subsystem
try:
    from Storage_IOCP import *
except Exception, e:
    from Storage_threadpool import *
