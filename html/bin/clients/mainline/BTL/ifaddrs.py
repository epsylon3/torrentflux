#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
routines for getting network interface addresses
"""
# by Benjamin C. Wiley Sittler, BSD/OSX support by Greg Hazel
__all__ = [
    'getifaddrs',
    'getaddrs',
    'getnetaddrs',
    'getbroadaddrs',
    'getdstaddrs',
    'main',
    'IFF_UP',
    'IFF_BROADCAST',
    'IFF_DEBUG',
    'IFF_LOOPBACK',
    'IFF_POINTOPOINT',
    'IFF_NOTRAILERS',
    'IFF_RUNNING',
    'IFF_NOARP',
    'IFF_PROMISC',
    'IFF_ALLMULTI',
    'IFF_MASTER',
    'IFF_SLAVE',
    'IFF_MULTICAST',
    'IFF_PORTSEL',
    'IFF_AUTOMEDIA',
    'IFF_DYNAMIC',
    'IFF_LOWER_UP',
    'IFF_DORMANT',
    'ARPHRD_NETROM',
    'ARPHRD_ETHER',
    'ARPHRD_EETHER',
    'ARPHRD_AX25',
    'ARPHRD_PRONET',
    'ARPHRD_CHAOS',
    'ARPHRD_IEEE802',
    'ARPHRD_ARCNET',
    'ARPHRD_APPLETLK',
    'ARPHRD_DLCI',
    'ARPHRD_ATM',
    'ARPHRD_METRICOM',
    'ARPHRD_IEEE1394',
    'ARPHRD_EUI64',
    'ARPHRD_INFINIBAND',
    'ARPHRD_SLIP',
    'ARPHRD_SLIP6',
    'ARPHRD_RSRVD',
    'ARPHRD_ADAPT',
    'ARPHRD_X25',
    'ARPHRD_HWX25',
    'ARPHRD_PPP',
    'ARPHRD_CISCO',
    'ARPHRD_HDLC',
    'ARPHRD_DDCMP',
    'ARPHRD_RAWHDLC',
    'ARPHRD_TUNNEL',
    'ARPHRD_TUNNEL6',
    'ARPHRD_FRAD',
    'ARPHRD_SKIP',
    'ARPHRD_LOOPBACK',
    'ARPHRD_LOCALTLK',
    'ARPHRD_FDDI',
    'ARPHRD_BIF',
    'ARPHRD_SIT',
    'ARPHRD_IPDDP',
    'ARPHRD_IPGRE',
    'ARPHRD_PIMREG',
    'ARPHRD_HIPPI',
    'ARPHRD_ASH',
    'ARPHRD_ECONET',
    'ARPHRD_IRDA',
    'ARPHRD_FCPP',
    'ARPHRD_FCAL',
    'ARPHRD_FCPL',
    'ARPHRD_FCFABRIC',
    'ARPHRD_IEEE802_TR',
    'ARPHRD_IEEE80211',
    'ARPHRD_IEEE80211_PRISM',
    'ARPHRD_IEEE80211_RADIOTAP',
    'ARPHRD_VOID',
    'ARPHRD_NONE',
    'ETH_P_LOOP',
    'ETH_P_PUP',
    'ETH_P_PUPAT',
    'ETH_P_IP',
    'ETH_P_X25',
    'ETH_P_ARP',
    'ETH_P_BPQ',
    'ETH_P_IEEEPUP',
    'ETH_P_IEEEPUPAT',
    'ETH_P_DEC',
    'ETH_P_DNA_DL',
    'ETH_P_DNA_RC',
    'ETH_P_DNA_RT',
    'ETH_P_LAT',
    'ETH_P_DIAG',
    'ETH_P_CUST',
    'ETH_P_SCA',
    'ETH_P_RARP',
    'ETH_P_ATALK',
    'ETH_P_AARP',
    'ETH_P_8021Q',
    'ETH_P_IPX',
    'ETH_P_IPV6',
    'ETH_P_SLOW',
    'ETH_P_WCCP',
    'ETH_P_PPP_DISC',
    'ETH_P_PPP_SES',
    'ETH_P_MPLS_UC',
    'ETH_P_MPLS_MC',
    'ETH_P_ATMMPOA',
    'ETH_P_ATMFATE',
    'ETH_P_AOE',
    'ETH_P_TIPC',
    'ETH_P_802_3',
    'ETH_P_AX25',
    'ETH_P_ALL',
    'ETH_P_802_2',
    'ETH_P_SNAP',
    'ETH_P_DDCMP',
    'ETH_P_WAN_PPP',
    'ETH_P_PPP_MP',
    'ETH_P_LOCALTALK',
    'ETH_P_PPPTALK',
    'ETH_P_TR_802_2',
    'ETH_P_MOBITEX',
    'ETH_P_CONTROL',
    'ETH_P_IRDA',
    'ETH_P_ECONET',
    'ETH_P_HDLC',
    'ETH_P_ARCNET',
    ]

import sys
import os
import socket
import struct
from ctypes import *

from BTL.obsoletepythonsupport import set

_libc = None

BSD = sys.platform.startswith('darwin') or sys.platform.startswith('freebsd')

def libc():
    global _libc
    if _libc is None:
        uname = os.uname()
        assert sizeof(c_ushort) == 2
        assert sizeof(c_uint) == 4
        if sys.platform.startswith('darwin'):
            _libc = CDLL('libc.dylib')
        elif sys.platform.startswith('freebsd'):
            _libc = CDLL('libc.so.6')
        else:
            assert uname[0] == 'Linux' and [ int(x) for x in uname[2].split('.')[:2] ] >= [ 2, 2 ]
            _libc = CDLL('libc.so.6')
    return _libc

def errno():
    return cast(addressof(libc().errno), POINTER(POINTER(c_int)))[0][0]

uint16_t = c_ushort
uint32_t = c_uint
uint8_t = c_ubyte

if BSD:
    sa_family_t = c_uint8

    def SOCKADDR_COMMON(prefix):
        """
        Common data: address family and length.
        """
        return [ (prefix + 'len', c_uint8),
                 (prefix + 'family', sa_family_t) ]
else:
    sa_family_t = c_ushort 

    def SOCKADDR_COMMON(prefix):
        """
        Common data: address family and length.
        """
        return [ (prefix + 'family', sa_family_t) ]

SOCKADDR_COMMON_SIZE = sum([ sizeof(t) for n, t in SOCKADDR_COMMON('') ])

class sockaddr(Structure):
    """
    Structure describing a generic socket address.
    """
    pass

sockaddr._fields_ = SOCKADDR_COMMON('sa_') + [ # Common data: address family and length.
        ('sa_data', ARRAY(c_ubyte, 14)), # Address data.
        ]

class sockaddr_storage(Structure):
    """
    Structure large enough to hold any socket address (with the historical exception of AF_UNIX).  We reserve 128 bytes.
    """
    pass

_SS_SIZE = 128
__ss_aligntype = c_ulong
sockaddr_storage._fields_ = SOCKADDR_COMMON('ss_') + [ # Address family, etc.
    ('__ss_align', __ss_aligntype), # Force desired alignment.
    ('__ss_padding', ARRAY(c_byte, _SS_SIZE - 2 * sizeof(__ss_aligntype))),
    ]

class sockaddr_ll(Structure):
    pass

sockaddr_ll._fields_ = SOCKADDR_COMMON('sll_') + [
    ('sll_protocol', c_ushort),
    ('sll_ifindex', c_int),
    ('sll_hatype', c_ushort),
    ('sll_pkttype', c_ubyte),
    ('sll_halen', c_ubyte),
    ('sll_addr', ARRAY(c_ubyte, 8)),
    ]

in_port_t = uint16_t
in_addr_t = uint32_t

class in_addr(Structure):
    """
    Internet address.
    """
    pass

in_addr._fields_ = [
    ('s_addr', in_addr_t)
    ]

class in6_u(Union):
    pass

in6_u._fields_ = [
    ('u6_addr8', ARRAY(uint8_t, 16)),
    ('u6_addr16', ARRAY(uint16_t, 8)),
    ('u6_addr32', ARRAY(uint32_t, 4)),
    ]

class in6_addr(Structure):
    """
    IPv6 address
    """
    pass

in6_addr._fields_ = [
    ('in6_u', in6_u),
    ]

class sockaddr_in(Structure):
    """
    Structure describing an Internet socket address.
    """
    pass

sockaddr_in._fields_ = SOCKADDR_COMMON('sin_') + [
    ('sin_port', in_port_t), # Port number.
    ('sin_addr', in_addr), # Internet address.
    ('sin_zero', ARRAY(c_ubyte, sizeof(sockaddr) - SOCKADDR_COMMON_SIZE - sizeof(in_port_t) - sizeof(in_addr))), # Pad to size of `struct sockaddr'.
    ]

class sockaddr_in6(Structure):
    """
    Structure describing an IPv6 socket address.
    """
    pass

sockaddr_in6._fields_ = SOCKADDR_COMMON('sin6_') + [
    ('sin6_port', in_port_t), # Transport layer port #
    ('sin6_flowinfo', uint32_t), # IPv6 flow information
    ('sin6_addr', in6_addr), # IPv6 address
    ('sin6_scope_id', uint32_t), # IPv6 scope-id
    ]

class net_device_stats(Structure):
    """
    Network device statistics.
    """
    pass

net_device_stats._fields_ = [
    ('rx_packets', c_ulong),
    ('tx_packets', c_ulong),
    ('rx_bytes', c_ulong),
    ('tx_bytes', c_ulong),
    ('rx_errors', c_ulong),
    ('tx_errors', c_ulong),
    ('rx_dropped', c_ulong),
    ('tx_dropped', c_ulong),
    ('multicast', c_ulong),
    ('collisions', c_ulong),
    ('rx_length_errors', c_ulong),
    ('rx_over_errors', c_ulong),
    ('rx_crc_errors', c_ulong),
    ('rx_frame_errors', c_ulong),
    ('rx_fifo_errors', c_ulong),
    ('rx_missed_errors', c_ulong),
    ('tx_aborted_errors', c_ulong),
    ('tx_carrier_errors', c_ulong),
    ('tx_fifo_errors', c_ulong),
    ('tx_heartbeat_errors', c_ulong),
    ('tx_window_errors', c_ulong),
    ('rx_compressed', c_ulong),
    ('tx_compressed', c_ulong),
    ]

class ifa_ifu(Union):
    """
   At most one of the following two is valid.  If the IFF_BROADCAST
   bit is set in `ifa_flags', then `ifa_broadaddr' is valid.  If the
   IFF_POINTOPOINT bit is set, then `ifa_dstaddr' is valid.
   It is never the case that both these bits are set at once. 
    """
    pass

ifa_ifu._fields_=[
    ('ifu_broadaddr', POINTER(sockaddr)), # Broadcast address of this interface.
    ('ifu_dstaddr', POINTER(sockaddr)), # Point-to-point destination address.
    ]

class ifaddrs(Structure):
    """
    The `getifaddrs' function generates a linked list of these structures.
    Each element of the list describes one network interface.
    """
    pass

ifaddrs._fields_=[
    ('ifa_next', POINTER(ifaddrs)), # Pointer to the next structure.
    ('ifa_name', c_char_p), # Name of this network interface.
    ('ifa_flags', c_uint), # Flags as from SIOCGIFFLAGS ioctl.
    ('ifa_addr', POINTER(sockaddr)), # Network address of this interface.
    ('ifa_netmask', POINTER(sockaddr)), # Netmask of this interface.
    ('ifa_ifu', ifa_ifu),
    ('ifa_data', c_void_p), # Address-specific data (may be unused).
    ]

class NamedLong(long):
    def __new__(self, name, value):
        self._long = long.__new__(self, value)
        self._long._name = name
        return self._long
    def __repr__(self):
        return self._name
    pass

class OrSet(set):
    def __repr__(self):
        return ' | '.join([ repr(x) for x in self ])

IFF_UP = NamedLong(name = 'IFF_UP', value = 0x1)
IFF_BROADCAST = NamedLong(name = 'IFF_BROADCAST', value = 0x2)
IFF_DEBUG = NamedLong(name = 'IFF_DEBUG', value = 0x4)
IFF_LOOPBACK = NamedLong(name = 'IFF_LOOPBACK', value = 0x8)
IFF_POINTOPOINT = NamedLong(name = 'IFF_POINTOPOINT', value = 0x10)
IFF_NOTRAILERS = NamedLong(name = 'IFF_NOTRAILERS', value = 0x20)
IFF_RUNNING = NamedLong(name = 'IFF_RUNNING', value = 0x40)
IFF_NOARP = NamedLong(name = 'IFF_NOARP', value = 0x80)
IFF_PROMISC = NamedLong(name = 'IFF_PROMISC', value = 0x100)
IFF_ALLMULTI = NamedLong(name = 'IFF_ALLMULTI', value = 0x200)
IFF_MASTER = NamedLong(name = 'IFF_MASTER', value = 0x400)
IFF_SLAVE = NamedLong(name = 'IFF_SLAVE', value = 0x800)
IFF_MULTICAST = NamedLong(name = 'IFF_MULTICAST', value = 0x1000)
IFF_PORTSEL = NamedLong(name = 'IFF_PORTSEL', value = 0x2000)
IFF_AUTOMEDIA = NamedLong(name = 'IFF_AUTOMEDIA', value = 0x4000)
IFF_DYNAMIC = NamedLong(name = 'IFF_DYNAMIC', value = 0x8000L)
IFF_LOWER_UP = NamedLong(name = 'IFF_LOWER_UP', value = 0x10000)
IFF_DORMANT = NamedLong(name = 'IFF_DORMANT', value = 0x20000)

ARPHRD_NETROM = NamedLong(name = 'ARPHRD_NETROM', value = 0)
ARPHRD_ETHER = NamedLong(name = 'ARPHRD_ETHER', value = 1)
ARPHRD_EETHER = NamedLong(name = 'ARPHRD_EETHER', value = 2)
ARPHRD_AX25 = NamedLong(name = 'ARPHRD_AX25', value = 3)
ARPHRD_PRONET = NamedLong(name = 'ARPHRD_PRONET', value = 4)
ARPHRD_CHAOS = NamedLong(name = 'ARPHRD_CHAOS', value = 5)
ARPHRD_IEEE802 = NamedLong(name = 'ARPHRD_IEEE802', value = 6)
ARPHRD_ARCNET = NamedLong(name = 'ARPHRD_ARCNET', value = 7)
ARPHRD_APPLETLK = NamedLong(name = 'ARPHRD_APPLETLK', value = 8)
ARPHRD_DLCI = NamedLong(name = 'ARPHRD_DLCI', value = 15)
ARPHRD_ATM = NamedLong(name = 'ARPHRD_ATM', value = 19)
ARPHRD_METRICOM = NamedLong(name = 'ARPHRD_METRICOM', value = 23)
ARPHRD_IEEE1394 = NamedLong(name = 'ARPHRD_IEEE1394', value = 24)
ARPHRD_EUI64 = NamedLong(name = 'ARPHRD_EUI64', value = 27)
ARPHRD_INFINIBAND = NamedLong(name = 'ARPHRD_INFINIBAND', value = 32)
ARPHRD_SLIP = NamedLong(name = 'ARPHRD_SLIP', value = 256)
ARPHRD_SLIP6 = NamedLong(name = 'ARPHRD_SLIP6', value = 258)
ARPHRD_RSRVD = NamedLong(name = 'ARPHRD_RSRVD', value = 260)
ARPHRD_ADAPT = NamedLong(name = 'ARPHRD_ADAPT', value = 264)
ARPHRD_X25 = NamedLong(name = 'ARPHRD_X25', value = 271)
ARPHRD_HWX25 = NamedLong(name = 'ARPHRD_HWX25', value = 272)
ARPHRD_PPP = NamedLong(name = 'ARPHRD_PPP', value = 512)
ARPHRD_CISCO = NamedLong(name = 'ARPHRD_CISCO', value = 513)
ARPHRD_HDLC = NamedLong(name = 'ARPHRD_HDLC', value = ARPHRD_CISCO)
ARPHRD_DDCMP = NamedLong(name = 'ARPHRD_DDCMP', value = 517)
ARPHRD_RAWHDLC = NamedLong(name = 'ARPHRD_RAWHDLC', value = 518)
ARPHRD_TUNNEL = NamedLong(name = 'ARPHRD_TUNNEL', value = 768)
ARPHRD_TUNNEL6 = NamedLong(name = 'ARPHRD_TUNNEL6', value = 769)
ARPHRD_FRAD = NamedLong(name = 'ARPHRD_FRAD', value = 770)
ARPHRD_SKIP = NamedLong(name = 'ARPHRD_SKIP', value = 771)
ARPHRD_LOOPBACK = NamedLong(name = 'ARPHRD_LOOPBACK', value = 772)
ARPHRD_LOCALTLK = NamedLong(name = 'ARPHRD_LOCALTLK', value = 773)
ARPHRD_FDDI = NamedLong(name = 'ARPHRD_FDDI', value = 774)
ARPHRD_BIF = NamedLong(name = 'ARPHRD_BIF', value = 775)
ARPHRD_SIT = NamedLong(name = 'ARPHRD_SIT', value = 776)
ARPHRD_IPDDP = NamedLong(name = 'ARPHRD_IPDDP', value = 777)
ARPHRD_IPGRE = NamedLong(name = 'ARPHRD_IPGRE', value = 778)
ARPHRD_PIMREG = NamedLong(name = 'ARPHRD_PIMREG', value = 779)
ARPHRD_HIPPI = NamedLong(name = 'ARPHRD_HIPPI', value = 780)
ARPHRD_ASH = NamedLong(name = 'ARPHRD_ASH', value = 781)
ARPHRD_ECONET = NamedLong(name = 'ARPHRD_ECONET', value = 782)
ARPHRD_IRDA = NamedLong(name = 'ARPHRD_IRDA', value = 783)
ARPHRD_FCPP = NamedLong(name = 'ARPHRD_FCPP', value = 784)
ARPHRD_FCAL = NamedLong(name = 'ARPHRD_FCAL', value = 785)
ARPHRD_FCPL = NamedLong(name = 'ARPHRD_FCPL', value = 786)
ARPHRD_FCFABRIC = NamedLong(name = 'ARPHRD_FCFABRIC', value = 787)
ARPHRD_IEEE802_TR = NamedLong(name = 'ARPHRD_IEEE802_TR', value = 800)
ARPHRD_IEEE80211 = NamedLong(name = 'ARPHRD_IEEE80211', value = 801)
ARPHRD_IEEE80211_PRISM = NamedLong(name = 'ARPHRD_IEEE80211_PRISM', value = 802)
ARPHRD_IEEE80211_RADIOTAP = NamedLong(name = 'ARPHRD_IEEE80211_RADIOTAP', value = 803)
ARPHRD_VOID = NamedLong(name = 'ARPHRD_VOID', value = 0xFFFF)
ARPHRD_NONE = NamedLong(name = 'ARPHRD_NONE', value = 0xFFFE)

ETH_P_LOOP = NamedLong(name = 'ETH_P_LOOP', value = 0x0060)
ETH_P_PUP = NamedLong(name = 'ETH_P_PUP', value = 0x0200)
ETH_P_PUPAT = NamedLong(name = 'ETH_P_PUPAT', value = 0x0201)
ETH_P_IP = NamedLong(name = 'ETH_P_IP', value = 0x0800)
ETH_P_X25 = NamedLong(name = 'ETH_P_X25', value = 0x0805)
ETH_P_ARP = NamedLong(name = 'ETH_P_ARP', value = 0x0806)
ETH_P_BPQ = NamedLong(name = 'ETH_P_BPQ', value = 0x08FF)
ETH_P_IEEEPUP = NamedLong(name = 'ETH_P_IEEEPUP', value = 0x0a00)
ETH_P_IEEEPUPAT = NamedLong(name = 'ETH_P_IEEEPUPAT', value = 0x0a01)
ETH_P_DEC = NamedLong(name = 'ETH_P_DEC', value = 0x6000)
ETH_P_DNA_DL = NamedLong(name = 'ETH_P_DNA_DL', value = 0x6001)
ETH_P_DNA_RC = NamedLong(name = 'ETH_P_DNA_RC', value = 0x6002)
ETH_P_DNA_RT = NamedLong(name = 'ETH_P_DNA_RT', value = 0x6003)
ETH_P_LAT = NamedLong(name = 'ETH_P_LAT', value = 0x6004)
ETH_P_DIAG = NamedLong(name = 'ETH_P_DIAG', value = 0x6005)
ETH_P_CUST = NamedLong(name = 'ETH_P_CUST', value = 0x6006)
ETH_P_SCA = NamedLong(name = 'ETH_P_SCA', value = 0x6007)
ETH_P_RARP = NamedLong(name = 'ETH_P_RARP', value = 0x8035)
ETH_P_ATALK = NamedLong(name = 'ETH_P_ATALK', value = 0x809B)
ETH_P_AARP = NamedLong(name = 'ETH_P_AARP', value = 0x80F3)
ETH_P_8021Q = NamedLong(name = 'ETH_P_8021Q', value = 0x8100)
ETH_P_IPX = NamedLong(name = 'ETH_P_IPX', value = 0x8137)
ETH_P_IPV6 = NamedLong(name = 'ETH_P_IPV6', value = 0x86DD)
ETH_P_SLOW = NamedLong(name = 'ETH_P_SLOW', value = 0x8809)
ETH_P_WCCP = NamedLong(name = 'ETH_P_WCCP', value = 0x883E)
ETH_P_PPP_DISC = NamedLong(name = 'ETH_P_PPP_DISC', value = 0x8863)
ETH_P_PPP_SES = NamedLong(name = 'ETH_P_PPP_SES', value = 0x8864)
ETH_P_MPLS_UC = NamedLong(name = 'ETH_P_MPLS_UC', value = 0x8847)
ETH_P_MPLS_MC = NamedLong(name = 'ETH_P_MPLS_MC', value = 0x8848)
ETH_P_ATMMPOA = NamedLong(name = 'ETH_P_ATMMPOA', value = 0x884c)
ETH_P_ATMFATE = NamedLong(name = 'ETH_P_ATMFATE', value = 0x8884)
ETH_P_AOE = NamedLong(name = 'ETH_P_AOE', value = 0x88A2)
ETH_P_TIPC = NamedLong(name = 'ETH_P_TIPC', value = 0x88CA)
ETH_P_802_3 = NamedLong(name = 'ETH_P_802_3', value = 0x0001)
ETH_P_AX25 = NamedLong(name = 'ETH_P_AX25', value = 0x0002)
ETH_P_ALL = NamedLong(name = 'ETH_P_ALL', value = 0x0003)
ETH_P_802_2 = NamedLong(name = 'ETH_P_802_2', value = 0x0004)
ETH_P_SNAP = NamedLong(name = 'ETH_P_SNAP', value = 0x0005)
ETH_P_DDCMP = NamedLong(name = 'ETH_P_DDCMP', value = 0x0006)
ETH_P_WAN_PPP = NamedLong(name = 'ETH_P_WAN_PPP', value = 0x0007)
ETH_P_PPP_MP = NamedLong(name = 'ETH_P_PPP_MP', value = 0x0008)
ETH_P_LOCALTALK = NamedLong(name = 'ETH_P_LOCALTALK', value = 0x0009)
ETH_P_PPPTALK = NamedLong(name = 'ETH_P_PPPTALK', value = 0x0010)
ETH_P_TR_802_2 = NamedLong(name = 'ETH_P_TR_802_2', value = 0x0011)
ETH_P_MOBITEX = NamedLong(name = 'ETH_P_MOBITEX', value = 0x0015)
ETH_P_CONTROL = NamedLong(name = 'ETH_P_CONTROL', value = 0x0016)
ETH_P_IRDA = NamedLong(name = 'ETH_P_IRDA', value = 0x0017)
ETH_P_ECONET = NamedLong(name = 'ETH_P_ECONET', value = 0x0018)
ETH_P_HDLC = NamedLong(name = 'ETH_P_HDLC', value = 0x0019)
ETH_P_ARCNET = NamedLong(name = 'ETH_P_ARCNET', value = 0x001A)

def NamedLongs(x, names):
    s = OrSet()
    for k in names:
        if x & k:
            s |= OrSet([k])
            x ^= k
    k = 1L
    while x:
        if x & k:
            s |= OrSet([k])
            x ^= k
        k+=k
    return s

def _getifaddrs(ifap):
    """
   Create a linked list of `struct ifaddrs' structures, one for each
   network interface on the host machine.  If successful, store the
   list in *IFAP and return 0.  On errors, return -1 and set `errno'.

   The storage returned in *IFAP is allocated dynamically and can
   only be properly freed by passing it to `freeifaddrs'.
    """
    __getifaddrs = libc().getifaddrs
    return CFUNCTYPE(c_int, POINTER(POINTER(ifaddrs)))(__getifaddrs)(ifap)

def _freeifaddrs(ifa):
    """
   Reclaim the storage allocated by a previous `getifaddrs' call.
    """
    __freeifaddrs = libc().freeifaddrs
    return CFUNCTYPE(None, POINTER(ifaddrs))(__freeifaddrs)(ifa)

def hardware_type(hatype):
    this = sys.modules[__name__]
    return ([ x for x in [ NamedLong(n, getattr(this, n)) for n in dir(this) if n[:len('ARPHRD_')] == 'ARPHRD_' ] if x == hatype ] + [ hatype ])[0]

def eth_protocol_type(protocol):
    this = sys.modules[__name__]
    return ([ x for x in [ NamedLong(n, getattr(this, n)) for n in dir(this) if n[:len('ETH_P_')] == 'ETH_P_' ] if x == protocol ] + [ protocol ])[0]

def packet_type(pkttype):
    return ([ x for x in [ NamedLong(n, getattr(socket, n)) for n in dir(socket) if n[:len('PACKET_')] == 'PACKET_' ] if x == pkttype ] + [ pkttype ])[0]

def addrfamily(family):
    return ([ x for x in [ NamedLong(n, getattr(socket, n)) for n in dir(socket) if n[:len('AF_')] == 'AF_' ] if x == family ] + [ family ])[0]

def sockaddr2addr(ifname, addr):
    """
    Convert a sockaddr pointer (addr) to a descriptive dict or None
    for a void pointer.
    """
    if addr:
        sa = addr[0]
    else:
        return None
    d = { 'family': addrfamily(sa.sa_family) }
    if hasattr(socket, 'AF_INET6') and sa.sa_family == socket.AF_INET6:
        sin6 = cast(addr, POINTER(sockaddr_in6))[0]
        d['port'] = sin6.sin6_port or None
        d['addr'] = ':'.join([ '%04.4x' % socket.ntohs(x) for x in sin6.sin6_addr.in6_u.u6_addr16 ])
        d['flowinfo'] = sin6.sin6_flowinfo or None
        d['scope_id'] = sin6.sin6_scope_id
    elif hasattr(socket, 'AF_INET') and sa.sa_family == socket.AF_INET:
        sin = cast(addr, POINTER(sockaddr_in))[0]
        d['port'] = sin.sin_port or None
        d['addr'] = '.'.join([ str(ord(x)) for x in struct.pack('I', sin.sin_addr.s_addr) ])
    elif hasattr(socket, 'AF_PACKET') and sa.sa_family == socket.AF_PACKET:
        sll = cast(addr, POINTER(sockaddr_ll))[0]
        #d['ifindex'] = sll.sll_ifindex
        hwaddr = None
        if sll.sll_hatype == ARPHRD_ETHER and sll.sll_halen == 6:
            hwaddr = ':'.join([ chr(x).encode('hex') for x in sll.sll_addr[:sll.sll_halen] ])
        elif sll.sll_hatype == ARPHRD_SIT and sll.sll_halen == 4:
            try:
                hwaddr = socket.inet_ntop(socket.AF_INET,
                                          ''.join([ chr(x) for x in sll.sll_addr[:sll.sll_halen] ]))
            except:
                pass
        d['addr'] = (ifname,
                     eth_protocol_type(sll.sll_protocol),
                     packet_type(sll.sll_pkttype),
                     hardware_type(sll.sll_hatype),
                     ) + ((hwaddr is not None) and (hwaddr,) or ())
    else:
        pass
    try:
        if 'addr' in d:
            d['addr'] = socket.inet_ntop(sa.sa_family,
                                         socket.inet_pton(sa.sa_family,
                                                          d['addr']))
    except:
        pass
    return dict([ (k, v) for k, v in d.items() if v is not None ])

def flagset(flagbits):
    if isinstance(flagbits, set):
        return flagbits
    return NamedLongs(flagbits,
                      (IFF_UP,
                       IFF_BROADCAST,
                       IFF_DEBUG,
                       IFF_LOOPBACK,
                       IFF_POINTOPOINT,
                       IFF_NOTRAILERS,
                       IFF_RUNNING,
                       IFF_NOARP,
                       IFF_PROMISC,
                       IFF_ALLMULTI,
                       IFF_MASTER,
                       IFF_SLAVE,
                       IFF_MULTICAST,
                       IFF_PORTSEL,
                       IFF_AUTOMEDIA,
                       IFF_DYNAMIC,
                       IFF_LOWER_UP,
                       IFF_DORMANT,
                       ))

def getifaddrs(name = None):
    """
   Create a list of ifaddrs, one for each network interface on the
   host machine.  If successful, return the list.  On errors, raises
   an exception.  If the optional name is not None, only entries for
   that interface name are returned.
    """
    ifa = POINTER(ifaddrs)()
    ret = _getifaddrs(byref(ifa))
    ifa0 = ifa
    if ret == -1:
        raise IOError(os.strerror(errno()))
    try:
        iflist = []
        while ifa:
            d = {
                'name': ifa[0].ifa_name,
                'flags': flagset(ifa[0].ifa_flags),
                }
            d['addr'] = sockaddr2addr(d['name'], ifa[0].ifa_addr)
            d['netmask'] = sockaddr2addr(d['name'], ifa[0].ifa_netmask)
            if ifa[0].ifa_flags & IFF_BROADCAST:
                d['broadaddr'] = sockaddr2addr(d['name'], ifa[0].ifa_ifu.ifu_broadaddr)
            elif ifa[0].ifa_flags & IFF_POINTOPOINT:
                d['dstaddr'] = sockaddr2addr(d['name'], ifa[0].ifa_ifu.ifu_dstaddr)
            #d['data'] = ifa[0].ifa_data or None
            if ifa[0].ifa_data:
                if (d.get('addr') is not None and
                    hasattr(socket, 'AF_PACKET') and
                    d['addr'].get('family') == socket.AF_PACKET):
                    
                    nds = cast(ifa[0].ifa_data, POINTER(net_device_stats))[0]
                    d['data'] = {
                        'rx_packets': nds.rx_packets,
                        'tx_packets': nds.tx_packets,
                        'rx_bytes': nds.rx_bytes,
                        'tx_bytes': nds.tx_bytes,
                        'rx_errors': nds.rx_errors,
                        'tx_errors': nds.tx_errors,
                        'rx_dropped': nds.rx_dropped,
                        'tx_dropped': nds.tx_dropped,
                        'multicast': nds.multicast,
                        'collisions': nds.collisions,
                        'rx_length_errors': nds.rx_length_errors,
                        'rx_over_errors': nds.rx_over_errors,
                        'rx_crc_errors': nds.rx_crc_errors,
                        'rx_frame_errors': nds.rx_frame_errors,
                        'rx_fifo_errors': nds.rx_fifo_errors,
                        'rx_missed_errors': nds.rx_missed_errors,
                        'tx_aborted_errors': nds.tx_aborted_errors,
                        'tx_carrier_errors': nds.tx_carrier_errors,
                        'tx_fifo_errors': nds.tx_fifo_errors,
                        'tx_heartbeat_errors': nds.tx_heartbeat_errors,
                        'tx_window_errors': nds.tx_window_errors,
                        'rx_compressed': nds.rx_compressed,
                        'tx_compressed': nds.tx_compressed,
                        }
            iflist.append(dict([ (k, v) for k, v in d.items() if v is not None ]))
            ifa = ifa[0].ifa_next
        return [ iface for iface in iflist if name is None or iface.get('name') == name ]
    finally:
        _freeifaddrs(ifa0)

def getaddrs(family = None, flags = OrSet([IFF_UP, IFF_RUNNING]), name = None):
    flags = flagset(flags)
    for a in getifaddrs(name = name):
        if family is not None and a.get('addr', {}).get('family') != family:
            continue
        if flags:
            for flag in flags:
                if flag not in flagset(a.get('flags', 0)):
                    continue
        if 'addr' in a and 'addr' in a['addr']:
            yield a['addr']['addr']

def getnetaddrs(family = None, flags = OrSet([IFF_UP, IFF_RUNNING]), name = None):
    flags = flagset(flags)
    for a in getifaddrs(name = name):
        if family is not None and a.get('addr', {}).get('family') != family:
            continue
        if 'netmask' in a and a.get('netmask', {}).get('family') != a.get('addr', {}).get('family') != family:
            continue
        if flags:
            for flag in flags:
                if flag not in flagset(a.get('flags', 0)):
                    continue
        if 'addr' in a and 'addr' in a['addr']:
            yield str(a['addr']['addr']) + (('netmask' in a and 'addr' in a['netmask'] and a['netmask'].get('family') == a['addr']['family']) and '/' + str(a['netmask']['addr']) or '')

def getbroadaddrs(family = None, flags = OrSet([IFF_UP, IFF_RUNNING, IFF_BROADCAST]), name = None):
    flags = flagset(flags)
    for a in getifaddrs(name = name):
        if family is not None and a.get('broadaddr', {}).get('family') != family:
            continue
        if flags:
            for flag in flags:
                if flag not in flagset(a.get('flags', 0)):
                    continue
        if 'broadaddr' in a and 'addr' in a['broadaddr']:
            yield str(a['broadaddr']['addr']) + (('netmask' in a and 'addr' in a['netmask'] and a['netmask'].get('family') == a['broadaddr']['family']) and '/' + str(a['netmask']['addr']) or '')

def getdstaddrs(family = None, flags = OrSet([IFF_UP, IFF_RUNNING, IFF_POINTOPOINT]), name = None):
    flags = flagset(flags)
    for a in getifaddrs(name = None):
        if family is not None and a.get('dstaddr', {}).get('family') != family:
            continue
        if flags:
            for flag in flags:
                if flag not in flagset(a.get('flags', 0)):
                    continue
        if 'dstaddr' in a and 'addr' in a['dstaddr']:
            yield a['dstaddr']['addr']

def main():
    '''
    Print a list of network interfaces.
    '''
    print 'live interface addresses:'
    for a in getaddrs():
        print '\t' 'addr', a
    for a in getdstaddrs():
        print '\t' 'dstaddr', a
    for a in getnetaddrs():
        print '\t' 'netaddr', a
    for a in getbroadaddrs():
        print '\t' 'broadaddr', a
    print 'all interface details:'
    for a in getifaddrs():
        print '\t' + a['name'] + ':'
        i = a.items()
        i.sort()
        for k, v in i:
            if k not in ('name', 'data'):
                print '\t\t' + k, `v`
        if 'data' in a:
            print '\t\t' 'data' + ':'
            i = a['data'].items()
            i.sort()
            for k, v in i:
                print '\t\t\t' + k, `v`

if __name__ == '__main__':
    main()

