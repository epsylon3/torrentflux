# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from unittest import *

from BitTorrent import RawServer_twisted

from khashmir import *
import khash
from copy import copy

from random import randrange
from krpc import KRPC

KRPC.noisy=0
import os

if __name__ =="__main__":
    tests = defaultTestLoader.loadTestsFromNames([sys.argv[0][:-3]])
    result = TextTestRunner().run(tests)

class MultiTest(TestCase):
    num = 25
    def _done(self, val):
        self.done = 1
        
    def setUp(self):
        self.l = []
        self.startport = 10088
        d = dict([(x[0],x[1]) for x in common_options + rare_options])
        self.r = RawServer(d)
        for i in range(self.num):
            self.l.append(Khashmir('127.0.0.1', self.startport + i, '/tmp/%s.test' % (self.startport + i), self.r))
        self.r.listen_once(1)
        self.r.listen_once(1)
        
        for i in self.l:
            try:
                i.addContact('127.0.0.1', self.l[randrange(0,self.num)].port)
            except:
                pass
            try:
                i.addContact('127.0.0.1', self.l[randrange(0,self.num)].port)
            except:
                pass
            try:
                i.addContact('127.0.0.1', self.l[randrange(0,self.num)].port)
            except:
                pass
            self.r.listen_once(1)
            self.r.listen_once(1)
            self.r.listen_once(1) 
            
        for i in self.l:
            self.done = 0
            i.findCloseNodes(self._done)
            while not self.done:
                self.r.listen_once(1)
        for i in self.l:
            self.done = 0
            i.findCloseNodes(self._done)
            while not self.done:
                self.r.listen_once(1)

    def tearDown(self):
        for i in self.l:
            self.r.stop_listening_udp(i.socket)
            i.socket.close()
            
        self.r.listen_once(1)
        
    def testStoreRetrieve(self):
        for i in range(10):
            K = khash.newID()
            V = khash.newID()
            
            for a in range(3):
                self.done = 0
                def _scb(val):
                    self.done = 1
                self.l[randrange(0, self.num)].storeValueForKey(K, V, _scb)
                while not self.done:
                    self.r.listen_once(1)


                def _rcb(val):
                    if not val:
                        self.done = 1
                        self.assertEqual(self.got, 1)
                    elif V in val:
                        self.got = 1
                for x in range(3):
                    self.got = 0
                    self.done = 0
                    self.l[randrange(0, self.num)].valueForKey(K, _rcb)
                    while not self.done:
                        self.r.listen_once(1)

class AASimpleTests(TestCase):
    def setUp(self):
        d = dict([(x[0],x[1]) for x in common_options + rare_options])
        self.r = RawServer(d)
        self.a = Khashmir('127.0.0.1', 4044, '/tmp/a.test', self.r)
        self.b = Khashmir('127.0.0.1', 4045, '/tmp/b.test', self.r)
        
    def tearDown(self):
        self.r.stop_listening_udp(self.a.socket)
        self.r.stop_listening_udp(self.b.socket)        
        self.a.socket.close()
        self.b.socket.close()

    def addContacts(self):
        self.a.addContact('127.0.0.1', 4045)
        self.r.listen_once(1)
        self.r.listen_once(1)

    def testStoreRetrieve(self):
        self.addContacts()
        self.got = 0
        self.a.storeValueForKey(sha('foo').digest(), 'foobar')
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.a.valueForKey(sha('foo').digest(), self._cb)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)
        self.r.listen_once(1)

    def _cb(self, val):
        if not val:
            self.assertEqual(self.got, 1)
        elif 'foobar' in val:
            self.got = 1

    def testAddContact(self):
        self.assertEqual(len(self.a.table.buckets), 1) 
        self.assertEqual(len(self.a.table.buckets[0].l), 0)

        self.assertEqual(len(self.b.table.buckets), 1) 
        self.assertEqual(len(self.b.table.buckets[0].l), 0)

        self.addContacts()

        self.assertEqual(len(self.a.table.buckets), 1) 
        self.assertEqual(len(self.a.table.buckets[0].l), 1)
        self.assertEqual(len(self.b.table.buckets), 1) 
        self.assertEqual(len(self.b.table.buckets[0].l), 1)





            
            
            
