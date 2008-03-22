# greenlet-based coroutine convenience functions.
#
# author: David Harrison and Greg Hazel

from BTL.reactor_magic import reactor
from BTL import defer
from BTL import coro
from BTL.greenlet_yielddefer import like_yield, coroutine, GreenletWithDeferred
import greenlet
from twisted.python.failure import Failure


# Analogous to time.sleep, but it returns a deferred whose callback
# is called after 'secs' seconds.
wait = coro.wait

def init_yield(clss, *args, **kwargs):
    """Instantiate an object of type clss and then call its asynchronous initializer
       (__dfinit__). The __dfinit__ returns a Deferred.  When the deferred's callback
       is called execution resumes at init_yield and the fully initialized object is
       returned."""
    kwargs['__magic_init_yield'] = True
    obj = clss(*args, **kwargs)       # synchronous initialization.
    like_yield(obj.__dfinit__())      # asynchronous initialization.
         # How it works: async_init returns a deferred.  like_yield
         # installs callbacks in the deferred to the greenlet's switch.
         # When the deferred completes, it calls the greenlet's switch
         # causing execution to resume here.
    return obj

default_yield_timeout = 10                   # in seconds.
def timeout_yield(orig_df, timeout = None ):
    """like_yield with a timeout.  Pased timeout is
       in seconds.  If timeout is None then uses
       the default default_yield_timeout.  If the function f
       eventually completes (i.e., its deferred gets called) after
       having already timed out then the result is tossed.

       timeout is set to None rather than default_yield_timeout so that
       the default can be changed after import timeout_yield
       by changing default_yield_timeout.

       WARNING: It is left to the caller to free up any state that might
       be held by the hung deferred.
       """
    assert isinstance(orig_df, defer.Deferred)
    df = defer.Deferred()
    if timeout is None:
        timeout = default_yield_timeout
    t = reactor.callLater(timeout, defer.timeout, df)
    def good(r):
        if t.active():
            df.callback(r)
    def bad(r):
        if t.active():
            df.errback(r)
    orig_df.addCallbacks(good, bad)
    try:
        r = like_yield(df)
    finally:
        if t.active():
            t.cancel()
    return r


# Use this as a decorator to __init__ on any class in order to require the
# class be initialized using init_yield.  This guarantees that the
# asynchronous initializer __dfinit__ gets called.  Ex:
#
#     class Foo(object):
#         @use_init_yield
#         def __init__( self, a, b, c):
#             ...
#         def __dfinit__( self ):
#             ...
#
# Now to instantiate an object of type Foo, we use init_yield:
#
#     foo = init_yield(Foo,a,b,c)
# If we try to instantiate Foo directly, we get an exception:
#
#     foo = Foo(a,b,c)  # causes an AssertionException.
use_init_yield = coro.use_init_yield
