# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from BTL.platform import bttime as time

import const

from khash import intify
from ktable import KTable, K
from util import unpackNodes
from krpc import KRPCProtocolError, KRPCSelfNodeError
from bisect import insort

class NodeWrap(object):
    def __init__(self, node, target):
        self.target = target
        self.node = node

    def __cmp__(self, o):
        """ this function is for sorting nodes relative to the ID we are looking for """
        y, x = self.target ^ o.node.num, self.target ^ self.node.num
        if x > y:
            return 1
        elif x < y:
            return -1
        return 0
        
class ActionBase(object):
    """ base class for some long running asynchronous proccesses like finding nodes or values """
    def __init__(self, table, target, callback, callLater):
        self.table = table
        self.target = target
        self.callLater = callLater
        self.num = intify(target)
        self.found = {}
        self.foundq = []
        self.queried = {}
        self.queriedip = {}
        self.answered = {}
        self.answeredq = []
        self.callback = callback
        self.outstanding = 0
        self.finished = 0
    
    def sort(self, a, b):
        """ this function is for sorting nodes relative to the ID we are looking for """
        x, y = self.num ^ a.num, self.num ^ b.num
        if x > y:
            return 1
        elif x < y:
            return -1
        return 0

    def shouldQuery(self, node):
        if node.id == self.table.node.id:
            return False
        elif (node.host, node.port) not in self.queriedip and node.id not in self.queried:
            self.queriedip[(node.host, node.port)] = 1
            self.queried[node.id] = 1
            return True
        return False
    
    def _cleanup(self):
        self.foundq = None
        self.found = None
        self.queried = None
        self.queriedip = None

    def goWithNodes(self, t):
        pass
    
    

FIND_NODE_TIMEOUT = 15

class FindNode(ActionBase):
    """ find node action merits it's own class as it is a long running stateful process """
    def handleGotNodes(self, dict):
        _krpc_sender = dict['_krpc_sender']
        dict = dict['rsp']
        sender = {'id' : dict["id"]}
        sender['port'] = _krpc_sender[1]        
        sender['host'] = _krpc_sender[0]        
        sender = self.table.Node().initWithDict(sender)
        try:
            l = unpackNodes(dict.get("nodes", []))
            if not self.answered.has_key(sender.id):
                self.answered[sender.id] = sender
            insort(self.answeredq, NodeWrap(sender, self.num))
        except:
            l = []
            self.table.invalidateNode(sender)
            
        if self.finished:
            # a day late and a dollar short
            return
        self.outstanding = self.outstanding - 1
        for node in l:
            n = self.table.Node().initWithDict(node)
            if not self.found.has_key(n.id) and not self.queried.has_key(n.id):
                self.found[n.id] = n
                insort(self.foundq, NodeWrap(n, self.num))
                self.table.insertNode(n, contacted=0)
        self.schedule()

    def finish(self, result):
        self.finished=1
        self._cleanup()
        self.callLater(0, self.callback, result)        
        
    def schedule(self):
        """
            send messages to new peers, if necessary
        """
        if self.finished:
            return
        for wrapper in self.foundq:
            node = wrapper.node
            if self.shouldQuery(node):
                if len(self.answeredq) >= K and self.answeredq[K-1] < wrapper:
                    break
                #xxxx t.timeout = time.time() + FIND_NODE_TIMEOUT
                try:
                    df = node.findNode(self.target, self.table.node.id)
                except KRPCSelfNodeError:
                    pass
                else:
                    df.addCallbacks(self.handleGotNodes, self.makeMsgFailed(node))
                    self.outstanding = self.outstanding + 1
            if self.outstanding >= const.CONCURRENT_REQS:
                break
        assert(self.outstanding) >=0
        if self.outstanding == 0:
            self.finish(self.answeredq[:K])

    def makeMsgFailed(self, node):
        return lambda err : self._defaultGotNodes(err, node)

    def _defaultGotNodes(self, err, node):
        self.outstanding = self.outstanding - 1
        self.schedule()

    def goWithNodes(self, nodes):
        """
            this starts the process, our argument is a transaction with t.extras being our list of nodes
            it's a transaction since we got called from the dispatcher
        """
        for node in nodes:
            if node.id == self.table.node.id:
                continue
            else:
                self.found[node.id] = node
                insort(self.foundq, NodeWrap(node, self.num))
        self.schedule()
    

