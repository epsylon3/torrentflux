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
from BitTorrent.CurrentRateMeasure import Measure
from const import *
from random import randrange, shuffle
from traceback import print_exc

class KRateLimiter:
    # special rate limiter that drops entries that have been sitting in the queue for longer than self.age seconds
    # by default we toss anything that has less than 5 seconds to live
    def __init__(self, transport, rate, call_later, rlcount, rate_period, age=(KRPC_TIMEOUT - 5)):
        self.q = []
        self.transport = transport
        self.rate = rate
        self.curr = 0
        self.running = False
        self.age = age
        self.last = 0
        self.call_later = call_later
        self.rlcount = rlcount
        self.measure = Measure(rate_period)
        self.sent=self.dropped=0
        if self.rate == 0:
            self.rate = 1e10
            
    def sendto(self, s, i, addr):
        self.q.append((time(), (s, i, addr)))
        if not self.running:
            self.run(check=True)

    def run(self, check=False):
        t = time()
        self.expire(t)
        self.curr -= (t - self.last) * self.rate
        self.last = t
        if check:
            self.curr = max(self.curr, 0 - self.rate)

        shuffle(self.q)
        while self.q and self.curr <= 0:
            x, tup = self.q.pop()
            size = len(tup[0])
            self.curr += size
            try:
                self.transport.sendto(*tup)
                self.sent+=1
                self.rlcount(size)
                self.measure.update_rate(size)
            except:
                if tup[2][1] != 0:
                    print ">>> sendto exception", tup
                    print_exc()
        self.q.sort()
        if self.q or self.curr > 0:
            self.running = True
            # sleep for at least a half second
            self.call_later(max(self.curr / self.rate, 0.5), self.run)
        else:
            self.running = False
                          
    def expire(self, t=time()):
        if self.q:
            expire_time = t - self.age
            while self.q and self.q[0][0] < expire_time:
                self.q.pop(0)
                self.dropped+=1
