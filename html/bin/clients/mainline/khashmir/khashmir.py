# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import const
from socket import gethostbyname

from BTL.platform import bttime as time

from sha import sha
import re
from BitTorrent.defaultargs import common_options, rare_options
from BitTorrent.RawServer_twisted import RawServer

from ktable import KTable, K
from knode import *
from kstore import KStore
from khash import newID, newIDInRange

from util import packNodes
from actions import FindNode, GetValue, KeyExpirer, StoreValue
import krpc

import sys
import os
import traceback

from BTL.bencode import bencode, bdecode

from defer import Deferred
from random import randrange
from kstore import sample

from BTL.stackthreading import Event, Thread

ip_pat = re.compile('[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}')

class KhashmirDBExcept(Exception):
    pass

def foo(bytes):
    pass
    
# this is the base class, has base functionality and find node, no key-value mappings
class KhashmirBase:
    _Node = KNodeBase
    def __init__(self, host, port, data_dir, rawserver=None, max_ul_rate=1024, checkpoint=True, errfunc=None, rlcount=foo, config={'pause':False, 'max_rate_period':20}):
        if rawserver:
            self.rawserver = rawserver
        else:
            self.flag = Event()
            d = dict([(x[0],x[1]) for x in common_options + rare_options])            
            self.rawserver = RawServer(self.flag, d)
        self.max_ul_rate = max_ul_rate
        self.socket = None
        self.config = config
        self.setup(host, port, data_dir, rlcount, checkpoint)

    def setup(self, host, port, data_dir, rlcount, checkpoint=True):
        self.host = host
        self.port = port
        self.ddir = data_dir
        self.store = KStore()
        self.pingcache = {}
        self.socket = self.rawserver.create_udpsocket(self.port, self.host)
        self.udp = krpc.hostbroker(self, (self.host, self.port), self.socket, self.rawserver.add_task, self.max_ul_rate, self.config, rlcount)
        self._load()
        self.rawserver.start_listening_udp(self.socket, self.udp)
        self.last = time()
        KeyExpirer(self.store, self.rawserver.add_task)
        self.refreshTable(force=1)
        if checkpoint:
            self.rawserver.add_task(30, self.findCloseNodes, lambda a: a, True)
            self.rawserver.add_task(60, self.checkpoint, 1)

    def Node(self):
        n = self._Node(self.udp.connectionForAddr)
        n.table = self
        return n
    
    def __del__(self):
        if self.socket is not None:
            self.rawserver.stop_listening_udp(self.socket)
            self.socket.close()
        
    def _load(self):
        do_load = False
        try:
            s = open(os.path.join(self.ddir, "routing_table"), 'r').read()
            dict = bdecode(s)
        except:
            id = newID()
        else:
            id = dict['id']
            do_load = True
            
        self.node = self._Node(self.udp.connectionForAddr).init(id, self.host, self.port)
        self.table = KTable(self.node)
        if do_load:
            self._loadRoutingTable(dict['rt'])

        
    def checkpoint(self, auto=0):
        d = {}
        d['id'] = self.node.id
        d['rt'] = self._dumpRoutingTable()
        try:
            f = open(os.path.join(self.ddir, "routing_table"), 'wb')
            f.write(bencode(d))
            f.close()
        except Exception, e:
            #XXX real error here
            print ">>> unable to dump routing table!", str(e)
            pass
        
        
        if auto:
            self.rawserver.add_task(randrange(int(const.CHECKPOINT_INTERVAL * .9),
                                              int(const.CHECKPOINT_INTERVAL * 1.1)),
                                    self.checkpoint, 1)
        
    def _loadRoutingTable(self, nodes):
        """
            load routing table nodes from database
            it's usually a good idea to call refreshTable(force=1) after loading the table
        """
        for rec in nodes:
            n = self.Node().initWithDict(rec)
            self.table.insertNode(n, contacted=0, nocheck=True)

    def _dumpRoutingTable(self):
        """
            save routing table nodes to the database
        """
        l = []
        for bucket in self.table.buckets:
            for node in bucket.l:
                l.append({'id':node.id, 'host':node.host, 'port':node.port, 'age':int(node.age)})
        return l
        
            
    def _addContact(self, host, port, callback=None):
        """
            ping this node and add the contact info to the table on pong!
        """
        n =self.Node().init(const.NULL_ID, host, port)
        try:
            self.sendPing(n, callback=callback)
        except krpc.KRPCSelfNodeError:
            # our own node
            pass


    #######
    #######  LOCAL INTERFACE    - use these methods!
    def addContact(self, ip, port, callback=None):
        """
            ping this node and add the contact info to the table on pong!
        """
        if ip_pat.match(ip):
            self._addContact(ip, port)
        else:
            def go(ip=ip, port=port):
                ip = gethostbyname(ip)
                self.rawserver.external_add_task(0, self._addContact, ip, port)
            t = Thread(target=go)
            t.start()


    ## this call is async!
    def findNode(self, id, callback, errback=None):
        """ returns the contact info for node, or the k closest nodes, from the global table """
        # get K nodes out of local table/cache, or the node we want
        nodes = self.table.findNodes(id, invalid=True)
        l = [x for x in nodes if x.invalid]
        if len(l) > 4:
            nodes = sample(l , 4) + self.table.findNodes(id, invalid=False)[:4]

        d = Deferred()
        if errback:
            d.addCallbacks(callback, errback)
        else:
            d.addCallback(callback)
        if len(nodes) == 1 and nodes[0].id == id :
            d.callback(nodes)
        else:
            # create our search state
            state = FindNode(self, id, d.callback, self.rawserver.add_task)
            self.rawserver.external_add_task(0, state.goWithNodes, nodes)
    
    def insertNode(self, n, contacted=1):
        """
        insert a node in our local table, pinging oldest contact in bucket, if necessary
        
        If all you have is a host/port, then use addContact, which calls this method after
        receiving the PONG from the remote node.  The reason for the seperation is we can't insert
        a node into the table without it's peer-ID.  That means of course the node passed into this
        method needs to be a properly formed Node object with a valid ID.
        """
        old = self.table.insertNode(n, contacted=contacted)
        if old and old != n:
            if not old.inPing():
                self.checkOldNode(old, n, contacted)
            else:
                l = self.pingcache.get(old.id, [])
                if (len(l) < 10 or contacted) and len(l) < 15:
                    l.append((n, contacted))
                    self.pingcache[old.id] = l

                

    def checkOldNode(self, old, new, contacted=False):
        ## these are the callbacks used when we ping the oldest node in a bucket

        def cmp(a, b):
            if a[1] == 1 and b[1] == 0:
                return -1
            elif b[1] == 1 and a[1] == 0:
                return 1
            else:
                return 0
            
        def _staleNodeHandler(dict, old=old, new=new, contacted=contacted):
            """ called if the pinged node never responds """
            if old.fails >= 2:
                l = self.pingcache.get(old.id, [])
                l.sort(cmp)
                if l:
                    n, nc = l[0]
                    if (not contacted) and nc:
                        l = l[1:] + [(new, contacted)]
                        new = n
                        contacted = nc
                o = self.table.replaceStaleNode(old, new)
                if o and o != new:
                    self.checkOldNode(o, new)
                    try:
                        self.pingcache[o.id] = self.pingcache[old.id]
                        del(self.pingcache[old.id])
                    except KeyError:
                        pass
                else:
                    if l:
                        del(self.pingcache[old.id])
                        l.sort(cmp)
                        l = l[:5]
                        for node in l:
                            self.insertNode(node[0], node[1])
            else:
                l = self.pingcache.get(old.id, [])
                if l:
                    del(self.pingcache[old.id])
                self.insertNode(new, contacted)
                l = l[:5]
                for node in l:
                    self.insertNode(node[0], node[1])
                    
        def _notStaleNodeHandler(dict, old=old, new=new, contacted=contacted):
            """ called when we get a pong from the old node """
            self.table.insertNode(old, True)
            self.insertNode(new, contacted)
            l = self.pingcache.get(old.id, [])
            l.sort(cmp)
            l = l[:5]
            for node in l:
                self.insertNode(node[0], node[1])
            try:
                del(self.pingcache[old.id])
            except KeyError:
                pass
        try:
            df = old.ping(self.node.id)
        except krpc.KRPCSelfNodeError:
            pass
        df.addCallbacks(_notStaleNodeHandler, _staleNodeHandler)

    def sendPing(self, node, callback=None):
        """
            ping a node
        """
        try:
            df = node.ping(self.node.id)
        except krpc.KRPCSelfNodeError:
            pass
        else:
            ## these are the callbacks we use when we issue a PING
            def _pongHandler(dict, node=node, table=self.table, callback=callback):
                _krpc_sender = dict['_krpc_sender']
                dict = dict['rsp']
                sender = {'id' : dict['id']}
                sender['host'] = _krpc_sender[0]
                sender['port'] = _krpc_sender[1]
                n = self.Node().initWithDict(sender)
                table.insertNode(n)
                if callback:
                    callback()
            def _defaultPong(err, node=node, table=self.table, callback=callback):
                if callback:
                    callback()

            df.addCallbacks(_pongHandler,_defaultPong)

    def findCloseNodes(self, callback=lambda a: a, auto=False):
        """
            This does a findNode on the ID one away from our own.  
            This will allow us to populate our table with nodes on our network closest to our own.
            This is called as soon as we start up with an empty table
        """
        if not self.config['pause']:
            id = self.node.id[:-1] + chr((ord(self.node.id[-1]) + 1) % 256)
            self.findNode(id, callback)
        if auto:
            if not self.config['pause']:
                self.refreshTable()
            self.rawserver.external_add_task(randrange(int(const.FIND_CLOSE_INTERVAL *0.9),
                                                       int(const.FIND_CLOSE_INTERVAL *1.1)),
                                             self.findCloseNodes, lambda a: True, True)

    def refreshTable(self, force=0):
        """
            force=1 will refresh table regardless of last bucket access time
        """
        def callback(nodes):
            pass

        refresh = [bucket for bucket in self.table.buckets if force or (len(bucket.l) < K) or len(filter(lambda a: a.invalid, bucket.l)) or (time() - bucket.lastAccessed > const.BUCKET_STALENESS)]
        for bucket in refresh:
            id = newIDInRange(bucket.min, bucket.max)
            self.findNode(id, callback)

    def stats(self):
        """
        Returns (num_contacts, num_nodes)
        num_contacts: number contacts in our routing table
        num_nodes: number of nodes estimated in the entire dht
        """
        num_contacts = reduce(lambda a, b: a + len(b.l), self.table.buckets, 0)
        num_nodes = const.K * (2**(len(self.table.buckets) - 1))
        return {'num_contacts':num_contacts, 'num_nodes':num_nodes}

    def krpc_ping(self, id, _krpc_sender):
        sender = {'id' : id}
        sender['host'] = _krpc_sender[0]
        sender['port'] = _krpc_sender[1]        
        n = self.Node().initWithDict(sender)
        self.insertNode(n, contacted=0)
        return {"id" : self.node.id}
        
    def krpc_find_node(self, target, id, _krpc_sender):
        nodes = self.table.findNodes(target, invalid=False)
        nodes = map(lambda node: node.senderDict(), nodes)
        sender = {'id' : id}
        sender['host'] = _krpc_sender[0]
        sender['port'] = _krpc_sender[1]        
        n = self.Node().initWithDict(sender)
        self.insertNode(n, contacted=0)
        return {"nodes" : packNodes(nodes), "id" : self.node.id}


