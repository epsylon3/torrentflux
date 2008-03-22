# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import sys
import weakref
import traceback
import BTL.stackthreading as threading
from twisted.internet import defer
from twisted.python import failure
debug = False

tp_Failure = failure.Failure

# used to emulate sys.exc_info()
def exc_info(self):
    return self.type, self.value, self.tb
tp_Failure.exc_info = exc_info

# Maybe Dangerous. If you're having memory leaks, look here.
# used to prevent traceback stripping for standard re-raises
#old_cleanFailure = tp_Failure.cleanFailure
#def cleanFailure(self):
#    self.tb2 = self.tb
#    old_cleanFailure(self)
#    self.tb = self.tb2
#tp_Failure.cleanFailure = cleanFailure

class Failure(tp_Failure):
    def __init__(self, *a, **kw):
        tp_Failure.__init__(self, *a, **kw)
        # magic to allow re-raise of failures to do proper stack appending
        if hasattr(self.value, 'failure'):
            self.stack = self.stack[:-2] + self.value.failure.stack
            self.frames = self.frames[:-2] + self.value.failure.frames
        self.value.failure = self
failure.Failure = Failure

fail = defer.fail
succeed = defer.succeed
execute = defer.execute
maybeDeferred = defer.maybeDeferred
timeout = defer.timeout

DeferredQueue = defer.DeferredQueue
Deferred = defer.Deferred

def getResult(self):
    if isinstance(self.result, tp_Failure):
        r = self.result
        self.addErrback(lambda fuckoff: None)
        r.raiseException()
    return self.result
Deferred.getResult = getResult

Deferred_errback = Deferred.errback
def errback(self, fail):
    assert isinstance(fail, (tp_Failure, Exception)), repr(fail)
    # this can check the wrong failure type if the imports occur in the
    # wrong order.
    #Deferred_errback(self, fail)
    if not isinstance(fail, tp_Failure):
        fail = Failure(fail)
    self._startRunCallbacks(fail)
errback.__doc__ = Deferred_errback.__doc__
Deferred.errback = errback

def addLogback(self, logger, logmsg):
    if not callable(logger):
        logger = logger.error
    def logback(failure):
        logger(logmsg, exc_info=failure.exc_info())
    return self.addErrback(logback)
Deferred.addLogback = addLogback

# not totally safe, but a start.
# This lets you call callback/errback from any thread.
# The next step would be for addCallbak and addErrback to be safe.
class ThreadableDeferred(Deferred):
    def __init__(self, queue_func):
        assert callable(queue_func)
        self.queue_func = queue_func
        Deferred.__init__(self)

    def callback(self, result):
        self.queue_func(Deferred.callback, self, result)

    def errback(self, result):
        self.queue_func(Deferred.errback, self, result)


# go ahead and forget to call start()!
class ThreadedDeferred(Deferred):

    def __init__(self, queue_func, f, *args, **kwargs):
        Deferred.__init__(self)
        daemon = False
        if 'daemon' in kwargs:
            daemon = kwargs.pop('daemon')
        self.f = f
        start = True
        if queue_func is None:
            start = False
            queue_func = lambda f, *a, **kw : f(*a, **kw)
        self.queue_func = queue_func
        self.args = args
        self.kwargs = kwargs
        self.t = threading.Thread(target=self.run)
        self.t.setDaemon(daemon)
        if start:
            self.start()

    def start(self):
        self.t.start()

    def run(self):
        try:
            r = self.f(*self.args, **self.kwargs)
            self.queue_func(self.callback, r)
        except:
            self.queue_func(self.errback, Failure())


class DeferredEvent(Deferred, threading._Event):
    def __init__(self, *a, **kw):
        threading._Event.__init__(self)
        Deferred.__init__(self, *a, **kw)

    def set(self):
        threading._Event.set(self)
        self.callback(None) # hmm, None?


def run_deferred(df, f, *a, **kw):
    try:
        v = f(*a, **kw)
    except:
        df.errback(Failure())
    else:
        df.callback(v)
    return df


def run_deferred_and_queue(df, queue_task, f, *args, **kwargs):
    try:
        v = f(*args, **kwargs)
    except:
        queue_task(df.errback, Failure())
        del df
    else:
        if isinstance(v, Deferred):
            # v is owned by the caller, so add the callback
            # now, but the task itself should queue.
            # lamdba over df here would break 'del df' above
            # so do it with a local function.
            def make_queueback(func):
                return lambda r : queue_task(func, r)
            v.addCallback(make_queueback(df.callback))
            v.addErrback(make_queueback(df.errback))
        else:
            queue_task(df.callback, v)


def defer_to_thread(local_queue_task, thread_queue_task, f, *args, **kwargs):
    df = Deferred()
    thread_queue_task(run_deferred_and_queue, df, local_queue_task,
                      f, *args, **kwargs)
    return df

def wrap_task(add_task):
    return lambda _f, *args, **kwargs : add_task(0, _f, *args, **kwargs)
