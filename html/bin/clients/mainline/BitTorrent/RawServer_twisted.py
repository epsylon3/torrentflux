# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Greg Hazel

import os
import sys
import socket
import signal
import string
import struct
import thread
import logging

from BTL.translation import _
from BTL.obsoletepythonsupport import set
from BTL.defer import DeferredEvent, Failure
from BTL.SaneThreadedResolver import SaneThreadedResolver

##############################################################
profile = False

if profile:
    try:
        from BTL.profile import Profiler, Stats
        prof_file_name = 'rawserver.prof'
    except ImportError, e:
        print "profiling not available:", e
        profile = False
##############################################################

from twisted.python import threadable
# needed for twisted 1.3
# otherwise the 'thread safety' functions are not 'thread safe'
threadable.init(1)

from BTL.reactor_magic import noSignals, reactor, is_iocpreactor

# as far as I know, we work with twisted 1.3 and >= 2.0
#import twisted.copyright
#if twisted.copyright.version.split('.') < 2:
#    raise ImportError(_("RawServer_twisted requires twisted 2.0.0 or greater"))

from twisted.protocols.policies import TimeoutMixin
from twisted.internet.protocol import DatagramProtocol, Protocol, ClientFactory
from twisted.internet.threads import deferToThread
from twisted.internet import error, interfaces

from BTL.ConnectionRateLimitReactor import connectionRateLimitReactor

letters = set(string.letters)
main_thread = thread.get_ident()
rawserver_logger = logging.getLogger('RawServer')

NOLINGER = struct.pack('ii', 1, 0)

# python sucks.
SHUT_RD = getattr(socket, 'SHUT_RD', 0)
SHUT_WR = getattr(socket, 'SHUT_WR', 1)

# this is a base class for all the callbacks the server could use
class Handler(object):

    # called when the connection is being attempted
    def connection_starting(self, addr):
        pass

    # called when the connection is ready for writiing
    def connection_made(self, s):
        pass

    # called when a connection attempt failed (failed, refused, or requested)
    def connection_failed(self, s, exception):
        pass

    def data_came_in(self, addr, data):
        pass

    # called once when the current write buffer empties completely
    def connection_flushed(self, s):
        pass

    # called when a connection dies (lost or requested)
    def connection_lost(self, s):
        pass


