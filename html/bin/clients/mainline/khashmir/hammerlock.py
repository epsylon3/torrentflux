# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

INTERVAL = 60
PERIODS = 5

class Hammerlock:
    def __init__(self, rate, call_later):
        self.rate = rate
        self.call_later = call_later
        self.curr = 0
        self.buckets = [{} for x in range(PERIODS)]
        self.call_later(INTERVAL, self._cycle)
        
    def _cycle(self):
        self.curr = (self.curr + 1) % PERIODS
        self.buckets[self.curr] = {}
        self.call_later(INTERVAL, self._cycle)

    def check(self, addr):
        x = self.buckets[self.curr].get(addr, 0) + 1
        self.buckets[self.curr][addr] = x
        x = 0
        for bucket in self.buckets:
            x += bucket.get(addr, 0) 
        if x >= self.rate:
            return False
        else:
            return True
    
