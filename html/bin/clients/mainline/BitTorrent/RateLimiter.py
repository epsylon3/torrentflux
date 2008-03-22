# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Uoti Urpala and Andrew Loewenstern

from BTL.platform import bttime

def minctx(a,b):
    A = B = 0
    if a.rate > 0:
        A = a.offset_amount / a.rate
    if b.rate > 0:
        B = b.offset_amount / b.rate
    if A <= B:
            return a
    return b

class Dummy(object):
    def __init__(self, next):
        self.next_upload = next
    def send_partial(self, size):
        return 0
    closed = False

class RateLimitedGroup(object):
    def __init__(self, rate, got_exception):
        self.got_exception = got_exception
        # limiting
        self.check_time = 0
        self.lasttime = bttime()
        self.offset_amount = 0
        self.set_rate(rate)
        # accounting
        self.count = 0
        self.counts = []
        
    def set_rate(self, new_rate):
        self.rate = new_rate
        self.check_time = 0
        self.offset_amount = 0
        
class MultiRateLimiter(object):
    def __init__(self, sched):
        self.sched = sched
        self.last = None
        self.upload_rate = 0
        self.unitsize = 17000
        self.offset_amount = 0
        self.ctxs = [] # list of contexts with connections in the queue
        self.ctx_counts = {} # dict conn -> how many connections each context has
        
    def set_parameters(self, rate, unitsize):
        if unitsize > 17000:
            # Since data is sent to peers in a round-robin fashion, max one
            # full request at a time, setting this higher would send more data
            # to peers that use request sizes larger than standard 16 KiB.
            # 17000 instead of 16384 to allow room for metadata messages.
            unitsize = 17000
        self.upload_rate = rate
        self.unitsize = unitsize
        self.lasttime = bttime()
        self.offset_amount = 0

    def queue(self, conn, ctx):
        assert conn.next_upload is None
        if ctx not in self.ctxs:
            ctx.check_time = 1
            self.ctxs.append(ctx)
            self.ctx_counts[ctx] = 1
        else:
            self.ctx_counts[ctx] += 1

        if self.last is None:
            self.last = conn
            conn.next_upload = conn
            self.try_send(True)
        else:
            conn.next_upload = self.last.next_upload
            self.last.next_upload = conn
            self.last = conn

    def increase_offset(self, bytes):
        self.offset_amount += bytes

    def try_send(self, check_time = False):
        t = bttime()
        cur = self.last.next_upload

        if self.upload_rate > 0:
            self.offset_amount -= (t - self.lasttime) * self.upload_rate
            if check_time:
                self.offset_amount = max(self.offset_amount, -1 * self.unitsize)
        else:
            self.offset_amount = 0

        self.lasttime = t

        for ctx in self.ctxs:
            if ctx.rate == 0:
                ctx.offset_amount = 0
                ctx.lasttime = t                
            elif ctx.lasttime != t:
                ctx.offset_amount -=(t - ctx.lasttime) * ctx.rate
                ctx.lasttime = t
                if ctx.check_time:
                    ctx.offset_amount = max(ctx.offset_amount, -1 * self.unitsize)

        min_offset = reduce(minctx, self.ctxs)
        ctx = cur.connection_manager.context.rlgroup
        while self.offset_amount <= 0 and min_offset.offset_amount <= 0:
            if ctx.offset_amount <= 0:
                try:
                    bytes = cur.send_partial(self.unitsize)
                except KeyboardInterrupt:
                    raise
                except Exception, e:
                    cur.connection_manager.context.rlgroup.got_exception(e)
                    cur = self.last.next_upload
                    bytes = 0

                if self.upload_rate > 0:
                    self.offset_amount += bytes
                if ctx.rate > 0:
                    ctx.offset_amount += bytes

                ctx.count += bytes
                
                if bytes == 0 or not cur.connection.is_flushed():
                    if self.last is cur:
                        self.last = None
                        cur.next_upload = None
                        self.ctx_counts = {}
                        self.ctxs = []
                        break
                    else:
                        self.last.next_upload = cur.next_upload
                        cur.next_upload = None
                        old = ctx
                        cur = self.last.next_upload
                        ctx = cur.connection_manager.context.rlgroup
                        self.ctx_counts[old] -= 1
                        if self.ctx_counts[old] == 0:
                            del(self.ctx_counts[old])
                            self.ctxs.remove(old)
                        if min_offset == old:
                            min_offset = reduce(minctx, self.ctxs)
                else:
                    if ctx == min_offset:
                        min_offset = reduce(minctx, self.ctxs)
                    self.last = cur
                    cur = cur.next_upload
                    ctx = cur.connection_manager.context.rlgroup
            else:
                self.last = cur
                cur = self.last.next_upload
                ctx = cur.connection_manager.context.rlgroup
        else:
            myDelay = minCtxDelay = 0
            if self.upload_rate > 0:
                myDelay = 1.0 * self.offset_amount / self.upload_rate
            if min_offset.rate > 0:
                minCtxDelay = 1.0 * min_offset.offset_amount / min_offset.rate
            delay = max(myDelay, minCtxDelay)
            self.sched(self.try_send, delay)


    def clean_closed(self):
        if self.last is None:
            return
        orig = self.last
        if self.last.closed:
            self.last = Dummy(self.last.next_upload)
            self.last.connection_manager = orig.connection_manager
        c = self.last
        while True:
            if c.next_upload is orig:
                c.next_upload = self.last
                break
            if c.next_upload.closed:
                o = c.next_upload
                c.next_upload = Dummy(c.next_upload.next_upload)
                c.next_upload.connection_manager = o.connection_manager
            c = c.next_upload

