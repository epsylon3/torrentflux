# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from binascii import a2b_hex, b2a_hex
import types
import decimal

class EBError(ValueError):
    pass

class EBObject(object):
    def __init__(self):
        pass
    
    def get_int(self):
        raise EBError
        return 0

    def get_string(self):
        raise EBError
        return ''

    def get_ustring(self):
        raise EBError
        return u''

    def get_list(self):
        raise EBError
        return [EBObject()]

    def get_dict(self):
        raise EBError
        return {u'': EBObject()}

class IntEBObject(EBObject):
    def __init__(self, i):
        self.v = i

    def get_int(self):
        return self.v

class StringEBObject(EBObject):
    def __init__(self, s):
        self.v = s

    def get_string(self):
        return self.v

class UStringEBObject(EBObject):
    def __init__(self, u):
        self.v = u

    def get_ustring(self):
        return self.v

class ListEBObject(EBObject):
    def __init__(self, l):
        self.v = l

    def get_list(self):
        return self.v

class DictEBObject(EBObject):
    def __init__(self, d):
        self.v = d

    def get_dict(self):
        return self.v

def toint(s):
    return int(b2a_hex(s), 16)

def tostr(i):
    if i == 0:
        return ''
    h = hex(i)[2:]
    if h[-1] == 'L':
        h = h[:-1]
    if len(h) & 1 == 1:
        h = '0' + h
    return a2b_hex(h)

def read_int(s, pos):
    y = ord(s[pos])
    pos += 1
    if not y & 0x80:
        return y, pos
    elif not y & 0x40:
        y = y & 0x7f
        return toint(s[pos:pos + y]), pos + y
    else:
        y = y & 0x3F
        z = toint(s[pos:pos + y])
        pos += y
        return toint(s[pos:pos + z]), pos + z

def decode_none(s, pos):
    return None, pos

def decode_int(s, pos):
    i, pos = read_int(s, pos)
    return i, pos

def decode_decimal(s, pos):
    i, pos = read_int(s, pos)
    r = s[pos:pos + i]
    return decimal.Decimal(r), pos + i

def decode_bool(s, pos):
    i, pos = read_int(s, pos)
    return bool(i), pos

def decode_negative_int(s, pos):
    i, pos = read_int(s, pos)
    return -i, pos

def decode_string(s, pos):
    i, pos = read_int(s, pos)
    r = s[pos:pos + i]
    return r, pos + i

def decode_float(s, pos):
    r, newpos = decode_string(s, pos)
    f = float(r)
    return f, newpos

def decode_ustring(s, pos):
    i, pos = read_int(s, pos)
    r = s[pos:pos + i].decode('utf-8')
    return r, pos + i

def decode_list(s, pos):
    r = []
    while s[pos] != ']':
        next, pos = decode_obj(s, pos)
        r.append(next)
    return r, pos + 1

def decode_dict(s, pos):
    r = {}
    while s[pos] != '}':
        key, pos = decode_obj(s, pos)
        val, pos = decode_obj(s, pos)
        r[key] = val
    return r, pos + 1

def decode_obj(s, pos):
    c = s[pos]
    pos += 1
    if c == 'n':
        return decode_none(s, pos)
    elif c == 'i':
        return decode_int(s, pos)
    elif c == 'd':
        return decode_decimal(s, pos)
    elif c == 'b':
        return decode_bool(s, pos)
    elif c == '-':
        return decode_negative_int(s, pos)
    elif c == 'f':
        return decode_float(s, pos)
    elif c == 's':
        return decode_string(s, pos)
    elif c == 'u':
        return decode_ustring(s, pos)
    elif c == '[':
        return decode_list(s, pos)
    elif c == '{':
        return decode_dict(s, pos)
    else:
        raise EBError('invalid type character: %s' % str(c))

class EBIndexError(IndexError, EBError):
    pass

def ebdecode(x):
    try:
        r, pos = decode_obj(x, 0)
    except IndexError:
        raise EBIndexError('apparently truncated string')
    except UnicodeDecodeError:
        raise EBError('invalid utf-8')
    if pos != len(x):
        raise EBError('excess data after valid prefix')
    return r

class EBencached(object):

    __slots__ = ['bencoded']

    def __init__(self, s):
        self.bencoded = s

def encode_bencached(x,r):
    r.append(x.bencoded)

