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

from twisted.internet import protocol
from BTL.decorate import decorate_func

## someday twisted might do this for me
class SmartReconnectingClientFactory(protocol.ReconnectingClientFactory):

    def buildProtocol(self, addr):
        prot = protocol.ReconnectingClientFactory.buildProtocol(self, addr)

        # decorate the protocol with a delay reset
        prot.connectionMade = decorate_func(self.resetDelay,
                                            prot.connectionMade)
        
        return prot    