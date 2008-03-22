# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# By Greg Hazel and David Harrison

debug = False
#debug = True

import os
import Queue
import socket
import itertools
import random
from pprint import pprint
from BTL.platform import bttime
import BTL.stackthreading as threading
from BTL.HostIP import get_host_ip, get_host_ips
from BitTorrent.platform import spawn, app_root
#from twisted.web.xmlrpc import Proxy

if os.name == 'nt':
    import win32icmp
IP_TTL_EXPIRED_TRANSIT = 11013
IP_SUCCESS = 0

def daemon_thread(target, args=()):
    t = threading.Thread(target=target, args=args)
    t.setDaemon(True)
    return t

def in_common(routes):
    """routes is a list of lists, each containing a route to a peer."""
    # Greg: This is a little weird.  Nodes appear in the queue in
    # increasing order of TTL among nodes in the path to
    # a given IP.  However, there is no guarantee of ordering
    # between IP's.  As a result, a closer branching
    # may be missed if the traceroute following that branch
    # is delayed longer than the traceroutes to other IPs.
    #   --Dave
    r = []
    branch = False
    for n in itertools.izip(*routes):

        # strip dead nodes
        f = [i for i in n if i !='*']

        # ignore all dead nodes
        if len(f) == 0:
            continue
        
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
        def f(rtt):
            pass
        if new_rtt:
            self.new_rtt = new_rtt
        else:
            self.new_rtt = f

    def set_nodes_restart(self, nodes):
        pass

    def get_current_rtt(self):
        return self.instantanious_rtt


# someday I'll write this using twisted.  --D. Harrison
#class RTTMonitorUnix(RTTMonitorBase):    

import xmlrpclib   # blech. Better with twisted, but RTTMonitorWin32
                   # was already written to handle blocking Icmp calls.

class Icmp(object):
    """Implements ICMP."""
    def create_file(self):
        return 0

    def ping(self, fid, addr, ttl, timeout):
        # returns addr, status, rtt.
        pass

    def close(self,fid):
        pass

class UnixIcmp(Icmp):
    def __init__(self, external_add_task, port):
        assert callable(external_add_task)  # rawserver's 
        assert type(port) in (int,long) and port > 0 and port <= 65535
        #pid = os.spawnl(os.P_NOWAIT, "xicmp", str(port))
        print "Spawning xicmp on port ", port
        xicmp = os.path.join( app_root, "icmp", "xicmp" )
        spawn( xicmp, str(port) )
        def _start_proxy(port):
            self.proxy = xmlrpclib.ServerProxy('http://localhost:%d' % port)
        external_add_task(4.0, _start_proxy, port)  # allow time to spawn.

    def create_file(self):
        return self.proxy.IcmpCreateFile()

    def ping(self, fid, addr, ttl, timeout):
        try:
            return self.proxy.IcmpSendEcho( fid, addr, ttl, timeout )
        except xmlrpclib.Fault:
            return None

    def close(self,fid):
        #print "UnixIcmp: close: fid=", fid
        return self.proxy.IcmpCloseHandle( fid )

class Options(object):
    pass

class Win32Icmp(Icmp):
    def __init__(self):
        self.o = Options()

    def create_file(self):
        i = win32icmp.IcmpCreateFile()

    def ping(self, fid, addr, ttl, timeout):
        self.o.Ttl = ttl
        return win32icmp.IcmpSendEcho(fid, addr, None, self.o, timeout)

    def close(self,fid):
        win32icmp.IcmpCloseHandle(fid)
    
def _set_min(x, y):
    if x is None:
        return y
    if y is None:
        return x
    return min(x, y)

_set_max = max


