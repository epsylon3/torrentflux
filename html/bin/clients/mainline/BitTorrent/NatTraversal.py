# someday: http://files.dns-sd.org/draft-nat-port-mapping.txt
# today: http://www.upnp.org/

import os
import sys
import Queue
import socket
import random
import logging
import urlparse
if os.name == 'nt':
    import pywintypes
    import win32com.client

from BTL import defer
from BitTorrent.platform import os_version
from BTL.sparse_set import SparseSet
from BTL.exceptions import str_exc
from BitTorrent.RawServer_twisted import Handler
from BitTorrent.BeautifulSupe import BeautifulSupe
from BTL.yielddefer import launch_coroutine, wrap_task
from BTL.HostIP import get_host_ip, get_deferred_host_ip
import twisted.copyright
from twisted.internet import reactor

from urllib import FancyURLopener, addinfourl
from urllib2 import URLError, HTTPError, Request
from httplib import BadStatusLine, HTTPResponse

import BTL.stackthreading as threading

nat_logger = logging.getLogger('NatTraversal')
nat_logger.setLevel(logging.WARNING)

def UnsupportedWarning(s):
    nat_logger.warning("NAT Traversal warning " + ("(%s: %s)."  % (os_version, s)))

def UPNPError(s):
    nat_logger.error("UPnP ERROR: " + ("(%s: %s)."  % (os_version, s)))


class UPnPException(Exception):
    pass


class NATEventLoop(threading.Thread):

    def __init__(self):
        threading.Thread.__init__(self)
        self.queue = Queue.Queue()
        self.killswitch = defer.DeferredEvent()
        def ignore(*a, **kw):
            pass
        self.killswitch.addCallback(ignore)            
        self.setDaemon(True)

    def run(self):

        while not self.killswitch.isSet():

            (f, a, kw) = self.queue.get()
            
            try:
                nat_logger.debug("NATEventLoop Event: %s" % f.__name__)
                f(*a, **kw)
                nat_logger.debug("NATEventLoop Event: %s finished." % f.__name__)
            except:
                # sys can be none during interpritter shutdown
                if sys is None:
                    break
                nat_logger.exception("Error in NATEventLoop for %s" % str(f.__name__))


class NatTraverser(object):

    def __init__(self, rawserver):
        self.rawserver = rawserver
        
        self.register_requests = []
        self.unregister_requests = []
        self.list_requests = []

        self.service = None
        self.services = []
        self.current_service = 0

        if self.rawserver.config['upnp']:
            if os.name == 'nt':
                self.services.append(WindowsUPnP)
            self.services.append(ManualUPnP)

        self.event_loop = NATEventLoop()
        self.event_loop.start()
        
        self.resume_init_services()

    def add_task(self, f, *a, **kw):
        self.event_loop.queue.put((f, a, kw))        

    def init_services(self):
        # this loop is a little funny so a service can resume the init if it fails later
        if not self.rawserver.config['upnp']:
            return
        while self.current_service < len(self.services):
            service = self.services[self.current_service]
            self.current_service += 1
            try:
                nat_logger.info("Trying: %s" % service.__name__)
                service(self)
                break
            except Exception, e:
                nat_logger.warning(str_exc(e))
        else:
            e = "Unable to detect any UPnP services"
            UnsupportedWarning(e)
            self._cancel_queue(e)

    def resume_init_services(self):
        self.add_task(self.init_services)

    def attach_service(self, service):
        nat_logger.info("Using: %s" % type(service).__name__)
        self.service = service
        self.add_task(self._flush_queue)

    def detach_service(self, service):
        if service != self.service:
            nat_logger.error("Service: %s is not in use!" % type(service).__name__)
            return
        nat_logger.info("Detached: %s" % type(service).__name__)
        self.service = None
        
    def _flush_queue(self):
        if self.service:
            for mapping in self.register_requests:
                self.add_task(self.service.safe_register_port, mapping)
            self.register_requests = []
        
            for request in self.unregister_requests:
                # unregisters can block, because they occur at shutdown
                self.service.unregister_port(*request)
            self.unregister_requests = []

            for request in self.list_requests:
                self.add_task(self._list_ports, request)
            self.list_requests = []

    def _cancel_queue(self, e):
        for mapping in self.register_requests:
            mapping.d.errback(Exception(e))
        self.register_requests = []

        # can't run or cancel blocking removes    
        self.unregister_requests = []

        for request in self.list_requests:
            request.errback(Exception(e))
        self.list_requests = []

    def _gen_deferred(self):
        return defer.ThreadableDeferred(reactor.callFromThread)

    def register_port(self, external_port, internal_port, protocol,
                      host = None, service_name = None, remote_host=''):
        mapping = UPnPPortMapping(external_port, internal_port, protocol,
                                  host, service_name, remote_host)
        mapping.d = self._gen_deferred()
        self.register_requests.append(mapping)

        self.add_task(self._flush_queue)

        return mapping.d
    
    def unregister_port(self, external_port, protocol):
        self.unregister_requests.append((external_port, protocol))

        # unregisters can block, because they occur at shutdown
        self._flush_queue()

    def _list_ports(self, d):
        matches = self.service._list_ports()
        d.callback(matches)

    def list_ports(self):
        d = self._gen_deferred()
        self.list_requests.append(d)
        self.add_task(self._flush_queue)
        return d


