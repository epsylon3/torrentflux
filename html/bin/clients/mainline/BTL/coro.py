# coroutine convenience functions

from BTL.reactor_magic import reactor
from BTL.yielddefer import launch_coroutine
from BTL.defer import wrap_task, Deferred

def coro(f, *args, **kwargs):
    return launch_coroutine(wrap_task(reactor.callLater), f, *args, **kwargs)

def coroutine(_f):
    """Use the following as a decorator. Ex:

          @coroutine
          def mycoro():
             ...
             yield df
             df.getResult()

          ...
          df = mycoro()
          ...

       Unlike the coroutine decorator in greenlet_yielddefer, this works without
       greenlets.  This is also typically cleaner than using coro().
       """
    def replacement(*a, **kw):
        return launch_coroutine(wrap_task(reactor.callLater), _f, *a, **kw)
    return replacement

def wait(n):
    df = Deferred()
    reactor.callLater(n, df.callback, 0)
    return df


@coroutine
def init_yield(clss, *args, **kwargs):
    """Instantiate an object of type clss and then call its asynchronous initializer
       (__dfinit__). The __dfinit__ returns a Deferred.  When the deferred's callback is called
       execution resumes at init_yield and the fully initialized object is returned."""
    kwargs['__magic_init_yield'] = True
    obj = clss(*args, **kwargs)       # synchronous initialization.
    df = obj.__dfinit__()                # asynchronous (deferred) initialization.
    yield df
    df.getResult()
    yield obj

def use_init_yield( init ):
    """Use this as a decorator to any class's __init__ to require that
       class be initialized using init_yield.  This guarantees that the
       asynchronous initializer __dfinit__ gets called.  Ex:

           class Foo(object):
               @use_init_yield
               def __init__( self, a, b, c):
                   ...
                   some_synchronous_initialization()
                   ...
               def __dfinit__( self ):
                   ...
                   df = some_initialization()
                   return df
                   ...

       Now to instantiate an object of type Foo, we use init_yield:

           df = init_yield(Foo,a,b,c)
           yield df
           foo = df.getResult()

       If we try to instantiate Foo directly, we get an exception:

           foo = Foo(a,b,c)  # causes an AssertionException.
       """
    def look_for_magic( *a, **kw ):
        assert '__magic_init_yield' in kw, "Instantiate using init_yield"
        del kw['__magic_init_yield']
        init(*a, **kw)   # __init__ returns nothing.
    return look_for_magic