get_value_timeout = 15
class GetValue(FindNode):
    def __init__(self, table, target, callback, callLater, find="findValue"):
        FindNode.__init__(self, table, target, callback, callLater)
        self.findValue = find
            
    """ get value task """
    def handleGotNodes(self, dict):
        _krpc_sender = dict['_krpc_sender']
        dict = dict['rsp']
        sender = {'id' : dict["id"]}
        sender['port'] = _krpc_sender[1]
        sender['host'] = _krpc_sender[0]                
        sender = self.table.Node().initWithDict(sender)
        
        if self.finished or self.answered.has_key(sender.id):
            # a day late and a dollar short
            return
        self.outstanding = self.outstanding - 1

        answered = True
        
        # go through nodes
        # if we have any closer than what we already got, query them
        if dict.has_key('nodes'):
            try:
                l = unpackNodes(dict.get('nodes',[]))
            except:
                # considered an incorrect answer
                answered = False
                l = []
            
            for node in l:
                n = self.table.Node().initWithDict(node)
                if not self.found.has_key(n.id):
                    self.table.insertNode(n)
                    self.found[n.id] = n
                    insort(self.foundq, NodeWrap(n, self.num))
        elif dict.has_key('values'):
            def x(y, z=self.results):
                if not z.has_key(y):
                    z[y] = 1
                    return y
                else:
                    return None
            z = len(dict.get('values', []))
            v = filter(None, map(x, dict.get('values',[])))
            if(len(v)):
                self.callLater(0, self.callback, v)

        if answered:
            self.answered[sender.id] = sender
            insort(self.answeredq, NodeWrap(sender, self.num))
            
        self.schedule()
        
    ## get value
    def schedule(self):
        if self.finished:
            return
        for wrapper in self.foundq:
            node = wrapper.node
            if self.shouldQuery(node):
                if len(self.answeredq) >= K and self.answeredq[K-1] < wrapper:
                    # done searching
                    break
                #xxx t.timeout = time.time() + GET_VALUE_TIMEOUT
                try:
                    f = getattr(node, self.findValue)
                except AttributeError:
                    print ">>> findValue %s doesn't have a %s method!" % (node, self.findValue)
                else:
                    try:
                        df = f(self.target, self.table.node.id)
                        df.addCallback(self.handleGotNodes)
                        df.addErrback(self.makeMsgFailed(node))
                        self.outstanding = self.outstanding + 1
                        self.queried[node.id] = 1
                    except KRPCSelfNodeError:
                        pass
            if self.outstanding >= const.CONCURRENT_REQS:
                break
        assert(self.outstanding) >=0
        if self.outstanding == 0:
            ## all done
            self.finish([])

    ## get value
    def goWithNodes(self, nodes, found=None):
        self.results = {}
        if found:
            for n in found:
                self.results[n] = 1
        for node in nodes:
            if node.id == self.table.node.id:
                continue
            else:
                self.found[node.id] = node
                insort(self.foundq, NodeWrap(node, self.num))
        self.schedule()


class StoreValue(ActionBase):
    def __init__(self, table, target, value, callback, callLater, store="storeValue"):
        ActionBase.__init__(self, table, target, callback, callLater)
        self.value = value
        self.stored = []
        self.store = store
        
    def storedValue(self, t, node):
        self.outstanding -= 1
        if self.finished:
            return
        self.stored.append(t)
        if len(self.stored) >= const.STORE_REDUNDANCY:
            self.finished=1
            self.callback(self.stored)
        else:
            if not len(self.stored) + self.outstanding >= const.STORE_REDUNDANCY:
                self.schedule()
        return t
    
    def storeFailed(self, t, node):
        self.outstanding -= 1
        if self.finished:
            return t
        self.schedule()
        return t
    
    def schedule(self):
        if self.finished:
            return
        num = const.CONCURRENT_REQS - self.outstanding
        if num > const.STORE_REDUNDANCY - len(self.stored):
            num = const.STORE_REDUNDANCY - len(self.stored)
        if num == 0 and not self.finished:
            self.finished=1
            self.callback(self.stored)
        while num > 0:
            try:
                node = self.nodes.pop()
            except IndexError:
                if self.outstanding == 0:
                    self.finished = 1
                    self._cleanup()
                    self.callback(self.stored)
                return
            else:
                if not node.id == self.table.node.id:
                    try:
                        f = getattr(node, self.store)
                    except AttributeError:
                        print ">>> %s doesn't have a %s method!" % (node, self.store)
                    else:
                        try:
                            df = f(self.target, self.value, self.table.node.id)
                        except KRPCProtocolError:
                            self.table.table.invalidateNode(node)
                        except KRPCSelfNodeError:
                            pass
                        else:
                            df.addCallback(self.storedValue, node=node)
                            df.addErrback(self.storeFailed, node=node)
                            self.outstanding += 1
                            num -= 1
                        
    def goWithNodes(self, nodes):
        self.nodes = nodes
        self.nodes.sort(self.sort)
        self.schedule()


class GetAndStore(GetValue):
    def __init__(self, table, target, value, callback, storecallback, callLater, find="findValue", store="storeValue"):
        self.store = store
        self.value = value
        self.cb2 = callback
        self.storecallback = storecallback
        def cb(res):
            self.cb2(res)
            if not(res):
                n = StoreValue(self.table, self.target, self.value, self.doneStored, self.callLater, self.store)
                n.goWithNodes(self.answered.values())
        GetValue.__init__(self, table, target, cb, callLater, find)

    def doneStored(self, dict):
        self.storecallback(dict)
        
class KeyExpirer:
    def __init__(self, store, callLater):
        self.store = store
        self.callLater = callLater
        self.callLater(const.KEINITIAL_DELAY, self.doExpire)
    
    def doExpire(self):
        self.cut = time() - const.KE_AGE
        self.store.expire(self.cut)
        self.callLater(const.KE_DELAY, self.doExpire)
