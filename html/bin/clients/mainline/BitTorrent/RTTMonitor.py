# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# By Greg Hazel and a smigden by Dave Harrison

debug = False

import os
import Queue
import socket
import random
from pprint import pprint
from BTL.platform import bttime
import BTL.stackthreading as threading
from BTL.HostIP import get_host_ip, get_host_ips
from BTL.exceptions import str_exc

if os.name == 'nt':
    from BTL import win32icmp

def daemon_thread(target, args=()):
    t = threading.Thread(target=target, args=args)
    t.setDaemon(True)
    return t

def izip_some(p, *a):
    for i in a:
        if len(i) > p:
            yield i[p]

def izip_any(*a):
    m = max([len(i) for i in a])
    for x in xrange(m):
        yield izip_some(x, *a)
    
def in_common(routes):
    """routes is a list of lists, each containing a route to a peer."""
    r = []
    branch = False
    for n in izip_any(*routes): #itertools.izip(*routes):

        # strip dead nodes
        f = [i for i in n if i != '*']

        # ignore all dead nodes
        if len(f) == 0:
            continue

        c = [ (f.count(x), x) for x in f ]
        c.sort()
        if debug:
            pprint(c)
        top = c[-1][0]
        # majority wins
        if top > 2 and top > (len(f) * 0.50):
            f = [c[-1][1]]
        
        if len(set(f)) == 1:
            r.append(f[0])
        else:
            # more than one unique node, the tree has branched
            branch = True
            break
    return (branch, r)


class RTTMonitorBase(object):
    def __init__(self, new_rtt=None):
        self.instantanious_rtt = None
        self.min_rtt = None
        self.max_rtt = None
        def f(rtt):
            pass
        if new_rtt:
            self.new_rtt = new_rtt
        else:
            self.new_rtt = f

    def set_nodes_restart(self, nodes):
        pass

    def get_min_rtt(self):
        return self.min_rtt

    def get_max_rtt(self):
        return self.max_rtt

    def get_current_rtt(self):
        return self.instantanious_rtt


class RTTMonitorUnix(RTTMonitorBase):
    # I assume this will have a unix implementation some day
    pass


def _set_min(x, y):
    if x is None:
        return y
    if y is None:
        return x
    return min(x, y)
_set_max = max