class NATBase(object):

    def safe_register_port(self, new_mapping):

        # check for the host now, while we're in the thread and before
        # we need to read it.
        new_mapping.populate_host()
        
        nat_logger.info("You asked for: " + str(new_mapping))
        new_mapping.original_external_port = new_mapping.external_port
        mappings = self._list_ports()

        used_ports = []
        for mapping in mappings:
            # only consider ports which match the same protocol
            if mapping.protocol == new_mapping.protocol:
                # look for exact matches
                if (mapping.host == new_mapping.host and
                    mapping.internal_port == new_mapping.internal_port):
                    # the service name could not match, that's ok.
                    new_mapping.d.callback(mapping.external_port)
                    nat_logger.info("Already effectively mapped: " + str(mapping))
                    return 
                # otherwise, add it to the list of used external ports
                used_ports.append(mapping.external_port)

        used_ports.sort()
        used_ports = SparseSet(used_ports)

        all_ports = SparseSet()
        all_ports.add(1024, 65535)
        free_ports = all_ports - used_ports
        new_mapping.external_port = random.choice(free_ports)

        nat_logger.info("I'll give you: " + str(new_mapping))
        self.register_port(new_mapping)
        
    def register_port(self, port):
        pass

    def unregister_port(self, external_port, protocol):
        pass

    def _list_ports(self):
        pass


class UPnPPortMapping(object):

    def __init__(self, external_port, internal_port, protocol,
                 host = None, service_name = None, remote_host=''):
        self.external_port = int(external_port)
        self.internal_port = int(internal_port)
        self.protocol = protocol

        self.host = host
        self.remote_host = ''

        self.service_name = service_name

        self.d = defer.Deferred()

    def populate_host(self):
        # throw out '' or None or ints, also look for semi-valid IPs
        if not isinstance(self.host, str) or self.host.count('.') < 3:
            self.host = get_host_ip()
        
    def __str__(self):
        if not self.remote_host:
            remote = 'external'
        else:
            remote = self.remote_host
        return "%s %s %s:%d %s:%d" % (self.service_name, self.protocol,
                                      self.remote_host,
                                      self.external_port,
                                      self.host, self.internal_port)


def VerifySOAPResponse(request, response):
    if response.code != 200:
        raise HTTPError(request.get_full_url(),
                        response.code, str(response.msg) + " (unexpected SOAP response code)",
                        response.info(), response)

    data = response.read()
    bs = BeautifulSupe(data)
    # On Matt's Linksys WRT54G rev 4 v.1.0 I saw u: instead of m:
    # and ignoring that caused the router to crash
    soap_response = bs.scour("m:", "Response")
    if not soap_response:
        raise HTTPError(request.get_full_url(),
                        response.code, str(response.msg) +
                        " (incorrect SOAP response method)",
                        response.info(), response)
    return soap_response[0]
    
def SOAPResponseToDict(soap_response):
    result = {}
    for tag in soap_response.child_elements():
        value = None
        if tag.contents:
            value = str(tag.contents[0])
        result[tag.name] = value
    return result

