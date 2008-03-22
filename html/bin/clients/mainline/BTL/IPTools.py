# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from struct import pack, unpack
from socket import inet_aton, inet_ntoa

def compact(ip, port):
    return pack("!4sH", inet_aton(ip), port) # ! == "network order"
                                             # 4s == "4-byte string."
                                             # H == "unsigned short"

def uncompact(x):
    ip, port = unpack("!4sH", x)
    return inet_ntoa(ip), port

def uncompact_sequence(b):
    for x in xrange(0, len(b), 6):
        ip, port = uncompact(b[x:x+6])
        port = int(port)
        yield (ip, port)

def compact_sequence(s):
    b = []
    for addr in s:
        c = compact(addr[0], addr[1])
        b.append(c)
    return ''.join(b)

##import ctypes
##class CompactAddr(ctypes.Structure):
##    _fields_ = [('ip', ctypes.c_int32),
##                ('port', ctypes.c_int16)]
##
##def compact_sequence_c(s):
##    b = ctypes.create_string_buffer(6 * len(s))
##    a = ctypes.addressof(b)
##    for i, addr in enumerate(s):
##        c = compact(addr[0], addr[1])
##        ctypes.cast(
##        offset = i*6
##        b[offset:offset + 6] = c
##    return b
