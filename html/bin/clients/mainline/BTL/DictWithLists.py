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
# These are some handy dict types:
#
# DictWithLists:
#   acts like a dict, but adding a key,value appends value to a list at that key
#   getting a value at a key returns the first value in the list
#   a key is only removed when the list is empty
#
# OrderedDict:
#  just like a dict, but d.keys() is in insertion order
#
# OrderedDictWithLists:
#  a combination of the two concepts that keeps lists at key locations in
#  insertion order
#
# by Greg Hazel
# with code from David Benjamin and contributers

from BTL.Lists import QList
from BTL.obsoletepythonsupport import set

class ReallyIterableDict(dict):
    
    # third level takes advantage of second level definitions
    def iteritems(self):
        for k in self:
            yield (k, self[k])
    def iterkeys(self):
        return self.__iter__()

    # fourth level uses definitions from lower levels
    def itervalues(self):
        for _, v in self.iteritems():
            yield v
    def values(self):
        return [v for _, v in self.iteritems()]
    def items(self):
        return list(self.iteritems())
    
class DictWithLists(ReallyIterableDict):

    def __init__(self, d = None, parent = ReallyIterableDict):
        self.parent = parent
        # python dict() can't take None
        if d:
            self.parent.__init__(self, d)
        else:
            self.parent.__init__(self)            

    def popitem(self):
        try:
            key = self.keys()[0]
        except IndexError:
            raise KeyError('popitem(): dictionary is empty')
        return (key, self.pop(key))

    def pop(self, key, *args):
        if key not in self and len(args) > 0:
            return args[0]

        l = self[key]
        data = l.popleft()

        # so we don't leak blank lists
        if len(l) == 0:
            self.parent.__delitem__(self, key)

        return data
    pop_from_row = pop

    def get_from_row(self, key):
        return self[key][0]
            
    def getrow(self, key):
        return self[key]

    def poprow(self, key):
        return self.parent.pop(self, key)

    def setrow(self, key, l):
        if len(l) == 0:
            return
        self.parent.__setitem__(self, key, l)
        
    def push(self, key, value):
        # a little footwork because the QList constructor is slow
        if key not in self:
            v = QList([value])
            self.parent.__setitem__(self, key, v)
        else:
            self[key].append(value)
    push_to_row = push

    def keys(self):
        return self.parent.keys(self)

    def total_length(self):
        t = 0
        for k in self.iterkeys():
            t += len(self.getrow(k))
        return t


class DictWithInts(dict):

    def add(self, value):
        self.setdefault(value, 0)
        self[value] += 1

    def remove(self, value):
        if self[value] == 1:
            del self[value]
        else:
            self[value] -= 1


class DictWithSets(DictWithLists):

    def pop(self, key, *args):
        if key not in self and len(args) > 0:
            return args[0]

        l = self[key]
        data = l.pop()

        # so we don't leak blank sets
        if len(l) == 0:
            self.parent.__delitem__(self, key)

        return data
    pop_from_row = pop
                
    def push(self, key, value):
        if key not in self:
            v = set([value])
            self.parent.__setitem__(self, key, v)
        else:
            self[key].add(value)
    push_to_row = push

    def remove_fom_row(self, key, value):
        l = self[key]
        l.remove(value)

        # so we don't leak blank sets
        if len(l) == 0:
            self.parent.__delitem__(self, key)
        

# from http://aspn.activestate.com/ASPN/Cookbook/Python/Recipe/107747
class OrderedDict(ReallyIterableDict):
    def __init__(self, d = None):
        self._keys = []
        # python dict() can't take None
        if d:
            ReallyIterableDict.__init__(self, dict)
        else:
            ReallyIterableDict.__init__(self)

    def __delitem__(self, key):
        ReallyIterableDict.__delitem__(self, key)
        self._keys.remove(key)

    def __setitem__(self, key, item):
        ReallyIterableDict.__setitem__(self, key, item)
        if key not in self._keys:
            self._keys.append(key)

    def clear(self):
        ReallyIterableDict.clear(self)
        self._keys = []

    def copy(self):
        newInstance = OrderedDict()
        newInstance.update(self)
        return newInstance

    def items(self):
        return zip(self._keys, self.values())

    def keys(self):
        return self._keys[:]

    def __iter__(self):
        return iter(self._keys)

    def pop(self, key):
        val = ReallyIterableDict.pop(self, key)
        self._keys.remove(key)
        return val

    def popitem(self):
        try:
            key = self._keys[0]
        except IndexError:
            raise KeyError('dictionary is empty')

        val = self.pop(key)

        return (key, val)

    def setdefault(self, key, failobj = None):
        if key not in self._keys:
            self._keys.append(key)
        return ReallyIterableDict.setdefault(self, key, failobj)

    def update(self, dict):
        for (key,val) in dict.items():
            self.__setitem__(key,val)

    def values(self):
        return map(self.get, self._keys)

class OrderedDictWithLists(DictWithLists, OrderedDict):

    def __init__(self, dict = None, parent = OrderedDict):
        DictWithLists.__init__(self, dict, parent = parent)

    def __iter__(self):
        return iter(self._keys)


if __name__=='__main__':
    
    d = DictWithLists()

    for i in xrange(50):
        for j in xrange(50):
            d.push(i, j)

    for i in xrange(50):
        for j in xrange(50):
            assert d.pop(i) == j

    od = OrderedDict()

    def make_str(i):
        return str(i) + "extra"

    for i in xrange(50):
        od[make_str(i)] = 1

    for i,j in zip(xrange(50), od.keys()):
        assert make_str(i) == j

    odl = OrderedDictWithLists()

    for i in xrange(50):
        for j in xrange(50):
            odl.push(make_str(i), j)

    for i in xrange(50):
        for j in xrange(50):
            assert odl.pop(make_str(i)) == j

    od = OrderedDict()
    od['2'] = [1,1,1,1,1]
    od['1'] = [2,2,2,2,2]
    od['3'] = [3,3,3,3,3]
    k = od.keys()[0]
    assert k == '2'

    odl = OrderedDictWithLists()
    odl.setrow('2', [1,1,1,1,1])
    odl.setrow('1', [2,2,2,2,2])
    odl.setrow('3', [3,3,3,3,3])
    k = odl.keys()[0]
    assert k == '2'

    od = OrderedDict()
    od['2'] = [1,1,1,1,1]
    od['1'] = [2,2,2,2,2]
    od['3'] = [3,3,3,3,3]
    r = []
    for k in od.iterkeys():
        r.append(k)
    assert r == ['2', '1', '3']

    odl = OrderedDictWithLists()
    odl.setrow('2', [1,1,1,1,1])
    odl.setrow('1', [2,2,2,2,2])
    odl.setrow('3', [3,3,3,3,3])
    r = []
    for k in odl.iterkeys():
        r.append(k)
    assert r == ['2', '1', '3']

    d = DictWithLists()
    d.push(4, 3)
    d.push(4, 4)
    d.push(4, 2)
    d.push(4, 1)    
    assert d.poprow(4) == QList([3,4,2,1])