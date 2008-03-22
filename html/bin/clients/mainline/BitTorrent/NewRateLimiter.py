# This was built to be like SFQ but turned out like round-robin.
# (Why didn't you just use Deficit Round Robin?) --Dave
# (Because of unitsize) --Greg
#
# I call it Heirarchical Round Robin Bucket Percentage Style
#
# by Greg Hazel

import time
import traceback
from BTL.platform import bttime
from BTL.DictWithLists import OrderedDictWithLists


# these are for logging and such
class GlobalRate(object):
    def __init__(self):
        self.total = 0.0    
        self.start_time = bttime()
        self.last_time = self.start_time

    def print_rate(self, size):
        self.total += size
        this_time = bttime()
        start_delta = this_time - self.start_time
        this_delta = this_time - self.last_time
        if start_delta > 0 and this_delta > 0:
            print "UPLOAD: This:", size / this_delta, "Total:", self.total / start_delta
        self.last_time = this_time


global_rate = GlobalRate()


# very simple.
# every call gives you the duration since the last call in tokens
class DeltaTokens(object):
    def __init__(self, rate):
        self.set_rate(rate)

    def set_rate(self, rate):        
        self.rate = rate
        # clear the history since the rate has changed and it could be way off
        self.last_time = bttime()

    # return the number of tokens you can have since the last call
    def __call__(self):
        new_time = bttime()
        delta_time = new_time - self.last_time
        # if last time was more than a second ago, we can't give a clear
        # approximation since rate is in tokens per second.
        delta_time = min(delta_time, 1.0)
        if delta_time <= 0:
            return 0
        tokens = self.rate * delta_time
        self.last_time = new_time
        return tokens

    # allows you to subtract tokens from DeltaTokens to compensate
    def remove_tokens(self, x):
        if self.rate == 0:
            # shit, I don't know.
            self.last_time += x
        else:
            self.last_time += x / self.rate

    # returns the time until you'll get tokens again
    def get_remaining_time(self):
        return max(0, self.last_time - bttime())


class Classifer(object):
    def __init__(self):
        self.channels = OrderedDictWithLists()

    def add_data(self, keyable, func):
        # hmm, this should rotate every 10 seconds or so, but moving over the
        # old data is hard (can't write out-of-order)
        #key = sha.sha(id(o)).hexdigest()[0]

        # this is technically round-robin
        key = keyable

        self.channels.push_to_row(key, func)

    def rem_data(self, key):
        try:
            l = self.channels.poprow(key)
            l.clear()
        except KeyError:
            pass

    def rotate_data(self):
        # the removes the top-most row from the ordereddict
        k = self.channels.iterkeys().next()
        l = self.channels.poprow(k)
        
        data = l.popleft()
        # this puts the whole row at the bottom of the ordereddict
        self.channels.setrow(k, l)
        
        return data
        
    def __len__(self):
        return len(self.channels)


