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

if __name__ == '__main__':
    import sys
    sys.path = ['.','..'] + sys.path  # HACK to simplify unit testing.

from BTL.translation import _

class BEGIN:    # represents special BEGIN location before first next.
    pass

from UserDict import DictMixin
from cmap_swig import *
import sys
from weakref import WeakKeyDictionary
LEAK_TEST = False

class CMap(object,DictMixin):  
    """In-order mapping. Provides same operations and behavior as a dict,
       but provides in-order iteration.  Additionally provides operations to
       find the nearest key <= or >= a given key.

       This provides a significantly wider set of operations than
       berkeley db BTrees, but it provides no means for persistence.

       LIMITATION: The key must be a python numeric type, e.g., an integer
       or a float.  The value can be any python object.

         Operation:       Time                 Applicable
                          Complexity:          Methods:
         ---------------------------------------------------
         Item insertion:  O(log n)             append, __setitem__
         Item deletion:   O(log n + k)         __delitem__, erase   
         Key search:      O(log n)             __getitem__, get, find, 
                                               __contains__
         Value search:    n/a
         Iteration step:  amortized O(1),      next, prev
                          worst-case O(log n)
         Memory:          O(n)

       n = number of elements in map.  k = number of iterators pointing
       into map.  CMap assumes there are few iterators in existence at 
       any given time. 
       
       Iterators are not invalidated by insertions.  Iterators are
       invalidated by deletions only when the key-value pair
       referenced is deleted.  Deletion has a '+k' because the
       __delitem__ searches linearly through the set of iterators
       pointing into this map to find any iterator pointing at the
       deleted item and then invalidates the iterator.

       This class is backed by the C++ STL map class, but conforms
       to the Python container interface."""

    class _AbstractIterator:
        """Iterates over elements in the map in order."""

        def __init__(self, m, si = BEGIN ): # "s.." implies swig object.
            """Creates an iterator pointing to element si in map m.
            
               Do not instantiate directly.  Use iterkeys, itervalues, or
               iteritems.

               The _AbstractIterator takes ownership of any C++ iterator
               (i.e., the swig object 'si') and will deallocate it when
               the iterator is deallocated.

               Examples of typical behavior:

               >>> from CMap import *
               >>> m = CMap()
               >>> m[12] = 6
               >>> m[9] = 4
               >>> for k in m:
               ...     print int(k)
               ...
               9
               12
               >>>

               Example edge cases (empty map):

               >>> from CMap import *
               >>> m = CMap()
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

               @param map: CMap.
               @param node: Node that this iterator will point at.  If None
                 then the iterator points to end().  If BEGIN
                 then the iterator points to one before the beginning.
             """
            assert isinstance(m, CMap)
            assert not isinstance(si, CMap._AbstractIterator)
            if si == None:
                self._si = map_end(m._smap)
            else:
                self._si = si           # C++ iterator wrapped by swig.
            self._map = m
            m._iterators[self] = 1      # using map as set of weak references.

        def __hash__(self):
            return id(self)
        
        def __cmp__(self, other):
            if not self._si or not other._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN and other._si == BEGIN: return 0
            if self._si == BEGIN and other._si != BEGIN: return -1
            elif self._si != BEGIN and other._si == BEGIN: return 1
            return iter_cmp(self._map._smap, self._si, other._si )

        def at_begin(self):
            """equivalent to self == m.begin() where m is a CMap.
            
                 >>> from CMap import CMap
                 >>> m = CMap()
                 >>> i = m.begin()
                 >>> i == m.begin()
                 True
                 >>> i.at_begin()
                 True
                 >>> i == m.end()   # no elements so begin()==end()
                 True
                 >>> i.at_end()
                 True
                 >>> m[6] = 'foo'   # insertion does not invalidate iterators.
                 >>> i = m.begin()
                 >>> i == m.end()
                 False
                 >>> i.value()
                 'foo'
                 >>> try:           # test at_begin when not at beginning.
                 ...    i.next()
                 ... except StopIteration:
                 ...    print 'ok'
                 ok
                 >>> i.at_begin()
                 False
                     
                 
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:  # BEGIN is one before begin().  Yuck!!
                return False
            return map_iter_at_begin(self._map._smap, self._si)
        
        def at_end(self):
            """equivalent to self == m.end() where m is a CMap, but
               at_end is faster because it avoids the dynamic memory
               alloation in m.end().

                 >>> from CMap import CMap
                 >>> m = CMap()
                 >>> m[6] = 'foo'
                 >>> i = m.end()   # test when at end.
                 >>> i == m.end()
                 True
                 >>> i.at_end()
                 True
                 >>> int(i.prev())
                 6
                 >>> i.at_end()    # testing when not at end.
                 False

               """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                return False
            return map_iter_at_end(self._map._smap, self._si)
        
        def key(self):
            """@return: the key of the key-value pair referenced by this
                   iterator.
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to .next."))
            elif map_iter_at_end(self._map._smap, self._si):
                raise IndexError()
            
            return iter_key(self._si)

        def value(self):
            """@return: the value of the key-value pair currently referenced
                   by this iterator.
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to next."))
            elif map_iter_at_end(self._map._smap, self._si):
                raise IndexError()

            return iter_value(self._si)
        
        def item(self):
            """@return the key-value pair referenced by this iterator.
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            return self.key(), self.value()

        def _next(self):
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                self._si = map_begin(self._map._smap)

                if map_iter_at_end(self._map._smap,self._si):
                    raise StopIteration
                return

            if map_iter_at_end(self._map._smap,self._si):
                raise StopIteration

            iter_incr(self._si)

            if map_iter_at_end(self._map._smap,self._si):
                raise StopIteration
            
        def _prev(self):
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise StopIteration()
            
            elif map_iter_at_begin(self._map._smap, self._si):
                self._si = BEGIN
                raise StopIteration

            iter_decr(self._si)

        def __del__(self):
            # Python note: if a reference to x is intentionally
            # eliminated using "del x" and there are other references
            # to x then __del__ does not get called at this time.
            # Only when the last reference is deleted by an intentional
            # "del" or when the reference goes out of scope does
            # the __del__ method get called.
            self._invalidate()
            
        def _invalidate(self):
            if self._si == None:
                return
            try:
                del self._map._iterators[self]
            except KeyError:
                pass  # could've been removed because weak reference,
                      # and because _invalidate is called from __del__.
            if self._si != BEGIN:
                iter_delete(self._si)
            self._si = None

        def __iter__(self):
            """If the iterator is itself iteratable then we do things like:
                >>> from CMap import CMap
                >>> m = CMap()
                >>> m[10] = 'foo'
                >>> m[11] = 'bar'
                >>> for x in m.itervalues():
                ...     print x
                ...
                foo
                bar
                
            """
            return self

        def __len__(self):
            return len(self._map)

    class KeyIterator(_AbstractIterator):
        def next(self):
            """Returns the next key in the map.
            
               Insertion does not invalidate iterators.  Deletion only
               invalidates an iterator if the iterator pointed at the
               key-value pair being deleted.
               
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
                   >>> from CMap import *
                   >>> m = CMap()
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
                   >>> from CMap import CMap
                   >>> m = CMap()
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
            self._next()
            return self.key()

        def prev(self):
            """Returns the previous key in the map.

               See next() for more detail and examples.
               """
            self._prev()
            return self.key()

    class ValueIterator(_AbstractIterator):
        def next(self):
            """@return: next value in the map.

                >>> from CMap import *
                >>> m = CMap()
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

                >>> from CMap import CMap
                >>> m = CMap()
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
    
    def __init__(self, d={} ):
        """Instantiate RBTree containing values from passed dict and
           ordered based on cmp.

            >>> m = CMap()
            >>> len(m)
            0
            >>> m[5]=2
            >>> len(m)
            1
            >>> print m[5]
            2

        """
        #self._index = {}                # to speed up searches.
        self._smap = map_constructor()  # C++ map wrapped by swig.
        for key, value in d.items():
            self[key]=value
        self._iterators = WeakKeyDictionary()
                                   # whenever node is deleted. search iterators
                                   # for any iterator that becomes invalid.

    def __contains__(self,x):
        return self.get(x) != None

    def __iter__(self):
        """@return: KeyIterator positioned one before the beginning of the
            key ordering so that the first next() returns the first key."""
        return CMap.KeyIterator(self)

    def begin(self):
        """Returns an iterator pointing at first key-value pair.  This
           differs from iterkeys, itervalues, and iteritems which return an
           iterator pointing one before the first key-value pair.

           @return: key iterator to first key-value.

              >>> from CMap import *
              >>> m = CMap()
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
        i = CMap.KeyIterator(self, map_begin(self._smap) )
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
        i = CMap.KeyIterator(self,None) # None means one after last node.
        return i

    def iterkeys(self):
        return CMap.KeyIterator(self)

    def itervalues(self):
        return CMap.ValueIterator(self)

    def iteritems(self):
        return CMap.ItemIterator(self)

    def __len__(self):
        return map_size(self._smap)

    def __str__(self):
        s = "{"
        first = True
        for k,v in self.items():
            if first:
                first = False
            else:
                s += ", "
            if type(v) == str:
                s += "%s: '%s'" % (k,v)
            else:
                s += "%s: %s" % (k,v)
        s += "}"
        return s
    
    def __repr__(self):
        return self.__str__()
    
    def __getitem__(self, key):
        # IMPL 1: without _index
        return map_find(self._smap,key)     # raises KeyError if key not found

        # IMPL 2: with _index.
        #return iter_value(self._index[key])

    def __setitem__(self, key, value):
        """
            >>> from CMap import CMap
            >>> m = CMap()
            >>> m[6] = 'bar'
            >>> m[6]
            'bar'
            >>>
            """
        assert type(key) == int or type(key) == float
        
        # IMPL 1. without _index.
        map_set(self._smap,key,value)

        ## IMPL 2. with _index
        ## If using indices following allows us to perform only one search.
        #i = map_insert_iter(self._smap,key,value)
        #if iter_value(i) != value:
        #    iter_set(i,value)     
        #else: self._index[key] = i
        ## END IMPL2

    def __delitem__(self, key):
        """Deletes the item with matching key from the map.

           This takes O(log n + k) where n is the number of elements
           in the map and k is the number of iterators pointing into the map.
           Before deleting the item it linearly searches through
           all iterators pointing into the map and invalidates any that
           are pointing at the item about to be deleted.

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
           >>> try:
           ...   i.value()
           ... except RuntimeError:
           ...   print 'ok'
           ok
           >>> j.value()   # deletion should not invalidate other iterators.
           'boo'

           """
        
        #map_erase( self._smap, key )  # map_erase is dangerous.  It could
                                       # delete the node causing an iterator
                                       # to become invalid. --Dave
                                       
        si = map_find_iter( self._smap, key )  # si = swig'd iterator.
        if map_iter_at_end(self._smap, si):
            iter_delete(si)
            raise KeyError(key)

        for i in list(self._iterators):
            if iter_cmp( self._smap, i._si, si ) == 0:
                i._invalidate()
        map_iter_erase( self._smap, si )
        iter_delete(si)

        #iter_delete( self._index[key] )  # IMPL 2. with _index.
        #del self._index[key]             # IMPL 2. with _index.

    def erase(self, iter):
        """Remove item pointed to by the iterator.  All iterators that
           point at the erased item including the passed iterator
           are immediately invalidated after the deletion completes.

           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[12] = 'foo'
           >>> i = m.find(12)
           >>> m.erase(i)
           >>> len(m) == 0
           True

           """
        if not iter._si:
            raise RuntimeError( _("invalid iterator") )
        if iter._si == BEGIN:
            raise IndexError(_("Iterator does not point at key-value pair" ))
        if self is not iter._map:
            raise IndexError(_("Iterator points into a different CMap."))
        if map_iter_at_end(self._smap, iter._si):
            raise IndexError( _("Cannot erase end() iterator.") )

        # invalidate iterators.
        for i in list(self._iterators):
            if iter._si is not i._si and iiter_cmp( self._smmap, iter._si, i._si ) == 0:
                i._invalidate()

        # remove item from the map.
        map_iter_erase( self._smap, iter._si )        

        # invalidate last iterator pointing to the deleted location in the map.
        iter._invalidate()

    def __del__(self):

        # invalidate all iterators.
        for i in list(self._iterators):
            i._invalidate()
        map_delete(self._smap)

    def get(self, key, default=None):
        """@return value corresponding to specified key or return 'default'
               if the key is not found.
           """
        try:
            return map_find(self._smap,key)     # IMPL 1. without _index.
            #return iter_value(self._index[key])  # IMPL 2. with _index.

        except KeyError:
            return default

    def keys(self):
        """
           >>> from CMap import *
           >>> m = CMap()
           >>> m[4.0] = 7
           >>> m[6.0] = 3
           >>> [int(x) for x in m.keys()]  # m.keys() but guaranteed integers.
           [4, 6]
           
        """
        k = []
        for key in self:
            k.append(key)
        return k
    
    def values(self):
        """
           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[4.0] = 7
           >>> m[6.0] = 3
           >>> m.values()
           [7, 3]
           
        """
        i = self.itervalues()
        v = []
        try:
            while True:
                v.append(i.next())
        except StopIteration:
            pass
        return v
        

    def items(self):
        """
           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[4.0] = 7
           >>> m[6.0] = 3
           >>> [(int(x[0]),int(x[1])) for x in m.items()]
           [(4, 7), (6, 3)]
           
        """
    
        i = self.iteritems()
        itms = []
        try:
            while True:
                itms.append(i.next())
        except StopIteration:
            pass
        
        return itms
    
    def has_key(self, key):
        """
           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[4.0] = 7
           >>> if m.has_key(4): print 'ok'
           ...
           ok
           >>> if not m.has_key(7): print 'ok'
           ...
           ok
           
        """
        try:
            self[key]
        except KeyError:
            return False
        return True

    def clear(self):
        """delete all entries

           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[4] = 7
           >>> m.clear()
           >>> print len(m)
           0
           
        """

        self.__del__()
        self._smap = map_constructor()

    def copy(self):
        """return shallow copy"""
        return CMap(self)

    def lower_bound(self,key):
        """
         Finds smallest key equal to or above the lower bound.

         Takes O(log n) time.

         @param x: Key of (key, value) pair to be located.
         @return: Key Iterator pointing to first item equal to or greater
                  than key, or end() if no such item exists.

           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> i = m.lower_bound(11)   # iterator.
           >>> int(i.key())
           15
           >>> i.value()
           'bar'
           
        Edge cases:
           >>> from CMap import CMap
           >>> m = CMap()
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
        return CMap.KeyIterator(self, map_lower_bound( self._smap, key ))


    def upper_bound(self, key):
        """
         Finds largest key equal to or below the upper bound.  In keeping
         with the [begin,end) convention, the returned iterator
         actually points to the key one above the upper bound. 

         Takes O(log n) time.

         @param  x:  Key of (key, value) pair to be located.
         @return:  Iterator pointing to first element equal to or greater than
                  key, or end() if no such item exists.

           >>> from CMap import CMap
           >>> m = CMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> m[17] = 'choo'
           >>> i = m.upper_bound(11)   # iterator.
           >>> i.value()
           'bar'

         Edge cases:
           >>> from CMap import CMap
           >>> m = CMap()
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
        return CMap.KeyIterator(self, map_upper_bound( self._smap, key ))

    def find(self,key):
        """
          Finds the item with matching key and returns a KeyIterator
          pointing at the item.  If no match is found then returns end().
     
          Takes O(log n) time.
     
            >>> from CMap import CMap
            >>> m = CMap()
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
        return CMap.KeyIterator(self, map_find_iter( self._smap, key ))

    def update_key( self, iter, key ):
        """
          Modifies the key of the item referenced by iter.  If the
          key change is small enough that no reordering occurs then
          this takes amortized O(1) time.  If a reordering occurs then
          this takes O(log n).

          WARNING!!! The passed iterator MUST be assumed to be invalid
          upon return and should be deallocated.

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
            >>> try:                 # updating an iter pointing at BEGIN.
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
            >>> i = m.end()
            >>> try:                 # updating an iter pointing at end().
            ...    m.update_key(i,10)
            ... except IndexError:
            ...    print 'ok'
            ...
            ok
                        
        """
        assert isinstance(iter,CMap._AbstractIterator)
        if iter._si == BEGIN:
            raise IndexError( _("Iterator does not point at key-value pair") )
        if self is not iter._map:
            raise IndexError(_("Iterator points into a different CIndexedMap."))
        if map_iter_at_end(self._smap, iter._si):
            raise IndexError( _("Cannot update end() iterator.") )
        map_iter_update_key(self._smap, iter._si, key)

    def append(self, key, value):
        """Performs an insertion with the hint that it probably should
           go at the end.

           Raises KeyError if the key is already in the map.

             >>> from CMap import CMap
             >>> m = CMap()
             >>> m.append(5.0,'foo')    # append to empty map.
             >>> len(m)
             1
             >>> [int(x) for x in m.keys()] # see note (1)
             [5]
             >>> m.append(10.0, 'bar')  # append in-order
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(5, 'foo'), (10, 'bar')]
             >>> m.append(3.0, 'coo')   # out-of-order.
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar')]
             >>> try:
             ...     m.append(10.0, 'blah') # append key already in map.
             ... except KeyError:
             ...     print 'ok'
             ...
             ok
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar')]
             >>>

             note (1): int(x[0]) is used because 5.0 can appear as either 5
             or 5.0 depending on the version of python.
           """
        map_append(self._smap,key,value)
    
        