def SOAPErrorToString(response):
    if not isinstance(response, Exception):
        data = response.read()
        bs = BeautifulSupe(data)
        error = bs.first('errorDescription')
        if error:
            return str(error.contents[0])
    return str(response)

_urlopener = None
def urlopen_custom(req, rawserver):
    global _urlopener

    if not _urlopener:
        opener = FancyURLopener()        
        _urlopener = opener
        #remove User-Agent
        del _urlopener.addheaders[:]

    if not isinstance(req, str):
        #for header in r.headers:
        #    _urlopener.addheaders.append((header, r.headers[header]))
        #return _urlopener.open(r.get_full_url(), r.data)
        
        # All this has to be done manually, since httplib and urllib 1 and 2
        # add headers to the request that some routers do not accept.
        # A minimal, functional request includes the headers:
        # Content-Length
        # Soapaction
        # I have found the following to be specifically disallowed:
        # User-agent
        # Connection
        # Accept-encoding

        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

        (scheme, netloc, path, params, query, fragment) = urlparse.urlparse(req.get_full_url())

        if not scheme.startswith("http"):
            raise ValueError("UPnP URL scheme is not http: " + req.get_full_url())

        if len(path) == 0:
            path = '/'

        if netloc.count(":") > 0:
            host, port = netloc.split(':', 1)
            try:
                port = int(port)
            except:
                raise ValueError("UPnP URL port is not int: " + req.get_full_url())
        else:
            host = netloc
            port = 80
        
        header_str = ''
        data = ''
        method = ''
        header_str = " " + path + " HTTP/1.0\r\n"
        if req.has_data():
            method = 'POST'
            header_str = method + header_str
            header_str += "Content-Length: " + str(len(req.data)) + "\r\n"
            data = req.data + "\r\n"
        else:
            method = 'GET'
            header_str = method + header_str
            
        header_str += "Host: " + host + ":" + str(port) + "\r\n"
        
        for header in req.headers:
            header_str += header + ": " + str(req.headers[header]) + "\r\n"

        header_str += "\r\n"
        data = header_str + data

        try:
            rawserver.add_pending_connection(host)
            s.connect((host, port))
        finally:
            rawserver.remove_pending_connection(host)
            
        s.send(data)
        r = HTTPResponse(s, method=method)
        r.begin()

        r.recv = r.read
        fp = socket._fileobject(r)

        resp = addinfourl(fp, r.msg, req.get_full_url())
        resp.code = r.status
        resp.msg = r.reason
                   
        return resp

    return _urlopener.open(req)