class ConnectionWrapper(object):

    def __init__(self, rawserver, handler, context):
        if handler is None:
            raise ValueError("Handler should not be None")
        self.ip = None             # peer ip
        self.port = None           # peer port
        self.dying = False
        self.paused = False
        self.encrypt = None
        self.flushed = False
        self.connector = None
        self.transport = None
        self.write_open = True
        self.reset_timeout = None
        self.callback_connection = None

        self.post_init(rawserver, handler, context)

    def post_init(self, rawserver, handler, context):
        if handler is None:
            raise ValueError("Handler should not be None")
        self.rawserver = rawserver
        self.handler = handler
        self.context = context
        if self.rawserver:
            self.rawserver.single_sockets.add(self)

    def attach_connector(self, connector):
        self.connector = connector
        addr = connector.getDestination()
        try:
            self.ip = addr.host
            self.port = addr.port
        except:
            # unix sockets, for example
            pass

    def get_socket(self):
        s = None
        if interfaces.ISystemHandle.providedBy(self.transport):
            s = self.transport.getHandle()
        return s

    def pause_reading(self):
        # interfaces are the stupedist crap ever
        if (hasattr(interfaces.IProducer, "providedBy") and
            not interfaces.IProducer.providedBy(self.transport)):
            print "No producer", self.ip, self.port, self.transport
            return
        # not explicitly needed, but iocpreactor has a bug where the author is a moron
        if self.paused:
            return
        self.transport.pauseProducing()
        self.paused = True

    def resume_reading(self):
        if (hasattr(interfaces.IProducer, "providedBy") and
            not interfaces.IProducer.providedBy(self.transport)):
            print "No producer", self.ip, self.port, self.transport
            return
        # not explicitly needed, but iocpreactor has a bug where the author is a moron
        if not self.paused:
            return
        self.paused = False
        try:
            self.transport.resumeProducing()
        except Exception, e:
            # I bet these are harmless
            print "resumeProducing error", type(e), e

    def attach_transport(self, callback_connection, transport, reset_timeout):
        self.transport = transport
        self.callback_connection = callback_connection
        self.reset_timeout = reset_timeout

        if hasattr(self.transport, 'registerProducer'):
            # Multicast uses sendto, which does not buffer.
            # It has no producer api
            self.transport.registerProducer(self, False)

        try:
            addr = self.transport.getPeer()
        except:
            # udp, for example
            addr = self.transport.getHost()

        try:
            self.ip = addr.host
            self.port = addr.port
        except:
            # unix sockets, for example
            pass

        tos = self.rawserver.config.get('peer_socket_tos', 0)
        if tos != 0:
            s = self.get_socket()

            try:
                s.setsockopt(socket.IPPROTO_IP, socket.IP_TOS, tos)
            except socket.error:
                pass

    def sendto(self, packet, flags, addr):
        ret = None
        try:
            ret = self.transport.write(packet, addr)
        except:
            # dont be so noisy here
            pass
            # rawserver_logger.warning("UDP sendto failed", exc_info=sys.exc_info())

        return ret

    def write(self, b):
        self.flushed = False
        if not self.write_open:
            return
        if self.encrypt is not None:
            b = self.encrypt(b)
        # bleh
        if isinstance(b, buffer):
            b = str(b)
        self.transport.write(b)

    def resumeProducing(self):
        self.flushed = True
        # why do you tease me so?
        if self.handler is not None:
            # calling flushed from the write is bad form
            self.rawserver.add_task(0, self.handler.connection_flushed, self)

    def pauseProducing(self):
        # auto pause by not resuming
        pass

    def stopProducing(self):
        self.write_open = False

    def is_flushed(self):
        return self.flushed

    def shutdown(self, how):
        if how == SHUT_WR:
            if hasattr(self.transport, "loseWriteConnection"):
                self.transport.loseWriteConnection()
            else:
                # twisted 1.3 sucks
                try:
                    self.transport.socket.shutdown(how)
                except:
                    pass
        elif how == SHUT_RD:
            self.transport.stopListening()
        else:
            self.close()

    def stopConnecting(self):
        return self.connector.stopConnecting()

    def close(self):
        self.stopProducing()

        if self.rawserver.config.get('close_with_rst', True):
            try:
                s = self.get_socket()
                s.setsockopt(socket.SOL_SOCKET, socket.SO_LINGER, NOLINGER)
            except:
                pass

        if self.transport:
            try:
                self.transport.unregisterProducer()
            except KeyError:
                # bug in iocpreactor: http://twistedmatrix.com/trac/ticket/1657
                pass
            if (hasattr(self.transport, 'protocol') and
                isinstance(self.transport.protocol, CallbackDatagramProtocol)):
                # udp connections should only call stopListening
                self.transport.stopListening()
            else:
                self.transport.loseConnection()
        elif self.connector:
            self.connector.disconnect()

    def _cleanup(self):

        self.handler = None

        del self.transport
        del self.connector
        del self.context

        if self.callback_connection:
            if self.callback_connection.can_timeout:
                self.callback_connection.setTimeout(None)
            self.callback_connection.connection = None
            del self.callback_connection


