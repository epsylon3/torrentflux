# by Greg Hazel

from BTL.sparse_set import SparseSet

class RequestManager(object):

    def __init__(self, request_size, piece_size, numpieces, total_length):
        self.request_size = request_size
        self.piece_size = piece_size
        self.numpieces = numpieces
        
        # hmm
        self.total_length = total_length

        self.amount_inactive = total_length
        self.endgame = False

        # If chunks have been requested then _inactive_requests has a list
        # of the unrequested chunks. Otherwise the piece is not in the dict.
        self.inactive_requests = {}

        # If chunks have been requested then active_requests has a list
        # of the unrequested chunks which are pending on the network.
        # Otherwise the piece is not in the dict.
        self.active_requests = {}

        # If all chunks have been requested and the piece is not written
        # to the disk, it is in this set. Equivalent to an empty list in
        # _inactive_requests.
        self.fully_active = set()

    def set_storage(self, storage):
        self.storage = storage

    def want_requests(self, index):
        # blah, this is dumb.
        if self.storage.have[index]:
            return False
        assert ((index in self.inactive_requests and
                 len(self.inactive_requests[index]) == 0) ==
                (index in self.fully_active))
        if index in self.fully_active:
            return False
        return True

    def iter_want(self):
        for index in self.storage.have_set.iterneg(0, self.numpieces):
            assert ((index in self.inactive_requests and
                     len(self.inactive_requests[index]) == 0) ==
                    (index in self.fully_active))
            if index in self.fully_active:
                continue
            yield index
    
    def _break_up(self, begin, length):
        l = []
        x = 0
        request_size = self.request_size
        while x + request_size < length:
            l.append((begin + x, request_size))
            x += request_size
        l.append((begin + x, length - x))
        return l

    def _piecelen(self, piece):
        if piece < self.numpieces - 1:
            return self.piece_size
        else:
            return self.total_length - piece * self.piece_size

    def _make_inactive(self, index):
        self.inactive_requests[index] = self._break_up(0, self._piecelen(index))
        self.active_requests[index] = []
        
    def new_request(self, index, full=False):
        # returns (begin, length)
        if index not in self.inactive_requests:
            self._make_inactive(index)
        rs = self.inactive_requests[index]
        if full:
            s = SparseSet()
            while rs:
                r = rs.pop()
                s.add(r[0], r[0] + r[1])
            b, e = s.largest_range()
            s.remove(b, e)
            reqs = self._break_up(b, e - b)
            for r in reqs:
                assert r[1] <= self.request_size
                self.active_requests[index].append(r)
            # I don't like this. the function should return reqs
            r = (b, e - b)
            for b, e in s.iterrange():
                rs.extend(self._break_up(b, e - b))
        else:
            # why min? do we want all the blocks in order?
            r = min(rs)
            rs.remove(r)
            assert r[1] <= self.request_size
            self.active_requests[index].append(r)
        self.amount_inactive -= r[1]
        assert self.amount_inactive >= 0, ('Amount inactive: %d' %
                                           self.amount_inactive)
        if len(rs) == 0:
            self.fully_active.add(index)
        if self.amount_inactive == 0:
            self.endgame = True
        assert (r[0] + r[1]) <= self._piecelen(index)
        return r

    def request_lost(self, index, begin, length):
        if len(self.inactive_requests[index]) == 0:
            self.fully_active.remove(index)
        self.amount_inactive += length
        r = (begin, length)
        self.active_requests[index].remove(r)
        self.inactive_requests[index].extend(self._break_up(*r))

    def request_received(self, index, begin, length):
        self.active_requests[index].remove((begin, length))

    def add_inactive(self, index, request_list):
        assert index not in self.inactive_requests
        assert index not in self.active_requests
        a = []
        for r in request_list:
            a.extend(self._break_up(*r))

        # amount_inactive does not include partials we've written to disk
        t = self._piecelen(index)
        for b, l in a:
            t -= l
        self.amount_inactive -= t
        
        self.inactive_requests[index] = a
        self.active_requests[index] = []
        
    def get_unwritten_requests(self):
        # collapse inactive and active requests into one set
        unwritten = {}
        for k, v in self.inactive_requests.iteritems():
            if v:
                unwritten.setdefault(k, []).extend(v)
        for k, v in self.active_requests.iteritems():
            if v:
                unwritten.setdefault(k, []).extend(v)
        return unwritten

    def is_piece_received(self, index):
        # hm.
        return (not self.want_requests(index) and
                len(self.active_requests[index]) == 0)

    def piece_finished(self, index):
        del self.inactive_requests[index]
        del self.active_requests[index]
        self.fully_active.remove(index)
 