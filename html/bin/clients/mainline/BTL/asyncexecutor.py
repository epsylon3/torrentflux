""" An simple lightweight asynchronous executor class with nice
    java type static methods """
from twisted.python.threadpool import ThreadPool



class AsyncExecutor(object):
    """ defaults to minthreads=5, maxthreads=20 """
    pool = ThreadPool( name = 'AsyncExecutorPool')

    def _execute(self,  func, *args, **kwargs):
        if not self.pool.started:
            self.pool.start()
        self.pool.dispatch(None, func, *args, **kwargs)
    
    execute = classmethod(_execute)
    stop = pool.stop
    
def test():
    import random
    import time

    def test(digit):
        print 'Testing %d' % digit
        time.sleep(random.randint(1, 5000)/1000)
        print '     finished with test %d' % digit
    for i in xrange(10):
        AsyncExecutor.execute(test, )
    AsyncExecutor.stop() 

if __name__ == '__main__':
    test()
    
    
    
    