def make_int(i):
    if i < 0x80:
        return chr(i)
    s = tostr(i)
    if len(s) < 0x40:
        return chr(0x80 | len(s)) + s
    s2 = tostr(len(s))
    return chr(0xC0 | len(s2)) + s2 + s

def encode_none(v, r):
    r.extend(('n', ''))

def encode_int(i, r):
    if i >= 0:
        r.extend(('i', make_int(i)))
    else:
        r.extend(('-', make_int(-i)))

def encode_decimal(d, r):
    s = str(d)
    r.extend(('d', make_int(len(s)), str(s)))

def encode_bool(b, r):
    r.extend(('b', make_int(int(bool(b)))))

def encode_float(f, r):
    s = repr(f)
    r.extend(('f', make_int(len(s)), s))
        
def encode_string(s, r):
    r.extend(('s', make_int(len(s)), s))

def encode_unicode_string(u, r):
    s = u.encode('utf-8')
    r.extend(('u', make_int(len(s)), s))

def encode_list(x, r):
    r.append('[')
    for i in x:
        encode_func[type(i)](i, r)
    r.append(']')

def encode_dict(x, r):
    r.append('{')
    ilist = x.items()
    ilist.sort()
    for k, v in ilist:
        encode_func[type(k)](k, r)
        encode_func[type(v)](v, r)
    r.append('}')

encode_func = {}
encode_func[EBencached] = encode_bencached
encode_func[types.NoneType] = encode_none
encode_func[int] = encode_int
encode_func[long] = encode_int
encode_func[decimal.Decimal] = encode_decimal
encode_func[bool] = encode_bool
encode_func[float] = encode_float
encode_func[str] = encode_string
encode_func[unicode] = encode_unicode_string
encode_func[list] = encode_list
encode_func[tuple] = encode_list
encode_func[dict] = encode_dict

def encode_wrapped(x, r):
    encode_func[type(x.v)](x.v, r)

encode_func[IntEBObject] = encode_wrapped
encode_func[StringEBObject] = encode_wrapped
encode_func[UStringEBObject] = encode_wrapped
encode_func[ListEBObject] = encode_wrapped
encode_func[DictEBObject] = encode_wrapped

def ebencode(x):
    r = []
    encode_func[type(x)](x, r)
    return ''.join(r)

def c(v):
    s = ebencode(v)
    r = ebdecode(s)
    assert v == r
    if isinstance(v, bool):
        assert isinstance(r, bool)
    elif isinstance(v, (int, long)) and isinstance(r, (int, long)):
        # assume it's right
        pass
    else:
        assert type(v) == type(r), '%s is not %s' % (type(v), type(r))
    assert ebencode(r) == s

c(None)
c(0)
c(3)
c(3l)
c(500)
c(-4)
c(True)
c(False)
c(4.0)
c(-4.0)
c(2 ** 5000 + 27)
c('abc')
c(decimal.Decimal('4.5'))
c(u'pqr')
c([1, 2])
c([2, 'abc', u'pqr'])
c({})
c([[]])
c({u'a': 2})
c({u'abc': 2, u'pqr': 4})
c([[1, 2], ['abc', 'pqr']])

##class StreamEbdecode:
##    def __init__(self):
##        self.buf = ''
##        self.bufint = None
##        self.returns = []
##
##    def add(self, stuff):
##        self.buf += stuff
##        try:
##            while True:
##                if self.bufint is None:
##                    mylength, pos = read_int(self.buf, 0)
##                else:
##                    mylength, pos = self.bufint, 0
##                if pos + mylength > len(self.buf):
##                    self.bufint = mylength
##                    self.buf = self.buf[pos:]
##                    break
##                mything = ebdecode(self.buf[pos:pos + mylength])
##                self.returns.append(mything)
##                self.buf = self.buf[pos + mylength:]
##                self.bufint = None
##        except IndexError:
##            pass
##
##    def next(self):
##        return self.returns.pop(0)
##
##def streamwrap(thing):
##    x = ebencode(thing)
##    return make_int(len(x)) + x
##
##def c2(v):
##    b = ''
##    for i in v:
##        b += streamwrap(i)
##    r = []
##    mystream = StreamEbdecode()
##    for i in xrange(0, len(b), 11):
##        mystream.add(b[i:min(i + 11, len(b))])
##        try:
##            while True:
##                r.append(mystream.next())
##        except IndexError:
##            pass
##    assert r == v
##
##c2(['a'])
##c2(range(5000))
##c2([''.join(str(i) for i in xrange(j)) for j in xrange(300)])
