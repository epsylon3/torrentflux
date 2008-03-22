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

from __future__ import division

import sys
from BTL.platform import app_name
from BTL.translation import _
from BitTorrent import BTFailure
from BTL.obsoletepythonsupport import *
from BTL.hash import sha
from BitTorrent.RawServer_twisted import Handler
from BitTorrent.Connector import Connector
from BitTorrent.HTTPConnector import HTTPConnector
from BitTorrent.LocalDiscovery import LocalDiscovery
from BitTorrent.InternetWatcher import InternetSubscriber
from BTL.DictWithLists import DictWithInts, OrderedDict
from BTL.platform import bttime
from BTL.rand_tools import iter_rand_pos
import random
import logging
import urlparse

ONLY_LOCAL = False

GLOBAL_FILTER = None
def GLOBAL_FILTER(ip, port, direction=""):
    #print ip, direction
    return False
GLOBAL_FILTER = None

# header, reserved, download id, my id, [length, message]

LOWER_BOUND = 1
UPPER_BOUND = 120
BUFFER = 1.2
use_timeout_order = False
timeout_order = [3, 15, 30]
debug = False

def set_timeout_metrics(delta):
    delta = max(delta, 0.0001)
    avg = ((timeout_order[0] / BUFFER) + delta) / 2
    avg *= BUFFER
    avg = max(LOWER_BOUND, avg)
    avg = min(UPPER_BOUND, avg)
    timeout_order[0] = avg
    timeout_order[2] = timeout_order[0] * 30
    timeout_order[1] = timeout_order[2] / 2
    

class GaurdedInitialConnection(Handler):
    def __init__(self, parent, id, encrypt=False, log_prefix="", lan=False,
                 urgent=False, timeout=None ):
        self.t = None
        self.id = id
        self.lan = lan
        self.parent = parent
        self.urgent = urgent
        self.timeout = timeout
        self.encrypt = encrypt
        self.connector = None
        self.log_prefix = log_prefix

    def _make_connector(self, s):
        addr = (s.ip, s.port)
        self.parent.cache_complete_peer(addr, self.id, type(self),
                                        encrypt=self.encrypt,
                                        urgent=self.urgent,
                                        lan=self.lan)
        return Connector(self.parent, s, self.id, True,
                         obfuscate_outgoing=self.encrypt,
                         log_prefix=self.log_prefix,
                         lan=self.lan)


    def connection_starting(self, addr):
        self.start = bttime()
        self.t = self.parent.add_task(self.timeout,
                                      self.parent._cancel_connection, addr)

    def _abort_timeout(self):
        if self.t and self.t.active():
            self.t.cancel()
        self.t = None        
        
    def connection_made(self, s):
        t = bttime() - self.start
        set_timeout_metrics(t)
        addr = (s.ip, s.port)

        if debug:        
            self.parent.logger.warning('connection made: %s %s' %
                                       (addr, t))

        del self.parent.pending_connections[addr]

        self._abort_timeout()        

        con = self._make_connector(s)
        self.parent._add_connection(con)
            
        # if the pending queue filled and put the remaining connections
        # into the spare list, this will push more connections in to pending
        self.parent.replace_connection()
        
    def connection_failed(self, s, exception):
        addr = (s.ip, s.port)
        if debug:
            self.parent.logger.warning('connection failed: %s %s' %
                                       (addr, exception.getErrorMessage()))

        if s.connector.wasPreempted():
            self.parent._resubmit_connection(addr)

        del self.parent.pending_connections[addr]

        self._abort_timeout()        

        # only holepunch if this connection timed out entirely
        if self.timeout >= timeout_order[-1]:        
            c = self.parent.find_connection_in_common(addr)
            if c:
                c.send_holepunch_request(addr)

        self.parent.replace_connection()


