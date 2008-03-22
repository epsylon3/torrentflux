# usage:
#
# from twisted.internet import reactor
# from ConnectionRateLimitReactor import connectionRateLimitReactor
# connectionRateLimitReactor(reactor, max_incomplete=10)
#
# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# by Greg Hazel

import random
import threading
from twisted.python import failure
from twisted.python import threadable
from twisted.internet import error, address, abstract
from BTL.circular_list import CircularList
from BTL.Lists import QList
from BTL.decorate import decorate_func

debug = False


class HookedFactory(object):
    
    def __init__(self, connector, factory):
        self.connector = connector
        self.factory = factory

    def clientConnectionFailed(self, connector, reason):
        if self.connector._started:
            self.connector.complete()
        return self.factory.clientConnectionFailed(connector, reason)

    def buildProtocol(self, addr):
        p = self.factory.buildProtocol(addr)
        p.connectionMade = decorate_func(self.connector.complete,
                                         p.connectionMade)
        return p

    def __getattr__(self, attr):
        return getattr(self.factory, attr)
    

class IRobotConnector(object):
    # I did this to be nice, but zope sucks.
    ##implements(interfaces.IConnector)

    def __init__(self, reactor, protocol, host, port, factory, owner, urgent,
                 *a, **kw):
        self.reactor = reactor
        self.protocol = protocol
        assert self.protocol in ('INET', 'SSL')
        self.host = host
        self.port = port
        self.owner = owner
        self.urgent = urgent
        self.a = a
        self.kw = kw
        self.connector = None
        self._started = False
        self.preempted = False

        self.factory = HookedFactory(self, factory)

    def started(self):
        if self._started:
            raise ValueError("Connector is already started!")
        self._started = True
        self.reactor.add_pending_connection(self.host, self)
        
    def disconnect(self):
        if self._started:
            return self.connector.disconnect()
        return self.stopConnecting()

    def _cleanup(self):
        if hasattr(self, 'a'):
            del self.a
        if hasattr(self, 'kw'):
            del self.kw
        if hasattr(self, 'factory'):
            del self.factory
        if hasattr(self, 'connector'):
            del self.connector
        
    def stopConnecting(self):
        if self._started:
            self.connector.stopConnecting()
            self._cleanup()
            return            
        self.reactor.drop_postponed(self)
        # for accuracy
        self.factory.startedConnecting(self)
        abort = failure.Failure(error.UserError(string="Connection preempted"))
        self.factory.clientConnectionFailed(self, abort)
        self._cleanup()
            
    def connect(self):
        if debug: print 'connecting', self.host, self.port
        self.started()
        try:
            if self.protocol == 'SSL':
                self.connector = self.reactor.old_connectSSL(self.host,
                                                             self.port,
                                                             self.factory,
                                                             *self.a, **self.kw)
            else:
                self.connector = self.reactor.old_connectTCP(self.host,
                                                             self.port,
                                                             self.factory,
                                                             *self.a, **self.kw)
            # because other callbacks use this one
            self.connector.wasPreempted = self.wasPreempted
        except:
            # make sure failures get removed before we raise
            self.complete()
            raise
        # if connect is re-called on the connector, we want to restart
        self.connector.connect = decorate_func(self.started,
                                               self.connector.connect)
        return self

    def wasPreempted(self):
        return self.preempted

    def complete(self):
        if not self._started:
            return
        self._started = False
        self.reactor._remove_pending_connection(self.host, self)
        self._cleanup()

    def getDestination(self):
        return address.IPv4Address('TCP', self.host, self.port, self.protocol)


class Postponed(CircularList):

    def __init__(self):
        CircularList.__init__(self)
        self.it = iter(self)
        self.preempt = QList()
        self.cm_to_list = {}

    def __len__(self):
        l = 0
        for k, v in self.cm_to_list.iteritems():
            l += len(v)
        l += len(self.preempt)
        return l

    def append_preempt(self, c):
        return self.preempt.append(c)
    
    def add_connection(self, keyable, c):
        if keyable not in self.cm_to_list:
            self.cm_to_list[keyable] = QList()
            self.prepend(keyable)
        self.cm_to_list[keyable].append(c)

    def pop_connection(self):
        if self.preempt:
            return self.preempt.popleft()
        keyable = self.it.next()
        l = self.cm_to_list[keyable]
        c = l.popleft()
        if len(l) == 0:
            self.remove(keyable)
            del self.cm_to_list[keyable]
        return c

    def remove_connection(self, keyable, c):
        # hmmm
        if c.urgent:
            self.preempt.remove(c)
            return
        l = self.cm_to_list[keyable]
        l.remove(c)
        if len(l) == 0:
            self.remove(keyable)
            del self.cm_to_list[keyable]


