# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

try:
    from random import sample
except ImportError:
    from random import choice
    def sample(l, n):
        if len(l) <= n:
            return l
        d = {}
        while len(d) < n:
            d[choice(l)] = 1
        return d.keys()
    
from BTL.platform import bttime as time

class KItem:
    def __init__(self, key, value):
        self.t = time()
        self.k = key
        self.v = value
    def __cmp__(self, a):
        # same value = same item, only used to keep dupes out of db
        if self.v == a.v:
            return 0

        # compare by time
        if self.t < a.t:
            return -1
        elif self.t > a.t:
            return 1
        else:
            return 0

    def __hash__(self):
        return self.v.__hash__()
    
    def __repr__(self):
        return `(self.k, self.v, time() - self.t)`

## in memory data store for distributed tracker
## keeps a list of values per key in dictionary
## keeps expiration for each key in a queue
## can efficiently expire all values older than a given time
## can insert one val at a time, or a list:  ks['key'] = 'value' or ks['key'] = ['v1', 'v2', 'v3']
class KStore:
    def __init__(self):
        self.d = {}
        self.q = []
        
    def __getitem__(self, key):
        return [x.v for x in self.d[key]]

    def __setitem__(self, key, value):
        if type(value) == type([]):
            [self.__setitem__(key, v) for v in value]
            return
        x = KItem(key, value)
        try:
            l = self.d[key]
        except KeyError:
            self.d[key] = [x]
        else:
            # this is slow
            try:
                i = l.index(x)
                del(l[i])
            except ValueError:
                pass
            l.insert(0, x)
        self.q.append(x)

    def __delitem__(self, key):
        del(self.d[key])

    def __len__(self):
        return len(self.d)
    
    def keys(self):
        return self.d.keys()

    def values(self):
        return [self[key] for key in self.keys()]

    def items(self):
        return [(key, self[key]) for key in self.keys()]

    def expire(self, t):
        #.expire values inserted prior to t
        try:
            while self.q[0].t <= t:
                x = self.q.pop(0)
                try:
                    l = self.d[x.k]
                    try:
                        while l[-1].t <= t:
                            l.pop()
                    except IndexError:
                        del(self.d[x.k])
                except KeyError:
                    pass
        except IndexError:
            pass
    
    def sample(self, key, n):
        # returns n random values of key, or all values if less than n
        try:
            l = [x.v for x in sample(self.d[key], n)]
        except ValueError:
            l = [x.v for x in self.d[key]]
        return l