class Scheduler(object):
    def __init__(self, rate, add_task):
        """@param rate: rate at which 'tokens' are generated. 
           @param add_task: callback to schedule an event.
        """
        self.add_task = add_task

        self.classifier = Classifer()
        self.delta_tokens = DeltaTokens(rate)
        self.task = None

        self.children = {}

    def set_rate(self, rate, cascade=True):
        self.delta_tokens.set_rate(rate)

        if cascade:        
            for child, scale in self.children.iteritems():
                child.set_rate(rate * scale)

        # the rate changed, so it's possible the loop is
        # running slower than it needs to
        self.restart_loop(0)

    def add_child(self, child, scale):
        self.children[child] = scale
        child.set_rate(self.delta_tokens.rate * scale)
        
    def remove_child(self, child):
        del self.children[child]

    def add_data(self, keyable, func):
        self.classifier.add_data(keyable, func)
        # kick off a loop since we have data now
        self.restart_loop(0)

    def restart_loop(self, t):
        # check for pending loop event
        if self.task and not self.task.called:
            ## look at when it's scheduled to occur
            # we can special case events which have a delta of 0, since they
            # should occur asap. no need to check the time.
            if self.task.delta == 0:
                return
            # use time.time since twisted does anyway
            s = self.task.getTime() - time.time()
            if s > t:
                # if it would occur after the time we want, reset it
                self.task.reset(t)
                self.task.delta = t
        else:
            if t == 0:
                # don't spin the event loop needlessly
                self.run()
            else:
                self.task = self.add_task(t, self.run)
                self.task.delta = t

    def _write(self, to_write):
        amount = 0
        each = min(self.delta_tokens.rate, self.unitsize)

        if self.children:
            for child, scale in self.children.iteritems():
                child.set_rate(self.delta_tokens.rate * scale, cascade=False)

            i = 0                
            while amount < to_write and len(self.classifier) > 0:

                (func, args) = self.classifier.rotate_data()
                # ERROR: func can fill buffers, so use the on_flush technique
                try:
                    amount += func(each)
                except:
                    # don't stop the loop if we hit an error
                    traceback.print_exc()
                i += 1
                if i == len(self.children):
                    break
                
            for child, scale in self.children.iteritems():
                # really max, but we happen to know it can't exceed amount
                child.set_rate(amount, cascade=False)

        while amount < to_write and len(self.classifier) > 0:

            func = self.classifier.rotate_data()
            # ERROR: func can fill buffers, so use the on_flush technique
            try:
                amount += func(each)
            except:
                # don't stop the loop if we hit an error
                traceback.print_exc()
            
        return amount

    def _run_once(self):
        f_to_write = self.delta_tokens()
        to_write = int(f_to_write)
        if to_write == 0:
            written = 0
        else:
            written = self._write(to_write)

            # for debugging
            #print "Ideal:", self.delta_tokens.rate, f_to_write
            #global_rate.print_rate(written)

        self.delta_tokens.remove_tokens(written - f_to_write)

        return written        

    def run(self):
        t = 0
        while t == 0:
            if len(self.classifier) == 0:
                return

            self._run_once()

            t = self.delta_tokens.get_remaining_time()
        self.restart_loop(t)

    
# made to look like the original
class MultiRateLimiter(Scheduler):

    # Since data is sent to peers in a round-robin fashion, max one
    # full request at a time, setting this higher would send more data
    # to peers that use request sizes larger than standard 16 KiB.
    # 17000 instead of 16384 to allow room for metadata messages.
    max_unitsize = 17000
    
    def __init__(self, sched, parent=None):
        Scheduler.__init__(self, rate = 0, add_task = sched)
        if parent == None:        
            self.run()

    def set_parameters(self, rate, unitsize=2**500):
        self.set_rate(rate)
        unitsize = min(unitsize, self.max_unitsize)
        self.unitsize = unitsize

    def queue(self, conn):
        keyable = conn
        self.add_data(keyable, conn.send_partial)
        
    def dequeue(self, keyable):
        self.classifier.rem_data(keyable)

    def increase_offset(self, bytes):
        # hackity hack hack
        self.delta_tokens.remove_tokens(0 - bytes)



class FakeConnection(object):
    def __init__(self, gr):
        self.gr = gr

    def _use_length_(self, length):
        def do():
            return length
        return self.write(do)

    def write(self, fn, *args):
        size = fn(*args)
        self.gr.print_rate(size)
        return size

 
if __name__ == '__main__':

    profile = True
    try:
        from BTL.profile import Profiler, Stats
        prof_file_name = 'NewRateLimiter.prof'
    except ImportError, e:
        print "profiling not available:", e
        profile = False

    import os
    import random

    from RawServer_twisted import RawServer
    from twisted.internet import task
    from BTL.defer import DeferredEvent

    rawserver = RawServer()

    s = Scheduler(4096, add_task = rawserver.add_task)
    s.unitsize = 17000

    a = []
    for i in xrange(500):
        keyable = FakeConnection(global_rate)
        a.append(keyable)

    freq = 0.01

    def push():
        if random.randint(0, 5 / freq) == 0:
            rate = random.randint(1, 100) * 1000
            print "new rate", rate
            s.set_rate(rate)
        for c in a:    
            s.add_data(c, c._use_length_)
    
    t = task.LoopingCall(push)
    t.start(freq)
    
##    m = MultiRateLimiter(sched=rawserver.add_task)
##    m.set_parameters(120000000)
##    class C(object):
##        def send_partial(self, size):
##            global_rate.print_rate(size)
##            rawserver.add_task(0, m.queue, self)
##            return size
##            
##    m.queue(C())

    if profile:
        try:
            os.unlink(prof_file_name)
        except:
            pass
        prof = Profiler()
        prof.enable()

    rawserver.listen_forever()

    if profile:
        prof.disable()
        st = Stats(prof.getstats())
        st.sort()
        f = open(prof_file_name, 'wb')
        st.dump(file=f)