class CIndexedMap(CMap):
    """This is an ordered mapping, exactly like CMap except that it
       provides a cross-index allowing average O(1) searches based on value.
       This adds  the constraint that values must be unique.

         Operation:       Time                 Applicable
                          Complexity:          Methods:
         ---------------------------------------------------
         Item insertion:  O(log n)             append, __setitem__
         Item deletion:   O(log n + k)         __delitem__, erase   
         Key search:      O(log n)             __getitem__, get, find, 
                                               __contains__
         Value search:    average O(1)  as per dict
         Iteration step:  amortized O(1),      next, prev
                          worst-case O(log n)
         Memory:          O(n)

       n = number of elements in map.  k = number of iterators pointing
       into map.  CIndexedMap assumes there are few iterators in existence 
       at any given time. 

       The hash table increases the factor in the
       O(n) memory cost of the Map by a constant
    """
    
    def __init__(self, dict={} ):
        CMap.__init__(self,dict)
        self._value_index = {}   # cross-index. maps value->iterator.

    def __setitem__(self, key, value):
        """
            >>> from CMap import *
            >>> m = CIndexedMap()
            >>> m[6] = 'bar'
            >>> m[6]
            'bar'
            >>> int(m.get_key_by_value('bar'))
            6
            >>> try:
            ...    m[7] = 'bar'
            ... except ValueError:
            ...    print 'value error'
            value error
            >>> m[6] = 'foo'
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
        assert type(key) == int or type(key) == float
        if self._value_index.has_key(value) and \
           iter_key(self._value_index[value]) != key:
            raise ValueError( _("Value %s already exists.  Values must be "
                "unique.") % str(value) )

        si = map_insert_iter(self._smap,key,value) # si points where insert
                                                   # should occur whether 
                                                   # insert succeeded or not.
                                                   # si == "swig iterator"
        sival = iter_value(si)
        if sival != value:          # if insert failed because k already exists
            iter_set(si,value)      # then force set.
            self._value_index[value] = si
            viter = self._value_index[sival]
            iter_delete(viter)     # remove old value from index
            del self._value_index[sival]  
        else:                      # else insert succeeded so update index.
            self._value_index[value] = si
        #self._index[key] = si       # IMPL 2. with _index.

    def __delitem__(self, key):
        """
            >>> from CMap import CIndexedMap
            >>> m = CIndexedMap()
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
        i = map_find_iter( self._smap, key )
        if map_iter_at_end( self._smap, i ):
            iter_delete(i)
            raise KeyError(key)
        else:
            value = iter_value(i)
            for i in list(self._iterators):
                if iter_cmp( self._smap, i._si, iter._si ) == 0:
                    i._invalidate()
            map_iter_erase( self._smap, i )
            viter = self._value_index[value]
            iter_delete(i)
            iter_delete( viter )
            del self._value_index[value]
            #del self._index[key]         # IMPL 2. with _index.
        assert map_size(self._smap) == len(self._value_index)

    def has_value(self, value):
       return self._value_index.has_key(value)

    def get_key_by_value(self, value):
        """Returns the key cross-indexed from the passed unique value, or
           returns None if the value is not in the map."""
        si = self._value_index.get(value)  # si == "swig iterator"
        if si == None: return None
        return iter_key(si)

    def append( self, key, value ):
        """See CMap.append

             >>> from CMap import CIndexedMap
             >>> m = CIndexedMap()
             >>> m.append(5,'foo')
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(5, 'foo')]
             >>> m.append(10, 'bar')
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(5, 'foo'), (10, 'bar')]
             >>> m.append(3, 'coo')   # out-of-order.
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar')]
             >>> int(m.get_key_by_value( 'bar' ))
             10
             >>> try:
             ...     m.append(10, 'blah') # append key already in map.
             ... except KeyError:
             ...     print 'ok'
             ...
             ok
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar')]
             >>> try:
             ...     m.append(10, 'coo') # append value already in map.
             ... except ValueError:
             ...     print 'ok'
             ...
             ok

        """
        if self._value_index.has_key(value) and \
           iter_key(self._value_index[value]) != key:
            raise ValueError(_("Value %s already exists and value must be "
                "unique.") % str(value) )
        
        si = map_append_iter(self._smap,key,value)
        if iter_value(si) != value:
            iter_delete(si)
            raise KeyError(key)
        self._value_index[value] = si
        

    def find_key_by_value(self, value):
        """Returns a key iterator cross-indexed from the passed unique value
           or end() if no value found.

           >>> from Map import *
           >>> m = CIndexedMap()
           >>> m[6] = 'abc'
           >>> i = m.find_key_by_value('abc')
           >>> int(i.key())
           6
           >>> i = m.find_key_by_value('xyz')
           >>> if i == m.end(): print 'i points at end()'
           i points at end()

        """
        si = self._value_index.get(value)  # si == "swig iterator."
        if si != None:
            si = iter_copy(si); # copy else operations like increment on the
                                # KeyIterator would modify the value index.
        return CMap.KeyIterator(self,si)

    def copy(self):
        """return shallow copy"""
        return CIndexedMap(self)

    def update_key( self, iter, key ):
        """
          see CMap.update_key.
          
          WARNING!! You MUST assume that the passed iterator is invalidated
          upon return.

          Typical use:
            >>> from CMap import CIndexedMap
            >>> m = CIndexedMap()
            >>> m[10] = 'foo'
            >>> m[8] = 'bar'
            >>> i = m.find(10)
            >>> m.update_key(i,7)   # i is assumed to be invalid upon return.
            >>> del i
            >>> int(m.get_key_by_value('foo'))
            7
            >>> [(int(x[0]),x[1]) for x in m.items()]    # reordering occurred.
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
            >>> try:
            ...     m.update_key(i,9)
            ... except KeyError:
            ...     print 'ok'
            ...
            ok
            >>> m[7]
            'foo'
            >>> int(m.get_key_by_value('foo'))
            7
            >>> i = m.iterkeys()
            >>> try:                 # updating an iter pointing at BEGIN.
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
        if not iter._si:
            raise RuntimeError( _("invalid iterator") )
        if iter._si == BEGIN:
            raise IndexError(_("Iterator does not point at key-value pair" ))
        if self is not iter._map:
            raise IndexError(_("Iterator points into a different "
                               "CIndexedMap."))
        if map_iter_at_end(self._smap, iter._si):
            raise IndexError( _("Cannot update end() iterator.") )
        si = map_iter_update_key_iter(self._smap, iter._si, key)
                                   # raises KeyError if key already in map.

        if si != iter._si:         # if map is reordered...
            value = iter.value();
            val_si = self._value_index[value]
            iter_delete(val_si)
            self._value_index[value] = si

    def erase(self, iter):
        """Remove item pointed to by the iterator.  Iterator is immediately
           invalidated after the deletion completes."""
        if not iter._si:
            raise RuntimeError( _("invalid iterator") )
        if iter._si == BEGIN:
            raise IndexError(_("Iterator does not point at key-value pair." ))
        if self is not iter._map:
            raise IndexError(_("Iterator points into a different "
                               "CIndexedMap."))
        if map_iter_at_end(self._smap, iter._si):
            raise IndexError( _("Cannot update end() iterator.") )
        value = iter.value()
        CMap.erase(self,iter)
        del self._value_index[value]

if __name__ == "__main__":
    import doctest
    import random


    ##############################################
    # UNIT TESTS
    print "Testing module"
    doctest.testmod(sys.modules[__name__])
    print "doctest complete."
    
    
    ##############################################
    # MEMORY LEAK TESTS

    if LEAK_TEST:
        i = 0
        import gc
        class X:
            x = range(1000)  # something moderately big.
    
        # TEST 1. This does not cause memory to grow.
        #m = CMap()
        #map_insert(m._smap,10,X())
        #while True:
        #    i += 1
        #    it = map_find_iter( m._smap, 10 )
        #    iter_delete(it)
        #    del it
        #    if i % 100 == 0:
        #      gc.collect()
    
        # TEST 2: This does not caus a memory leak.
        #m = map_constructor_double()
        #while True:
        #    i += 1
        #    map_insert_double(m,10,5)        # here
        #    it = map_find_iter_double( m, 10 )
        #    map_iter_erase_double( m, it )     # or here is the problem.
        #    iter_delete_double(it)
        #    del it
        #    #assert len(m) == 0
        #    assert map_size_double(m) == 0
        #    if i % 100 == 0:
        #      gc.collect()
    
        # TEST 3. No memory leak
        #m = CMap()
        #while True:
        #    i += 1
        #    map_insert(m._smap,10,X())        # here
        #    it = map_find_iter( m._smap, 10 )
        #    map_iter_erase( m._smap, it )     # or here is the problem.
        #    iter_delete(it)
        #    del it
        #    assert len(m) == 0
        #    assert map_size(m._smap) == 0
        #    if i % 100 == 0:
        #      gc.collect()
    
    
        # TEST 4: map creation and deletion.
        #while True:
        #  m = map_constructor()
        #  map_delete(m);
    
        # TEST 5: test iteration.
        #m = map_constructor()
        #for i in xrange(10):
        #    map_insert(m,i,X())
        #while True:
        #    i = map_begin(m)
        #    while not map_iter_at_begin(m,i):
        #      iter_incr(i)
        #    iter_delete(i)
    
        # TEST 6:
        #m = map_constructor()
        #for i in xrange(10):
        #    map_insert(m,i,X())
        #while True:
        #    map_find( m, random.randint(0,9) )
    
        # TEST 7:
        #m = map_constructor()
        #for i in xrange(50):  
        #  map_insert( m, i, X() )
        #while True:
        #  for i in xrange(50):
        #    map_set( m, i, X() )
    
        # TEST 8
        # aha!  Another leak! Fixed.
        #m = map_constructor()
        #while True:
        #    i += 1
        #    map_insert(m,10,X()) 
        #    map_erase(m,10)
        #    assert map_size(m) == 0
    
        # TEST 9
        m = map_constructor()
        for i in xrange(50):  
            map_insert( m, i, X() )
        while True:
            it = map_find_iter( m, 5 )
            map_iter_update_key( m, it, 1000 )  
            iter_delete(it)
            it = map_find_iter( m, 1000 )
            map_iter_update_key( m, it, 5)  
            iter_delete(it)
      

