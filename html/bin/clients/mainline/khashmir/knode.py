# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from node import Node
from defer import Deferred
from const import NULL_ID
from krpc import KRPCProtocolError

class IDChecker:
    def __init__(self, id):
        self.id = id

class KNodeBase(Node):
    __slots__= ('cfa', 'table')
    def __init__(self, cfa):
        Node.__init__(self)
        self.cfa = cfa

    def conn(self):
        return self.cfa((self.host, self.port))
    
    def checkSender(self, dict):
        try:
            senderid = dict['rsp']['id']
        except KeyError:
            raise KRPCProtocolError, "No peer id in response."
        else:
            if self.id != NULL_ID and senderid != self.id:
                self.table.table.invalidateNode(self)
            else:
                if self.id == NULL_ID:
                    self.id = senderid
                self.table.insertNode(self, contacted=1)
        return dict

    def errBack(self, err):
        self.table.table.nodeFailed(self)
        return err[0]
        
    def ping(self, id):
        df = self.conn().sendRequest('ping', {"id":id})
        self.conn().pinging = True
        def endping(x):
            self.conn().pinging = False
            return x
        df.addCallbacks(endping, endping)
        df.addCallbacks(self.checkSender, self.errBack)
        return df

    def findNode(self, target, id):
        df = self.conn().sendRequest('find_node', {"target" : target, "id": id})
        df.addErrback(self.errBack)
        df.addCallback(self.checkSender)
        return df

    def inPing(self):
        return self.conn().pinging
    
class KNodeRead(KNodeBase):
    def findValue(self, key, id):
        df =  self.conn().sendRequest('find_value', {"key" : key, "id" : id})
        df.addErrback(self.errBack)
        df.addCallback(self.checkSender)
        return df

class KNodeWrite(KNodeRead):
    def storeValue(self, key, value, id):
        df = self.conn().sendRequest('store_value', {"key" : key, "value" : value, "id": id})
        df.addErrback(self.errBack)
        df.addCallback(self.checkSender)
        return df
    def storeValues(self, key, value, id):
        df = self.conn().sendRequest('store_values', {"key" : key, "values" : value, "id": id})
        df.addErrback(self.errBack)
        df.addCallback(self.checkSender)
        return df
