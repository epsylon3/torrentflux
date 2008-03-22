# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# author: David Harrison

if __name__ == "__main__":
    import sys
    sys.path.append("..")

import sys #DEBUG
from BTL.platform import bttime as time
from BTL.reactor_magic import reactor
from BTL.Map import IndexedMultiMap
#from refcnt import getrc

RECENT_SIZE = 10   # number of items in the recently accessed set.
LEAK_TEST = False

class CacheMap:
    """this cache class allows caching with arbitrary expiration times.  This is different
       from BTL.Cache which assumes all items placed in the cache remain valid for the same
       duration.

       Like a BTL.Map, there can only be one instance of any given key in
       the cache at a time.  Subsequent inserts (__setitem__ or set)
       with the same key, will update the ttl and value for that
       key.

       Unlike a BTL.Map, CacheMap does not perform in-order iteration based on key, and
       key lookups (__getitem__) take average O(1) time.

       The map also has the option to have bounded size in which case it imposes the 
       following replacement algorithm: remove the oldest entries first unless
       those entries are in the recent access set.  Here 'old' refers to duration
       in the cache.  Recent set has bounded size.
       """

    # BTL.Cache places the cache entries in a queue.  We instead maintain an
    # IndexedMultiMap ordered based on expiration times.  The index allows nodes in the
    # map to be looked up in O(1) time based on value.
    def __init__(self, default_ttl = None, expire_interval = 60, touch_on_access = False,
                 max_items = None, recent_items = RECENT_SIZE ):
        """
           @param default_ttl: time to live when using __setitem__ rather than set.
           @param expire_interval:  time between removals of expired items in seconds. Otherwise,
               expired items are removed lazily.
           @param touch_on_access: refresh item expire time by ttl when item is accessed.
           @param max_items: maximum size of cache.  (see replacement algorithm above)
        """
        self._exp = IndexedMultiMap()  # expiration times.  Multiple items can have the same expiration
                                       # times, but there can only be one instance of any one key
                                       # in the CacheMap.
        self._data = {}
        self._ttl = default_ttl
        self._touch = touch_on_access
        self._max_items = max_items
        self._expire_interval = expire_interval
        if max_items is not None:
            self._recent = _BoundedCacheSet(int(min(recent_items,max_items)))
        else:
            self._recent = None
        reactor.callLater(self._expire_interval, self._expire)
        
    def __getitem__(self, key):  # O(1) if not touch and not newly expired, else O(log n)
        """Raises KeyError if the key is not in the cache.
           This can happen if the entry was deleted or expired."""
        ttl,v = self._data[key]  # ttl is duration, not absolute time.
        i = self._exp.find_key_by_value(key)     # O(1). Key in exp is time. 'key' variable
                                                 # is exp's value. :-)
        if i.at_end():
            raise KeyError()
        t = time()
        if i.key() < t:                          # expired.
            del self[key]                        # O(log n)
            raise KeyError()
        if self._recent:
            self._recent.add(key)
        if self._touch:
            self._exp.update_key(i,t+ttl)  # O(1) if no reordering else O(log n)
        return v

    def __setitem__(self, key, value): # O(log n).  actually O(log n + RECENT_SIZE)
        assert self._ttl > 0, "no default TTL defined.  Perhaps the caller should call set " \
               "rather than __setitem__."
        t = time()
        if self._data.has_key(key):
            ttl,_ = self._data[key]
        else:
            ttl = self._ttl
        self.set(key,value,ttl)
        if self._recent:
            self._recent.add(key)

        # perform cache replacement if necessary.
        if self._max_items is not None and len(self._data) > self._max_items:
            to_remove = []
            for t,k in self._exp.iteritems():
                                   # worst case is O(RECENT_SIZE), but it is highly unlikely
                                   # that all members of the recent access set are the oldest
                                   # in the cache.
                if k not in self._recent:
                    to_remove.append(k)
                if len(to_remove) >= len(self._data) - self._max_items:
                    break
            for k in to_remove:
                del self[k]                    

    def set(self, key, value, ttl):
        """Set using non-default TTL.  ttl is a duration, not an absolute
           time."""
        t = time()
        self._data[key] = (ttl, value)
        i = self._exp.find_key_by_value(key)
        if i.at_end():
            self._exp[t+ttl] = key
        else:
            assert i.value() == key
            self._exp.update_key(i,t+ttl)

    def __delitem__(self, key): # O(log n)
        del self._data[key]
        i = self._exp.find_key_by_value(key)
        if not i.at_end():  # No KeyError is generated if item is not in
                            # Cache because it could've been expired.
            self._exp.erase(i)

    def __len__(self):
        """Returns number of entries in the cache.  Includes any
           expired entries that haven't been removed yet.
           Takes O(1) time."""
        return len(self._data)

    def num_unexpired(self):
        """Returns number of unexpired entries in the cache.
           Any expired entries are removed before computing the length.
           Takes worst case O(n) time where n = the number of expired
           entries in the cache when this is called."""
        self._expire2()
        return len(self._data)
    
    def has_key(self, key):
        return self._data.has_key(key)

    def __contains__(self, key):
        return self._data.has_key(key)
    
    def keys(self):
        return self._data.keys()

    def _expire(self):
        self._expire2()              
        reactor.callLater(self._expire_interval, self._expire)

    def _expire2(self):
        t = time()
        #try:
        while True:
          i = self._exp.begin()
          if i.at_end():
              break
          if i.key() < t:
              key = i.value()
              self._exp.erase(i)
              del self._data[key]
          else:
              break
        assert len(self._data) == len(self._exp)
        #except:
        #    pass  # for example if an iterator is invalidated
                  # while expiring.