class RTTMonitorWin32(RTTMonitorBase):

    def __init__(self, new_rtt, interval = 0.5, timeout = 6.0):
        self.timeout = int(1000 * timeout)
        self.interval = interval
        self.stop_event = threading.Event()
        self.abort_traceroute = threading.Event()
        self.finished_event = threading.Event()
        # the thread is finished because it hasn't started
        self.finished_event.set()

        RTTMonitorBase.__init__(self, new_rtt)

    def set_nodes_restart(self, nodes):
        if len(nodes) > 10:
            nodes = random.sample(nodes, 10)
        else:
            nodes = list(nodes)
        t = threading.Thread(target=self.run, args=(nodes,))
        t.setDaemon(True)
        t.start()

    def get_route(self, q, dst):
        try:
            dst = socket.gethostbyname(dst)
            self.traceroute(dst, self.timeout, lambda n : q.put((dst, n)))
        except socket.gaierror:
            # if hostbyname lookup fails, it's not a node we can use.
            # maybe this should be a warning or something, but a downed
            # internet connection will cause a lot of these
            pass

    def run(self, nodes):
        
        q = Queue.Queue()

        dst = None
        # handy for hard-coding common node
        #dst = '68.87.195.50'; nodes = [dst,]; common = nodes
        if not dst: 
            threads = []
            for i in nodes:
                t = daemon_thread(target=self.get_route, args=(q, i, ))
                threads.append(t)
                t.start()

            waiter_done_event = threading.Event()
            def waiter(threads, waiter_done_event):
                try:
                    for thread in threads:
                        thread.join()   # blocks until thread terminates.
                except Exception, e:
                    print "waiter hiccupped", e
                waiter_done_event.set()
            waiting_thread = daemon_thread(target=waiter,
                                           args=(threads, waiter_done_event, ))
            waiting_thread.start()

            common = []
            routes = {}
            while not waiter_done_event.isSet():
                try:
                    msg = q.get(True, 1.0)
                except Queue.Empty:
                    pass
                else:
                    dst = msg[0]
                    # nodes appear in the queue in 
                    # increasing order of TTL.
                    new_node = msg[1]
                    routes.setdefault(dst, []).append(new_node)
                    branch, common = in_common(routes.values())
                    if branch:
                        break

            self.abort_traceroute.set()
            waiter_done_event.wait()
            self.abort_traceroute.clear()

            local_ips = get_host_ips()
            new_common = []
            for c in common:
                if c not in local_ips:
                    new_common.append(c)
            common = new_common
            
            if debug:
                pprint(common)

            if len(common) == 0:
                # this should be inspected, it's not a simple debug message
                if debug:
                    print "No common node"
                    pprint(routes)
                return

            del routes

            dst = common[-1]

        # kill the old thread
        self.stop_event.set()
        # wait for it to finish
        self.finished_event.wait()
        # clear to indicate we're running
        self.finished_event.clear()
        self.stop_event.clear()
        
        if debug:
            print "Farthest common hop [%s]" % dst

        # range can change if the node in common changes
        self.min_rtt = None
        self.max_rtt = None

        # Ping a representative peer but set the ttl to the length of the
        # common path so that the farthest common hop responds with
        # ICMP time exceeded.  (Some routers will send time exceeded 
        # messages, but they won't respond to ICMP pings directly)  
        representative = random.sample(nodes, 1)[0]
        if debug: 
            print ("pinging representative %s ttl=%d" %
                   (representative, len(common)))
        try:            
            while not self.stop_event.isSet():
                start = bttime()
                rtt = self.ping(representative, 5000, ttl=len(common))
                self.instantanious_rtt = rtt

                self.min_rtt = _set_min(self.min_rtt, rtt)
                self.max_rtt = _set_max(self.max_rtt, rtt)

                delta = bttime() - start
                self.stop_event.wait(self.interval - delta)
                if debug: print "RTTMonitor.py: new_rtt %s" % rtt
                self.new_rtt(self.instantanious_rtt)
        except Exception, e:
            import traceback
            traceback.print_exc()
            print "ABORTING", e
            
        self.finished_event.set()

    def traceroute(self, dst, timeout, report=None):
        """If report is None then this returns the route as a list of IP
           addresses.  If report is not None then this calls report as each
           node is discovered in the path (e.g., if there are 6 hops in the
           path then report gets called 6 times)."""

        if debug:
            print "Tracing route to [%s]" % dst

        i = win32icmp.IcmpCreateFile()

        o = win32icmp.Options()

        route = None
        if report == None:
            route = []
            def add_node(node):
                route.append(node)
            report = add_node

        for ttl in xrange(64):
            o.Ttl = ttl
            try:
                if ttl == 0:
                    addr = get_host_ip()
                    status = -1
                    rtt = 0
                else:
                    addr, status, rtt = win32icmp.IcmpSendEcho(i, dst, None, o,
                                                               timeout)
                if debug:
                    print "ttl", ttl, "\t", rtt, "ms\t", addr
                report(addr)
                if status == win32icmp.IP_SUCCESS:
                    if debug:
                        print "Traceroute complete in", ttl, "hops"
                    break
            except Exception, e:
                report('*')
                if debug:
                    print "Hop", ttl, "failed:", str_exc(e)
            if self.abort_traceroute.isSet():
                break

        win32icmp.IcmpCloseHandle(i)

        if route:
            return route

    def ping(self, dst, timeout, ttl = None):
        """Returns ICMP echo round-trip time to dst or returns None if a
           timeout occurs.  timeout is measured in milliseconds. 
          
           The TTL is useful if the caller wants to ping the router that 
           is a number of hops in the direction of the dst, e.g., when a 
           router will not respond to pings directly but will generate 
           ICMP Time Exceeded messages."""
        i = win32icmp.IcmpCreateFile()
        rtt = None

        try:
            o = None            
            if ttl is not None:
                o = win32icmp.Options()
                o.Ttl = ttl

            addr, status, rtt = win32icmp.IcmpSendEcho(i, dst, None, o, timeout)
            if debug:
                if status == win32icmp.IP_SUCCESS:
                    print "Reply from", addr + ":", "time=" + str(rtt)
                elif status == win32icmp.IP_TTL_EXPIRED_TRANSIT:
                    print "Ping ttl expired %d: from %s time=%s" %(
                          status, str(addr), str(rtt))
                else:
                    print "Ping failed", win32icmp.status[status]
        except KeyboardInterrupt:
            raise
        except Exception, e:
            if debug:
                print "Ping failed:", str_exc(e)

        win32icmp.IcmpCloseHandle(i)

        return rtt

if os.name == 'nt':
    RTTMonitor = RTTMonitorWin32
else:
    RTTMonitor = RTTMonitorUnix
