# ctypes version of win32icmp
# so I don't have to recompile for future versions of python.
# you're welcome.
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
from BTL.iptypes import IPAddr, inet_addr

icmp = ctypes.windll.icmp

IcmpCreateFile = icmp.IcmpCreateFile
IcmpCloseHandle = icmp.IcmpCloseHandle

class IP_OPTION_INFORMATION(ctypes.Structure):
    _fields_ = [ ("Ttl", ctypes.c_ubyte),
                 ("Tos", ctypes.c_ubyte),
                 ("Flags", ctypes.c_ubyte),
                 ("OptionsSize", ctypes.c_ubyte),
                 ("OptionsData", ctypes.POINTER(ctypes.c_ubyte)),
                 ]
Options = IP_OPTION_INFORMATION

class ICMP_ECHO_REPLY(ctypes.Structure):
    _fields_ = [ ("Address", IPAddr),
                 ("Status", ctypes.c_ulong),
                 ("RoundTripTime", ctypes.c_ulong),
                 ("DataSize", ctypes.c_ushort),
                 ("Reserved", ctypes.c_ushort),
                 ("Data", ctypes.c_void_p),
                 ("Options", IP_OPTION_INFORMATION),
                 ]

def IcmpSendEcho(handle, addr, data, options, timeout):
    reply = ICMP_ECHO_REPLY()
    data = data or ''
    if options:
        options = ctypes.byref(options)
    r = icmp.IcmpSendEcho(handle, inet_addr(addr),
                          data, len(data),
                          options,
                          ctypes.byref(reply),
                          ctypes.sizeof(ICMP_ECHO_REPLY) + len(data),
                          timeout)
    return str(reply.Address), reply.Status, reply.RoundTripTime


IP_STATUS_BASE              = 11000

IP_SUCCESS                  = 0
IP_BUF_TOO_SMALL            = (IP_STATUS_BASE + 1)
IP_DEST_NET_UNREACHABLE     = (IP_STATUS_BASE + 2)
IP_DEST_HOST_UNREACHABLE    = (IP_STATUS_BASE + 3)
IP_DEST_PROT_UNREACHABLE    = (IP_STATUS_BASE + 4)
IP_DEST_PORT_UNREACHABLE    = (IP_STATUS_BASE + 5)
IP_NO_RESOURCES             = (IP_STATUS_BASE + 6)
IP_BAD_OPTION               = (IP_STATUS_BASE + 7)
IP_HW_ERROR                 = (IP_STATUS_BASE + 8)
IP_PACKET_TOO_BIG           = (IP_STATUS_BASE + 9)
IP_REQ_TIMED_OUT            = (IP_STATUS_BASE + 10)
IP_BAD_REQ                  = (IP_STATUS_BASE + 11)
IP_BAD_ROUTE                = (IP_STATUS_BASE + 12)
IP_TTL_EXPIRED_TRANSIT      = (IP_STATUS_BASE + 13)
IP_TTL_EXPIRED_REASSEM      = (IP_STATUS_BASE + 14)
IP_PARAM_PROBLEM            = (IP_STATUS_BASE + 15)
IP_SOURCE_QUENCH            = (IP_STATUS_BASE + 16)
IP_OPTION_TOO_BIG           = (IP_STATUS_BASE + 17)
IP_BAD_DESTINATION          = (IP_STATUS_BASE + 18)
status = {}
for k, v in dict(globals()).iteritems():
    if k.startswith("IP_"):
        status[v] = k
