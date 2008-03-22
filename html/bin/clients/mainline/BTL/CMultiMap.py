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
from cmultimap_swig import *
import sys
from weakref import WeakKeyDictionary
LEAK_TEST = False

class CMultiMap(object, DictMixin):  
    """In-order mapping.  Similar to a dict, except that it provides in-order
       iteration and searches for the nearest key <= or >= a given key.  
       Distinguishes itself from CMap in that CMultiMap instances allows 
       multiple entries with the same key, thus __getitem__ and get always
       return a list.  If there are no matching keys then __getitem__
       or get returns an empty list, one match a single-element list, etc.
       Values with the same key have arbitrary order.

       LIMITATION: The key must be a double.  The value can be anything.

         Item insertion:  O(log n)          append, __setitem__
         Item deletion:   O(log n + k)      erase    
                          O(log n + k + m)  __delitem__
         Key search:      O(log n)          find, __contains__
                          O(log n + m)      __getitem__, get
         Value search:    n/a
         Iteration step:  amortized O(1), worst-case O(log n)
         Memory:          O(n)

       n = number of elements in map.  k = number of iterators pointing
       into map.  The assumption here is that there are few iterators
       in existence at any given time.  m = number of elements matching
       the key.
       
       Iterators are not invalidated by insertions.  Iterators are invalidated
       by deletions only when the key-value pair referenced is deleted.
       Deletion has a '+k' because __delitem__ searches linearly
       through the set of iterators to find any iterator pointing at the
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

               >>> from CMultiMap import CMultiMap
               >>> m = CMultiMap()
               >>> m[12] = 6
               >>> m[9] = 4
               >>> for k in m:
               ...     print int(k)
               ...
               9
               12
               >>>

               Example edge cases (empty map):

               >>> from CMultiMap import CMultiMap
               >>> m = CMultiMap()
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

               @param mmap: CMultiMap.
               @param node: Node that this iterator will point at.  If None
                 then the iterator points to end().  If BEGIN
                 then the iterator points to one before the beginning.
             """
            assert isinstance(m, CMultiMap)
            assert not isinstance(si, CMultiMap._AbstractIterator)
            if si == None:
                self._si = mmap_end(m._smmap)
            else:
                self._si = si           # C++ iterator wrapped by swig.
            self._mmap = m
            m._iterators[self] = 1      # using map as set of weak references.

        def __hash__(self):
            return id(self)
        
        def __cmp__(self, other):
            if not self._si or not other._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN and other._si == BEGIN: return 0
            if self._si == BEGIN and other._si != BEGIN: return -1
            elif self._si != BEGIN and other._si == BEGIN: return 1
            return iiter_cmp(self._mmap._smmap, self._si, other._si )

        def at_begin(self):
            """equivalent to self == m.begin() where m is a CMultiMap.
            
                 >>> from CMultiMap import CMultiMap
                 >>> m = CMultiMap()
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
            return mmap_iiter_at_begin(self._mmap._smmap, self._si)
        
        def at_end(self):
            """equivalent to self == m.end() where m is a CMap, but
               at_end is faster because it avoids the dynamic memory
               alloation in m.end().

                 >>> from CMultiMap import CMultiMap
                 >>> m = CMultiMap()
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
            return mmap_iiter_at_end(self._mmap._smmap, self._si)
        
        def key(self):
            """@return: the key of the key-value pair referenced by this
                   iterator.
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to .next."))
            elif mmap_iiter_at_end(self._mmap._smmap, self._si):
                raise IndexError()
            
            return iiter_key(self._si)

        def value(self):
            """@return: the value of the key-value pair currently referenced
                   by this iterator.
            """
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise IndexError(_("Cannot dereference iterator until after "
                                 "first call to next."))
            elif mmap_iiter_at_end(self._mmap._smmap, self._si):
                raise IndexError()

            return iiter_value(self._si)
        
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
                self._si = mmap_begin(self._mmap._smmap)

                if mmap_iiter_at_end(self._mmap._smmap,self._si):
                    raise StopIteration
                return

            if mmap_iiter_at_end(self._mmap._smmap,self._si):
                raise StopIteration

            iiter_incr(self._si)

            if mmap_iiter_at_end(self._mmap._smmap,self._si):
                raise StopIteration
            
        def _prev(self):
            if not self._si:
                raise RuntimeError( _("invalid iterator") )
            if self._si == BEGIN:
                raise StopIteration()
            
            elif mmap_iiter_at_begin(self._mmap._smmap, self._si):
                self._si = BEGIN
                raise StopIteration

            iiter_decr(self._si)

        def __del__(self):
            # Python note: "del x" merely eliminates one reference to an
            # object. __del__ isn't called until the ref count goes to 0.
            # Only when the last reference is gone is __del__ called.
            self._invalidate()
            
        def _invalidate(self):
            if self._si == None:  # if already invalidated...
                return
            try:
                del self._mmap._iterators[self]
            except KeyError:
                pass  # could've been removed because weak reference,
                      # and because _invalidate is called from __del__.
            if self._si != BEGIN:
                iiter_delete(self._si)
            self._si = None
                
        def __iter__(self):
            """If the iterator is itself iteratable then we do things like:
                >>> from CMultiMap import CMultiMap
                >>> m = CMultiMap()
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
            return len(self._mmap)
        
    class KeyIterator(_AbstractIterator):
        def next(self):
            """Returns the next key in the mmap.
            
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
                   >>> from CMultiMap import *
                   >>> m = CMultiMap()
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
                   >>> from CMultiMap import CMultiMap
                   >>> m = CMultiMap()
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
            """Returns the previous key in the mmap.

               See next() for more detail and examples.
               """
            self._prev()
            return self.key()

    class ValueIterator(_AbstractIterator):
        def next(self):
            """@return: next value in the mmap.

                >>> from CMultiMap import *
                >>> m = CMultiMap()
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
            """@return: next item in the mmap's key ordering.

                >>> from CMultiMap import CMultiMap
                >>> m = CMultiMap()
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

            >>> m = CMultiMap()
            >>> len(m)
            0
            >>> m[5]=2
            >>> len(m)
            1
            >>> print m[5]
            [2]

        """
        self._smmap = mmap_constructor()  # C++ mmap wrapped by swig.
        for key, value in d.items():
            self[key]=value
        self._iterators = WeakKeyDictionary()
                                   # whenever node is deleted. search iterators
                                   # for any iterator that becomes invalid.

    def __contains__(self,x):
        return self.has_key(x)

    def __iter__(self):
        """@return: KeyIterator positioned one before the beginning of the
            key ordering so that the first next() returns the first key."""
        return CMultiMap.KeyIterator(self)

    def begin(self):
        """Returns an iterator pointing at first key-value pair.  This
           differs from iterkeys, itervalues, and iteritems which return an
           iterator pointing one before the first key-value pair.

           @return: key iterator to first key-value.

              >>> from CMultiMap import *
              >>> m = CMultiMap()
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
        i = CMultiMap.KeyIterator(self, mmap_begin(self._smmap) )
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
        i = CMultiMap.KeyIterator(self,None) # None means one after last node.
        return i

    def iterkeys(self):
        return CMultiMap.KeyIterator(self)

    def itervalues(self):
        return CMultiMap.ValueIterator(self)

    def iteritems(self):
        return CMultiMap.ItemIterator(self)

    def __len__(self):
        return mmap_size(self._smmap)

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
        """Returns a list containing all matching values or the empty list
           if the key is not found.

           This differs in behavior from CMap which simply returns the value or
           throws a KeyError if it is not present.
           """
        si = mmap_find_iiter(self._smmap,key) # raises KeyError if key not found
        result = []
        while not mmap_iiter_at_end(self._smmap, si) and iiter_key(si) == key:
          result.append( iiter_value(si) )
          iiter_incr(si)
        iiter_delete(si)
        return  result

    def __setitem__(self, key, value):
        """
            >>> from CMultiMap import CMultiMap
            >>> m = CMultiMap()
            >>> m[6] = 'bar'
            >>> m[6]
            ['bar']
            >>>
            """
        assert type(key) == int or type(key) == float
        mmap_insert(self._smmap,key,value)

    def __delitem__(self, key):
        """Deletes all items with matching key from the mmap.

           This takes O(log n + km) where n is the number of elements
           in the mmap and k is the number of iterators pointing into the mmap,
           and m is the number of items matching the key.
           
           Before deleting ab item it linearly searches through
           all iterators pointing into the mmap and invalidates any that
           are pointing at the item about to be deleted.

           Raises a KeyError if the key is not found.

           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
           >>> m[12] = 'foo'
           >>> m[13] = 'bar'
           >>> m[14] = 'boo'
           >>> del m[12]
           >>> m[12]
           []
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

        si = sprev = None

        try:
          #mmap_erase( self._smmap, key )  # mmap_erase is dangerous.  It could
                                           # delete the node causing an
                                           # iterator to become invalid. --Dave
                                         
          si = mmap_find_iiter( self._smmap, key )  # si = swig'd iterator.
          if mmap_iiter_at_end(self._smmap, si):
              raise KeyError(key)
          sprev = iiter_copy(si)
          
          # HERE this could be written more efficiently. --Dave.
          while not mmap_iiter_at_end(self._smmap, si) and \
                iiter_key(si) == key:
              for i in list(self._iterators):
                  if iiter_cmp( self._smmap, i._si, si ) == 0:
                      i._invalidate()
              iiter_incr(si)
              mmap_iiter_erase( self._smmap, sprev )
              iiter_assign(sprev, si)
              
        finally:      
          if si:
              iiter_delete(si)
          if sprev:
              iiter_delete(sprev)

    def erase(self, iter):
        """Remove item pointed to by the iterator.  All iterators that
           point at the erased item including the passed iterator
           are immediately invalidated after the deletion completes.

           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
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
        if self is not iter._mmap:
            raise IndexError(_("Iterator points into a different CMultiMap."))
        if mmap_iiter_at_end(self._smmap, iter._si):
            raise IndexError( _("Cannot erase end() iterator.") )

        # invalidate iterators.
        for i in list(self._iterators):
            if iter._si is not i._si and iiter_cmp( self._smmap, iter._si, i._si ) == 0:
                i._invalidate()

        # remove item from the map.
        mmap_iiter_erase( self._smmap, iter._si )

        # invalidate last iterator pointing to the deleted location in the map.
        iter._invalidate() 


    def __del__(self):

        # invalidate all iterators.
        for i in list(self._iterators):
            i._invalidate()
        mmap_delete(self._smmap)

    def get(self, key, default=None):
        """
           @return list containing values corresponding to specified key or
               return a single-element list containing 'default'
               if the key is not found.  If 'default' is None then the
               empty list is returned when the key is not found.

           >>> from CMultiMap import *
           >>> m = CMultiMap()
           >>> m[5] = 'a'
           >>> m.get(5)
           ['a']
           >>> m[5] = 'b'
           >>> m.get(5)
           ['a', 'b']
           >>> m.get(6)
           []
           >>> m.get(6,'c')
           ['c']
 
           """
        if self.has_key(key):
            return self[key]
        if default is None:
            return []
        return [default]

    def keys(self):
        """
           >>> from CMultiMap import *
           >>> m = CMultiMap()
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
           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
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
           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
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
           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
           >>> m[4.0] = 7
           >>> if m.has_key(4): print 'ok'
           ...
           ok
           >>> if not m.has_key(7): print 'ok'
           ...
           ok
           
        """
        try:
            mmap_find(self._smmap, key)
        except KeyError:
            return False
        return True

    def clear(self):
        """delete all entries

           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
           >>> m[4] = 7
           >>> m.clear()
           >>> print len(m)
           0
           
        """

        self.__del__()
        self._smmap = mmap_constructor()

    def copy(self):
        """return shallow copy"""
        return CMultiMap(self)

    def lower_bound(self,key):
        """
         Finds smallest key equal to or above the lower bound.

         Takes O(log n) time.

         @param x: Key of (key, value) pair to be located.
         @return: Key Iterator pointing to first item equal to or greater
                  than key, or end() if no such item exists.

           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> i = m.lower_bound(11)   # iterator.
           >>> int(i.key())
           15
           >>> i.value()
           'bar'
           
        Edge cases:
           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
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
        return CMultiMap.KeyIterator(self, mmap_lower_bound( self._smmap, key ))


    def upper_bound(self, key):
        """
         Finds largest key equal to or below the upper bound.  In keeping
         with the [begin,end) convention, the returned iterator
         actually points to the key one above the upper bound. 

         Takes O(log n) time.

         @param  x:  Key of (key, value) pair to be located.
         @return:  Iterator pointing to first element equal to or greater than
                  key, or end() if no such item exists.

           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
           >>> m[10] = 'foo'
           >>> m[15] = 'bar'
           >>> m[17] = 'choo'
           >>> i = m.upper_bound(11)   # iterator.
           >>> i.value()
           'bar'

         Edge cases:
           >>> from CMultiMap import CMultiMap
           >>> m = CMultiMap()
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
        return CMultiMap.KeyIterator(self, mmap_upper_bound( self._smmap, key ))

    def find(self,key):
        """
          Finds the first item with matching key and returns a KeyIterator
          pointing at the item.  If no match is found then returns end().
     
          Takes O(log n) time.
     
            >>> from CMultiMap import CMultiMap
            >>> m = CMultiMap()
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
        return CMultiMap.KeyIterator(self, mmap_find_iiter( self._smmap, key ))

    def update_key( self, iter, key ):
        """
          Modifies the key of the item referenced by iter.  If the
          key change is small enough that no reordering occurs then
          this takes amortized O(1) time.  If a reordering occurs then
          this takes O(log n).

          WARNING!!! The passed iterator MUST be assumed to be invalid
          upon return.  Any further operation on the passed iterator other than 
          deallocation results in a RuntimeError exception.

          Typical use:
            >>> from CMultiMap import CMultiMap
            >>> m = CMultiMap()
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
            >>> m.update_key(i,9)  # update to key already in the mmap.
            >>> m[7]
            []
            >>> m[9]
            ['foo', 'bar']
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
        assert isinstance(iter,CMultiMap._AbstractIterator)
        if not iter._si:
            raise RuntimeError( _("invalid iterator") )
        if iter._si == BEGIN:
            raise IndexError(_("Iterator does not point at key-value pair" ))
        if self is not iter._mmap:
            raise IndexError(_("Iterator points into a different CMultiMap."))
        if mmap_iiter_at_end(self._smmap, iter._si):
            raise IndexError( _("Cannot erase end() iterator.") )

        mmap_iiter_update_key(self._smmap, iter._si, key)

    def append(self, key, value):
        """Performs an insertion with the hint that it probably should
           go at the end.

           Raises KeyError if the key is already in the mmap.

             >>> from CMultiMap import CMultiMap
             >>> m = CMultiMap()
             >>> m.append(5.0,'foo')    # append to empty mmap.
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
             >>> m.append(10.0, 'blah') # append key already in mmap.
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar'), (10, 'blah')]
             >>>

             note (1): int(x[0]) is used because 5.0 can appear as either 5
             or 5.0 depending on the version of python.
           """
        mmap_append(self._smmap,key,value)
    

class CIndexedMultiMap(CMultiMap):
    """This is an ordered mmapping, exactly like CMultiMap except that it
       provides a cross-index allowing average O(1) searches based on value.
       This adds the constraint that values must be unique (multiple equal
       keys can still be exist in the map).

         Item insertion:  O(log n)       append, __setitem__
         Item deletion:   O(log n)    
         Key search:      O(log n)       __getitem__, get, find, __contains__
         Value search:    average O(1)  as per dict
         Iteration step:  amortized O(1), worst-case O(log n)
         Memory:          O(n)


       The hash table increases the factor in the
       O(n) memory cost of the Map by a constant
    """
    def __init__(self, dict={} ):
        CMultiMap.__init__(self,dict)
        self._value_index = {}   # cross-index. mmaps value->iterator.

    def __setitem__(self, key, value):
        """
            >>> from CMultiMap import *
            >>> m = CIndexedMultiMap()
            >>> m[6] = 'bar'
            >>> m[6]
            ['bar']
            >>> int(m.get_key_by_value('bar'))
            6
            >>> try:
            ...    m[7] = 'bar'      # values must be unique!
            ... except ValueError:
            ...    print 'value error'
            value error
            >>> m[6] = 'foo'
            >>> m[6]
            ['bar', 'foo']
            >>> try:
            ...     m[7] = 'bar'     # 2 values to 1 key. Values still unique!
            ... except ValueError:
            ...     print 'value error'
            value error
            >>> m[7]
            []
            >>> int(m.get_key_by_value('bar'))
            6
    
        """
        assert type(key) == int or type(key) == float
        if self._value_index.has_key(value) and \
           iiter_key(self._value_index[value]) != key:
            raise ValueError( _("Value %s already exists.  Values must be "
                "unique.") % str(value) )
        
        si = mmap_insert_iiter(self._smmap,key,value) # si points where insert
                                                   # should occur whether 
                                                   # insert succeeded or not.
                                                   # si == "swig iterator"
        sival = iiter_value(si)
        if sival != value:          # if insert failed because k already exists
            iiter_set(si,value)      # then force set.
            self._value_index[value] = si
            viter = self._value_index[sival]
            iiter_delete(viter)     # remove old value from index
            del self._value_index[sival]  
        else:                      # else insert succeeded so update index.
            self._value_index[value] = si

    def __delitem__(self, key):
        """
            >>> from CMultiMap import CIndexedMultiMap
            >>> m = CIndexedMultiMap()
            >>> m[6] = 'bar'
            >>> m[6]
            ['bar']
            >>> int(m.get_key_by_value('bar'))
            6
            >>> del m[6]
            >>> if m.get_key_by_value('bar'):
            ...     print 'found'
            ... else:
            ...     print 'not found.'
            not found.

        """
        i = mmap_find_iiter( self._smmap, key )
        if mmap_iiter_at_end( self._smmap, i ):
            iiter_delete(i)
            raise KeyError(key)
        else:
            value = iiter_value(i)
            for i in list(self._iterators):
                if iiter_cmp( self._smmap, i._si, iter._si ) == 0:
                    i._invalidate()
            mmap_iiter_erase( self._smmap, i )
            viter = self._value_index[value]
            iiter_delete(i)
            iiter_delete( viter )
            del self._value_index[value]
        assert mmap_size(self._smmap) == len(self._value_index)

    def has_value(self, value):
       return self._value_index.has_key(value)

    def get_key_by_value(self, value):
        """Returns the key cross-indexed from the passed unique value, or
           returns None if the value is not in the mmap."""
        si = self._value_index.get(value)  # si == "swig iterator"
        if si == None: return None
        return iiter_key(si)

    def append( self, key, value ):
        """See CMultiMap.append

             >>> from CMultiMap import CIndexedMultiMap
             >>> m = CIndexedMultiMap()
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
             >>> m.append(10, 'blah') # append key already in mmap.
             >>> [(int(x[0]),x[1]) for x in m.items()]
             [(3, 'coo'), (5, 'foo'), (10, 'bar'), (10, 'blah')]
             >>> try:
             ...     m.append(10, 'coo') # append value already in mmap.
             ... except ValueError:
             ...     print 'ok'
             ...
             ok

        """
        if self._value_index.has_key(value) and \
           iiter_key(self._value_index[value]) != key:
            raise ValueError(_("Value %s already exists and value must be "
                "unique.") % str(value) )
        
        si = mmap_append_iiter(self._smmap,key,value)
        if iiter_value(si) != value:
            iiter_delete(si)
            raise KeyError(key)
        self._value_index[value] = si
        

    def find_key_by_value(self, value):
        """Returns a key iterator cross-indexed from the passed unique value
           or end() if no value found.

           >>> from CMultiMap import CIndexedMultiMap
           >>> m = CIndexedMultiMap()
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
            si = iiter_copy(si); # copy else operations like increment on the
                                # KeyIterator would modify the value index.
        return CMultiMap.KeyIterator(self,si)

    def copy(self):
        """return shallow copy"""
        return CIndexedMultiMap(self)

    def update_key( self, iter, key ):
        """
          see CMultiMap.update_key.
          
          WARNING!! You MUST assume that the passed iterator is invalidated
          upon return.

          Typical use:
            >>> from CMultiMap import CIndexedMultiMap
            >>> m = CIndexedMultiMap()
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
            >>> m.update_key(i,9)
            >>> m[7]
            []
            >>> m[9]
            ['foo', 'bar']
            >>> int(m.get_key_by_value('foo'))
            9
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
        if self is not iter._mmap:
            raise IndexError(_("Iterator points into a different "
                               "CIndexedMultiMap."))
        if mmap_iiter_at_end(self._smmap, iter._si):
            raise IndexError( _("Cannot update end() iterator.") )

        si = mmap_iiter_update_key_iiter(self._smmap, iter._si, key)
                                   # raises KeyError if key already in mmap.

        if si != iter._si:         # if mmap is reordered...
            value = iter.value();
            val_si = self._value_index[value]
            iiter_delete(val_si)
            self._value_index[value] = si
            
    def erase(self, iter):
        """Remove item pointed to by the iterator.  Iterator is immediately
           invalidated after the deletion completes."""
        value = iter.value()
        CMultiMap.erase(self,iter)
        del self._value_index[value]

if __name__ == "__main__":

    import sys, doctest

    ############
    # UNIT TESTS
    print "Testing module"
    doctest.testmod(sys.modules[__name__])

    if LEAK_TEST:
        # Now test for memory leaks.
        print "testing for memory leaks.  Loop at top to see if process memory allocation grows."
        print "CTRL-C to stop test."
        # Run > top
        # Does memory consumption for the process continuously increase? Yes == leak.
        m = CMultiMap()
        
        # insert and delete repeatedly.
        i = 0
        import gc
        class X:
            x = range(1000)  # something moderately big.
        
        #while True:
        #    i += 1
        #    mmap_insert(m._smmap,10,X())
        #    it = mmap_find_iiter( m._smmap, 10 )
        #    mmap_iiter_erase( m._smmap, it )
        #    iiter_delete(it)
        #    assert len(m) == 0
        #    assert mmap_size(m._smmap) == 0
        #    if i % 100 == 0:
        #      gc.collect()
            
        while True:
            i += 1
            m[10] = X()
            del m[10]
            assert len(m) == 0
            if i % 100 == 0:
              gc.collect()
       
