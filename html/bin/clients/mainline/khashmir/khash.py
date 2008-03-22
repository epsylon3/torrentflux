# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from sha import sha
from random import randint

#this is ugly, hopefully os.entropy will be in 2.4
try:
    from entropy import entropy
except ImportError:
    def entropy(n):
        s = ''
        for i in range(n):
            s += chr(randint(0,255))
        return s

def intify(hstr):
    """20 bit hash, big-endian -> long python integer"""
    assert len(hstr) == 20
    return long(hstr.encode('hex'), 16)

def stringify(num):
    """long int -> 20-character string"""
    str = hex(num)[2:]
    if str[-1] == 'L':
        str = str[:-1]
    if len(str) % 2 != 0:
        str = '0' + str
    str = str.decode('hex')
    return (20 - len(str)) *'\x00' + str
    
def distance(a, b):
    """distance between two 160-bit hashes expressed as 20-character strings"""
    return intify(a) ^ intify(b)


def newID():
    """returns a new pseudorandom globally unique ID string"""
    h = sha()
    h.update(entropy(20))
    return h.digest()

def newIDInRange(min, max):
    return stringify(randRange(min,max))
    
def randRange(min, max):
    return min + intify(newID()) % (max - min)
    
def newTID():
    return randRange(-2**30, 2**30)

### Test Cases ###
import unittest

class NewID(unittest.TestCase):
    def testLength(self):
        self.assertEqual(len(newID()), 20)
    def testHundreds(self):
        for x in xrange(100):
            self.testLength

class Intify(unittest.TestCase):
    known = [('\0' * 20, 0),
            ('\xff' * 20, 2L**160 - 1),
            ]
    def testKnown(self):
        for str, value in self.known: 
            self.assertEqual(intify(str),  value)
    def testEndianessOnce(self):
        h = newID()
        while h[-1] == '\xff':
            h = newID()
        k = h[:-1] + chr(ord(h[-1]) + 1)
        self.assertEqual(intify(k) - intify(h), 1)
    def testEndianessLots(self):
        for x in xrange(100):
            self.testEndianessOnce()

class Disantance(unittest.TestCase):
    known = [
            (("\0" * 20, "\xff" * 20), 2**160L -1),
            ((sha("foo").digest(), sha("foo").digest()), 0),
            ((sha("bar").digest(), sha("bar").digest()), 0)
            ]
    def testKnown(self):
        for pair, dist in self.known:
            self.assertEqual(distance(pair[0], pair[1]), dist)
    def testCommutitive(self):
        for i in xrange(100):
            x, y, z = newID(), newID(), newID()
            self.assertEqual(distance(x,y) ^ distance(y, z), distance(x, z))
        
class RandRange(unittest.TestCase):
    def testOnce(self):
        a = intify(newID())
        b = intify(newID())
        if a < b:
            c = randRange(a, b)
            self.assertEqual(a <= c < b, 1, "output out of range %d  %d  %d" % (b, c, a))
        else:
            c = randRange(b, a)
            assert b <= c < a, "output out of range %d  %d  %d" % (b, c, a)

    def testOneHundredTimes(self):
        for i in xrange(100):
            self.testOnce()



if __name__ == '__main__':
    unittest.main()   

    
