# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from defer import Deferred
from BTL.bencode import bencode, bdecode
import socket
from BitTorrent.RawServer_twisted import Handler
from BTL.platform import bttime
from BTL.translation import _
import time
from math import log10

import sys
from traceback import print_exc

from khash import distance
from BTL.cache import Cache
from KRateLimiter import KRateLimiter
from hammerlock import Hammerlock


from const import *

# commands
TID = 't'
REQ = 'q'
RSP = 'r'
TYP = 'y'
ARG = 'a'
ERR = 'e'

class KRPCFailSilently(Exception):
    pass

class KRPCProtocolError(Exception):
    pass

class KRPCServerError(Exception):
    pass

class KRPCSelfNodeError(Exception):
    pass

class hostbroker(Handler):       
    def __init__(self, server, addr, transport, call_later, max_ul_rate, config, rlcount):
        self.server = server
        self.addr = addr
        self.transport = transport
        self.rltransport = KRateLimiter(transport, max_ul_rate, call_later, rlcount, config['max_rate_period'])
        self.call_later = call_later
        self.connections = Cache(touch_on_access=True)
        self.hammerlock = Hammerlock(100, call_later)
        self.expire_connections(loop=True)
        self.config = config
        if not self.config.has_key('pause'):
            self.config['pause'] = False
        
    def expire_connections(self, loop=False):
        self.connections.expire(bttime() - KRPC_CONNECTION_CACHE_TIME)
        if loop:
            self.call_later(KRPC_CONNECTION_CACHE_TIME, self.expire_connections, True)

    def data_came_in(self, addr, datagram):
        #if addr != self.addr:
        if not self.config['pause'] and self.hammerlock.check(addr):
            c = self.connectionForAddr(addr)
            c.datagramReceived(datagram, addr)

    def connection_lost(self, socket):
        ## this is like, bad
        print ">>> connection lost!", socket

    def connectionForAddr(self, addr):
        if addr == self.addr:
            raise KRPCSelfNodeError()
        if not self.connections.has_key(addr):
            conn = KRPC(addr, self.server, self.transport, self.rltransport, self.call_later)
            self.connections[addr] = conn
        else:
            conn = self.connections[addr]
        return conn


## connection
class KRPC(object):
    __slots__ = ('noisy','call_later','transport','rltransport','factory','addr','tids','mtid','pinging')
    noisy = 0
    def __init__(self, addr, server, transport, rltransport, call_later):
        self.call_later = call_later
        self.transport = transport
        self.rltransport = rltransport        
        self.factory = server
        self.addr = addr
        self.tids = {}
        self.mtid = 0
        self.pinging = False
        
    def sendErr(self, addr, tid, code, msg):
        ## send error
        out = bencode({TID:tid, TYP:ERR, ERR :(code, msg)})
        olen = len(out)
        self.rltransport.sendto(out, 0, addr)
        return olen                 

    def datagramReceived(self, str, addr):
        # bdecode
        try:
            msg = bdecode(str)
        except Exception, e:
            if self.noisy:
                print "response decode error: " + `e`, `str`
        else:
            #if self.noisy:
            #    print msg
            # look at msg type
            if msg[TYP]  == REQ:
                ilen = len(str)
                # if request
                #	tell factory to handle
                f = getattr(self.factory ,"krpc_" + msg[REQ], None)
                msg[ARG]['_krpc_sender'] =  self.addr
                if f and callable(f):
                    try:
                        ret = apply(f, (), msg[ARG])
                    except KRPCFailSilently:
                        pass
                    except KRPCServerError, e:
                        olen = self.sendErr(addr, msg[TID], 202, "Server Error: %s" % e.args[0])
                    except KRPCProtocolError, e:
                        olen = self.sendErr(addr, msg[TID], 204, "Protocol Error: %s" % e.args[0])                        
                    except Exception, e:
                        print_exc(20)
                        olen = self.sendErr(addr, msg[TID], 202, "Server Error")
                    else:
                        if ret:
                            #	make response
                            out = bencode({TID : msg[TID], TYP : RSP, RSP : ret})
                        else:
                            out = bencode({TID : msg[TID], TYP : RSP, RSP : {}})
                        #	send response
                        olen = len(out)
                        self.rltransport.sendto(out, 0, addr)

                else:
                    if self.noisy:
                        #print "don't know about method %s" % msg[REQ]
                        pass
                    # unknown method
                    olen = self.sendErr(addr, msg[TID], *KERR_METHOD_UNKNOWN)
                if self.noisy:
                    try:
                        ndist = 10 * log10(2**160 * 1.0 / distance(self.factory.node.id, msg[ARG]['id']))
                        ndist = int(ndist)
                    except OverflowError:
                        ndist = 999

                    h = None
                    if msg[ARG].has_key('target'):
                        h = msg[ARG]['target']
                    elif msg[ARG].has_key('info_hash'):
                        h = msg[ARG]['info_hash']
                    else:
                        tdist = '-'

                    if h != None:
                        try:
                            tdist = 10 * log10(2**160 * 1.0 / distance(self.factory.node.id, h))
                            tdist = int(tdist)
                        except OverflowError:
                            tdist = 999

                    t = time.localtime()
                    t = "%2d-%2d-%2d %2d:%2d:%2d" % (t[0], t[1], t[2], t[3], t[4], t[5])
                    print "%s %s %s >>> %s - %s %s %s - %s %s" % (t,
                                                                  msg[ARG]['id'].encode('base64')[:4],
                                                                  addr,
                                                                  self.factory.node.port, 
                                                                  ilen,
                                                                  msg[REQ],
                                                                  olen,
                                                                  ndist,
                                                                  tdist)
            elif msg[TYP] == RSP:
                # if response
                # 	lookup tid
                if self.tids.has_key(msg[TID]):
                    df = self.tids[msg[TID]]
                    # 	callback
                    del(self.tids[msg[TID]])
                    df.callback({'rsp' : msg[RSP], '_krpc_sender': addr})
                else:
                    # no tid, this transaction timed out already...
                    pass
                
            elif msg[TYP] == ERR:
                # if error
                # 	lookup tid
                if self.tids.has_key(msg[TID]):
                    df = self.tids[msg[TID]]
                    # 	callback
                    df.errback(msg[ERR])
                    del(self.tids[msg[TID]])
                else:
                    # day late and dollar short
                    pass
            else:
                # unknown message type
                df = self.tids[msg[TID]]
                # 	callback
                df.errback((KRPC_ERROR_RECEIVED_UNKNOWN, _("received unknown message type")))
                del(self.tids[msg[TID]])
                
    def sendRequest(self, method, args):
        # make message
        # send it
        msg = {TID : chr(self.mtid), TYP : REQ,  REQ : method, ARG : args}
        self.mtid = (self.mtid + 1) % 256
        s = bencode(msg)
        d = Deferred()
        self.tids[msg[TID]] = d
        self.call_later(KRPC_TIMEOUT, self.timeOut, msg[TID])
        self.call_later(0, self._send, s, d)
        return d

    def timeOut(self, id):
        if self.tids.has_key(id):
            df = self.tids[id]
            del(self.tids[id])
            df.errback((KRPC_ERROR_TIMEOUT, _("timeout")))

    def _send(self, s, d):
        try:
            self.transport.sendto(s, 0, self.addr)
        except socket.error:
            d.errback((KRPC_SOCKET_ERROR, _("socket error")))
            
