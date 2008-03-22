# handy stuff for windows ip functions
#
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

import ctypes
import socket
import struct

class IPAddr(ctypes.Structure):
    _fields_ = [ ("S_addr", ctypes.c_ulong),
                 ]

    def __str__(self):
        return socket.inet_ntoa(struct.pack("L", self.S_addr))

def inet_addr(ip):
    return IPAddr(struct.unpack("L", socket.inet_aton(ip))[0])