## This class provides read-only access to the DHT, valueForKey
## you probably want to use this mixin and provide your own write methods
class KhashmirRead(KhashmirBase):
    _Node = KNodeRead
    def retrieveValues(self, key):
        try:
            l = self.store[key]
        except KeyError:
            l = []
        return l
    ## also async
    def valueForKey(self, key, callback, searchlocal = 1):
        """ returns the values found for key in global table
            callback will be called with a list of values for each peer that returns unique values
            final callback will be an empty list - probably should change to 'more coming' arg
        """
        nodes = self.table.findNodes(key)
        
        # get locals
        if searchlocal:
            l = self.retrieveValues(key)
            if len(l) > 0:
                self.rawserver.external_add_task(0, callback, l)
        else:
            l = []
        
        # create our search state
        state = GetValue(self, key, callback, self.rawserver.add_task)
        self.rawserver.external_add_task(0, state.goWithNodes, nodes, l)

    def krpc_find_value(self, key, id, _krpc_sender):
        sender = {'id' : id}
        sender['host'] = _krpc_sender[0]
        sender['port'] = _krpc_sender[1]        
        n = self.Node().initWithDict(sender)
        self.insertNode(n, contacted=0)
    
        l = self.retrieveValues(key)
        if len(l) > 0:
            return {'values' : l, "id": self.node.id}
        else:
            nodes = self.table.findNodes(key, invalid=False)
            nodes = map(lambda node: node.senderDict(), nodes)
            return {'nodes' : packNodes(nodes), "id": self.node.id}

