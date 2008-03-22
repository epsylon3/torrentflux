# binary tree search of data file for countrycode
#
# by Greg Hazel

import os
import mmap
import struct
import socket
import bisect

root, file = os.path.split(__file__)
addrmap = os.path.join(root, "addrmap.dat")
if not os.path.exists(addrmap):
    from BTL.platform import get_module_filename
    root, file = os.path.split(get_module_filename())
    addrmap = os.path.join(root, "addrmap.dat")
    if not os.path.exists(addrmap):
        addrmap = os.path.abspath("addrmap.dat")


### generates the addrmap file
##import ipfree
##f = open(addrmap, 'wb')
##for e in ipfree.addrmap:
##    # stupid 0 padded nonsense.
##    d = '.'.join([ str(int(i)) for i in e[0].split('.') ])
##    ip = socket.inet_aton(d)
##    f.write(ip + e[1])
##f.close()

def int_to_ip(i):
    s = struct.pack("!L", i)
    return socket.inet_ntoa(s)

def ip_to_int(ip):
    s = socket.inet_aton(ip)
    return struct.unpack("!L", s)[0]

class ListMMap(object):

    def __init__(self):
        self.f = open(addrmap, 'rb')
        self.size = os.path.getsize(addrmap)
        self.m = mmap.mmap(self.f.fileno(), self.size, access=mmap.ACCESS_READ)

    def __getitem__(self, i):
        p = i * 6
        if p + 6 > self.size:
            raise IndexError("memory map index out of range")
        s = self.m[p:p+6]
        d = struct.unpack("!L", s[:4])[0]
        return (d, s[4:])

    def __len__(self):
        return self.size / 6

    def find(self, ip):
        d = ip_to_int(ip)
        i = max(bisect.bisect_right(self, (d, )) - 1, 0)
        return self[i][1]

    
l = ListMMap()
lookup = l.find

if __name__ == "__main__":
    assert l.find("129.97.128.15") == "CA"
    assert l.find("217.198.112.1") == "EU"
    assert l.find("221.97.211.1") == "US"
    assert l.find("0.0.0.0") == "US"
    assert l.find("0.0.0.1") == "US"
    assert l.find("0.255.255.255") == "US"
    assert l.find("1.0.0.1") == "IN"
    assert l.find("1.0.0.255") == "IN"