#class RTTMonitorWin32(RTTMonitorBase):
class RTTMonitorBlocking(RTTMonitorBase):
    def __init__(self, new_rtt, icmp, interval = 0.5, timeout = 6.0 ):
        """
          @param new_rtt called every time a ping arrives.
          @param icmp is the ICMP implementation.
          @param timeout (currently uses a static timeout threshold)
        """
        assert callable(new_rtt)
        assert isinstance(icmp, Icmp)

        self.icmp = icmp
        self.nodes = []
        self.timeout = int(1000 * timeout)  # in ms.
        self.interval = interval
        self.stop_event = threading.Event()
        self.abort_traceroute = threading.Event()
        self.finished_event = threading.Event()
        # the thread is finished because it hasn't started
        self.finished_event.set()

        RTTMonitorBase.__init__(self, new_rtt)

    def set_nodes_restart(self, nodes):
        if debug:
            pprint( "set_nodes_restart: nodes=%s" % str(nodes))
        self.nodes = []
        for node in nodes:
            self.add_node(node)
        t = threading.Thread(target=self.run, args=(list(self.nodes),))
        t.setDaemon(True)
        t.start()

    def add_node(self, node):
        self.nodes.append(node)

    def get_route(self, q, dst):
        try:
            dst = socket.gethostbyname(dst)
            self.traceroute(dst, self.timeout, lambda n : q.put((dst, n)))
        except socket.gaierror:
            # if hostbyname lookup fails, it's not a node we can use
            # maybe this should be a warning or something, but a downed
            # internet connection will cause a lot of these
            pass

    def run(self, nodes):
        
        q = Queue.Queue()

        dst = None
        # handy for hard-coding common node
        #dst = '68.87.195.50'
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
            #print "tracerouting..."
            hop_check = 0    # distance (in hops) being checked for branch.
            hop_cnt = {}     # number responses received at the given distance.
            farthest_possible = 1000 # farthest distance possible as
                                     # determined by the shortest number of 
                                     # hops to a node in the passed nodes.
            branch = False
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
                    if dst not in routes:
                        l = []
                        routes[dst] = l
                    else:
                        l = routes[dst]
                    l.append(new_node)
                    #print "calling in_common with ", routes.values()

                    # BEGIN replaces in_common
                    #hops_so_far = len(routes[dst])
                    ## It is not possible for the common path to be longer
                    ## than the closest node.
                    #if dst == new_node and hops_so_far < farthest_possible:
                    #    farthest_possible = hops_so_far
                    #if hop_cnt.has_key(hops_so_far):
                    #    hop_cnt[hops_so_far] += 1
                    #else:
                    #    hop_cnt[hops_so_far] = 1
                    #
                    #if hops_so_far == hop_check:
                    #    # if got all pings for a given distance then see if
                    #    # there is a branch.
                    #    while hop_cnt[hop_check] == len(nodes) and \
                    #          hop_check <= farthest_possible:
                    #        n = None
                    #        for r in routes:
                    #            if n is not None and n != routes[d]:
                    #                branch = True
                    #                break
                    #            if routes[hop_check] != '*':
                    #                n = routes[hop_check]
                    #        else:
                    #            common.append(n)
                    #        hop_check += 1
                    #    if hop_check > farthest_possible:
                    #        branch = True 
                    ## END        
                    branch, common = in_common(routes.values())
                    if branch:
                        break

            #print "done tracerouting..."
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
                    print "No common node", routes
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

        # Ping a representative peer but set the ttl to the length of the
        # common path so that the farthest common hop responds with
        # ICMP time exceeded.  (Some routers will send time exceeded 
        # messages, but they won't respond to ICMP pings directly)  
        representative = nodes[random.randrange(0, len(nodes))]
        if debug: 
            print "pinging representative %s ttl=%d" % (
                representative,len(common))
        try:            
            while not self.stop_event.isSet():
                start = bttime()
                rtt = self.ping(representative, 5000, ttl=len(common))
                self.instantanious_rtt = rtt

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

        i = self.icmp.create_file()

        o = Options()

        route = None
        if report == None:
            route = []
            def add_node(node):
                route.append(node)
            report = add_node

        for ttl in xrange(64):
            try:
                if ttl == 0:
                    addr = get_host_ip()
                    status = -1
                    rtt = 0
                else:
                    addr, status, rtt = self.icmp.ping(i,dst,ttl,timeout)
                if debug:
                    print "ttl", ttl, "\t", rtt, "ms\t", addr
                report(addr)
                if status == IP_SUCCESS:
                    if debug:
                        print "Traceroute complete in", ttl, "hops"
                    break
            except Exception, e:
                report('*')
                if debug:
                    print "Hop", ttl, "failed:", str(e)
            if self.abort_traceroute.isSet():
                break

        self.icmp.close(i)

        if route:
            return route

    def ping(self, dst, timeout, ttl = None):
        """Returns ICMP echo round-trip time to dst or returns None if a
           timeout occurs.  timeout is measured in milliseconds. 
          
           The TTL is useful if the caller wants to ping the router that 
           is a number of hops in the direction of the dst, e.g., when a 
           router will not respond to pings directly but will generate 
           ICMP Time Exceeded messages."""
        i = self.icmp.create_file()
        rtt = None
        try:
            addr, status, rtt = self.icmp.ping(i, dst, ttl, timeout)
            if debug:
                if status == IP_SUCCESS:
                    print "Reply from", addr + ":", "time=" + str(rtt)
                elif status == IP_TTL_EXPIRED_TRANSIT:
                    print "Ping ttl expired %d: from %s time=%s" %(
                          status, str(addr), str(rtt))
                else:
                    print "Ping failed", status
        except Exception, e:
            if debug:
                print "Ping failed:", str(e)

        self.icmp.close(i)

        return rtt

#if os.name == 'nt':
#    RTTMonitor = RTTMonitorWin32
#else:
#    RTTMonitor = RTTMonitorUnix
RTTMonitor = RTTMonitorBlocking
