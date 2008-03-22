# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen

from BTL.platform import bttime


class CurrentRateMeasure(object):

    def __init__(self, max_rate_period, fudge=5):
        self.max_rate_period = max_rate_period
        self.ratesince = bttime() - fudge
        self.last = self.ratesince
        self.rate = 0.0
        self.total = 0
        self.when_next_expected = bttime() + fudge

    def add_amount(self, amount):
        """ add number of bytes received """
        self.total += amount
        t = bttime()
        if t < self.when_next_expected and amount == 0:
            return self.rate
        self.rate = (self.rate * (self.last - self.ratesince) +
                     amount) / (t - self.ratesince)
        self.last = t
        self.ratesince = max(self.ratesince, t - self.max_rate_period)
        self.when_next_expected = t + min((amount / max(self.rate, 0.0001)), 5)

    def get_rate(self):
        """ returns bytes per second """
        self.add_amount(0)
        return self.rate

    def get_rate_noupdate(self):
        """ returns bytes per second """
        return self.rate

    def time_until_rate(self, newrate):
        if self.rate <= newrate:
            return 0
        t = bttime() - self.ratesince
        # as long as the newrate is lower than rate, we wait
        # longer before throttling.
        return ((self.rate * t) / newrate) - t

    def get_total(self):
        return self.total
