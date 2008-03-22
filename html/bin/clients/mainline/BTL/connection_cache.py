import sys
import time
import random
import Queue
import traceback
from LIFOQueue import LIFOQueue
import pycurllib

max_wait = 5
max_connections = 1
inf_wait_max_connections = 1000

class ConnectionCache(object):
    def __init__(self, max=15):
        self.size = 0
        self.max = max
        self.cache = LIFOQueue(maxsize = self.max)

    def get_connection(self):

        if self.size > max_connections:
            # ERROR: Should log this!
            #sys.stderr.write("ConnectionCache queue exceeds %d (%d)\n" %
            #                 (max_connections, self.cache.qsize()))
            pass

        try:
            return self.cache.get_nowait()
        except Queue.Empty:
            pass

        # I chose not to lock here. Max is advisory, if two threads
        # eagerly await a connection near max, I say allow them both
        # to make one
        if self.size < self.max:
            self.size += 1
            return self._make_connection()

        try:
            return self.cache.get(True, max_wait)
        except Queue.Empty:
            # ERROR: Should log this!
            #sys.stderr.write("ConnectionCache waited more than "
            #                 "%d seconds for one of %d connections!\n" %
            #                 (max_wait, self.size))
            pass

        if self.size > inf_wait_max_connections:
            return self.cache.get()

        self.size += 1
        return self._make_connection()

    def put_connection(self, c):
        self.cache.put(c)


class PyCURL_Cache(ConnectionCache):

    def __init__(self, uri, max):
        self.uri = uri
        ConnectionCache.__init__(self, max)

    def _make_connection(self):
        r = pycurllib.Request(self.uri)
        #r.set_timeout(20)
        return r

class CacheSet(object):
    def __init__(self, max_per_cache = max_connections):
        self.cache = {}
        self.max_per_cache = max_per_cache

    def get_cache(self, cachetype, url):
        if url not in self.cache:
            self.cache[url] = cachetype(url, max=self.max_per_cache)
        return self.cache[url]

cache_set = CacheSet()        

