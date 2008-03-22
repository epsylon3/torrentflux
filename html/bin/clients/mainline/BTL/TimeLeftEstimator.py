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


class TimeLeftEstimator(object):

    def __init__(self, left):
        self.start = None
        self.last = None
        self.rate = 0
        self.remaining = None
        self.left = left
        self.broke = False
        self.got_anything = False
        self.when_next_expected = bttime() + 5

    def add_amount(self, amount):
        """ add number of bytes received """
        if not self.got_anything:
            self.got_anything = True
            self.start = bttime() - 2
            self.last = self.start
            self.left -= amount
            return
        self.update(bttime(), amount)

    def remove_amount(self, amount):
        self.left += amount

    def get_time_left(self):
        """ returns seconds """
        if not self.got_anything:
            return None
        t = bttime()
        if t - self.last > 15:
            self.update(t, 0)
        return self.remaining

    def get_size_left(self):
        return self.left

    def update(self, t, amount):
        self.left -= amount
        if t < self.when_next_expected and amount == 0:
            return
        try:
            self.rate = ((self.rate * (self.last - self.start)) + amount) / (t - self.start)
            self.last = t
            self.remaining = self.left / self.rate
            if self.start < self.last - self.remaining:
                self.start = self.last - self.remaining
        except ZeroDivisionError:
            self.remaining = None
        if self.broke and self.last - self.start < 20:
            self.start = self.last - 20
        if self.last - self.start > 20:
            self.broke = True
        self.when_next_expected = t + min((amount / max(self.rate, 0.0001)), 5)
