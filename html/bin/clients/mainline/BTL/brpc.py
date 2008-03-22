### brpc

## query = bencode({'y':'q', 'q':'<method>', 'a':[<params>])
## response = bencode({'y':'r', 'r':<return value>}}
## fault = bencode({'y':'e','c':'<fault code>', 's':'<fault string>'

from xmlrpclib import Error, Fault
from types import TupleType

from BTL.bencode import bencode, bdecode

def dump_fault(code, msg):
    return bencode({'y':'e', 'c':code, 's':msg})


def dumps(params, methodname=None, methodresponse=None, encoding=None, allow_none=False):
    if methodresponse and isinstance(params, TupleType):
        assert len(params) == 1, "response tuple must be a singleton"
    if methodname:
        out = bencode({'y':'q', 'q':methodname, 'a':params})
    elif isinstance(params, Fault):
        out = bencode({'y':'e', 'c':params.faultCode, 's':params.faultString})
    elif methodresponse:
        out = bencode({'y':'r', 'r':params[0]})
    else:
        raise Error("")
    return out

def loads(data):
    d = bdecode(data)
    if d['y'] == 'e':
        raise Fault(d['c'], d['s']) # the server raised a fault
    elif d['y'] == 'r':
        # why is this return value so weird?
        # because it's the way that loads works in xmlrpclib
        return (d['r'],), None
    elif d['y'] == 'q':
        return d['a'], d['q']
    raise ValueError
    


class DFault(Exception):
    """Indicates an Datagram BRPC fault package."""

    # If you return a DFault with tid=None from within a function called via
    # twispread's TBRPC.callRemote then TBRPC will insert the tid for the call.
    def __init__(self, faultCode, faultString, tid=None):
        self.faultCode = faultCode
        self.faultString = faultString
        self.tid = tid
        self.args = (faultCode, faultString)
        
    def __repr__(self):
        return (
            "<Fault %s: %s>" %
            (self.faultCode, repr(self.faultString))
            )

### datagram interface
### has transaction ID as third return valuebt
### slightly different API, returns a tid as third argument in query/response
def dumpd(params, methodname=None, methodresponse=None, encoding=None, allow_none=False, tid=None):
    assert tid is not None, "need a transaction identifier"
    if methodname:
        out = bencode({'y':'q', 't':tid, 'q':methodname, 'a':params})
    elif isinstance(params, DFault):
        out = bencode({'y':'e', 't':tid, 'c':params.faultCode, 's':params.faultString})
    elif methodresponse:
        out = bencode({'y':'r', 't':tid, 'r':params})
    else:
        raise Error("")
    return out

def loadd(data):
    d = bdecode(data)
    if d['y'] == 'e':
        raise DFault(d['c'], d['s'], d['t'])
    elif d['y'] == 'r':
        return d['r'], None, d['t']
    elif d['y'] == 'q':
        return d['a'], d['q'], d['t']
    raise ValueError
    

