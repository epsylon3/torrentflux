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

## up
# p.add(piece, p.remove(piece) + 1)
## down
# p.add(piece, p.remove(piece) - 1)

from BTL.sparse_set import SparseSet

class PieceSetBuckets(object):
    """A PieceBuckets object is an array of arrays.  ith bucket contains
       pieces that have i known instances within the network.  Pieces
       within each bucket are randomly ordered."""
    def __init__(self):
        # [SparseSet(piece)]
        self.buckets = []
        # {piece: (bucket)}
        self.place_in_buckets = {}

    def get_position(self, piece):  # returns which bucket piece is in.
        return self.place_in_buckets[piece]

    def __contains__(self, piece):
        return piece in self.place_in_buckets

    def add(self, piece, bucketindex):
        assert not self.place_in_buckets.has_key(piece)
        while len(self.buckets) <= bucketindex:
            self.buckets.append(SparseSet())
        bucket = self.buckets[bucketindex]
        bucket.add(piece)
        self.place_in_buckets[piece] = bucketindex

    def remove(self, piece):
        bucketindex = self.place_in_buckets.pop(piece)
        bucket = self.buckets[bucketindex]
        bucket.subtract(piece)
        while len(self.buckets) > 0 and len(self.buckets[-1]) == 0:
            del self.buckets[-1]
        return bucketindex

    def prepend_bucket(self):
        # it' possible we had everything to begin with
        if len(self.buckets) == 0:
            return
        self.buckets.insert(0, SparseSet())
        # bleh.
        for piece in self.place_in_buckets:
            self.place_in_buckets[piece] += 1

    def popleft_bucket(self):
        # it' possible we had everything to begin with
        if len(self.buckets) == 0:
            return 
        self.buckets.pop(0)
        # bleh.
        for piece in self.place_in_buckets:
            self.place_in_buckets[piece] -= 1


import array
import bisect

def resolve_typecode(n):
    if n < 32768:
        return 'h'
    return 'l'

class SortedPieceBuckets(object):
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
        newspot = bisect.bisect_right(bucket, piece)
        bucket.insert(newspot, piece)
        self.place_in_buckets[piece] = (bucketindex, newspot)

    def remove(self, piece):
        bucketindex, bucketpos = self.place_in_buckets.pop(piece)
        bucket = self.buckets[bucketindex]
        newspot = bisect.bisect_left(bucket, piece)
        del bucket[newspot]
        while len(self.buckets) > 0 and len(self.buckets[-1]) == 0:
            del self.buckets[-1]
        return bucketindex
    
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
