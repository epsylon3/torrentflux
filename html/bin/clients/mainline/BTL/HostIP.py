# a very simple (and silly) mechanism for getting the host_ip

import socket
from BTL.platform import bttime
from BTL.obsoletepythonsupport import set
from BTL.reactor_magic import reactor
from BTL import defer
import BTL.stackthreading as threading
from twisted.internet.protocol import ClientFactory, Protocol
from twisted.protocols.policies import TimeoutMixin
try:
    from BTL.iphelp import get_route_ip
except:
    get_route_ip = None
    

import thread

_host_ip = 'unknown'
_host_ip_callbacks = []
_host_ip_cachetime = 0
_host_ips = None
_host_ips_cachetime = 0
_thread_running = False
CACHE_TIME = 3600 # hour

wrap_task = reactor.callFromThread


class RecorderProtocol(TimeoutMixin, Protocol):

    def makeConnection(self, transport):
        self.setTimeout(20)
        Protocol.makeConnection(self, transport)

    def connectionMade(self):
        _got_result(self.transport.getHost().host)
        self.transport.write("GET /myip HTTP/1.0\r\n\r\n")
        self.transport.loseConnection()

    def connectionLost(self, reason):
        _got_result(None)


class RecorderFactory(ClientFactory):
    
    def clientConnectionFailed(self, connector, reason):
        _got_result(None)


def _resolve():
    try:
        ip = socket.gethostbyname(socket.gethostname())
    except socket.error, e:
        ip = 'unknown'
    reactor.callFromThread(_got_result, ip)


def _finish(ip):
    global _thread_running
    _thread_running = False
    _got_result(ip)


def _got_result(ip):
    global _host_ip
    global _host_ip_callbacks
    global _host_ip_cachetime
    global _thread_running
    if hasattr(reactor, 'ident'):
        assert reactor.ident == thread.get_ident()

    if _thread_running:
        return

    if ip is None:
        t = threading.Thread(target=_resolve)
        t.setDaemon(True)
        _thread_running = True
        t.start()
        return

    if ip is not 'unknown':
        _host_ip = ip
        _host_ip_cachetime = bttime()
            
    l = _host_ip_callbacks
    _host_ip_callbacks = []
    for df in l:
        df.callback(_host_ip)


def get_deferred_host_ip():
    global _host_ip
    global _host_ip_callbacks
    global _host_ip_cachetime
    if hasattr(reactor, 'ident'):
        assert reactor.ident == thread.get_ident()

    if _host_ip is not 'unknown' and _host_ip_cachetime + CACHE_TIME > bttime():
        return defer.succeed(_host_ip)

    if get_route_ip:
        ip = get_route_ip()
        if ip:
            _host_ip = ip
            _host_ip_cachetime = bttime()
            return defer.succeed(_host_ip)            

    df = defer.Deferred()
    
    if not _host_ip_callbacks:
        def connect(ip):
            factory = RecorderFactory()
            factory.protocol = RecorderProtocol
            if hasattr(reactor, 'limiter'):
                reactor.connectTCP(ip, 80, factory, urgent=True)
            else:
                reactor.connectTCP(ip, 80, factory)            
        rdf = reactor.resolve("ip.bittorrent.com")
        rdf.addCallback(connect)
        rdf.addErrback(lambda e : _got_result(None))
    
    _host_ip_callbacks.append(df)

    return df


def get_host_ip():
    """ Blocking version, do not use from reactor thread! """
    global _host_ip
    global _host_ip_callbacks
    global _host_ip_cachetime
    if hasattr(reactor, 'ident'):
        assert reactor.ident != thread.get_ident()

    if _host_ip is not 'unknown' and _host_ip_cachetime + CACHE_TIME > bttime():
        return _host_ip

    if get_route_ip:
        ip = get_route_ip()
        if ip:
            _host_ip = ip
            _host_ip_cachetime = bttime()
            return _host_ip

    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(5)
        
        # what moron decided to make try/except/finally not work?
        # Guido van Rossum.
        try:
            s.connect(("ip.bittorrent.com", 80))
            endpoint = s.getsockname()
            _host_ip = endpoint[0]
            _host_ip_cachetime = bttime()
            s.send("GET /myip HTTP/1.0\r\n\r\n")
        except (socket.error, socket.timeout), e:
            try:
                _host_ip = socket.gethostbyname(socket.gethostname())
            except socket.error, e:
                pass
        try:
            s.close()
        except:
            pass
    except:
        pass        
        
    return _host_ip


def get_deferred_host_ips():
    global _host_ips
    global _host_ips_cachetime
    if hasattr(reactor, 'ident'):
        assert reactor.ident == thread.get_ident()

    if _host_ips is not None and _host_ips_cachetime + CACHE_TIME > bttime():
        return defer.succeed(_host_ips)

    df = get_deferred_host_ip()
    finaldf = defer.Deferred()
    df.addCallback(_get_deferred_host_ips2, finaldf)
    return finaldf


def _get_deferred_host_ips2(host_ip, finaldf):
    if hasattr(reactor, 'ident'):
        assert reactor.ident == thread.get_ident()
    df = defer.ThreadedDeferred(wrap_task, _get_deferred_host_ips3,
                                host_ip, daemon=True)
    df.chainDeferred(finaldf)


def _get_deferred_host_ips3(host_ip):
    global _host_ips
    global _host_ips_cachetime
    if hasattr(reactor, 'ident'):
        assert reactor.ident != thread.get_ident()
    
    l = set()

    if host_ip is not 'unknown':
        l.add(host_ip)

    try:
        hostname = socket.gethostname()
        hostname, aliaslist, ipaddrlist = socket.gethostbyname_ex(hostname)
        l.update(ipaddrlist)
    except socket.error, e:
        print "ARG", e

    _host_ips = l
    _host_ips_cachetime = bttime()

    return _host_ips

def get_host_ips():
    return _get_deferred_host_ips3(get_host_ip())
