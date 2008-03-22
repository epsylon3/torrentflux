# yielddefer is an async programming mechanism with a blocking look-alike syntax
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
# launch_coroutine maintains the illusion that the passed function
# (a generator) runs from beginning to end yielding when necessary
# for some job to complete and then continuing where it left off.
# 
# def f():
#    ...
#    yield some_thing_that_takes_time()
#    ...
#    result = yield something_else()
#    ...
# 
# from inside a generator launched with launch_coroutine:
# wait on a deferred to be called back by yielding it
# return None by simply returning
# return an exception by throwing one
# return a value by yielding a non-Deferred
#
# by Greg Hazel

from __future__ import generators
import sys
import types
import traceback
from BTL.defer import Deferred, Failure
from BTL.stackthreading import _print_traceback
from twisted.python import failure

debug = False
name_debug = False

class GenWithDeferred(object):

    if debug:
        __slots__ = ['gen', 'current_deferred', 'deferred', 'queue_task' 'stack']
    else:
        __slots__ = ['gen', 'current_deferred', 'deferred', 'queue_task']

    def __init__(self, gen, deferred, queue_task):
        self.gen = gen
        self.deferred = deferred
        self.queue_task = queue_task
        self.current_deferred = None

        if debug:
            try:
                raise ZeroDivisionError
            except ZeroDivisionError:
                f = sys.exc_info()[2].tb_frame.f_back

            self.stack = traceback.extract_stack(f)
            # cut out GenWithDeferred() and launch_coroutine
            self.stack = self.stack[:-2]

    def cleanup(self):
        del self.gen
        del self.deferred
        del self.queue_task
        del self.current_deferred
        if debug:
            del self.stack

    if name_debug:
        def __getattr__(self, attr):
            if '_recall' not in attr:
                raise AttributeError(attr)
            return self._recall

        def _queue_task_chain(self, v):
            recall = getattr(self, "_recall_%s" % self.gen.gi_frame.f_code.co_name)
            self.queue_task(recall)
            return v
    else:
        def _queue_task_chain(self, v):
            self.queue_task(self._recall)
            return v

    def next(self):
        if not self.current_deferred:
            return self.gen.next()
        
        if isinstance(self.current_deferred.result, failure.Failure):
            r = self.current_deferred.result
            self.current_deferred.addErrback(lambda fuckoff: None)
            return self.gen.throw(*r.exc_info())
        return self.gen.send(self.current_deferred.result)

    def _recall(self):
        try:
            df = self.next()
        except StopIteration:
            self.deferred.callback(None)
            self.cleanup()
        except Exception, e:

            exc_type, value, tb = sys.exc_info()

            ## Magic Traceback Hacking
            if debug:
                # interpreter shutdown
                if not sys:
                    return
                # HERE.  This should really be logged or else bittorrent-
                # curses will never be able to properly output. --Dave
                _print_traceback(sys.stderr, self.stack,
                                 "generator %s" % self.gen.gi_frame.f_code.co_name, 0,
                                 exc_type, value, tb)
            else:
                #if (tb.tb_lineno != self.gen.gi_frame.f_lineno or
                #    tb.f_code.co_filename != self.gen.gi_frame.f_code.co_filename):
                #    tb = FakeTb(self.gen.gi_frame, tb)
                pass
            ## Magic Traceback Hacking

            self.deferred.errback(Failure(value, exc_type, tb))
            del tb
            self.cleanup()
        else:
            if not isinstance(df, Deferred):
                self.deferred.callback(df)
                self.cleanup()
                return

            self.current_deferred = df
            df.addCallback(self._queue_task_chain)
            df.addErrback(self._queue_task_chain)
            del df
        
class FakeTb(object):
    __slots__ = ['tb_frame', 'tb_lineno', 'tb_orig', 'tb_next']
    def __init__(self, frame, tb):
        self.tb_frame = frame
        self.tb_lineno = frame.f_lineno
        self.tb_orig = tb
        self.tb_next = tb.tb_next

def _launch_generator(queue_task, g, main_df):
    gwd = GenWithDeferred(g, main_df, queue_task)
    ## the first one is fired for you
    ##gwd._recall()
    # the first one is not fired for you, because if it errors the sys.exc_info
    # causes an unresolvable circular reference that makes the gwd.deferred never
    # be deleted.
    gwd._queue_task_chain(None)

def launch_coroutine(queue_task, f, *args, **kwargs):
    main_df = Deferred()
    try:
        g = f(*args, **kwargs)
    except Exception, e:
        if debug:
            traceback.print_exc()
        main_df.errback(Failure())
    else:        
        if isinstance(g, types.GeneratorType):
            _launch_generator(queue_task, g, main_df)
        else:
            # we got a non-generator, just callback with the return value
            main_df.callback(g)
    return main_df

# decorator
def coroutine(func, queue_task):
    def replacement(*a, **kw):
        return launch_coroutine(queue_task, func, *a, **kw)
    return replacement

def wrap_task(add_task):
    return lambda _f, *args, **kwargs : add_task(0, _f, *args, **kwargs)
_wrap_task = wrap_task   