class HTTPInitialConnection(GaurdedInitialConnection):

    def _make_connector(self, s):
        addr = (s.ip, s.port)
        self.parent.cache_complete_peer(addr, self.id, type(self),
                                        urgent=self.urgent)
        # ow!
        piece_size = self.parent.downloader.storage.piece_size
        urlage = self.parent.downloader.urlage
        return HTTPConnector(self.parent, piece_size, urlage, s, self.id, True,
                             log_prefix=self.log_prefix)
    

class ConnectionManager(InternetSubscriber):

    def __init__(self, make_upload, downloader, choker,
                 numpieces, ratelimiter,
                 rawserver, config, private, my_id, add_task, infohash, context,
                 addcontactfunc, reported_port, tracker_ips, log_prefix ): 
        """
            @param downloader: MultiDownload for this torrent.
            @param my_id: my peer id.
            @param tracker_ips: list of tracker ip addresses.
               ConnectionManager does not drop connections from the tracker.
               This allows trackers to perform NAT checks even when there
               are max_allow_in connections.
            @param log_prefix: string used as the prefix for all
               log entries generated by the ConnectionManager and its
               created Connectors.
        """
        self.make_upload = make_upload
        self.downloader = downloader
        self.choker = choker
        # aaargh
        self.piece_size = downloader.storage.piece_size
        self.numpieces = numpieces
        self.ratelimiter = ratelimiter
        self.rawserver = rawserver
        self.my_id = my_id
        self.private = private
        self.config = config
        self.add_task = add_task
        self.infohash = infohash
        self.context = context
        self.addcontact = addcontactfunc
        self.reported_port = reported_port
        self.everinc = False
        self.tracker_ips = tracker_ips
        self.log_prefix = log_prefix
        self.logger = logging.getLogger(self.log_prefix)
        self.closed = False        

        # submitted
        self.pending_connections = {}

        # transport connected
        self.connectors = set()

        # protocol active
        # we do a lot of itterating and few mutations, so use a list
        self.complete_connectors = [] # set()

        # use a dict for a little semi-randomness        
        self.spares = {} # OrderedDict()
        self.cached_peers = OrderedDict()
        self.cache_limit = 300

        self.connector_ips = DictWithInts()
        self.connector_ids = DictWithInts()

        self.banned = set()

        self._ka_task = self.add_task(config['keepalive_interval'],
                                      self.send_keepalives)
        self._pex_task = None
        if not self.private:
            self._pex_task = self.add_task(config['pex_interval'],
                                           self.send_pex)

        self.reopen(reported_port)        

    def cleanup(self):
        if not self.closed:
            self.close_connections()
        del self.context
        self.cached_peers.clear()
        if self._ka_task.active():
            self._ka_task.cancel()
        if self._pex_task and self._pex_task.active():
            self._pex_task.cancel()

    def reopen(self, port):
        self.closed = False
        self.reported_port = port
        self.unthrottle_connections()
        for addr in self.cached_peers:
            self._fire_cached_connection(addr)
        self.rawserver.internet_watcher.add_subscriber(self)

    def internet_active(self):
        for addr in self.cached_peers.iterkeys():
            self._fire_cached_connection(addr)

    def remove_addr_from_cache(self, addr):
        # could have been an incoming connection
        # or could have been dropped by the cache limit
        if addr in self.cached_peers:
            del self.cached_peers[addr]

    def try_one_connection(self):
        keys = self.cached_peers.keys()
        if not keys:
            return False
        addr = random.choice(keys)
        self._fire_cached_connection(addr)
        return True

    def _fire_cached_connection(self, addr):
        v = self.cached_peers[addr]
        complete, (id, handler, a, kw) = v
        return self._start_connection(addr, id, handler, *a, **kw)

    def cache_complete_peer(self, addr, pid, handler, *a, **kw):
        self.cache_peer(addr, pid, handler, 1, *a, **kw)

    def cache_incomplete_peer(self, addr, pid, handler, *a, **kw):
        self.cache_peer(addr, pid, handler, 0, *a, **kw)

    def cache_peer(self, addr, pid, handler, complete, *a, **kw):
        # obey the cache size limit
        if (addr not in self.cached_peers and
            len(self.cached_peers) >= self.cache_limit):
            for k, v in self.cached_peers.iteritems():
                if not v[0]:
                    del self.cached_peers[k]
                    break
            else:
                # cache full of completes, delete a random peer.
                # yes, this can cache an incomplete when the cache is full of
                # completes, but only 1 because of the filter above.
                oldaddr = self.cached_peers.keys()[0]
                del self.cached_peers[oldaddr]
        elif not complete:
            if addr in self.cached_peers and self.cached_peers[addr][0]:
                # don't overwrite a complete with an incomplete.
                return
        self.cached_peers[addr] = (complete, (pid, handler, a, kw))

    def send_keepalives(self):
        self._ka_task = self.add_task(self.config['keepalive_interval'],
                                      self.send_keepalives)
        for c in self.complete_connectors:
            c.send_keepalive()

    def send_pex(self):
        self._pex_task = self.add_task(self.config['pex_interval'],
                                       self.send_pex)
        pex_set = set()
        for c in self.complete_connectors:
            if c.listening_port:
                pex_set.add((c.ip, c.listening_port))
        for c in self.complete_connectors:
            c.send_pex(pex_set)

    def hashcheck_succeeded(self, i):
        for c in self.complete_connectors:
            # should we send a have message if peer already has the piece?
            # yes! it is low bandwidth and useful for that peer.
            c.send_have(i)

    def find_connection_in_common(self, addr):
        for c in self.complete_connectors:
            if addr in c.remote_pex_set:
                return c
        
    # returns False if the connection info has been pushed on to self.spares
    # other filters and a successful connection return True
    def start_connection(self, addr, id=None, encrypt=False, lan=False):
        """@param addr: domain name/ip address and port pair.
           @param id: peer id.
           """
        return self._start_connection(addr, id, GaurdedInitialConnection,
                                      encrypt=encrypt,
                                      lan=lan)
    
    def start_http_connection(self, url):
        r = urlparse.urlparse(url)
        host = r[1]
        if ':' in host:
            host, port = host.split(':')
            port = int(port)
        else:
            port = 80
        df = self.rawserver.gethostbyname(host)
        df.addCallback(self._connect_http, port, url)
        df.addLogback(self.logger.warning, "Resolve failed")

    def _connect_http(self, ip, port, url):
        self._start_connection((ip, port), url,
                               HTTPInitialConnection, urgent=True)

    def _start_connection(self, addr, pid, handler, *a, **kw):
        """@param addr: domain name/ip address and port pair.
           @param pid: peer id.
           """
        if self.closed:
            return True 
        if addr[0] in self.banned:
            return True
        if pid == self.my_id:
            return True

        for v in self.connectors:
            if pid and v.id == pid:
                return True
            if self.config['one_connection_per_ip'] and v.ip == addr[0]:
                return True

        total_outstanding = len(self.connectors)
        # it's possible the pending connections could eventually complete,
        # so we have to account for those when enforcing max_initiate
        total_outstanding += len(self.pending_connections)
        
        if total_outstanding >= self.config['max_initiate']:
            self.spares[(addr, pid)] = (handler, a, kw)
            return False

        # if these fail, I'm getting a very weird addr object        
        assert isinstance(addr, tuple)
        assert isinstance(addr[0], str)
        assert isinstance(addr[1], int)
        if ONLY_LOCAL and addr[0] != "127.0.0.1" and not addr[0].startswith("192.168") and addr[1] != 80:
            return True

        if GLOBAL_FILTER and not GLOBAL_FILTER(addr[0], addr[1], "out"):
            return True

        if addr not in self.cached_peers:
            self.cache_incomplete_peer(addr, pid, handler, *a, **kw)

        # sometimes we try to connect to a peer we're already trying to 
        # connect to 
        #assert addr not in self.pending_connections
        if addr in self.pending_connections:
            return True

        kw['log_prefix'] = self.log_prefix
        timeout = 30
        if use_timeout_order:
            timeout = timeout_order[0]
        kw.setdefault('timeout', timeout)
        h = handler(self, pid, *a, **kw)
        self.pending_connections[addr] = (h, (addr, pid, handler, a, kw))
        urgent = kw.pop('urgent', False)
        connector = self.rawserver.start_connection(addr, h, self.context,
                                                    # we'll handle timeouts.
                                                    # not so fond of this.
                                                    timeout=None,
                                                    urgent=urgent)
        h.connector = connector

        return True

    def _resubmit_connection(self, addr):
        # we leave it on pending_connections.
        # so the standard connection_failed handling occurs.
        h, info = self.pending_connections[addr]
        addr, pid, handler, a, kw = info

        self.spares[(addr, pid)] = (handler, a, kw)

    def _cancel_connection(self, addr):
        if addr not in self.pending_connections:
            # already made
            return

        # we leave it on pending_connections.
        # so the standard connection_failed handling occurs.
        h, info = self.pending_connections[addr]
        addr, pid, handler, a, kw = info

        if use_timeout_order and h.timeout < timeout_order[-1]:
            for t in timeout_order:
                if t > h.timeout:
                    h.timeout = t
                    break
            else:
                h.timeout = timeout_order[-1]
            # this feels odd
            kw['timeout'] = h.timeout
            self.spares[(addr, pid)] = (handler, a, kw)

        # do this last, since twisted might fire the event handler from inside
        # the function
        # HMM:
        # should be stopConnecting, but I've seen this fail.
        # close does the same thing, but disconnects in the case where the
        # connection was made. Not sure how that occurs without add being in
        # self.pending_connections
        # Maybe this was fixed recently in CRLR.
        #h.connector.stopConnecting()
        h.connector.close()

    def connection_handshake_completed(self, connector):

        self.connector_ips.add(connector.ip)
        self.connector_ids.add(connector.id)

        self.complete_connectors.append(connector)

        connector.upload = self.make_upload(connector)
        connector.download = self.downloader.make_download(connector)
        self.choker.connection_made(connector)
        if connector.uses_dht:
            connector.send_port(self.reported_port)

        if self.config['resolve_hostnames']:
            df = self.rawserver.gethostbyaddr(connector.ip)
            def save_hostname(hostname_tuple):
                hostname, aliases, ips = hostname_tuple
                connector.hostname = hostname
            df.addCallback(save_hostname)
            df.addErrback(lambda fuckoff : None)

    def got_port(self, connector):
        if self.addcontact and connector.uses_dht and \
           connector.dht_port != None:
            self.addcontact(connector.connection.ip, connector.dht_port)

    def ever_got_incoming(self):
        return self.everinc

    def how_many_connections(self):
        return len(self.complete_connectors)

    def replace_connection(self):
        if self.closed:
            return
        while self.spares:
            k, v = self.spares.popitem()
            addr, id = k
            handler, a, kw = v
            started = self._start_connection(addr, id, handler, *a, **kw)
            if not started:
                # start_connection decided to push this connection back on to
                # self.spares because a limit was hit. break now or loop
                # forever
                break

    def throttle_connections(self):
        self.throttled = True
        for c in iter_rand_pos(self.connectors):
            c.connection.pause_reading()

    def unthrottle_connections(self):
        self.throttled = False
        for c in iter_rand_pos(self.connectors):
            c.connection.resume_reading()
            # arg. resume actually flushes the buffers in iocpreactor, so
            # we have to check the state constantly
            if self.throttled:
                break

    def close_connection(self, id):
        for c in self.connectors:
            if c.id == id and not c.closed:
                c.connection.close()
                c.closed = True

    def close_connections(self):
        self.rawserver.internet_watcher.remove_subscriber(self)
        self.closed = True

        pending = self.pending_connections.values()
        # drop connections which could be made after we're not interested
        for h, info in pending:
            h.connector.close()
            
        for c in self.connectors:
            if not c.closed:
                c.connection.close()
                c.closed = True

    def singleport_connection(self, connector):
        """hand-off from SingleportListener once the infohash is known and
           thus we can map a connection on to a particular Torrent."""
        
        if connector.ip in self.banned:
            return False
        m = self.config['max_allow_in']
        if (m and len(self.connectors) >= m and 
            connector.ip not in self.tracker_ips):
            return False
        self._add_connection(connector)
        if self.closed:
            return False
        connector.set_parent(self)
        connector.connection.context = self.context
        return True

    def _add_connection(self, connector):
        self.connectors.add(connector)

        if self.closed:
            connector.connection.close()
        elif self.throttled:
            connector.connection.pause_reading()

    def ban(self, ip):
        self.banned.add(ip)

    def connection_lost(self, connector):
        assert isinstance(connector, Connector)
        self.connectors.remove(connector)

        if self.ratelimiter:
            self.ratelimiter.dequeue(connector)

        if connector.complete:
            self.connector_ips.remove(connector.ip)
            self.connector_ids.remove(connector.id)
            
            self.complete_connectors.remove(connector)
            self.choker.connection_lost(connector)


