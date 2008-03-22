#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
routines for getting route information
"""
# by Benjamin C. Wiley Sittler
__all__ = [
    'getroutes',
    'main',
    'RTF_UP',
    'RTF_GATEWAY',
    'RTF_HOST',
    'RTF_REINSTATE',
    'RTF_DYNAMIC',
    'RTF_MODIFIED',
    'RTF_MTU',
    'RTF_MSS',
    'RTF_WINDOW',
    'RTF_IRTT',
    'RTF_REJECT',
    'RTF_STATIC',
    'RTF_XRESOLVE',
    'RTF_NOFORWARD',
    'RTF_THROW',
    'RTF_NOPMTUDISC',
    'RTF_DEFAULT',
    'RTF_ALLONLINK',
    'RTF_ADDRCONF',
    'RTF_LINKRT',
    'RTF_NONEXTHOP',
    'RTF_CACHE',
    'RTF_FLOW',
    'RTF_POLICY',
    'RTF_LOCAL',
    'RTF_INTERFACE',
    'RTF_MULTICAST',
    'RTF_BROADCAST',
    'RTF_NAT',
    'RTF_ADDRCLASSMASK',
]

import os
import socket
import struct

from BTL.obsoletepythonsupport import set

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

RTF_UP = NamedLong(name = 'RTF_UP', value = 0x0001)
RTF_GATEWAY = NamedLong(name = 'RTF_GATEWAY', value = 0x0002)
RTF_HOST = NamedLong(name = 'RTF_HOST', value = 0x0004)
RTF_REINSTATE = NamedLong(name = 'RTF_REINSTATE', value = 0x0008)
RTF_DYNAMIC = NamedLong(name = 'RTF_DYNAMIC', value = 0x0010)
RTF_MODIFIED = NamedLong(name = 'RTF_MODIFIED', value = 0x0020)
RTF_MTU = NamedLong(name = 'RTF_MTU', value = 0x0040)
RTF_MSS = NamedLong(name = 'RTF_MSS', value = RTF_MTU)
RTF_WINDOW = NamedLong(name = 'RTF_WINDOW', value = 0x0080)
RTF_IRTT = NamedLong(name = 'RTF_IRTT', value = 0x0100)
RTF_REJECT = NamedLong(name = 'RTF_REJECT', value = 0x0200)
RTF_STATIC = NamedLong(name = 'RTF_STATIC', value = 0x0400)
RTF_XRESOLVE = NamedLong(name = 'RTF_XRESOLVE', value = 0x0800)
RTF_NOFORWARD = NamedLong(name = 'RTF_NOFORWARD', value = 0x1000)
RTF_THROW = NamedLong(name = 'RTF_THROW', value = 0x2000)
RTF_NOPMTUDISC = NamedLong(name = 'RTF_NOPMTUDISC', value = 0x4000)
RTF_DEFAULT = NamedLong(name = 'RTF_DEFAULT', value = 0x00010000)
RTF_ALLONLINK = NamedLong(name = 'RTF_ALLONLINK', value = 0x00020000)
RTF_ADDRCONF = NamedLong(name = 'RTF_ADDRCONF', value = 0x00040000)
RTF_LINKRT = NamedLong(name = 'RTF_LINKRT', value = 0x00100000)
RTF_NONEXTHOP = NamedLong(name = 'RTF_NONEXTHOP', value = 0x00200000)
RTF_CACHE = NamedLong(name = 'RTF_CACHE', value = 0x01000000)
RTF_FLOW = NamedLong(name = 'RTF_FLOW', value = 0x02000000)
RTF_POLICY = NamedLong(name = 'RTF_POLICY', value = 0x04000000)
RTF_LOCAL = NamedLong(name = 'RTF_LOCAL', value = 0x80000000)
RTF_INTERFACE = NamedLong(name = 'RTF_INTERFACE', value = 0x40000000)
RTF_MULTICAST = NamedLong(name = 'RTF_MULTICAST', value = 0x20000000)
RTF_BROADCAST = NamedLong(name = 'RTF_BROADCAST', value = 0x10000000)
RTF_NAT = NamedLong(name = 'RTF_NAT', value = 0x08000000)
RTF_ADDRCLASSMASK = NamedLong(name = 'RTF_ADDRCLASSMASK', value = 0xF8000000)

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

def flagset(flagbits):
    if isinstance(flagbits, set):
        return flagbits
    return NamedLongs(flagbits,
                      (RTF_UP,
                       RTF_GATEWAY,
                       RTF_HOST,
                       RTF_REINSTATE,
                       RTF_DYNAMIC,
                       RTF_MODIFIED,
                       RTF_MTU,
                       RTF_MSS,
                       RTF_WINDOW,
                       RTF_IRTT,
                       RTF_REJECT,
                       RTF_STATIC,
                       RTF_XRESOLVE,
                       RTF_NOFORWARD,
                       RTF_THROW,
                       RTF_NOPMTUDISC,
                       RTF_DEFAULT,
                       RTF_ALLONLINK,
                       RTF_ADDRCONF,
                       RTF_LINKRT,
                       RTF_NONEXTHOP,
                       RTF_CACHE,
                       RTF_FLOW,
                       RTF_POLICY,
                       RTF_LOCAL,
                       RTF_INTERFACE,
                       RTF_MULTICAST,
                       RTF_BROADCAST,
                       RTF_NAT,
                       RTF_ADDRCLASSMASK,
                       ))

def addrfamily(family):
    return ([ x for x in [ NamedLong(n, getattr(socket, n)) for n in dir(socket) if n[:len('AF_')] == 'AF_' ] if x == family ] + [ family ])[0]

def getroutes(family = None, flags = OrSet([RTF_UP]), name = None):
    """
    This generator yields matching routes one-by-one. If the optional
    family parameter is not None, only routes for the given address
    family (AF_INET or AF_INET6) are yielded. If the optional name
    parameter is not None, only routes for the given interface are
    yielded.
    """
    uname = os.uname()
    assert uname[0] == 'Linux' and [ int(x) for x in uname[2].split('.')[:2] ] >= [ 2, 2 ]
    assert family in (None, socket.AF_INET, socket.AF_INET6)
    routes = []
    if family is None or family == socket.AF_INET:
        routes_ipv4 = [ line.rstrip(' ').split() for line in file('/proc/net/route').read().splitlines() ]
        routes_ipv4 = [ dict([('family', socket.AF_INET)] + [ (routes_ipv4[0][i].strip().lower(), route[i]) for i in range(len(route)) ]) for route in routes_ipv4[1:] ]
        routes = routes + routes_ipv4
    if family is None or family == socket.AF_INET6:
        routes_ipv6 = [ line.rstrip(' ').split() for line in file('/proc/net/ipv6_route').read().splitlines() ]
        routes_ipv6 = [ dict([('family', socket.AF_INET6)] + [ (('destination', 'destination_prefixlen', 'source', 'source_prefixlen', 'gateway', 'metric', 'refcnt', 'use', 'flags', 'iface')[i], route[i]) for i in range(len(route)) ]) for route in routes_ipv6 ]
        routes = routes + routes_ipv6
    for route in routes:
        route['family'] = addrfamily(route['family'])
        for field in ('flags', 'refcnt', 'use', 'metric', 'mtu', 'window', 'irtt', 'source_prefixlen', 'destination_prefixlen'):
            if field in route:
                route[field] = int(route[field], 16)
        for field in ('destination', 'gateway', 'mask', 'source'):
            if field in route:
                route[field] = route[field].decode('hex')
                if route['family'] == socket.AF_INET:
                    route[field] = struct.pack('!I', struct.unpack('I', route[field])[0])
                try:
                    route[field] = socket.inet_ntop(route['family'], route[field])
                except:
                    route[field] = route[field].encode('hex')
        if 'flags' in route:
            route['flags'] = flagset(route['flags'])
        if family is not None and route['family'] != family:
            continue
        for flag in flagset(flags or 0):
            if flag not in flagset(route.get('flags', 0)):
                break
        else:
            if name is not None and route.get('iface') != name:
                continue
            yield route

def main():
    print 'all route details:'
    for route in getroutes():
        print '\troute' + (
            ('destination' in route) and (' to ' + str(route.get('destination')) + (('destination_prefixlen' in route) and ('/' + str(route['destination_prefixlen'])) or (('mask' in route) and ('/' + str(route['mask'])) or ''))) or '') + (
            ('source' in route) and (' from ' + str(route['source']) + (('source_prefixlen' in route) and ('/' + str(route['source_prefixlen'])) or '')) or '') + (
            ('gateway' in route) and (' via ' + str(route['gateway'])) or '')
        rtkeys = route.keys()
        rtkeys.sort()
        for field in rtkeys:
            print '\t\t' + field, `route[field]`

if __name__ == '__main__':
    main()