###  provides a generic write method, you probably don't want to deploy something that allows
###  arbitrary value storage
class KhashmirWrite(KhashmirRead):
    _Node = KNodeWrite
    ## async, callback indicates nodes we got a response from (but no guarantee they didn't drop it on the floor)
    def storeValueForKey(self, key, value, callback=None):
        """ stores the value for key in the global table, returns immediately, no status 
            in this implementation, peers respond but don't indicate status to storing values
            a key can have many values
        """
        def _storeValueForKey(nodes, key=key, value=value, response=callback , table=self.table):
            if not response:
                # default callback
                def _storedValueHandler(sender):
                    pass
                response=_storedValueHandler
            action = StoreValue(self, key, value, response, self.rawserver.add_task)
            self.rawserver.external_add_task(0, action.goWithNodes, nodes)
            
        # this call is asynch
        self.findNode(key, _storeValueForKey)
                    
    def krpc_store_value(self, key, value, id, _krpc_sender):
        t = "%0.6f" % time()
        self.store[key] = value
        sender = {'id' : id}
        sender['host'] = _krpc_sender[0]
        sender['port'] = _krpc_sender[1]        
        n = self.Node().initWithDict(sender)
        self.insertNode(n, contacted=0)
        return {"id" : self.node.id}

# the whole shebang, for testing
class Khashmir(KhashmirWrite):
    _Node = KNodeWrite
