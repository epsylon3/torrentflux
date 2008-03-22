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
from krpc import *
from BitTorrent.defaultargs import common_options, rare_options
from BTL.stackthreading import Event
from BitTorrent.RawServer_twisted import RawServer
from node import Node

KRPC.noisy = 0

import sys

if __name__ =="__main__":
    tests = defaultTestLoader.loadTestsFromNames([sys.argv[0][:-3]])
    result = TextTestRunner().run(tests)


def connectionForAddr(host, port):
    return host
    

class Receiver(object):
    protocol = KRPC
    def __init__(self, addr):
        self.buf = []
        self.node = Node().init('0'*20, addr[0], addr[1])
    def krpc_store(self, msg, _krpc_sender):
        self.buf += [msg]
    def krpc_echo(self, msg, _krpc_sender):
        return msg

class KRPCTests(TestCase):
    def setUp(self):
        self.noisy = 0
        d = dict([(x[0],x[1]) for x in common_options + rare_options])
        self.r = RawServer(d)

        addr = ('127.0.0.1', 1180)
        self.as = self.r.create_udpsocket(addr[1], addr[0], True)
        self.af = Receiver(addr)
        self.a = hostbroker(self.af, addr, self.as, self.r.add_task)
        self.r.start_listening_udp(self.as, self.a)

        addr = ('127.0.0.1', 1181)
        self.bs = self.r.create_udpsocket(addr[1], addr[0], True)
        self.bf = Receiver(addr)
        self.b = hostbroker(self.bf, addr, self.bs, self.r.add_task)
        self.r.start_listening_udp(self.bs, self.b)
        
    def tearDown(self):
        self.as.close()
        self.bs.close()

    def testSimpleMessage(self):
        self.noisy = 0
        self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('store', {'msg' : "This is a test."})
        self.r.listen_once(0.01)
        self.assertEqual(self.bf.buf, ["This is a test."])

    def testMessageBlast(self):
        self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('store', {'msg' : "This is a test."})
        self.r.listen_once(0.01)
        self.assertEqual(self.bf.buf, ["This is a test."])
        self.bf.buf = []
        
        for i in range(100):
            self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('store', {'msg' : "This is a test."})
            self.r.listen_once(0.01)
            #self.bf.buf = []
        self.assertEqual(self.bf.buf, ["This is a test."] * 100)

    def testEcho(self):
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is a test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is a test.")

    def gotMsg(self, dict):
        _krpc_sender = dict['_krpc_sender']
        msg = dict['rsp']
        self.msg = msg

    def testManyEcho(self):
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is a test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is a test.")
        for i in xrange(100):
            self.msg = None
            df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is a test."})
            df.addCallback(self.gotMsg)
            self.r.listen_once(0.01)
            self.r.listen_once(0.01)
            self.assertEqual(self.msg, "This is a test.")

    def testMultiEcho(self):
        self.noisy = 0
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is a test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is a test.")

        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is another test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is another test.")

        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is yet another test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is yet another test.")

    def testEchoReset(self):
        self.noisy = 0
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is a test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is a test.")

        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is another test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is another test.")

        del(self.a.connections[('127.0.0.1', 1181)])
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('echo', {'msg' : "This is yet another test."})
        df.addCallback(self.gotMsg)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.msg, "This is yet another test.")

    def testLotsofEchoReset(self):
        for i in range(100):
            self.testEchoReset()
            
    def testUnknownMeth(self):
        self.noisy = 0
        df = self.a.connectionForAddr(('127.0.0.1', 1181)).sendRequest('blahblah', {'msg' : "This is a test."})
        df.addErrback(self.gotErr)
        self.r.listen_once(0.01)
        self.r.listen_once(0.01)
        self.assertEqual(self.err, KRPC_ERROR_METHOD_UNKNOWN)

    def gotErr(self, err):
        self.err = err
        