class _BoundedCacheSet:
    # implements LRU.  I could've implemented this using
    # a set and then removed a random item from the set whenever the set
    # got too large.  Hmmm...
    def __init__(self, max_items):
        assert max_items > 1
        self._max_items = max_items
        self._data = IndexedMultiMap()         # recent accesses.

    def add(self, key): # O(log n)
        i = self._data.find_key_by_value(key)
        t = time()
        if i.at_end():
            self._data[t] = key
        else:
            self._data.update_key(i,t)
            
        while len(self._data) > self._max_items:
            j = self._data.begin()
            assert not j.at_end()
            self._data.erase(j)

    def __contains__(self, key):
        i = self._data.find_key_by_value(key)
        return not i.at_end()
        
    def remove(self, key):
        i = self._data.find_key_by_value(key)
        if i.at_end():
            raise KeyError()
        self._data.erase(i)

    def __str__(self):
        return str(self._data)
                         

if __name__ == "__main__":
    from defer import Deferred
    import random
    from yielddefer import launch_coroutine, wrap_task
    def coro(f, *args, **kwargs):
        return launch_coroutine(wrap_task(reactor.callLater), f, *args, **kwargs)
    
    def run():
        coro(_run)
        
    def _run():
        TTL = 1
        SET_TTL = 2      # TTL used when explicitly setting TTL using "def set."
        EXPIRE_INTERVAL = .3
        EPSILON = .5

        ###
        # BoundedCacheSet correctness tests.
        c = _BoundedCacheSet(2)
        c.add(10)
        assert 10 in c
        c.add(15)
        assert 15 in c
        c.add(16)
        assert 16 in c
        assert 10 not in c
        assert 15 in c
        c.remove(15)
        assert 15 not in c
        try:
            c.remove(23)
            assert False
        except KeyError:
            pass

        ###
        # basic CacheMap correctness tests.
        c = CacheMap(default_ttl=TTL,expire_interval=EPSILON)
        class K(object):
            def __init__(self):
                self.x = range(10000)
        class V(object):
            def __init__(self):
                self.x = range(10000)
        
        k = K()
        v = V()
        t = time()
        c.set(k, v, SET_TTL)
        assert len(c) == 1
        assert c.num_unexpired() == 1
        assert c._exp.begin().key() < t + SET_TTL + EPSILON and \
               c._exp.begin().key() > t + SET_TTL - EPSILON, \
               "First item in c._exp should have expiration time that is close to the " \
               "current time + SET_TTL which is %s, but the expiration time is %s." \
               % (t+SET_TTL, c._exp.begin().key())               
        assert c.has_key(k)
        assert not c.has_key( "blah" )
        assert c[k] == v
        c._expire2()  # should not expire anything because little time has passed.
        assert len(c) == 1
        assert c.num_unexpired() == 1
        try:
            y = c[10]
            assert False, "should've raised KeyError."
        except KeyError:
            pass
        v2 = V()
        c[k] = v2
        assert c._exp.begin().key() < t + SET_TTL + EPSILON and \
               c._exp.begin().key() > t + SET_TTL - EPSILON, \
               "First item in c._exp should have expiration time that is close to the " \
               "current time + SET_TTL, but the expiration time is %s." % c._exp.begin().key()
        assert not c[k] == v
        assert c[k] == v2
        assert len(c) == 1
        assert c.num_unexpired() == 1
        k2 = K()
        t = time()
        c[k2] = v2
        assert c._exp.begin().key() < t + TTL + EPSILON and \
               c._exp.begin().key() > t + TTL - EPSILON, \
               "First item in c._exp should have expiration time that is close to the " \
               "current time + TTL, but the expiration time is %s." % c._exp.begin().key()
        assert c[k2] == v2
        assert not c[k] == v  # shouldn't be a problem with two items having the same value.
        assert len(c) == 2
        assert c.num_unexpired() == 2

        # wait long enough for the cache entries to expire.
        df = Deferred()
        reactor.callLater(SET_TTL+EPSILON, df.callback, None)
        yield df
        df.getResult()

        assert c.num_unexpired() == 0, "Should have expired all entries, but there are %d " \
               "unexpired items and %d items in c._data. " % (c.num_unexpired(), len(c._data))
        assert len(c) == 0
        assert len(c._exp) == 0
        assert len(c._data) == 0
        assert k not in c
        assert k2 not in c

        # basic correctness of bounded-size cache map.
        c = CacheMap(default_ttl=TTL,expire_interval=1000,max_items = 2)
        c[k] = v
        assert len(c) == 1
        assert c[k] == v
        c[k2] = v2
        assert len(c) == 2
        assert c[k2] == v2
        c[10] = 15
        assert len(c) == 2
        assert c[10] == 15
        assert c[k2] == v2   # order from most recent access is now [(k2,v2), (10,15), (k,v)].
        try:
            a = c[k]
            assert False, "when cache with size bound of 2 exceeded 2 elements, " \
                   "the oldest should've been removed."
        except KeyError:
            pass
        c[56] = 1          # order from most recent access ...
        assert len(c) == 2
        assert 56 in c
        assert 10 not in c
            
        
        ###
        # test expirations and for memory leaks.
        # Watch memory consumption (e.g., using top) and see if it grows.
        if LEAK_TEST:
            c = CacheMap(default_ttl=TTL,expire_interval=EPSILON)
            i = 0
            while True:
                for x in xrange(100):
                    i += 1
                    if i % 20 == 0:
                        print len(c)
                    c[i] = K()
                    if i % 5 == 0:
                        try:
                            l = len(c)
                            del c[i]
                            assert len(c) == l-1
                        except KeyError:
                            pass
    
                # allow time for expirations.
                df = Deferred()
                reactor.callLater(TTL+EPSILON,df.callback,None)
                yield df
                df.getResult()


    reactor.callLater(0,run)
    reactor.run()