class CallbackConnection(object):

    def attachTransport(self, transport, s):
        s.attach_transport(self, transport=transport,
                           reset_timeout=self.optionalResetTimeout)
        self.connection = s

    def connectionMade(self):
        s = self.connection
        s.handler.connection_made(s)
        self.optionalResetTimeout()

        self.factory.rawserver.connectionMade(s)

    def connectionLost(self, reason):
        reactor.callLater(0, self.post_connectionLost, reason)

    # twisted api inconsistancy workaround
    # sometimes connectionLost is called (not queued) from inside write()
    def post_connectionLost(self, reason):
        s = self.connection
        #print s.ip, s.port, reason.getErrorMessage()
        self.factory.rawserver._remove_socket(s, was_connected=True)

    def dataReceived(self, data):
        self.optionalResetTimeout()

        s = self.connection
        s.rawserver._make_wrapped_call(s.handler.data_came_in,
                                       s, data, wrapper=s)

    def datagramReceived(self, data, (host, port)):
        s = self.connection
        s.rawserver._make_wrapped_call(s.handler.data_came_in,
                                       (host, port), data, wrapper=s)

    def optionalResetTimeout(self):
        if self.can_timeout:
            self.resetTimeout()


class CallbackProtocol(CallbackConnection, TimeoutMixin, Protocol):

    def makeConnection(self, transport):
        self.attachTransport(transport, self.wrapper)

        self.can_timeout = True
        self.setTimeout(self.factory.rawserver.config.get('socket_timeout', 30))

        return Protocol.makeConnection(self, transport)


class CallbackDatagramProtocol(CallbackConnection, DatagramProtocol):

    def startProtocol(self):
        self.can_timeout = False
        self.attachTransport(self.transport, self.connection)
        return DatagramProtocol.startProtocol(self)


class ConnectionFactory(ClientFactory):

    protocol = CallbackProtocol

    def __init__(self, rawserver, outgoing):
        self.rawserver = rawserver
        self.outgoing = outgoing

    def add_connection_data(self, data):
        self.data = data

    def get_connection_data(self):
        return self.data

    def get_wrapper(self):
        if self.outgoing:
            wrapper = self.get_connection_data()
        else:
            args = self.get_connection_data()
            wrapper = ConnectionWrapper(*args)
        return wrapper

    def startedConnecting(self, connector):
        peer = connector.getDestination()
        addr = (peer.host, peer.port)
        wrapper = self.get_wrapper()
        if wrapper.handler is not None:
            wrapper.handler.connection_starting(addr)

    def buildProtocol(self, addr):
        protocol = ClientFactory.buildProtocol(self, addr)
        protocol.wrapper = self.get_wrapper()
        return protocol

    def clientConnectionFailed(self, connector, reason):
        wrapper = self.get_wrapper()

        # opt-out
        if not wrapper.dying:
            # this might not work - reason is not an exception
            wrapper.handler.connection_failed(wrapper, reason)
            wrapper.dying = True

        self.rawserver._remove_socket(wrapper)


# storage for socket creation requestions, and proxy once the connection is made
class SocketRequestProxy(object):

    def __init__(self, port, bind, protocol):
        self.port = port
        self.bind = bind
        self.protocol = protocol
        self.connection = None

    def __getattr__(self, name):
        return getattr(self.connection, name)

    def close(self):
        # closing the proxy doesn't mean anything.
        # you can stop_listening(), and then start again.
        # the socket only exists while it is listening
        if self.connection:
            self.connection.close()


class RawServerMixin(object):

    def __init__(self, config=None, noisy=True):
        self.noisy = noisy
        self.config = config
        if not self.config:
            self.config = {}
        self.sigint_flag = None
        self.sigint_installed = False

    # going away soon. call _context_wrap on the context.
    def _make_wrapped_call(self, _f, *args, **kwargs):
        wrapper = kwargs.pop('wrapper', None)
        try:
            _f(*args, **kwargs)
        except KeyboardInterrupt:
            raise
        except Exception, e:         # hopefully nothing raises strings
            # Incoming sockets can be assigned to a particular torrent during
            # a data_came_in call, and it's possible (though not likely) that
            # there could be a torrent-specific exception during the same call.
            # Therefore read the context after the call.
            context = None
            if wrapper is not None:
                context = wrapper.context
            if context is not None:
                context.got_exception(Failure())
            elif self.noisy:
                rawserver_logger.exception("Error in _make_wrapped_call for %s",
                                           _f.__name__)

    # must be called from the main thread
    def install_sigint_handler(self, flag = None):
        if flag is not None:
            self.sigint_flag = flag
        signal.signal(signal.SIGINT, self._handler)
        self.sigint_installed = True

    def _handler(self, signum, frame):
        if self.sigint_flag:
            self.external_add_task(0, self.sigint_flag.set)
        elif self.doneflag:
            self.external_add_task(0, self.doneflag.set)

        # Allow pressing ctrl-c multiple times to raise KeyboardInterrupt,
        # in case the program is in an infinite loop
        signal.signal(signal.SIGINT, signal.default_int_handler)


