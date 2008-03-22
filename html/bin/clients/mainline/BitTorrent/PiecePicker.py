# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen and Greg Hazel

import array
import random
import itertools

def resolve_typecode(n):
    if n < 32768:
        return 'h'
    return 'l'

class PieceBuckets(object):
    """A PieceBuckets object is an array of arrays.  ith bucket contains
       pieces that have i known instances within the network.  Pieces
       within each bucket are randomly ordered."""
    def __init__(self, typecode):
        self.typecode = typecode
        # [[piece]]
        self.buckets = []
        # {piece: (bucket, bucketpos)}
        self.place_in_buckets = {}

    def get_position(self, piece):  # returns which bucket piece is in.
        return self.place_in_buckets[piece][0]

    def __contains__(self, piece):
        return piece in self.place_in_buckets

    def add(self, piece, bucketindex):
        assert not self.place_in_buckets.has_key(piece)
        while len(self.buckets) <= bucketindex:
            self.buckets.append(array.array(self.typecode))
        bucket = self.buckets[bucketindex]
        # randomly swap piece with piece already in bucket...
        newspot = random.randrange(len(bucket) + 1)
        if newspot == len(bucket):
            bucket.append(piece)
        else:
            tomove = bucket[newspot]
            self.place_in_buckets[tomove] = (bucketindex, len(bucket))
            bucket.append(tomove)
            bucket[newspot] = piece
        self.place_in_buckets[piece] = (bucketindex, newspot)

    def remove(self, piece):
        bucketindex, bucketpos = self.place_in_buckets.pop(piece)
        bucket = self.buckets[bucketindex]
        tomove = bucket[-1]
        if tomove != piece:
            bucket[bucketpos] = tomove
            self.place_in_buckets[tomove] = (bucketindex, bucketpos)
        del bucket[-1]
        while len(self.buckets) > 0 and len(self.buckets[-1]) == 0:
            del self.buckets[-1]
        return bucketindex

    # to be removed
    def bump(self, piece):
        bucketindex, bucketpos = self.place_in_buckets[piece]
        bucket = self.buckets[bucketindex]
        tomove = bucket[-1]
        if tomove != piece:
            bucket[bucketpos] = tomove
            self.place_in_buckets[tomove] = (bucketindex, bucketpos)
            bucket[-1] = piece
            self.place_in_buckets[piece] = (bucketindex, len(bucket)-1)

    def prepend_bucket(self):
        # it' possible we had everything to begin with
        if len(self.buckets) == 0:
            return
        self.buckets.insert(0, array.array(self.typecode))
        # bleh.
        for piece in self.place_in_buckets:
            index, pos = self.place_in_buckets[piece]
            self.place_in_buckets[piece] = (index + 1, pos)

    def popleft_bucket(self):
        # it' possible we had everything to begin with
        if len(self.buckets) == 0:
            return
        self.buckets.pop(0)
        # bleh.
        for piece in self.place_in_buckets:
            index, pos = self.place_in_buckets[piece]
            self.place_in_buckets[piece] = (index - 1, pos)

class PiecePicker(object):

    def __init__(self, config, numpieces, not_have):
        self.config = config
        self.numpieces = numpieces
        self.typecode = resolve_typecode(numpieces)
        self.piece_bucketss = [PieceBuckets(self.typecode)]
        self.scrambled = array.array(self.typecode)
        self.numgot = self.numpieces
        for i in not_have:
            self.scrambled.append(i)
            self.piece_bucketss[0].add(i, 0)
            self.numgot -= 1
        random.shuffle(self.scrambled)

    def get_distributed_copies(self):
        base = 0
        for i, bucket in enumerate(self.piece_bucketss[0].buckets):
            l = len(bucket)
            if l == 0:
                # the whole bucket is full. keep going
                continue
            base = i + 1
            # remove the fractional size of this bucket, and stop
            base -= (float(l) / float(self.numpieces))
            break
        return base

    def set_priority(self, pieces, priority):
        while len(self.piece_bucketss) <= priority:
            self.piece_bucketss.append(PieceBuckets(self.typecode))
        for piece in pieces:
            for p in self.piece_bucketss:
                if piece in p:
                    self.piece_bucketss[priority].add(piece, p.remove(piece))
                break
            else:
                assert False

    def got_have_all(self):
        for p in self.piece_bucketss:
            p.prepend_bucket()

    def got_have(self, piece):
        for p in self.piece_bucketss:
            if piece in p:
                p.add(piece, p.remove(piece) + 1)
                return

    def lost_have_all(self):
        for p in self.piece_bucketss:
            p.popleft_bucket()

    def lost_have(self, piece):
        for p in self.piece_bucketss:
            if piece in p:
                p.add(piece, p.remove(piece) - 1)
                return

    def complete(self, piece):
        self.numgot += 1
        if self.numgot < self.config['rarest_first_cutoff']:
            self.scrambled.remove(piece)
        else:
            self.scrambled = None
        for p in self.piece_bucketss:
            if piece in p:
                p.remove(piece)
                break
        else:
            assert False
       

    def from_behind(self, haves, bans):
        for piece_buckets in self.piece_bucketss:
            for i in xrange(len(piece_buckets.buckets) - 1, 0, -1):
                for j in piece_buckets.buckets[i]:
                    if haves[j] and j not in bans:
                        return j
        return None

    def next(self, haves, tiebreaks, bans, suggests):
        """returns next piece to download.
           @param haves: set of pieces the remote peer has.
           @param tiebreaks: pieces with active (started) requests
           @param bans: pieces not to pick.
           @param suggests: set of suggestions made by the remote peer.
        """
        # first few pieces are provided in random rather than rarest-first 
        if self.numgot < self.config['rarest_first_cutoff']:
            for i in itertools.chain(tiebreaks, self.scrambled):
                if haves[i] and i not in bans:
                    return i
            return None

        # from highest priority to lowest priority piece buckets...
        for k in xrange(len(self.piece_bucketss) - 1, -1, -1):

            piece_buckets = self.piece_bucketss[k]

            # Of the same priority, a suggestion is taken first.
            for s in suggests:
                if s not in bans and haves[s] and s in piece_buckets:
                     return s

            bestnum = None
            best = None
            rarity_of_started = [(piece_buckets.get_position(i), i)
                    for i in tiebreaks if i not in bans and haves[i] and
                                          i in piece_buckets]
            if rarity_of_started:
                bestnum = min(rarity_of_started)[0]  # smallest bucket index
                best = random.choice([j for (i, j) in rarity_of_started
                    if i == bestnum]) # random pick of those in smallest bkt
                                                      
            for i in xrange(1, len(piece_buckets.buckets)):
                if bestnum == i:      # if best of started is also rarest...
                    return best
                for j in piece_buckets.buckets[i]:
                    if haves[j] and j not in bans:
                        return j      # return first found.
        return None
    
    # to be removed
    def bump(self, piece):
        for p in self.piece_bucketss:
            if piece in p:
                p.bump(piece)
                break
        else:
            assert False
