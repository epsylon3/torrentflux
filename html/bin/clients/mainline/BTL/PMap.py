#!/usr/bin/env python

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
# By David Harrison

# I was playing with doctest when I wrote this.  I still haven't
# decided how useful doctest is as opposed to implementing unit tests
# directly.  --Dave

# HEREDAVE: Create a bit in the map that is set whenever the
# the map is changed in a way that would invalidate existing iterators?
# Nope. That won't work. How do you know when to reset the bit.
#
# Another way is for the PMap to maintain a set of all valid iterators.
# Whenever an action occurs that invalidates iterators, the set is cleared.
# Before performing any operation on an iterator, the iterator checks
# whether it is in the valid set.  For CMap, we could maintain a dead bit
# for all values.  When a node is deleted, we set the dead bit.
# Before performing any operation, the iterator checks the dead bit.

from BTL.translation import _

from bisect import bisect_left, bisect_right, insort_left
from copy import copy

# by David Harrison

class PMap(object):
    """This is an ordered mapping.  PMap --> Python Map, because it is
       implemented using dicts and lists.

       Unlike a dict, it can be iterated in order and it supports
       lower and upper bound searches.  It also has an index implemented
       with a dict that allows O(1) time lookups based on key.

       The in-order mapping is implemented with a Python list.  The
       index and cross-index are implemented with dicts.

       Item insertion: O(n)
       Item deletion:  O(n)
       Key search:     O(1)
       Value search:   n/a
       Iteration step: O(1)

       This is not semantically equivalent to CMap or CIndexedMap
       in the following ways:
         - iterators are invalidated by insertions and deletions.
         - time complexity is different for many operations.
       """
           
    class _AbstractIterator(object):
        def __init__(self, map, i = -1 ):
            """Creates an iterator pointing to item si in the map.
            
               Do not instantiate directly.  Use iterkeys, itervalues, or
               iteritems.

               Examples of typical behavior:

               >>> from PMap import *
               >>> m = PMap()
               >>> m[12] = 6
               >>> m[9] = 4
               >>> for k in m:
               ...     print int(k)
               ...
               9
               12
               >>>

               Example edge cases (empty map):

               >>> from PMap import *
               >>> m = PMap()
               >>> try:
               ...     i = m.__iter__()
               ...     i.value()
               ... except IndexError:
               ...     print 'IndexError.'
               ...
               IndexError.
               >>> try:
               ...     i.next()
               ... except StopIteration:
               ...     print 'stopped'
               ...
               stopped

               @param map: PMap.
               @param node: Node that this iterator will point at.  If None
                 then the iterator points to end().  If -1
                 then the iterator points to one before the beginning.
             """
            if i == None: self._i = len(map)
            else: self._i = i
            self._map = map

        def __cmp__(self, other):
            return self._i - other._i

        def key(self):
            """@return: the key of the key-value pair referenced by this
                   iterator.
            """
            if self._i == -1:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to .next."))
                        
            return self._map._olist[self._i].k

        def value(self):
            """@return: the value of the key-value pair currently referenced
                   by this iterator.
            """
            if self._i == -1:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to next."))
            
            return self._map._olist[self._i].v
        
        def item(self):
            """@return the key-value pair referenced by this iterator.
            """
            return self.key(), self.value()

        def _next(self):
            self._i += 1
            if self._i >= len(self._map):
                self._i = len(self._map)
                raise StopIteration()
            
        def _prev(self):
            self._i -= 1
            if self._i <= -1:
                self._i = -1
                raise StopIteration()

            
    class KeyIterator(_AbstractIterator):
        """Returns the next key in the map.
        
           Unlike with CMap, insertion and deletion INVALIDATES iterators.
           
           This is implemented by moving the iterator and then
           dereferencing it.  If we dereferenced and then moved
           then we would get the odd behavior:
           
             Ex:  I have keys [1,2,3].  The iterator i points at 1.
               print i.next()   # prints 1
               print i.next()   # prints 2
               print i.prev()   # prints 3
               print i.prev()   # prints 2
           
           However, because we move and then dereference, when an
           iterator is first created it points to nowhere
           so that the first next moves to the first element.
           
           Ex:
               >>> from PMap import PMap
               >>> m = PMap()
               >>> m[5] = 1
               >>> m[8] = 4
               >>> i = m.__iter__()
               >>> print int(i.next())
               5
               >>> print int(i.next())
               8
               >>> print int(i.prev())
               5
           
           We are still left with the odd behavior that an
           iterator cannot be dereferenced until after the first next().
           
           Ex edge cases:
               >>> from PMap import PMap
               >>> m = PMap()
               >>> i = m.__iter__()
               >>> try:
               ...     i.prev()
               ... except StopIteration:
               ...     print 'StopIteration'
               ...
               StopIteration
               >>> m[5]='a'
               >>> i = m.iterkeys()
               >>> int(i.next())
               5
               >>> try: i.next()
               ... except StopIteration:  print 'StopIteration'
               ...
               StopIteration
               >>> int(i.prev())
               5
               >>> try: int(i.prev())
               ... except StopIteration: print 'StopIteration'
               ...
               StopIteration
               >>> int(i.next())
               5
               
        """
        def next(self):
            self._next()
            return self.key()

        def prev(self):
            self._prev()
            return self.key()
    
    class ValueIterator(_AbstractIterator):
        def next(self):
            """@return: next value in the map.

                >>> from PMap import *
                >>> m = PMap()
                >>> m[5] = 10
                >>> m[6] = 3
                >>> i = m.itervalues()
                >>> int(i.next())
                10
                >>> int(i.next())
                3
            """
            self._next()
            return self.value()

        def prev(self):
            self._prev()
            return self.value()
       
    class ItemIterator(_AbstractIterator):
        def next(self):
            """@return: next item in the map's key ordering.

                >>> from PMap import *
                >>> m = PMap()
                >>> m[5] = 10
                >>> m[6] = 3
                >>> i = m.iteritems()
                >>> k,v = i.next()
                >>> int(k)
                5
                >>> int(v)
                10
                >>> k,v = i.next()
                >>> int(k)
                6
                >>> int(v)
                3
            """
            self._next()
            return self.key(), self.value()

        def prev(self):
            self._prev()
            return self.key(), self.value()
    
    class Item(object):
        def __init__(self, k, v):
            self.k = k
            self.v = v

        def __cmp__(self, other):
            return self.k.__cmp__(other.k)
 
        def __str__(self):
            return "Item(%s,%s)" % ( str(self.k), str(self.v) )

        def __repr__(self):
            return "Item(%s,%s)" % ( str(self.k), str(self.v) )

    def __init__(self, d = {}):
        """
            >>> m = PMap()
            >>> len(m)
            0
            >>> m[5]=2
            >>> len(m)
            1
            >>> print m[5]
            2

        """
        self._olist = []         # list ordered based on key.
        self._index = {}         # keyed based on key.
        for key, value in d.items():
            self[key] = value

    def __contains__(self,x):
        return self.get(x) != None
    
    def __iter__(self):
        """@return: KeyIterator positioned one before the beginning of the
            key ordering so that the first next() returns the first key."""
        return PMap.KeyIterator(self)
    
    def begin(self):
        """Returns an iterator pointing at first key-value pair.  This
           differs from iterkeys, itervalues, and iteritems which return an
           iterator pointing one before the first key-value pair.

           @return: key iterator to first key-value.

              >>> from PMap import *
              >>> m = PMap()
              >>> m[5.0] = 'a'
              >>> i = m.begin()
              >>> int(i.key())    # raises no IndexError.
              5
              >>> i = m.iterkeys()
              >>> try:
              ...     i.key()
              ... except IndexError:
              ...     print 'IndexError raised'
              ...
              IndexError raised
           """
        i = PMap.KeyIterator(self,i=0)
        return i

        
    def end(self):
        """Returns an iterator pointing after end of key ordering.
           The iterator's prev method will move to the last
           key-value pair in the ordering.  This in keeping with
           the notion that a range is specified as [i,j) where
           j is not in the range, and the range [i,j) where i==j
           is an empty range.

           This operation takes O(1) time.

           @return: key iterator one after end.
           """
        i = PMap.KeyIterator(self,None)  # None goes to end of map.
        return i

    def iterkeys(self):
        return PMap.KeyIterator(self)

    def itervalues(self):
        return PMap.ValueIterator(self)

    def iteritems(self):
        return PMap.ItemIterator(self)

    def __len__(self):
        return len(self._olist)

    def __str__(self):
        # dict is not necessarily in order.
        #return str(dict(zip(self.keys(),self.values())))
        s = "{"
        first = True
        for k,v in self.items():
            if first:
                first = False
            else:
                s += ", "
            s += "%d: '%s'" % (k,v)
        s += "}"
        return s
    
    def __getitem__( self, k ):
        return self._index[k]

    def __setitem__(self, k, v ):
        """O(n) insertion worst case. 

            >>> from PMap import PMap
            >>> m = PMap()
            >>> m[6] = 'bar'
            >>> m[6]
            'bar'
            >>>            
            """
        insort_left(self._olist, PMap.Item(k,v))
        self._index[k] = v

    def __delitem__(self, k):
        """
           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[12] = 'foo'
           >>> m[13] = 'bar'
           >>> m[14] = 'boo'
           >>> del m[12]
           >>> try:
           ...   m[12]
           ... except KeyError:
           ...   print 'ok'
           ...
           ok
           >>> j = m.begin()
           >>> int(j.next())
           14
           >>> i = m.begin()
           >>> i.value()
           'bar'
           >>> del m[13]  # delete object referenced by an iterator
        
        """
        del self._index[k]       # raises KeyError if key not in index.
        i=bisect_left(self._olist,PMap.Item(k,None))
        if self._olist[i].k != k: raise KeyError(k)
        del self._olist[i]            
                
    def __del__(self):
        del self._olist
        del self._index
        
    
    def __repr__(self):
        return self.__str__()

    def get(self, key, default=None):
        """@return value corresponding to specified key or return 'default'
               if the key is not found.
           """
        return self._index.get(key,default)


    def keys(self):
        """
           >>> from PMap import *
           >>> m = PMap()
           >>> m[4] = 7
           >>> m[6] = 3
           >>> [int(x) for x in m.keys()]  # m.keys() but guaranteed integers.
           [4, 6]
           
        """
        k = []
        for item in self._olist:
            k.append(item.k)
        return k
    
    def values(self):
        """
           >>> from PMap import PMap
           >>> m = PMap()
           >>> m[4] = 7
           >>> m[6] = 3
           >>> m.values()
           [7, 3]
           
        """
        v = []
        for item in self._olist:
            v.append(item.v)
        return v
        

    def items(self):
        """
           >>> from PMap import PMap
           >>> m = PMap()
           >>> m[4] = 7
           >>> m[6] = 3
           >>> [(int(x[0]),int(x[1])) for x in m.items()]
           [(4, 7), (6, 3)]
           
        """
        itms = []
        for item in self._olist:
            itms.append((item.k,item.v))
        return itms
        
    def has_key(self, key):
        """
           >>> from PMap import PMap
           >>> m = PMap()
           >>> m[4] = 7
           >>> if m.has_key(4): print 'ok'
           ...
           ok
           >>> if not m.has_key(7): print 'ok'
           ...
           ok
           
        """
        return self._index.has_key(key)

    def __del__(self):
        del self._index
        del self._olist

    def clear(self):
        """delete all entries

           >>> from PMap import *
           >>> m = PMap()
           >>> m[4] = 7
           >>> m.clear()
           >>> print len(m)
           0
           
        """

        self.__del__()

        self._olist = []
        self._index = {}

    def copy(self):
        """return shallow copy"""
        return PMap(self)

    def lower_bound(self,key):
        """
         Finds smallest key equal to or above the lower bound.

         Takes O(log n) time.

         @param x: Key of (key, value) pair to be located.
         @return: Key Iterator pointing to first item equal to or greater
                  than key, or end() if no such item exists.

           >>> from PMap import PMap
           >>> m = PMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> i = m.lower_bound(11)   # iterator.
           >>> int(i.key())
           15
           >>> i.value()
           'bar'
           
         Edge cases:
           >>> from PMap import PMap
           >>> m = PMap()
           >>> i = m.lower_bound(11)
           >>> if i == m.end(): print 'ok'
           ...
           ok

           >>> m[10] = 'foo'
           >>> i = m.lower_bound(11)
           >>> if i == m.end(): print 'ok'
           ...
           ok
           >>> i = m.lower_bound(9)
           >>> if i == m.begin(): print 'ok'
           ...
           ok

        """
        return PMap.KeyIterator(self,bisect_right(self._olist,
                                         PMap.Item(key,None)))


    def upper_bound(self, key):
        """
         Finds largest key equal to or below the upper bound.  In keeping
         with the [begin,end) convention, the returned iterator
         actually points to the key one above the upper bound. 

         Takes O(log n) time.

         @param  x:  Key of (key, value) pair to be located.
         @return:  Iterator pointing to first element equal to or greater than
                  key, or end() if no such item exists.

           >>> from PMap import PMap
           >>> m = PMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> m[17] = 'choo'
           >>> i = m.upper_bound(11)   # iterator.
           >>> i.value()
           'bar'

         Edge cases:
           >>> from PMap import PMap
           >>> m = PMap()
           >>> i = m.upper_bound(11)
           >>> if i == m.end(): print 'ok'
           ...
           ok
           >>> m[10] = 'foo'
           >>> i = m.upper_bound(9)
           >>> i.value()
           'foo'
           >>> i = m.upper_bound(11)
           >>> if i == m.end(): print 'ok'
           ...
           ok

        """
        return PMap.KeyIterator(self, bisect_left(self._olist,
                                                 PMap.Item(key,None)))

    def find(self,key):
        """
          Finds the item with matching key and returns a KeyIterator
          pointing at the item.  If no match is found then returns end().
     
          Takes O(log n) time.
     
            >>> from PMap import PMap
            >>> m = PMap()
            >>> i = m.find(10)
            >>> if i == m.end(): print 'ok'
            ...
            ok
            >>> m[10] = 'foo'
            >>> i = m.find(10)
            >>> int(i.key())
            10
            >>> i.value()
            'foo'
     
        """
        i = bisect_left(self._olist,PMap.Item(key,None))
        if i >= len(self._olist): return self.end()
        if self._olist[i].k != key: return self.end()  
        return PMap.KeyIterator(self,i )

    def update_key(self, iter, key):
        """
          Modifies the key of the item referenced by iter.  If the
          key change is small enough that no reordering occurs then
          this takes amortized O(1) time.  If a reordering occurs then
          this takes O(log n).

          WARNING!!! All iterators including the passed iterator must
          be assumed to be invalid upon return.  (Note that this would
          not be the case with CMap, where update_key would at most
          invalidate the passed iterator).

          If the passed key is already in the map then this raises
          a KeyError exception and the map is left unchanged. If the
          iterator is point

          Typical use:
            >>> from CMap import CMap
            >>> m = CMap()
            >>> m[10] = 'foo'
            >>> m[8] = 'bar'
            >>> i = m.find(10)
            >>> m.update_key(i,7)   # i is assumed to be invalid upon return.
            >>> del i
            >>> [(int(x[0]),x[1]) for x in m.items()]  # reordering occurred.
            [(7, 'foo'), (8, 'bar')]
            >>> i = m.find(8)
            >>> m.update_key(i,9)   # no reordering.
            >>> del i
            >>> [(int(x[0]),x[1]) for x in m.items()]
            [(7, 'foo'), (9, 'bar')]

          Edge cases:          
            >>> i = m.find(7)
            >>> i.value()
            'foo'
            >>> try:                # update to key already in the map.
            ...     m.update_key(i,9)
            ... except KeyError:
            ...     print 'ok'
            ...
            ok
            >>> m[7]
            'foo'
            >>> i = m.iterkeys()
            >>> try:                # updating an iter pointing at BEGIN.
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
            >>> i = m.end()
            >>> try:                # updating an iter pointing at end().
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
                        
        """
        old_key = iter.key()
        if key == old_key: return
        try:
            before = copy(iter)
            before.prev()
            lower = before.key()
        except StopIteration:
            lower = old_key - 1  # arbitrarily lower.

        if lower < key:
            try:
                iter.next()
                higher = i.key()
            except StopIteration:
                higher = old_key + 1 # arbitrarily higher.            

            if key < higher:     # if no reordering is necessary...
                self._olist[iter._i].key = key  
                del self._index[old_key]
                self._index[key] = old_val
                return

        # else reordering is necessary so delete and reinsert.
        del self[old_key]
        self[key] = old_val

    def append(self, k, v):
        """Performs an insertion with the hint that it probably should
           go at the end.

           Raises KeyError if the key is already in the map.

             >>> from PMap import *
             >>> m = PMap()
             >>> m.append(5,'foo')
             >>> m
             {5: 'foo'}
             >>> m.append(10, 'bar')
             >>> m
             {5: 'foo', 10: 'bar'}
             >>> m.append(3, 'coo')   # out-of-order.
             >>> m
             {3: 'coo', 5: 'foo', 10: 'bar'}
             >>> try:
             ...     m.append(10, 'blah') # append key already in map.
             ... except KeyError:
             ...     print 'ok'
             ...
             ok
             >>> m
             {3: 'coo', 5: 'foo', 10: 'bar'}

        """
        if self._index.has_key(k):
            raise KeyError(_("Key is already in the map.  "
                             "Keys must be unique."))
                        
        if len(self._olist) == 0 or k > self._olist[len(self._olist)-1].k:
            self._olist.append(PMap.Item(k,v))
        else:
            insort_left(self._olist, PMap.Item(k,v))
        self._index[k] = v