class RawServer(RawServerMixin):
    """RawServer encapsulates I/O and task scheduling.

       I/O corresponds to the arrival data on a file descriptor,
       and a task is a scheduled callback.  A task is scheduled
       using add_task or external_add_task.  add_task is used from within the
       thread running the RawServer, external_add_task from other threads.

       tracker.py provides a simple example of how to use RawServer.

        1. creates an instance of RawServer

            r = RawServer(config)

        2. creates a socket by a call to create_serversocket.

            s = r.create_serversocket(config['port'], config['bind'])

        3. tells the raw server to listen to the socket and associate
           a protocol handler with the socket.

            r.start_listening(s,
                HTTPHandler(t.get, config['min_time_between_log_flushes']))

        4. tells the raw_server to listen for I/O or scheduled tasks
           until the r.stop() is called.

            r.listen_forever()

           When a remote client opens a connection, a new socket is
           returned from the server socket's accept method and the
           socket is assigned the same handler as was assigned to the
           server socket.

           As data arrives on a socket, the handler's data_came_in
           member function is called.  It is up to the handler to
           interpret the data and/or pass it on to other objects.
           In the tracker, the HTTP protocol handler passes the arriving data
           to an HTTPConnector object which maintains state specific
           to a given connection.

         For outgoing connections, the call start_connection() is used.
         """


    def __init__(self, config=None, noisy=True):
        """config is a dict that contains option-value pairs.
        """
        RawServerMixin.__init__(self, config, noisy)

        self.doneflag = None

        # init is fine until the loop starts
        self.ident = thread.get_ident()
        self.associated = False

        self.single_sockets = set()
        self.unix_sockets = set()
        self.udp_sockets = set()
        self.listened = False
        self.connections = 0

        ##############################################################
        if profile:
            try:
                os.unlink(prof_file_name)
            except:
                pass
            self.prof = Profiler()
        ##############################################################

        self.connection_limit = self.config.get('max_incomplete', 10)
        connectionRateLimitReactor(reactor, self.connection_limit)

        # bleh
        self.add_pending_connection = reactor.add_pending_connection
        self.remove_pending_connection = reactor.remove_pending_connection
        self.reactor = reactor

        self.reactor.resolver = SaneThreadedResolver(self.reactor)

        #from twisted.internet import task
        #l2 = task.LoopingCall(self._print_connection_count)
        #l2.start(1)


    ##############################################################
    def _print_connection_count(self):
        def _sl(x):
            if hasattr(x, "__len__"):
                return str(len(x))
            else:
                return str(x)

        c = len(self.single_sockets)
        u = len(self.udp_sockets)
        c -= u
        #s = "Connections(" + str(id(self)) + "): tcp(" + str(c) + ") upd(" + str(u) + ")"
        #rawserver_logger.debug(s)

        d = dict()
        for s in self.single_sockets:
            state = "None"
            if not s.dying and s.transport:
                try:
                    state = s.transport.state
                except:
                    state = "has transport"
            else:
                state = "No transport"
            if state not in d:
                d[state] = 0
            d[state] += 1
        #rawserver_logger.debug(d)
        print d

        sizes = "cc(" + _sl(self.connections)
        sizes += ") ss(" + _sl(self.single_sockets)
        sizes += ") us(" + _sl(self.udp_sockets) + ")"
        #rawserver_logger.debug(sizes)
        print sizes
    ##############################################################

    def get_remote_endpoints(self):
        addrs = [(s.ip, s.port) for s in self.single_sockets]
        return addrs