class ManualUPnP(NATBase, Handler):

    upnp_addr = ('239.255.255.250', 1900)

    search_string = ('M-SEARCH * HTTP/1.1\r\n' +
                     'Host:239.255.255.250:1900\r\n' +
                     'ST:urn:schemas-upnp-org:device:InternetGatewayDevice:1\r\n' +
                     'Man:"ssdp:discover"\r\n' +
                     'MX:3\r\n' +
                     '\r\n')

    # if you think for one second that I'm going to implement SOAP in any fashion, you're crazy
    
    get_mapping_template = ('<?xml version="1.0"?>' + 
                            '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"' +
                            's:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">' +
                            '<s:Body>' +
                            '<u:GetGenericPortMappingEntry xmlns:u=' +
                            '"urn:schemas-upnp-org:service:WANIPConnection:1">' +
                            '<NewPortMappingIndex>%d</NewPortMappingIndex>' +
                            '</u:GetGenericPortMappingEntry>' +
                            '</s:Body>' +
                            '</s:Envelope>')

    add_mapping_template = ('<?xml version="1.0"?>' +
                            '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle=' +
                            '"http://schemas.xmlsoap.org/soap/encoding/">' +
                            '<s:Body>' +
                            '<u:AddPortMapping xmlns:u="urn:schemas-upnp-org:service:WANIPConnection:1">' +
                            '<NewEnabled>1</NewEnabled>' +
                            '<NewRemoteHost>%s</NewRemoteHost>' +
                            '<NewLeaseDuration>0</NewLeaseDuration>' +
                            '<NewInternalPort>%d</NewInternalPort>' +
                            '<NewExternalPort>%d</NewExternalPort>' +
                            '<NewProtocol>%s</NewProtocol>' +
                            '<NewInternalClient>%s</NewInternalClient>' +
                            '<NewPortMappingDescription>%s</NewPortMappingDescription>' +
                            '</u:AddPortMapping>' +
                            '</s:Body>' +
                            '</s:Envelope>')

    delete_mapping_template = ('<?xml version="1.0"?>' +
                               '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle=' +
                               '"http://schemas.xmlsoap.org/soap/encoding/">' +
                               '<s:Body>' +
                               '<u:DeletePortMapping xmlns:u="urn:schemas-upnp-org:service:WANIPConnection:1">' +
                               '<NewRemoteHost></NewRemoteHost>' +
                               '<NewExternalPort>%d</NewExternalPort>' +
                               '<NewProtocol>%s</NewProtocol>' +
                               '</u:DeletePortMapping>' +
                               '</s:Body>' +
                               '</s:Envelope>')
    
    def _pretify(self, body):
        # I actually found a router that needed one tag per line
        body = body.replace('><', '>\r\n<')
        # don't add newlines in the middle of empty tags (like NewRemoteHost)
        body = body.replace('>\r\n</', '></')
        body = body.encode('utf-8')
        return body
    
    def _build_get_mapping_request(self, pmi):
        body = (self.get_mapping_template % (pmi))
        body = self._pretify(body)
        headers = {'SOAPAction': '"urn:schemas-upnp-org:service:WANIPConnection:1#' +
                                 'GetGenericPortMappingEntry"'}
        return Request(self.controlURL, body, headers)

    def _build_add_mapping_request(self, mapping):
        body = (self.add_mapping_template % (mapping.remote_host,
                                             mapping.internal_port,
                                             mapping.external_port,
                                             mapping.protocol, 
                                             mapping.host,
                                             mapping.service_name))
        body = self._pretify(body)
        headers = {'SOAPAction': '"urn:schemas-upnp-org:service:WANIPConnection:1#' +
                                 'AddPortMapping"'}
        return Request(self.controlURL, body, headers)

    def _build_delete_mapping_request(self, external_port, protocol):
        body = (self.delete_mapping_template % (external_port, protocol))
        body = self._pretify(body)
        headers = {'SOAPAction': '"urn:schemas-upnp-org:service:WANIPConnection:1#' +
                                 'DeletePortMapping"'}
        return Request(self.controlURL, body, headers)
        
    def __init__(self, traverser):
        NATBase.__init__(self)

        self.controlURL = None
        self.transport = None
        self.traverser = traverser
        self.rawserver = traverser.rawserver

        # this service can only be provided if rawserver supports multicast
        if not hasattr(self.rawserver, "create_multicastsocket"):
            raise AttributeError("RawServer does not support create_multicastsocket!")
               
        reactor.callFromThread(launch_coroutine,
                               wrap_task(reactor.callLater),
                               self.begin_discovery)

    def begin_discovery(self):
        # bind to an available port, and join the multicast group
        df = get_deferred_host_ip()
        yield df
        hostip = df.getResult()
        
        for p in xrange(self.upnp_addr[1], self.upnp_addr[1]+50):
            try:
                # Original RawServer cannot do this!
                s = self.rawserver.create_multicastsocket(p, hostip)
                self.transport = s
                self.rawserver.start_listening_multicast(s, self)
                df = s.listening_port.joinGroup(self.upnp_addr[0],
                                                socket.INADDR_ANY)
                yield df
                result = df.getResult()
                # blargh
                if twisted.copyright.version >= '2.4.0':
                    success = None
                    # ACKKKK..K. Prevents "Unhandled error in Deferred"
                    if df._debugInfo is not None:
                        df._debugInfo.failResult = None
                else:
                    success = 1
                if result is success:
                    break
                elif isinstance(result, twisted.python.failure.Failure):
                    # HACK.  If the failure contains a 'No such device' error
                    # then we abort the discovery because this error denotes
                    # that the peer is not connected to the network.
                    if hasattr( result.value, "__getitem__" ) and \
                       result.value[2] == 19:
                        yield 0   # abort discovery.      
                else:
                    # I suppose keep trying on different ports, but why would
                    # joinGroup fail?
                    self.transport = None
                    x = s.listening_port.stopListening()
                    if isinstance(x, defer.Deferred):
                        yield x
                        x.getResult()
            except socket.error, e:
                # may look weird, but spin the event loop once on failure
                yield defer.succeed(True)
            

        if not self.transport:
            # resume init services, because we couldn't bind to a port
            self.traverser.resume_init_services()
        else:
            self.transport.sendto(self.search_string, 0, self.upnp_addr)
            self.transport.sendto(self.search_string, 0, self.upnp_addr)
            reactor.callLater(6, self._discovery_timedout)

    def _discovery_timedout(self):
        if self.transport:
            nat_logger.warning("Discovery timed out")
            self.rawserver.stop_listening_multicast(self.transport)
            self.transport = None
            # resume init services, because we know we've failed
            self.traverser.resume_init_services()

    def register_port(self, mapping):
        request = self._build_add_mapping_request(mapping)

        try:
            response = urlopen_custom(request, self.rawserver)
            response = VerifySOAPResponse(request, response)
            mapping.d.callback(mapping.external_port)
            nat_logger.info("registered: " + str(mapping))
        except Exception, e: #HTTPError, URLError, BadStatusLine, you name it.
            error = SOAPErrorToString(e)
            mapping.d.errback(Exception(error))


    def unregister_port(self, external_port, protocol):
        request = self._build_delete_mapping_request(external_port, protocol)

        try:
            response = urlopen_custom(request, self.rawserver)
            response = VerifySOAPResponse(request, response)
            nat_logger.info("unregisterd: %s, %s" % (external_port, protocol))
        except Exception, e: #HTTPError, URLError, BadStatusLine, you name it.
            error = SOAPErrorToString(e)
            nat_logger.error(error)
    
    def data_came_in(self, addr, datagram):
        if self.transport is None:
            return
        try:
            statusline, response = datagram.split('\r\n', 1)
        except ValueError, e:
            nat_logger.error(str_exc(e) + ": " + str(datagram))
            # resume init services, because the data is unknown
            self.traverser.resume_init_services()
            return
            
        httpversion, statuscode, reasonline = statusline.split(None, 2)
        if (not httpversion.startswith('HTTP')) or (statuscode != '200'):
            return
        headers = response.split('\r\n')
        location = None
        for header in headers:
            prefix = 'location:'
            if header.lower().startswith(prefix):
                location = header[len(prefix):]
                location = location.strip()
        if location:
            self.rawserver.stop_listening_multicast(self.transport)
            self.transport = None

            self.traverser.add_task(self._got_location, location)

    def _got_location(self, location):
        if self.controlURL is not None:
            return

        URLBase = location

        for i in xrange(5): # retry
            try:
                data = urlopen_custom(location, self.rawserver).read()
            except IOError:
                nat_logger.warning("urlopen_custom timeout")
            except:
                nat_logger.warning("urlopen_custom error", exc_info=sys.exc_info())
            else:
                break
        else:
            nat_logger.warning("urlopen_custom error. giving up.")
            return

        try:
            bs = BeautifulSupe(data)
        except: # xml.parsers.expat.ExpatError, maybe others
            #open("wtf.xml", 'wb').write(data)
            nat_logger.warning("XML parse error", exc_info=sys.exc_info())
            return

        URLBase_tag = bs.first('URLBase')
        if URLBase_tag and URLBase_tag.contents:
            URLBase = str(URLBase_tag.contents[0])

        wanservices = bs.fetch('service',
                               dict(serviceType=
                                    'urn:schemas-upnp-org:service:WANIPConnection:'))
        wanservices += bs.fetch('service',
                                dict(serviceType=
                                     'urn:schemas-upnp-org:service:WANPPPConnection:'))
        for service in wanservices:
            controlURL = service.get('controlURL')
            if controlURL:
                self.controlURL = urlparse.urljoin(URLBase, controlURL)
                break

        if self.controlURL is None:
            # resume init services, because we know we've failed
            self.traverser.resume_init_services()
            return

        # attach service, so the queue gets flushed
        self.traverser.attach_service(self)
        
    def _list_ports(self):
        mappings = []
        _mappings_dict = {}

        if self.controlURL is None:
            raise UPnPException("ManualUPnP is not prepared")

        while True:            
            request = self._build_get_mapping_request(len(mappings))

            try:
                response = urlopen_custom(request, self.rawserver)
                soap_response = VerifySOAPResponse(request, response)
                results = SOAPResponseToDict(soap_response)
                mapping = UPnPPortMapping(results['NewExternalPort'], results['NewInternalPort'],
                                          results['NewProtocol'], results['NewInternalClient'],
                                          results['NewPortMappingDescription'])
                ports = (results['NewExternalPort'], results['NewInternalPort'])
                if ports in _mappings_dict:
                    # duplicate response, stop searching (because the router is clearly insane)
                    break
                mappings.append(mapping)
                _mappings_dict[ports] = 1
            except URLError, e:
                # SpecifiedArrayIndexInvalid, for example
                break
            except (HTTPError, BadStatusLine, socket.error):
                nat_logger.error("list_ports failed with:", exc_info=sys.exc_info())
                break 
        return mappings


