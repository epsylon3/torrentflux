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
from bisect import *
from types import *

import khash as hash
import const
from const import K, HASH_LENGTH, NULL_ID, MAX_FAILURES, MIN_PING_INTERVAL
from node import Node


def ls(a, b):
    return cmp(a.lastSeen, b.lastSeen)

class KTable(object):
    __slots__ = ('node', 'buckets')
    """local routing table for a kademlia like distributed hash table"""
    def __init__(self, node):
        # this is the root node, a.k.a. US!
        self.node = node
        self.buckets = [KBucket([], 0L, 2L**HASH_LENGTH)]
        self.insertNode(node)
        
    def _bucketIndexForInt(self, num):
        """the index of the bucket that should hold int"""
        return bisect_left(self.buckets, num)

    def bucketForInt(self, num):
        return self.buckets[self._bucketIndexForInt(num)]
    
    def findNodes(self, id, invalid=True):
        """
            return K nodes in our own local table closest to the ID.
        """
        
        if isinstance(id, str):
            num = hash.intify(id)
        elif isinstance(id, Node):
            num = id.num
        elif isinstance(id, int) or isinstance(id, long):
            num = id
        else:
            raise TypeError, "findNodes requires an int, string, or Node"
            
        nodes = []
        i = self._bucketIndexForInt(num)
        
        # if this node is already in our table then return it
        try:
            node = self.buckets[i].getNodeWithInt(num)
        except ValueError:
            pass
        else:
            return [node]
            
        # don't have the node, get the K closest nodes
        nodes = nodes + self.buckets[i].l
        if not invalid:
            nodes = [a for a in nodes if not a.invalid]
        if len(nodes) < K:
            # need more nodes
            min = i - 1
            max = i + 1
            while len(nodes) < K and (min >= 0 or max < len(self.buckets)):
                #ASw: note that this requires K be even
                if min >= 0:
                    nodes = nodes + self.buckets[min].l
                if max < len(self.buckets):
                    nodes = nodes + self.buckets[max].l
                min = min - 1
                max = max + 1
                if not invalid:
                    nodes = [a for a in nodes if not a.invalid]

        nodes.sort(lambda a, b, num=num: cmp(num ^ a.num, num ^ b.num))
        return nodes[:K]
        
    def _splitBucket(self, a):
        diff = (a.max - a.min) / 2
        b = KBucket([], a.max - diff, a.max)
        self.buckets.insert(self.buckets.index(a.min) + 1, b)
        a.max = a.max - diff
        # transfer nodes to new bucket
        for anode in a.l[:]:
            if anode.num >= a.max:
                a.removeNode(anode)
                b.addNode(anode)
    
    def replaceStaleNode(self, stale, new):
        """this is used by clients to replace a node returned by insertNode after
        it fails to respond to a Pong message"""
        i = self._bucketIndexForInt(stale.num)

        if self.buckets[i].hasNode(stale):
            self.buckets[i].removeNode(stale)
        if new and self.buckets[i].hasNode(new):
            self.buckets[i].seenNode(new)
        elif new:
            self.buckets[i].addNode(new)

        return
    
    def insertNode(self, node, contacted=1, nocheck=False):
        """ 
        this insert the node, returning None if successful, returns the oldest node in the bucket if it's full
        the caller responsible for pinging the returned node and calling replaceStaleNode if it is found to be stale!!
        contacted means that yes, we contacted THEM and we know the node is reachable
        """
        if node.id == NULL_ID or node.id == self.node.id:
            return

        if contacted:
            node.updateLastSeen()

        # get the bucket for this node
        i = self._bucketIndexForInt(node.num)
        # check to see if node is in the bucket already
        if self.buckets[i].hasNode(node):
            it = self.buckets[i].l.index(node.num)
            xnode = self.buckets[i].l[it]
            if contacted:
                node.age = xnode.age
                self.buckets[i].seenNode(node)
            elif xnode.lastSeen != 0 and xnode.port == node.port and xnode.host == node.host:
                xnode.updateLastSeen()
            return
        
        # we don't have this node, check to see if the bucket is full
        if not self.buckets[i].bucketFull():
            # no, append this node and return
            self.buckets[i].addNode(node)
            return

        # full bucket, check to see if any nodes are invalid
        t = time()
        invalid = [x for x in self.buckets[i].invalid.values() if x.invalid]
        if len(invalid) and not nocheck:
            invalid.sort(ls)
            while invalid and not self.buckets[i].hasNode(invalid[0]):
                del(self.buckets[i].invalid[invalid[0].num])
                invalid = invalid[1:]
            if invalid and (invalid[0].lastSeen == 0 and invalid[0].fails < MAX_FAILURES):
                return invalid[0]
            elif invalid:
                self.replaceStaleNode(invalid[0], node)
                return

        stale =  [n for n in self.buckets[i].l if (t - n.lastSeen) > MIN_PING_INTERVAL]
        if len(stale) and not nocheck:
            stale.sort(ls)
            return stale[0]
            
        # bucket is full and all nodes are valid, check to see if self.node is in the bucket
        if not (self.buckets[i].min <= self.node < self.buckets[i].max):
            return
        
        # this bucket is full and contains our node, split the bucket
        if len(self.buckets) >= HASH_LENGTH:
            # our table is FULL, this is really unlikely
            print "Hash Table is FULL!  Increase K!"
            return
            
        self._splitBucket(self.buckets[i])
        
        # now that the bucket is split and balanced, try to insert the node again
        return self.insertNode(node, contacted)
    
    def justSeenNode(self, id):
        """call this any time you get a message from a node
        it will update it in the table if it's there """
        try:
            n = self.findNodes(id)[0]
        except IndexError:
            return None
        else:
            if n.id != id:
                return None
            tstamp = n.lastSeen
            n.updateLastSeen()
            bucket = self.bucketForInt(n.num)
            bucket.seenNode(n)
            return tstamp
    
    def invalidateNode(self, n):
        """
            forget about node n - use when you know that node is invalid
        """
        n.invalid = True
        bucket = self.bucketForInt(n.num)
        bucket.invalidateNode(n)
    
    def nodeFailed(self, node):
        """ call this when a node fails to respond to a message, to invalidate that node """
        try:
            n = self.findNodes(node.num)[0]
        except IndexError:
            return None
        else:
            if n.id != node.id:
                return None
            if n.msgFailed() >= const.MAX_FAILURES:
                self.invalidateNode(n)

    def numPeers(self):
        """ estimated number of connectable nodes in global table """
        return 8 * (2 ** (len(self.buckets) - 1))
    
