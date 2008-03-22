# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from BTL.platform import bttime as time
from collections import deque

# The main drawback of this cache is that it only supports one time out 
# delay.  If you experience heavy load of cache inserts at time t then
# at t + expiration time, you will experience a heavy load due to
# expirations.  An alternative is to randomize the timeouts.  With 
# exponentially distributed timeouts we would expect load roughly obeying
# a Poisson process.  However, one can do better if the randomness if a
# function of load such that excess arrivals are redistributed evenly over the
# interval.

class Cache:
    # fixed TTL cache.  This assumes all entries have the same
    # TTL.
    def __init__(self, touch_on_access = False):
        self.data = {}
        self.q = deque()
        self.touch = touch_on_access
        
    def __getitem__(self, key):
        if self.touch:
            v = self.data[key][1]
            self[key] = v
        return self.data[key][1]

    def __setitem__(self, key, value):
        t = time()
        self.data[key] = (t, value)
        self.q.appendleft((t, key, value))

    def __delitem__(self, key):
        del(self.data[key])

    def has_key(self, key):
        return self.data.has_key(key)
    
    def keys(self):
        return self.data.keys()

    def expire(self, expire_time):
        try:
            while self.q[-1][0] < expire_time:
                x = self.q.pop()
                assert(x[0] < expire_time)
                try:
                    t, v = self.data[x[1]]
                    if v == x[2] and t == x[0]:
                        del(self.data[x[1]]) # only eliminates one reference to the
                                             # object.  If there is more than one
                                             # reference (for example if an
                                             # elements was "touched" by getitem)
                                             # then the item persists in the cache
                                             # until the last reference expires.
                                             # Note: frequently touching a cache entry
                                             # with long timeout intervals could be
                                             # viewed as a memory leak since the
                                             # cache can grow quite large.
                                             # This class is best used without
                                             # touch_on_access or with short expirations.
                except KeyError:
                    pass
        except IndexError:
            pass
        
        
