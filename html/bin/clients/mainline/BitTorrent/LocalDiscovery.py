# Zeroconf discovery of other BT clients on the local network.
#
# by Greg Hazel

import sys
import random
import socket
import logging
import Zeroconf
from BTL import stackthreading as threading
from BTL.HostIP import get_deferred_host_ip, get_host_ip

discovery_logger = logging.getLogger('LocalDiscovery')
discovery_logger.setLevel(logging.DEBUG)
#discovery_logger.addHandler(logging.StreamHandler(sys.stdout))

server = None
def _get_server():
    global server
    if not server:
        server = Zeroconf.Zeroconf()
    return server

class LocalDiscovery(object):

    def __init__(self, rawserver, port, got_peer):
        self.rawserver = rawserver
        self.port = port
        self.got_peer = got_peer
        self.server = _get_server()
        self.services = []

    def announce(self, infohash, peerid):
        discovery_logger.info("announcing: %s", infohash)

        # old
        #service_name = "_BitTorrent-%s._tcp.local." % infohash
        #service_type = service_name
        
        service_name = "%s._%s" % (peerid, infohash)
        service_type = "_bittorrent._tcp.local."
        
        browser = Zeroconf.ServiceBrowser(self.server, service_type, self)

        service = Zeroconf.ServiceInfo(service_type,
                                       "%s.%s" % (service_name, service_type),
                                       address = None, # to be filled in later
                                       port = self.port,
                                       weight = 0, priority = 0,
                                       properties = {}
                                      )
        service.browser = browser
        service.registered = False
        self.services.append(service)
        
        df = get_deferred_host_ip()
        df.addCallback(self._announce2, service)
        return service

    def _announce2(self, ip, service):
        if service not in self.services:
            # already removed
            return 
        service.registered = True
        service.address = socket.inet_aton(ip)
        #t = threading.Thread(target=self.server.registerService, args=(service,))
        #t.setDaemon(False)
        #t.start()
        # blocks!
        self.server.registerService(service)

    def unannounce(self, service):
        assert isinstance(service, Zeroconf.ServiceInfo)
        if service.registered:
            service.registered = False
            service.browser.cancel()
            self.server.unregisterService(service)
        self.services.remove(service)
            
    def addService(self, server, type, name):
        discovery_logger.info("Service %s added", repr(name))
        # Request more information about the service
        info = server.getServiceInfo(type, name)
        if info and info.address is not None:
            host = socket.inet_ntoa(info.address)
            try:
                port = int(info.port)
            except:
                discovery_logger.exception("Invalid Service (port not an int): "
                                           "%r" % info.__dict__)
                return
        
            addr = (host, port)
            ip = get_host_ip()

            if addr == (ip, self.port):
                # talking to self
                return

            # old
            #infohash = name.split("_BitTorrent-")[1][:-len("._tcp.local.")]

            peerid, infohash, service_type = name.split('.', 2)
            infohash = infohash[1:] # _

            discovery_logger.info("Got peer: %s:%d %s", host, port, infohash)

            # BUG: BitTorrent is so broken!
            #t = random.random() * 3
            # But I fixed it.
            t = 0

            self.rawserver.external_add_task(t, self._got_peer, addr, infohash)

    def removeService(self, server, type, name):
        discovery_logger.info("Service %s removed", repr(name))

    def _got_peer(self, addr, infohash):
        if self.got_peer:
            self.got_peer(addr, infohash)
            
    def stop(self):
        self.port = None
        self.got_peer = None
        for service in self.services:
            self.unannounce(service)

        
if __name__ == '__main__':
    import string
    from BitTorrent.RawServer_twisted import RawServer
    from BitTorrent.PeerID import make_id

    rawserver = RawServer()

    def run_task_and_exit():
        l = LocalDiscovery(rawserver, 6881,
                           lambda *a:sys.stdout.write("GOT: %s\n" % str(a)))
        l.announce("63f27f5023d7e49840ce89fc1ff988336c514b64",
                   make_id().encode('hex'))
    
    rawserver.add_task(0, run_task_and_exit)

    rawserver.listen_forever()
         
    
