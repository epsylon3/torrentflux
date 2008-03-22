# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import unittest
from BTLR.platform import bttime
from time import sleep

from kstore import KStore
if __name__ =="__main__":
    tests = unittest.defaultTestLoader.loadTestsFromNames(['test_kstore'])
    result = unittest.TextTestRunner().run(tests)


class BasicTests(unittest.TestCase):
    def setUp(self):
        self.k = KStore()
        
    def testNoKeys(self):
        self.assertEqual(self.k.keys(), [])

    def testKey(self):
        self.k['foo'] = 'bar'
        self.assertEqual(self.k.keys(), ['foo'])

    def testKeys(self):
        self.k['foo'] = 'bar'
        self.k['wing'] = 'wang'
        l = self.k.keys()
        l.sort()
        self.assertEqual(l, ['foo', 'wing'])
        
    def testInsert(self):
        self.k['foo'] = 'bar'
        self.assertEqual(self.k['foo'], ['bar'])

    def testInsertTwo(self):
        self.k['foo'] = 'bar'
        self.k['foo'] = 'bing'
        l = self.k['foo']
        l.sort()
        self.assertEqual(l, ['bar', 'bing'])
        
    def testExpire(self):
        self.k['foo'] = 'bar'
        self.k.expire(bttime() - 1)
        l = self.k['foo']
        l.sort()
        self.assertEqual(l, ['bar'])
        self.k['foo'] = 'bing'
        t = bttime()
        self.k.expire(bttime() - 1)
        l = self.k['foo']
        l.sort()
        self.assertEqual(l, ['bar', 'bing'])        
        self.k['foo'] = 'ding'
        self.k['foo'] = 'dang'
        l = self.k['foo']
        l.sort()
        self.assertEqual(l, ['bar', 'bing', 'dang', 'ding'])
        self.k.expire(t)
        l = self.k['foo']
        l.sort()
        self.assertEqual(l, ['dang', 'ding'])
        
    def testDup(self):
        self.k['foo'] = 'bar'
        self.k['foo'] = 'bar'
        self.assertEqual(self.k['foo'], ['bar'])

    def testSample(self):
        for i in xrange(2):
            self.k['foo'] = i
        l = self.k.sample('foo', 5)
        l.sort()
        self.assertEqual(l, [0, 1])

        for i in xrange(10):
            for i in xrange(10):
                self.k['bar'] = i
            l = self.k.sample('bar', 5)
            self.assertEqual(len(l), 5)
            for i in xrange(len(l)):
                self.assert_(l[i] not in l[i+1:])
        
