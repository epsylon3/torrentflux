# SparseSet is meant to act just like a set object, but without actually
# storing discrete values for every item in the set
#
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
# by Greg Hazel

from __future__ import generators

from bisect import bisect_left
from itertools import izip

try:
    from blist import blist
except ImportError:
    list_base = list
else:
    list_base = blist

class SparseSet(object):

    def __init__(self, s = None):
        self._begins = list_base()
        # ends are non-inclusive
        self._ends = list_base()
        if s is not None:
            if isinstance(s, SparseSet):
                self._begins = list_base(s._begins)
                self._ends = list_base(s._ends)
            else:                
                self.add_range(s)

    def _collapse_range(self, l):
        last = None
        begins = list_base()
        ends = list_base()
        if len(l) == 0:
            return begins, ends
        
        begins.append(l[0])
        for i in l:
            if last and i > (last + 1):
                ends.append(last + 1)
                begins.append(i)
            last = i

        if last is not None:
            ends.append(last + 1)

        return begins, ends        

    def subtract_range(self, l):
        begins, ends = self._collapse_range(l)
        for b, e in izip(begins, ends):
            self.subtract(b, e)
        
    def add_range(self, l):
        begins, ends = self._collapse_range(l)
        for b, e in izip(begins, ends):
            self.add(b, e)

    def add(self, begin, end=None):
        if end is None:
            end = begin + 1
        elif begin >= end:
            raise ValueError("begin(%d) >= end(%d)" % (begin, end))

        if len(self._begins) == 0:
            self._begins.append(begin)
            self._ends.append(end)
            return

        b_i = bisect_left(self._begins, begin)

        if b_i == 0:
            if begin >= self._begins[b_i]:
                begin = self._begins[b_i]
        elif begin <= self._ends[b_i - 1]:
            b_i -= 1
            begin = self._begins[b_i]

        e_i = bisect_left(self._ends, end, b_i)

        if e_i < len(self._ends):
            if end >= self._begins[e_i]:
                end = self._ends[e_i]
            else:
                e_i -= 1

        # small optimization
        if b_i == e_i:
            if b_i == len(self._begins):
                self._begins.append(begin)
                self._ends.append(end)
            else:
                self._begins[b_i] = begin
                self._ends[b_i] = end
            return

        # small optimization
        if b_i == e_i + 1:
            self._begins.insert(b_i, begin)
            self._ends.insert(b_i, end)
            return
        
        self._begins[b_i:e_i + 1] = (begin,)
        self._ends[b_i:e_i + 1] = (end,)

    def discard(self, begin, end=None):
        if end is None:
            end = begin + 1
        elif begin >= end:
            raise ValueError("begin(%d) >= end(%d)" % (begin, end))

        b_i = bisect_left(self._begins, begin)
        s_b_i = max(b_i - 1, 0)
        e_i = bisect_left(self._ends, end, s_b_i)

        beginning_is_an_end = False
        end_is_an_end = False

        if b_i > 0 and begin < self._ends[b_i - 1]:
            beginning_is_an_end = True

        if e_i < len(self._ends):
            if end == self._ends[e_i]:
                e_i += 1
            elif end > self._begins[e_i]:
                end_is_an_end = True

        del self._begins[b_i:e_i]
        del self._ends[b_i:e_i]

        if beginning_is_an_end:
            old_end = self._ends[b_i - 1]
            self._ends[b_i - 1] = begin
    
        if end_is_an_end:
            if beginning_is_an_end and b_i > e_i:
                self._begins.insert(b_i, end)
                self._ends.insert(b_i, old_end)
            self._begins[b_i] = end
    remove = discard
    subtract = discard

    def is_range_in(self, x, y):
        assert y > x
        i = bisect_left(self._begins, x)
        if i > 0 and x < self._ends[i - 1]:
            if y <= self._ends[i - 1]:
                return True
        if i < len(self._begins) and x >= self._begins[i] and x < self._ends[i]:
            if y <= self._ends[i]:
                return True
        return False

    def offset(self, x):
        for i in xrange(len(self._begins)):
            self._begins[i] += x
            self._ends[i] += x

    def __getitem__(self, i):
        r = i
        if r < 0:
            r = len(self) + i
        for b, e in izip(self._begins, self._ends):
            l = e - b
            if r < 0:
                break
            if l > r:
                return b + r
            r -= l
        raise IndexError("SparseSet index '%s' out of range" % i)

    def __iter__(self):
        for b, e in izip(self._begins, self._ends):
            for i in xrange(b, e):
                yield i

    def iterneg(self, begin, end):
        ranges = list_base()
        b_i = bisect_left(self._begins, begin)
        for b, e in izip(self._begins[b_i:], self._ends[b_i:]):
            for i in xrange(begin, b):
                yield i
            begin = e
        if begin < end:
            for i in xrange(begin, end):
                yield i        

    def iterrange(self):
        for b, e in izip(self._begins, self._ends):
            yield (b, e)

    def largest_range(self):
        m = None
        r = None
        for b, e in izip(self._begins, self._ends):
            if b - e > m:
                m = b - e
                r = (b, e)
        return r

    def __eq__(self, s):
        if not isinstance(s, SparseSet):
            return False
        return (self._begins == s._begins) and (self._ends == s._ends)

    def __ne__(self, s):
        if not isinstance(s, SparseSet):
            return True
        return (self._begins != s._begins) or (self._ends != s._ends)

    def __contains__(self, x):
        i = bisect_left(self._begins, x)
        if i > 0 and x < self._ends[i - 1]:
            return True
        if i < len(self._begins) and x == self._begins[i]:
            return True
        return False

    def __len__(self):
        l = 0
        for b, e in izip(self._begins, self._ends):
            l += e - b
        return l

    def __sub__(self, s):
        n = SparseSet(self)
        if isinstance(s, SparseSet):
            for b, e in izip(s._begins, s._ends):
                n.subtract(b, e)
        else:
            n.subtract_range(list_base(s))
        return n

    def __add__(self, s):
        n = SparseSet(self)
        if isinstance(s, SparseSet):
            for b, e in izip(s._begins, s._ends):
                n.add(b, e)
        else:
            n.add_range(list_base(s))
        return n

    def __repr__(self):
        return 'SparseSet(%s)' % str(zip(self._begins, self._ends))

    def __str__(self):
        return str(zip(self._begins, self._ends))