class ConnectionRateLimiter(object):
    
    def __init__(self, reactor, max_incomplete):
        self.reactor = reactor
        self.postponed = Postponed()
        self.max_incomplete = max_incomplete
        # this can go away when urllib does
        self.halfopen_hosts_lock = threading.RLock()
        self.halfopen_hosts = {}
        self.old_connectTCP = self.reactor.connectTCP
        self.old_connectSSL = self.reactor.connectSSL

        if debug:
            from twisted.internet import task
            def p():
                print len(self.postponed), [ (k, len(v)) for k, v in self.halfopen_hosts.iteritems() ]
                assert len(self.halfopen_hosts) <= self.max_incomplete
            task.LoopingCall(p).start(1)   

    # safe from any thread  
    def add_pending_connection(self, host, connector=None):
        if debug: print 'adding', host, 'IOthread', threadable.isInIOThread()
        self.halfopen_hosts_lock.acquire()
        self.halfopen_hosts.setdefault(host, []).append(connector)
        self.halfopen_hosts_lock.release()

    # thread footwork, because _remove actually starts new connections
    def remove_pending_connection(self, host, connector=None):
        if not threadable.isInIOThread():
            self.reactor.callFromThread(self._remove_pending_connection,
                                        host, connector)
        else:
            self._remove_pending_connection(host, connector)

    def _remove_pending_connection(self, host, connector=None):
        if debug: print 'removing', host
        self.halfopen_hosts_lock.acquire()
        self.halfopen_hosts[host].remove(connector)
        if len(self.halfopen_hosts[host]) == 0:
            del self.halfopen_hosts[host]
            self._push_new_connections()
        self.halfopen_hosts_lock.release()

    def _push_new_connections(self):
        if not self.postponed:
            return
        c = self.postponed.pop_connection()
        self._connect(c)

    def drop_postponed(self, c):
        self.postponed.remove_connection(c.owner, c)

    def _preempt_for(self, c):
        if debug: print '\npreempting for', c.host, c.port, '\n'
        self.postponed.append_preempt(c)
            
        sorted = []

        for connectors in self.halfopen_hosts.itervalues():

            # drop hosts with connectors that have no handle (urllib)
            # drop hosts with any urgent connectors
            can_preempt = True
            for s in connectors:
                if not s or s.urgent:
                    can_preempt = False
                    break
            if not can_preempt:
                continue
            
            sorted.append((len(connectors), connectors))

        if len(sorted) == 0:
            # give up. no hosts can be interrupted
            return

        # find the host with least connectors to interrupt            
        sorted.sort()
        connectors = sorted[0][1]
                
        for s in connectors:
            s.preempted = True
            if debug: print 'preempting', s.host, s.port
            s.disconnect()
        
    def _resolve_then_connect(self, c):
        if abstract.isIPAddress(c.host):
            self._connect(c)
            return c
        df = self.reactor.resolve(c.host)
        if debug: print 'resolving', c.host
        def set_host(ip):
            if debug: print 'resolved', c.host, ip
            c.host = ip
            self._connect(c)
        def error(f):
            # too lazy to figure out how to fail properly, so just connect
            self._connect(c)
        df.addCallbacks(set_host, error)
        return c

    def _connect(self, c):
        # the XP connection rate limiting is unique at the IP level
        if (len(self.halfopen_hosts) >= self.max_incomplete and
            c.host not in self.halfopen_hosts):
            if debug: print 'postponing', c.host, c.port
            if c.urgent:
                self._preempt_for(c)
            else:
                self.postponed.add_connection(c.owner, c)
        else:
            c.connect()
        return c

    def connectTCP(self, host, port, factory,
                   timeout=30, bindAddress=None, owner=None, urgent=True):
        c = IRobotConnector(self, 'INET', host, port, factory, owner, urgent,
                            timeout, bindAddress)
        self._resolve_then_connect(c)
        return c

    def connectSSL(self, host, port, factory, contextFactory,
                   timeout=30, bindAddress=None, owner=None, urgent=True):
        c = IRobotConnector(self, 'SSL', host, port, factory, owner, urgent,
                            contextFactory, timeout, bindAddress)
        self._resolve_then_connect(c)
        return c



def connectionRateLimitReactor(reactor, max_incomplete):
    if (hasattr(reactor, 'limiter') and
        reactor.limiter.max_incomplete != max_incomplete):
        print 'Changing max_incomplete for ConnectionRateLimiterReactor!'
        reactor.limiter.max_incomplete = max_incomplete
    else:    
        limiter = ConnectionRateLimiter(reactor, max_incomplete)
        reactor.connectTCP = limiter.connectTCP
        reactor.connectSSL = limiter.connectSSL
        reactor.add_pending_connection = limiter.add_pending_connection
        reactor.remove_pending_connection = limiter.remove_pending_connection
        reactor.limiter = limiter