class KBucket(object):
    __slots__ = ('min', 'max', 'lastAccessed', 'l', 'index', 'invalid')
    def __init__(self, contents, min, max):
        self.l = contents
        self.index = {}
        self.invalid = {}
        self.min = min
        self.max = max
        self.lastAccessed = time()
        
    def touch(self):
        self.lastAccessed = time()

    def lacmp(self, a, b):
        if a.lastSeen > b.lastSeen:
            return 1
        elif b.lastSeen > a.lastSeen:
            return -1
        return 0
        
    def sort(self):
        self.l.sort(self.lacmp)
        
    def getNodeWithInt(self, num):
        try:
            node = self.index[num]
        except KeyError:
            raise ValueError
        return node
    
    def addNode(self, node):
        if len(self.l) >= K:
            return
        if self.index.has_key(node.num):
            return
        self.l.append(node)
        self.index[node.num] = node
        self.touch()

    def removeNode(self, node):
        assert self.index.has_key(node.num)
        del(self.l[self.l.index(node.num)])
        del(self.index[node.num])
        try:
            del(self.invalid[node.num])
        except KeyError:
            pass

    def invalidateNode(self, node):
        self.invalid[node.num] = node

    def seenNode(self, node):
        try:
            del(self.invalid[node.num])
        except KeyError:
            pass
        it = self.l.index(node.num)
        del(self.l[it])
        self.l.append(node)
        self.index[node.num] = node
        
    def hasNode(self, node):
        return self.index.has_key(node.num)

    def bucketFull(self):
        return len(self.l) >= K
    
    def __repr__(self):
        return "<KBucket %d items (%d to %d)>" % (len(self.l), self.min, self.max)
    
    ## Comparators    
    # necessary for bisecting list of buckets with a hash expressed as an integer or a distance
    # compares integer or node object with the bucket's range
    def __lt__(self, a):
        if isinstance(a, Node): a = a.num
        return self.max <= a
    def __le__(self, a):
        if isinstance(a, Node): a = a.num
        return self.min < a
    def __gt__(self, a):
        if isinstance(a, Node): a = a.num
        return self.min > a
    def __ge__(self, a):
        if isinstance(a, Node): a = a.num
        return self.max >= a
    def __eq__(self, a):
        if isinstance(a, Node): a = a.num
        return self.min <= a and self.max > a
    def __ne__(self, a):
        if isinstance(a, Node): a = a.num
        return self.min >= a or self.max < a


### UNIT TESTS ###
import unittest

class TestKTable(unittest.TestCase):
    def setUp(self):
        self.a = Node().init(hash.newID(), 'localhost', 2002)
        self.t = KTable(self.a)

    def testAddNode(self):
        self.b = Node().init(hash.newID(), 'localhost', 2003)
        self.t.insertNode(self.b)
        self.assertEqual(len(self.t.buckets[0].l), 1)
        self.assertEqual(self.t.buckets[0].l[0], self.b)

    def testRemove(self):
        self.testAddNode()
        self.t.invalidateNode(self.b)
        self.assertEqual(len(self.t.buckets[0].l), 0)

    def testFail(self):
        self.testAddNode()
        for i in range(const.MAX_FAILURES - 1):
            self.t.nodeFailed(self.b)
            self.assertEqual(len(self.t.buckets[0].l), 1)
            self.assertEqual(self.t.buckets[0].l[0], self.b)
            
        self.t.nodeFailed(self.b)
        self.assertEqual(len(self.t.buckets[0].l), 0)


if __name__ == "__main__":
    unittest.main()