class AnyportListener(Handler):

    def __init__(self, port, singleport):
        self.port = port
        self.singleport = singleport
        rawserver = singleport.rawserver

        s = rawserver.create_serversocket(port, config['bind'])
        rawserver.start_listening(s, self)

    def __getattr__(self, attr):
        return getattr(self.singleport, attr)


class SingleportListener(Handler):
    """Manages a server socket common to all torrents.  When a remote
       peer opens a connection to the local peer, the SingleportListener
       maps that peer on to the appropriate torrent's connection manager
       (see SingleportListener.select_torrent).

       See Connector which upcalls to select_torrent after the infohash is 
       received in the opening handshake."""
    def __init__(self, rawserver, nattraverser, log_prefix, 
                 use_local_discovery):
        self.rawserver = rawserver
        self.nattraverser = nattraverser
        self.port = 0
        self.ports = {}
        self.port_change_notification = None
        self.torrents = {}
        self.connectors = set()
        self.infohash = None
        self.obfuscated_torrents = {}
        self.local_discovery = None
        self.ld_services = {}
        self.use_local_discovery = use_local_discovery
        self._creating_local_discovery = False
        self.log_prefix = log_prefix
        self.logger = logging.getLogger(self.log_prefix)

    def _close(self, port):        
        serversocket = self.ports[port][0]
        if self.nattraverser:
            try:
                self.nattraverser.unregister_port(port, "TCP")
            except:
                # blanket, just incase - we don't want to interrupt things
                self.logger.warning("UPnP deregistration error",
                                    exc_info=sys.exc_info())
        self.rawserver.stop_listening(serversocket)
        serversocket.close()
        if self.local_discovery:
            self.local_discovery.stop()
            self.local_discovery = None

    def _check_close(self, port):
        if not port or self.port == port or len(self.ports[port][1]) > 0:
            return
        self._close(port)
        del self.ports[port]

    def open_port(self, port, config):
        """Starts BitTorrent running as a server on the specified port."""
        if port in self.ports:
            self.port = port
            return
        s = self.rawserver.create_serversocket(port, config['bind'])
        if self.nattraverser:
            try:
                d = self.nattraverser.register_port(port, port, "TCP", 
                                                    config['bind'],
                                                    app_name)
                def change(*a):
                    self.rawserver.external_add_task(0, self._change_port, *a)
                d.addCallback(change)
                def silent(*e):
                    pass
                d.addErrback(silent)
            except:
                # blanket, just incase - we don't want to interrupt things
                self.logger.warning("UPnP registration error",
                                    exc_info=sys.exc_info())
        self.rawserver.start_listening(s, self)
        oldport = self.port
        self.port = port
        self.ports[port] = [s, {}]        
        self._check_close(oldport)

        if self.local_discovery:
            self.local_discovery.stop()
        if self.use_local_discovery:
            self._create_local_discovery()

    def _create_local_discovery(self):
        assert self.use_local_discovery
        self._creating_local_discovery = True
        try:
            self.local_discovery = LocalDiscovery(self.rawserver, self.port,
                                                  self._start_connection)
            self._creating_local_discovery = False
        except:
            self.rawserver.add_task(5, self._create_local_discovery)

    def _start_connection(self, addr, infohash):
        infohash = infohash.decode('hex')
        if infohash not in self.torrents:
            return
        connection_manager = self.torrents[infohash]
        # TODO: peer id?
        connection_manager.start_connection(addr, None)
        
    def _change_port(self, port):
        if self.port == port:
            return
        [serversocket, callbacks] = self.ports[self.port]
        self.ports[port] = [serversocket, callbacks]
        del self.ports[self.port]
        self.port = port
        for callback in callbacks:
            if callback:
                callback(port)

    def get_port(self, callback = None):
        if self.port:
            callbacks = self.ports[self.port][1]
            callbacks.setdefault(callback, 0)
            callbacks[callback] += 1
        return self.port

    def release_port(self, port, callback = None):
        callbacks = self.ports[port][1]
        callbacks[callback] -= 1
        if callbacks[callback] == 0:
            del callbacks[callback]
        self._check_close(port)

    def close_sockets(self):
        for port in self.ports.iterkeys():
            self._close(port)

    def add_torrent(self, infohash, connection_manager):
        if infohash in self.torrents:
            raise BTFailure(_("Can't start two separate instances of the same "
                              "torrent"))
        self.torrents[infohash] = connection_manager
        key = sha('req2' + infohash).digest()
        self.obfuscated_torrents[key] = connection_manager
        if self.local_discovery:
            service = self.local_discovery.announce(infohash.encode('hex'),
                                                    connection_manager.my_id.encode('hex'))
            self.ld_services[infohash] = service

    def remove_torrent(self, infohash):
        del self.torrents[infohash]
        del self.obfuscated_torrents[sha('req2' + infohash).digest()]
        if infohash in self.ld_services:
            service = self.ld_services.pop(infohash)
            if self.local_discovery:
                self.local_discovery.unannounce(service)

    def connection_made(self, connection):
        """Called when TCP connection has finished opening, but before
           BitTorrent protocol has begun."""
        if ONLY_LOCAL and connection.ip != '127.0.0.1' and not connection.ip.startswith("192.168") :
            return
        if GLOBAL_FILTER and not GLOBAL_FILTER(connection.ip, connection.port, "in"):
            return
        connector = Connector(self, connection, None, False,
                              log_prefix=self.log_prefix)
        self.connectors.add(connector)

    def select_torrent(self, connector, infohash):
        """Called when infohash has been received allowing us to map
           the connection on to a given Torrent's ConnectionManager."""
        # call-up from Connector.
        if infohash in self.torrents:
            accepted = self.torrents[infohash].singleport_connection(connector)
            if not accepted:
                # the connection manager may refuse the connection, in which
                # case keep the connection in our list until it is dropped
                connector.close()
            else:
                # otherwise remove it
                self.connectors.remove(connector)

    def select_torrent_obfuscated(self, connector, streamid):
        if ONLY_LOCAL and connector.connection.ip != '127.0.0.1':
            return
        if streamid not in self.obfuscated_torrents:
            return
        self.obfuscated_torrents[streamid].singleport_connection(connector)

    def connection_lost(self, connector):
        assert isinstance(connector, Connector)
        self.connectors.remove(connector)

    def remove_addr_from_cache(self, addr):
        # since this was incoming, we don't cache the peer anyway
        pass
    
