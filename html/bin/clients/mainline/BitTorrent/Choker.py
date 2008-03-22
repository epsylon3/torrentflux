# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Greg Hazel
# based on code by Bram Cohen

from __future__ import division

import math
import random
from BTL.obsoletepythonsupport import set

class Choker(object):

    def __init__(self, config, schedule):
        self.config = config
        self.schedule = schedule
        self.connections = []
        self.count = 0
        self.unchokes_since_last = 0
        self.interval = 5
        self.shutting_down = False
        #self.magic_number = 6 # magic 6 : (30 / self.interval)
        self.magic_number = (30 / self.interval)
        schedule(self.interval, self._round_robin)

    def _round_robin(self):
        self.schedule(self.interval, self._round_robin)
        self.count += 1
        # don't do more work than you have to
        if not self.connections:
            return
        # rotation for round-robin
        if self.count % self.magic_number == 0:
            for i, c in enumerate(self.connections):
                u = c.upload
                if u.choked and u.interested:
                    self.connections = self.connections[i:] + self.connections[:i]
                    break
        self._rechoke()

    ## new
    ############################################################################
    def _rechoke(self):
        # step 1:
        # get sorted in order of preference lists of peers
        # one for downloading torrents, and one for seeding torrents
        down_pref = []
        seed_pref = []
        for i, c in enumerate(self.connections):
            u = c.upload
            if c.download.have.numfalse == 0 or not u.interested:
                continue
            # I cry.
            if c.download.multidownload.storage.have.numfalse != 0:
                ## heuristic for downloading torrents
                if not c.download.is_snubbed():

                    ## simple download rate based
                    down_pref.append((-c.download.get_rate(), i))

                    ## ratio based
                    #dr = c.download.get_rate()
                    #ur = max(1, u.get_rate())
                    #ratio = dr / ur
                    #down_pref.append((-ratio, i))
            else:
                ## heuristic for seeding torrents

                ## Uoti special               
##                if c._decrypt is not None:
##                    seed_pref.append((self.count, u.get_rate(), i))
##                elif (u.unchoke_time > self.count - self.magic_number or
##                      u.buffer and c.connection.is_flushed()):
##                    seed_pref.append((u.unchoke_time, u.get_rate(), i))
##                else:
##                    seed_pref.append((1, u.get_rate(), i))

                ## sliding, first pass (see below)
                r = u.get_rate()
                if c._decrypt is not None:
                    seed_pref.append((2, r, i))
                else:
                    seed_pref.append((1, r, i))

        down_pref.sort()
        seed_pref.sort()
        #pprint(down_pref)
        #pprint(seed_pref)
        down_pref = [ self.connections[i] for junk, i in down_pref ]
        seed_pref = [ self.connections[i] for junk, junk, i in seed_pref ]

        max_uploads = self._max_uploads()

        ## sliding, second pass
##        # up-side-down sum for an idea of capacity
##        uprate_sum = sum(rates[-max_uploads:])
##        if max_uploads == 0:
##            avg_uprate = 0
##        else:
##            avg_uprate = uprate_sum / max_uploads
##        #print 'avg_uprate', avg_uprate, 'of', max_uploads
##        self.extra_slots = max(self.extra_slots - 1, 0)
##        if avg_uprate > self.arbitrary_min:
##            for r in rates:
##                if r < (avg_uprate * 0.80): # magic 80%
##                    self.extra_slots += 2
##                    break
##        self.extra_slots = min(len(seed_pref), self.extra_slots)
##        max_uploads += self.extra_slots
##        #print 'plus', self.extra_slots

        # step 2:
        # split the peer lists by a ratio to fill the available upload slots
        d_uploads = max(1, int(round(max_uploads * 0.70)))
        s_uploads = max(1, int(round(max_uploads * 0.30)))
        #print 'original', 'ds', d_uploads, 'us', s_uploads
        extra = max(0, d_uploads - len(down_pref))
        if extra > 0:
            s_uploads += extra
            d_uploads -= extra
        extra = max(0, s_uploads - len(seed_pref))
        if extra > 0:
            s_uploads -= extra
            d_uploads = min(d_uploads + extra, len(down_pref))
        #print 'ds', d_uploads, 'us', s_uploads        
        down_pref = down_pref[:d_uploads]
        seed_pref = seed_pref[:s_uploads]
        preferred = set(down_pref)
        preferred.update(seed_pref)

        # step 3:
        # enforce unchoke states
        count = 0
        to_choke = []
        for i, c in enumerate(self.connections):
            u = c.upload
            if c in preferred:
                u.unchoke(self.count)
                count += 1
            else:
                to_choke.append(c)

        # step 4:
        # enforce choke states and handle optimistics
        optimistics = max(self.config['min_uploads'],
                          max_uploads - len(preferred))
        #print 'optimistics', optimistics
        for c in to_choke:
            u = c.upload
            if c.download.have.numfalse == 0:
                u.choke()
            elif count >= optimistics:
                u.choke()
            else:
                # this one's optimistic
                u.unchoke(self.count)
                if u.interested:
                    count += 1
    ############################################################################

    def shutdown(self):
        self.shutting_down = True

    def connection_made(self, connection):
        p = random.randrange(len(self.connections) + 1)
        self.connections.insert(p, connection)

    def connection_lost(self, connection):
        self.connections.remove(connection)
        if (not self.shutting_down and
            connection.upload.interested and not connection.upload.choked):
            self._rechoke()

    def interested(self, connection):
        if not connection.upload.choked:
            self._rechoke()

    def not_interested(self, connection):
        if not connection.upload.choked:
            self._rechoke()

    def _max_uploads(self):
        uploads = self.config['max_uploads']
        rate = self.config['max_upload_rate']  / 1024
        if uploads > 0:
            pass
        elif rate <= 0:
            uploads = 7 # unlimited, just guess something here...
        elif rate < 9:
            uploads = 2
        elif rate < 15:
            uploads = 3
        elif rate < 42:
            uploads = 4
        else:
            uploads = int(math.sqrt(rate * .6))
        return uploads


