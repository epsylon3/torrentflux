# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import khash
from BTL.platform import bttime as time
from types import *

class Node(object):
    """encapsulate contact info"""
    __slots__ = ('fails','lastSeen','invalid','id','host','port','age')
    def __init__(self):
        self.fails = 0
        self.lastSeen = 0
        self.invalid = True
        self.id = self.host = self.port = ''
        self.age = time()
        
    def init(self, id, host, port):
        self.id = id
        self.num = khash.intify(id)
        self.host = host
        self.port = port
        self._senderDict = {'id': self.id, 'port' : self.port, 'host' : self.host}
        return self
    
    def initWithDict(self, dict):
        self._senderDict = dict
        self.id = dict['id']
        self.num = khash.intify(self.id)
        self.port = dict['port']
        self.host = dict['host']
        self.age = dict.get('age', self.age)
        return self
    
    def updateLastSeen(self):
        self.lastSeen = time()
        self.fails = 0
        self.invalid = False
        
    def msgFailed(self):
        self.fails = self.fails + 1
        return self.fails
    
    def senderDict(self):
        return self._senderDict

    def __hash__(self):
        return self.id.__hash__()
    
    def __repr__(self):
        return ">node <%s> %s<" % (self.id.encode('base64')[:4], (self.host, self.port))
    
    ## these comparators let us bisect/index a list full of nodes with either a node or an int/long
    def __lt__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num < a
    def __le__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num <= a
    def __gt__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num > a
    def __ge__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num >= a
    def __eq__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num == a
    def __ne__(self, a):
        if type(a) == InstanceType:
            a = a.num
        return self.num != a


import unittest

class TestNode(unittest.TestCase):
    def setUp(self):
        self.node = Node().init(khash.newID(), 'localhost', 2002)
    def testUpdateLastSeen(self):
        t = self.node.lastSeen
        self.node.updateLastSeen()
        assert t < self.node.lastSeen
    