class WindowsUPnPException(UPnPException):
    def __init__(self, msg, *args):
        msg += " (%s)" % os_version
        a = [msg] + list(args)
        UPnPException.__init__(self, *a)


class WindowsUPnP(NATBase):
    def __init__(self, traverser):
        NATBase.__init__(self)

        self.upnpnat = None
        self.port_collection = None
        self.traverser = traverser
        
        win32com.client.pythoncom.CoInitialize()
        
        try:
            self.upnpnat = win32com.client.Dispatch("HNetCfg.NATUPnP")
        except pywintypes.com_error, e:
            if (e[2][5] == -2147221005):
                raise WindowsUPnPException("invalid class string")
            else:
                raise

        try:
            self.port_collection = self.upnpnat.StaticPortMappingCollection
            if self.port_collection is None:
                raise WindowsUPnPException("none port_collection")
        except pywintypes.com_error, e:
            #if e[1].lower() == "exception occurred.":
            if (e[2][5] == -2147221164):
                # I think this is Class Not Registered.
                # Happens on Windows 98 after the XP ICS wizard has been run
                raise WindowsUPnPException("exception occurred, class not registered")
            else:
                raise

        # attach service, so the queue gets flushed
        self.traverser.attach_service(self)


    def register_port(self, mapping):
        try:
            self.port_collection.Add(mapping.external_port, mapping.protocol,
                                     mapping.internal_port, mapping.host,
                                     True, mapping.service_name)
            nat_logger.info("registered: " + str(mapping))
            mapping.d.callback(mapping.external_port)
        except pywintypes.com_error, e:
            # host == 'fake' or address already bound
            #if (e[2][5] == -2147024726):
            # host == '', or I haven't a clue
            #e.args[0] == -2147024894

            #mapping.d.errback(e)

            # detach self so the queue isn't flushed
            self.traverser.detach_service(self)

            if hasattr(mapping, 'original_external_port'):
                mapping.external_port = mapping.original_external_port
                del mapping.original_external_port

            # push this mapping back on the queue            
            self.traverser.register_requests.append(mapping)    

            # resume init services, because we know we've failed
            self.traverser.resume_init_services()

    def unregister_port(self, external_port, protocol):
        try:
            self.port_collection.Remove(external_port, protocol)
            nat_logger.info("unregisterd: %s, %s" % (external_port, protocol))
        except pywintypes.com_error, e:
            if (e[2][5] == -2147352567):
                UPNPError("Port %d:%s not bound" % (external_port, protocol))
            elif (e[2][5] == -2147221008):
                UPNPError("Port %d:%s is bound and is not ours to remove" % (external_port, protocol))
            elif (e[2][5] == -2147024894):
                UPNPError("Port %d:%s not bound (2)" % (external_port, protocol))
            else:
                raise

    def _list_ports(self):
        mappings = []

        try:
            for mp in self.port_collection:
                mapping = UPnPPortMapping(mp.ExternalPort, mp.InternalPort, mp.Protocol,
                                          mp.InternalClient, mp.Description)
                mappings.append(mapping)
        except pywintypes.com_error, e:
            # it's the "for mp in self.port_collection" iter that can throw
            # an exception.
            # com_error: (-2147220976, 'The owner of the PerUser subscription is
            #                           not logged on to the system specified',
            #             None, None)
            pass

        return mappings
            
