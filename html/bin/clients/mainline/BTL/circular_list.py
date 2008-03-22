# circular doubly linked list
#
# by Greg Hazel

import random


class Link(object):

    __slots__ = ['prev', 'data', 'next']    

    def __init__(self, data):
        self.prev = self
        self.data = data
        self.next = self

    def __str__(self):
        p = id(self.prev)
        n = id(self.next)
        return 'link:(%s, (%s, %s), %s)' % (p, id(self), self.data, n)


class CircularList(object):

    def __init__(self):
        self.iter = None
        self.link_refs = {} # data: link

    def prepend(self, data):
        link = Link(data)
        assert data not in self.link_refs
        self.link_refs[data] = link
        if not self.iter:
            self.iter = link
        else:
            self._insert_before(self.iter, link)
     
    def append(self, data):
        link = Link(data)
        assert data not in self.link_refs
        self.link_refs[data] = link
        if not self.iter:
            self.iter = link
        else:
            self._insert_after(self.iter, link)

    def remove(self, data):
        link = self.link_refs.pop(data)
        if len(self.link_refs) == 0:
            self.iter = None
            return
        prev = link.prev
        next = link.next
        assert next is not None and prev is not None
        prev.next = next
        next.prev = prev
        if link == self.iter:
            self.iter = next

    ## stuff I consider to be link-related
    ########
    def _double_link(self, link1, link2):
        # was a single item loop, move to a double
        assert link1.prev == link1 and link1.next == link1
        link1.prev = link2
        link1.next = link2
        link2.next = link1
        link2.prev = link1        

    def _insert_after(self, link1, link2):
        assert link1 != link2
        if link1.next == link1:
            self._double_link(link1, link2)
        else:
            link2.next = link1.next
            link2.prev = link1
            link1.next.prev = link2
            link1.next = link2

    def _insert_before(self, link1, link2):
        assert link1 != link2
        if link1.prev == link1:
            self._double_link(link1, link2)
        else:
            link2.prev = link1.prev
            link2.next = link1
            link1.prev.next = link2
            link1.prev = link2
    ########

    def iterator(self):
        for i in iter(self):
            yield i

    def __iter__(self):
        if not self.iter:
            return
        while True:
            yield self.iter.data
            # someone could remove an item during iteration
            if not self.iter:
                return
            self.iter = self.iter.next

    def __len__(self):
        return len(self.link_refs)

    def __str__(self):
        n = len(self.link_refs)
        a = []
        # don't interrupt iteration for a print
        first = self.iter
        next = first
        while next:
            a.append(str(next))
            next = next.next
            if next.data == first.data:
                break
        items = '\n'.join(a)
        return "iter: %s \n[\n%s\n]" % (self.iter, items)
    

if __name__ == '__main__':
    import time

    length = 80000
    class ltype(list):
        def prepend(self, i):
            self.insert(0, i)
    from BTL.Lists import QList
    class qtype(QList):
        def prepend(self, i):
            self.append(i)
        def iterator(self):
            if len(self) == 0:
                return
            while True:
                yield self[0]
                if len(self) == 0:
                    return
                self.append(self.popleft())

    #CircularList = ltype
    #CircularList = qtype
    print CircularList

    s = time.clock()    
    l = CircularList()
    for i in xrange(length):
        l.append(i)
    #print l
    print 'append ', time.clock() - s

    s = time.clock()    
    l = CircularList()
    for i in xrange(length):
        l.prepend(i)
    #print l
    print 'prepend', time.clock() - s

    s = time.clock()    
    l = CircularList()
    for i in xrange(length):
        if i % 2 == 0:
            l.prepend(i)
        else:
            l.append(i)
    #print l
    print 'sort   ', time.clock() - s

    #fair = {}
    s = time.clock()    
    l = CircularList()
    it = l.iterator()
    for i in xrange(length):
        l.prepend(i)
        #fair[i] = 0
        x = it.next()
        #print x, i
        #fair[x] += 1
        #assert x == i, '%s %s' % (x, i)
    #print l
    print 'iter   ', time.clock() - s
    #for k in fair:
    #    print k, fair[k]

    l = CircularList()
    print l
    l.prepend(0)
    print l
    l.prepend(1)
    print l
    l.remove(1)
    print l
    l.remove(0)
    print l
