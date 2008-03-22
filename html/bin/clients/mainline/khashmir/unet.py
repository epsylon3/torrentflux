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
#  knet.py
#  create a network of khashmir nodes
# usage: knet.py <num_nodes> <start_port> <ip_address>

from utkhashmir import UTKhashmir
from BitTorrent.RawServer_twisted import RawServer
from BitTorrent.defaultargs import common_options, rare_options
from random import randrange
from BitTorrent.stackthreading import Event
import sys, os

from krpc import KRPC
KRPC.noisy = 1

class Network:
    def __init__(self, size=0, startport=5555, localip='127.0.0.1'):
        self.num = size
        self.startport = startport
        self.localip = localip

    def _done(self, val):
        self.done = 1
        
    def simpleSetUp(self):
        #self.kfiles()
        d = dict([(x[0],x[1]) for x in common_options + rare_options])
        self.r = RawServer(Event(), d)
        self.l = []
        for i in range(self.num):
            self.l.append(UTKhashmir('', self.startport + i, 'kh%s.db' % (self.startport + i), self.r))
        
        for i in self.l:
            i.addContact(self.localip, self.l[randrange(0,self.num)].port)
            i.addContact(self.localip, self.l[randrange(0,self.num)].port)
            i.addContact(self.localip, self.l[randrange(0,self.num)].port)
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
            i.rawserver.stop_listening_udp(i.socket)
            i.socket.close()
        #self.kfiles()
        
    def kfiles(self):
        for i in range(self.startport, self.startport+self.num):
            try:
                os.unlink('kh%s.db' % i)
            except:
                pass
            
        self.r.listen_once(1)
    
if __name__ == "__main__":
    n = Network(int(sys.argv[1]), int(sys.argv[2]))
    n.simpleSetUp()
    print ">>> network ready"
    try:
        n.r.listen_forever()
    finally:
        n.tearDown()