class PIndexedMap(PMap):
    """This is an ordered mapping, exactly like PMap except that it
       provides a cross-index allowing O(1) searches based on value.
       This adds  the constraint that values must be unique.

       The cross-index is implemented with a dict.

       Item insertion: O(n)
       Item deletion:  O(n)
       Key search:     average O(1)
       Value search:   average O(1)
       Iteration step: O(1)
       Memory:         O(n)

       This is not semantically equivalent to CIndexedMap
       in the following ways:
         - iterators are invalidated by insertions and deletions.
       """
           
    def __init__(self, dict={} ):
        """
            >>> m = PIndexedMap()
            >>> len(m)
            0
            >>> m[5]=2
            >>> len(m)
            1
            >>> print m[5]
            2

        """
        self._value_index = {}    # keyed on value.
        PMap.__init__(self, dict)

    def __setitem__(self, k, v ):
        """O(n) insertion worst case. 

            >>> from PMap import *
            >>> m = PIndexedMap()
            >>> m[6] = 'bar'
            >>> m[6]
            'bar'
            >>> m.get_key_by_value('bar')
            6
            >>> try:
            ...    m[7] = 'bar'
            ... except ValueError:
            ...    print 'value error'
            value error
            >>> m[6] = 'foo'  # change 6 so 7 can be mapped to 'bar'
            >>> m[6]
            'foo'
            >>> m[7] = 'bar'
            >>> m[7]
            'bar'
            >>> m[7] = 'bar'  # should not raise exception
            >>> m[7] = 'goo'
            >>> m.get_key_by_value('bar')  # should return None.
            >>>
            
            """
        # if value is already in the map then throw an error.
        try:
            if self._value_index[v] != k:
                raise ValueError( _("Value is already in the map. "
                                  "Both value and key must be unique." ))
        except KeyError:
            # value was not in the cross index.
            pass

        try:
            old_val = self._index[k]
            self._index[k] = v
            del self._value_index[old_val]
            self._value_index[v] = k
            
        except KeyError:
            # key is not already in the map.
            pass
            
        insort_left(self._olist, PIndexedMap.Item(k,v))
        self._value_index[v] = k
        self._index[k] = v

    def __delitem__(self, k):
        """
            >>> from PMap import PIndexedMap
            >>> m = PIndexedMap()
            >>> m[6] = 'bar'
            >>> m[6]
            'bar'
            >>> int(m.get_key_by_value('bar'))
            6
            >>> del m[6]
            >>> if m.get_key_by_value('bar'):
            ...     print 'found'
            ... else:
            ...     print 'not found.'
            not found.

        """
        del self._index[k]       # raises KeyError if key not in index.
        i=bisect_left(self._olist,PIndexedMap.Item(k,None))
        if self._olist[i].k != k: raise KeyError(k)
        v=self._olist[i].v
        del self._value_index[v]
        del self._olist[i]
            
    def get_key_by_value( self, v ):
        """Returns the key cross-indexed from the passed unique value, or
           returns None if the value is not in the map."""
        k = self._value_index.get(v)
        if k == None: return None
        return k

    def find_key_by_value( self, v ):
        """Returns a key iterator cross-indexed from the passed unique value
           or end() if no value found.

           >>> from PMap import *
           >>> m = PIndexedMap()
           >>> m[6] = 'abc'
           >>> i = m.find_key_by_value('abc')
           >>> i.key()
           6
           >>> i = m.find_key_by_value('xyz')
           >>> if i == m.end(): print 'i points at end()'
           i points at end()

        """
        try:
            k = self._value_index[v]  # raises KeyError if no value found.
            i = bisect_left(self._olist,PIndexedMap.Item(k,None))
            return PIndexedMap.KeyIterator(self,i)
        except KeyError, e:
            return self.end()
                
    def __del__(self):
        del self._value_index
        PMap.__del__(self)
    
    def clear(self):
        """delete all entries

           >>> from PMap import PIndexedMap
           >>> m = PIndexedMap()
           >>> m[4] = 7
           >>> m.clear()
           >>> print len(m)
           0
           
        """
        PMap.clear(self)
        self._value_index = {}

    def copy(self):
        """return shallow copy"""
        return PIndexedMap(self)

    def update_key(self, iter, key):
        """
          Modifies the key of the item referenced by iter.  If the
          key change is small enough that no reordering occurs then
          this takes amortized O(1) time.  If a reordering occurs then
          this takes O(log n).

          WARNING!!! The passed iterator MUST be assumed to be invalid
          upon return and should be deallocated.

          If the passed key is already in the map then this raises
          a KeyError exception and the map is left unchanged. If the
          iterator is point

          Typical use:
            >>> from PMap import PIndexedMap
            >>> m = PIndexedMap()
            >>> m[10] = 'foo'
            >>> m[8] = 'bar'
            >>> i = m.find(10)
            >>> m.update_key(i,7)   # i is assumed to be invalid upon return.
            >>> del i
            >>> m                   # reordering occurred.
            {7: 'foo', 8: 'bar'}
            >>> i = m.find(8)
            >>> m.update_key(i,9)   # no reordering.
            >>> del i
            >>> m
            {7: 'foo', 9: 'bar'}

          Edge cases:          
            >>> i = m.find(7)
            >>> i.value()
            'foo'
            >>> try:                # update to key already in the map.
            ...     m.update_key(i,9)
            ... except KeyError:
            ...     print 'ok'
            ...
            ok
            >>> m[7]
            'foo'
            >>> i = m.iterkeys()
            >>> try:                # updating an iter pointing at BEGIN.
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
            >>> i = m.end()
            >>> try:                # updating an iter pointing at end().
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
                        
        """
        old_key = iter.key()
        if key == old_key: return
        old_val = iter.value()
        if self._index.has_key(key): raise KeyError(key)
        try:
            before = copy(iter)
            before.prev()
            lower = before.key()
        except StopIteration:
            lower = old_key - 1  # arbitrarily lower.

        if lower < key:
            try:
                iter.next()
                higher = i.key()
            except StopIteration:
                higher = old_key + 1 # arbitrarily higher.            

            if key < higher:     # if no reordering is necessary...
                self._olist[iter._i].key = key  
                del self._index[old_key]
                self._index[key] = old_val
                self._value_index[old_val] = key
                return

        # else reordering is necessary so delete and reinsert.
        del self[old_key]
        self[key] = old_val

    def append(self, k, v):
        """Performs an insertion with the hint that it probably should
           go at the end.

           Raises KeyError if the key is already in the map.

             >>> from PMap import PIndexedMap
             >>> m = PIndexedMap()
             >>> m.append(5,'foo')
             >>> m
             {5: 'foo'}
             >>> m.append(10, 'bar')
             >>> m
             {5: 'foo', 10: 'bar'}
             >>> m.append(3, 'coo')   # out-of-order.
             >>> m
             {3: 'coo', 5: 'foo', 10: 'bar'}
             >>> m.get_key_by_value( 'bar' )
             10
             >>> try:
             ...     m.append(10, 'blah') # append key already in map.
             ... except KeyError:
             ...     print 'ok'
             ...
             ok
             >>> m
             {3: 'coo', 5: 'foo', 10: 'bar'}
             >>> try:
             ...     m.append(10, 'coo') # append value already in map.
             ... except ValueError:
             ...     print 'ok'
             ...
             ok

        """
        # if value is already in the map then throw an error.
        try:
            if self._value_index[v] != k:
                raise ValueError( _("Value is already in the map. "
                                  "Both values and keys must be unique.") )
        except KeyError:
            # values was not in the cross index.
            pass

        if self._index.has_key(k):
            raise KeyError( _("Key is already in the map.  Both values and "
                            "keys must be unique.") )
                        
        if len(self._olist) == 0 or k > self._olist[len(self._olist)-1].k:
            self._olist.append(PIndexedMap.Item(k,v))
        else:
            insort_left(self._olist, PIndexedMap.Item(k,v))

        self._value_index[v] = k
        self._index[k] = v
        
if __name__ == "__main__":

    import sys, doctest

    ############
    # UNIT TESTS
    if len(sys.argv) == 1:
        import doctest,sys
        print "Testing module"
        doctest.testmod(sys.modules[__name__])