##    def add_task(self, delay, _f, *args, **kwargs):
##        """Schedule the passed function 'func' to be called after
##           'delay' seconds and pass the 'args'.
##
##           This should only be called by RawServer's thread."""
##        #assert thread.get_ident() == self.ident
##        return reactor.callLater(delay, _f, *args, **kwargs)
    add_task = reactor.callLater

    def external_add_task(self, delay, _f, *args, **kwargs):
        """Schedule the passed function 'func' to be called after
           'delay' seconds and pass 'args'.

           This should be called by threads other than RawServer's thread."""
        if delay == 0:
            return reactor.callFromThread(_f, *args, **kwargs)
        else:
            return reactor.callFromThread(reactor.callLater, delay,
                                          _f, *args, **kwargs)

    def create_unixserversocket(self, filename):
        s = SocketRequestProxy(0, filename, 'unix')

        factory = ConnectionFactory(self, outgoing=False)
        s.listening_port = reactor.listenUNIX(s.bind, factory)
        s.factory = factory
        s.listening_port.listening = True

        return s

    def create_serversocket(self, port, bind=''):
        s = SocketRequestProxy(port, bind, 'tcp')

        factory = ConnectionFactory(self, outgoing=False)
        try:
            s.listening_port = reactor.listenTCP(s.port, factory,
                                                 interface=s.bind)
        except error.CannotListenError, e:
            if e[0] != 0:
                raise e.socketError
            else:
                raise
        s.factory = factory
        s.listening_port.listening = True

        return s

    def _create_udpsocket(self, port, bind, create_func):
        s = SocketRequestProxy(port, bind, 'udp')

        protocol = CallbackDatagramProtocol()

        c = ConnectionWrapper(self, Handler(), None)
        s.connection = c
        protocol.connection = c

        try:
            s.listening_port = create_func(s.port, protocol, interface=s.bind)
        except error.CannotListenError, e:
            raise e.socketError
        s.listening_port.listening = True

        return s

    def create_udpsocket(self, port, bind=''):
        return self._create_udpsocket(port, bind,
                                      create_func = reactor.listenUDP)

    def create_multicastsocket(self, port, bind=''):
        return self._create_udpsocket(port, bind,
                                      create_func = reactor.listenMulticast)

    def _start_listening(self, s):
        if not s.listening_port.listening:
            s.listening_port.startListening()
            s.listening_port.listening = True

    def start_listening(self, serversocket, handler, context=None):
        data = (self, handler, context)
        serversocket.factory.add_connection_data(data)

        self._start_listening(serversocket)

    def start_listening_udp(self, serversocket, handler, context=None):
        c = serversocket.connection
        c.post_init(self, handler, context)

        self._start_listening(serversocket)

        self.udp_sockets.add(c)

    start_listening_multicast = start_listening_udp

    def stop_listening(self, serversocket):
        listening_port = serversocket.listening_port
        try:
            listening_port.stopListening()
        except AttributeError:
            # AttributeError: 'MulticastPort' object has no attribute 'handle_disconnected_stopListening'
            # sigh.
            pass
        listening_port.listening = False

    def stop_listening_udp(self, serversocket):
        self.stop_listening(serversocket)

        self.udp_sockets.remove(serversocket.connection)
        self.single_sockets.remove(serversocket.connection)

    stop_listening_multicast = stop_listening_udp

    def start_connection(self, dns, handler, context=None, do_bind=True,
                         timeout=30, urgent=False):
        """creates the client-side of a connection and associates it with
           the passed handler.  Data received on this conneciton are passed
           to the handler's data_came_in method."""
        addr = dns[0]
        port = int(dns[1])

        if len(letters.intersection(addr)) > 0:
            rawserver_logger.warning("Don't pass host names to RawServer")
            # this blocks, that's why we throw the warning
            addr = socket.gethostbyname(addr)

        bindaddr = None
        if do_bind:
            bindaddr = self.config.get('bind', '')
            if isinstance(bindaddr, str) and len(bindaddr) >= 0:
                bindaddr = (bindaddr, 0)
            else:
                bindaddr = None

        if handler is None:
            raise ValueError("Handler should not be None")

        c = ConnectionWrapper(self, handler, context)

        factory = ConnectionFactory(self, outgoing=True)
        factory.add_connection_data(c)

        if self.connection_limit:
            connector = reactor.connectTCP(addr, port, factory,
                                           owner=id(context),
                                           bindAddress=bindaddr, timeout=timeout,
                                           urgent=urgent)
        else:
            connector = reactor.connectTCP(addr, port, factory,
                                           bindAddress=bindaddr, timeout=timeout)

        c.attach_connector(connector)

        self.single_sockets.add(c)
        return c

    def associate_thread(self):
        assert not self.associated, \
               "RawServer has already been associated with a thread"
        self.ident = thread.get_ident()
        reactor.ident = self.ident
        self.associated = True

    def listen_forever(self, doneflag=None):
        """Main event processing loop for RawServer.
           RawServer listens until the doneFlag is set by some other
           thread.  The doneFlag tells all threads to clean-up and then
           exit."""

        if not doneflag:
            doneflag = DeferredEvent()
        assert isinstance(doneflag, DeferredEvent)
        self.doneflag = doneflag

        if not self.associated:
            self.associate_thread()

        if self.listened:
            Exception(_("listen_forever() should only be called once per reactor."))

        if main_thread == thread.get_ident() and not self.sigint_installed:
            self.install_sigint_handler()

        if is_iocpreactor and main_thread == thread.get_ident():
            def pulse():
                self.add_task(1, pulse)
            pulse()

        reactor.callLater(0, self.doneflag.addCallback, self._safestop)
        self.listened = True

        reactor.suggestThreadPoolSize(3)

        if profile:
            self.prof.enable()

        if noSignals:
            reactor.run(installSignalHandlers=False)
        else:
            reactor.run()

        if profile:
            self.prof.disable()
            st = Stats(self.prof.getstats())
            st.sort()
            f = open(prof_file_name, 'wb')
            st.dump(file=f)

    def listen_once(self, period=1e9):
        rawserver_logger.warning(_("listen_once() might not return until there "
                                   "is activity, and might not process the "
                                   "event you want. Use listen_forever()."))
        reactor.iterate(period)

    def stop(self):
        if self.doneflag and not self.doneflag.isSet():
            self.doneflag.set()

    def _safestop(self, r=None):
        if not threadable.isInIOThread():
            self.external_add_task(0, self._stop)
        else:
            self._stop()

    def _stop(self, r=None):
        assert thread.get_ident() == self.ident

        connections = list(self.single_sockets)
        for connection in connections:
            try:
                connection.close()
            except:
                pass

        reactor.suggestThreadPoolSize(0)
        try:
            reactor.stop()
        except RuntimeError:
            # exceptions.RuntimeError: can't stop reactor that isn't running
            pass

    def _remove_socket(self, s, was_connected=False):
        # opt-out
        if not s.dying:
            self._make_wrapped_call(s.handler.connection_lost, s, wrapper=s)

        s._cleanup()

        self.single_sockets.remove(s)

        if was_connected:
            self.connections -= 1

    def connectionMade(self, s):
        self.connections += 1

    def gethostbyname(self, name):
        return self.reactor.resolve(name)

    def gethostbyaddr(self, addr):
        return deferToThread(socket.gethostbyaddr, addr)
