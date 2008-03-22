# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen, Uoti Urpala

import array
import random

from BTL.sparse_set import SparseSet
from BTL.obsoletepythonsupport import set
from BitTorrent.Download import Download

SPARSE_SET = True
if SPARSE_SET:
    from BitTorrent.PieceSetBuckets import PieceSetBuckets
else:
    from BitTorrent.PieceSetBuckets import SortedPieceBuckets, resolve_typecode

RENDER = False

class PerIPStats(object):

    def __init__(self):
        self.numgood = 0
        self.bad = {}
        self.numconnections = 0
        self.lastdownload = None
        self.peerid = None

class MultiDownload(object):

    def __init__(self, config, storage, rm, urlage, picker, numpieces,
                 finished, errorfunc, kickfunc, banfunc, get_downrate):
        self.config = config
        self.storage = storage
        self.rm = rm
        self.urlage = urlage
        self.picker = picker
        self.errorfunc = errorfunc
        self.rerequester = None
        self.entered_endgame = False
        self.connection_manager = None
        self.chunksize = config['download_chunk_size']
        self.numpieces = numpieces
        self.finished = finished
        self.snub_time = config['snub_time']
        self.kickfunc = kickfunc
        self.banfunc = banfunc
        self.get_downrate = get_downrate
        self.downloads = []
        self.perip = {}
        self.bad_peers = {}
        self.discarded_bytes = 0
        self.useful_received_listeners = set()
        self.raw_received_listeners = set()
        
        if SPARSE_SET:
            self.piece_states = PieceSetBuckets()
            nothing = SparseSet()
            nothing.add(0, self.numpieces)
            self.piece_states.buckets.append(nothing)

            # I hate this
            nowhere = [(i, 0) for i in xrange(self.numpieces)]
            self.piece_states.place_in_buckets = dict(nowhere)
        else:
            typecode = resolve_typecode(self.numpieces)
            self.piece_states = SortedPieceBuckets(typecode)
            nothing = array.array(typecode, range(self.numpieces))
            self.piece_states.buckets.append(nothing)

            # I hate this
            nowhere = [(i, (0, i)) for i in xrange(self.numpieces)]
            self.piece_states.place_in_buckets = dict(nowhere)
        
        self.last_update = 0
        self.all_requests = set()

    def attach_connection_manager(self, connection_manager):
        self.connection_manager = connection_manager

    def aggregate_piece_states(self):
        d = {}
        d['h'] = self.storage.have_set
        d['t'] = set(self.rm.active_requests.iterkeys())

        for i, bucket in enumerate(self.piece_states.buckets):
            d[i] = bucket

        r = (self.numpieces, self.last_update, d)
        return r

    def get_unchoked_seed_count(self):
        seed_count = 0
        for d in self.downloads:
            if d.have.numfalse == 0 and not d.choked:
                seed_count += 1
        return seed_count

    def get_adjusted_distributed_copies(self):
        # compensate for the fact that piece picker does no
        # contain all the pieces
        num = self.picker.get_distributed_copies()
        percent_have = (float(len(self.storage.have_set)) /
                        float(self.numpieces))
        num += percent_have
        if self.rerequester and self.rerequester.tracker_num_seeds:
            num += self.rerequester.tracker_num_seeds
        return num

    def active_requests_add(self, r):
        self.last_update += 1
        
    def active_requests_remove(self, r):
        self.last_update += 1

    def got_have(self, piece):
        self.picker.got_have(piece)
        self.last_update += 1
        p = self.piece_states
        p.add(piece, p.remove(piece) + 1)

    def got_have_all(self):
        self.picker.got_have_all()
        self.last_update += 1
        self.piece_states.prepend_bucket()

    def lost_have(self, piece):
        self.picker.lost_have(piece)
        self.last_update += 1
        p = self.piece_states
        p.add(piece, p.remove(piece) - 1)

    def lost_have_all(self):
        self.picker.lost_have_all()
        self.last_update += 1
        self.piece_states.popleft_bucket()

    def check_enter_endgame(self):
        if not self.entered_endgame:
            if self.rm.endgame:
                self.entered_endgame = True
                self.all_requests = set()
                for d in self.downloads:
                    self.all_requests.update(d.active_requests)
        for d in self.downloads:
            d.fix_download_endgame()

    def hashchecked(self, index):
        if not self.storage.do_I_have(index):
            if self.rm.endgame:
                while self.rm.want_requests(index):
                    nb, nl = self.rm.new_request(index)
                    self.all_requests.add((index, nb, nl))
                for d in self.downloads:
                    d.fix_download_endgame()
            else:
                ds = [d for d in self.downloads if not d.choked]
                random.shuffle(ds)
                for d in ds:
                    d._request_more([index])
            return

        self.picker.complete(index)
        self.active_requests_remove(index)

        self.connection_manager.hashcheck_succeeded(index)

        if self.storage.have.numfalse == 0:
            for d in self.downloads:
                if d.have.numfalse == 0:
                    d.connector.close()

            self.finished()

    def make_download(self, connector):
        ip = connector.ip
        perip = self.perip.setdefault(ip, PerIPStats())
        perip.numconnections += 1
        d = Download(self, connector)
        d.add_useful_received_listener(self.fire_useful_received_listeners) 
        d.add_raw_received_listener(self.fire_raw_received_listeners)
        perip.lastdownload = d
        perip.peerid = connector.id
        self.downloads.append(d)
        return d

    def add_useful_received_listener(self,listener):
        """Listeners are called for useful arrivals to any of the downloaders
           managed by this MultiDownload object.
           (see Download.add_useful_received_listener for which listeners are
           called for bytes received by that particular Download."""
        self.useful_received_listeners.add(listener)

    def add_raw_received_listener(self, listener):
        """Listers are called whenever bytes arrive (i.e., to Connector.data_came_in)
           regardless of whether those bytes are useful."""
        self.raw_received_listeners.add(listener)

    def remove_useful_received_listener(self,listener):
        self.useful_received_listeners.remove(listener)

    def fire_useful_received_listeners(self,bytes):
        for f in self.useful_received_listeners:
            f(bytes)
            
    def remove_raw_received_listener(self, listener):
        self.raw_received_listeners.remove(listener)

    def fire_raw_received_listeners(self,bytes):
        for f in self.raw_received_listeners:
            f(bytes)

    def lost_peer(self, download):
        if download.have.numfalse == 0:
            # lost seed...
            pass
        self.downloads.remove(download)
        ip = download.connector.ip
        self.perip[ip].numconnections -= 1
        if self.perip[ip].lastdownload == download:
            self.perip[ip].lastdownload = None

    def kick(self, download):
        download.connector.protocol_violation("peer sent bad data")
        if not self.config['retaliate_to_garbled_data']:
            return
        ip = download.connector.ip
        peerid = download.connector.id
        # kickfunc will schedule connection.close() to be executed later; we
        # might now be inside RawServer event loop with events from that
        # connection already queued, and trying to handle them after doing
        # close() now could cause problems.
        self.kickfunc(download.connector)

    def ban(self, ip):
        if not self.config['retaliate_to_garbled_data']:
            return
        self.banfunc(ip)
        self.bad_peers[ip] = (True, self.perip[ip])

