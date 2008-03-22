# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

### generate a bunch of nodes that use a single contact point
usage = "usage: inserter.py <contact host> <contact port>"

from utkhashmir import UTKhashmir
from BitTorrent.RawServer_twisted import RawServer
from BitTorrent.defaultargs import common_options, rare_options
from khashmir.khash import newID
from random import randrange
from BitTorrent.stackthreading import Event
import sys, os

from khashmir.krpc import KRPC
KRPC.noisy = 1
done = 0
def d(n):
    global done
    done = done+1
    
if __name__=="__main__":
    host, port = sys.argv[1:]
    x = UTKhashmir("", 22038, "/tmp/cgcgcgc")
    x.addContact(host, int(port))
    x.rawserver.listen_once()
    x.findCloseNodes(d)
    while not done:
        x.rawserver.listen_once()
    l = []
    for i in range(10):
        k = newID()
        v = randrange(10000,20000)
        l.append((k, v))
        x.announcePeer(k, v, d)
    done = 1
    while done < 10:
        x.rawserver.listen_once(1)
    for k,v in l:
        print ">>>", `k`, v
