# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Originally written by Bram Cohen, heavily modified by Uoti Urpala
# Fast extensions added by David Harrison

from __future__ import generators

# DEBUG
# If you think FAST_EXTENSION is causing problems then set the following:
disable_fast_extension = False
# END DEBUG

noisy = False
log_data = False

# for crypto
from random import randrange
from BTL.hash import sha
from Crypto.Cipher import ARC4
# urandom comes from obsoletepythonsupport

import struct
from struct import pack, unpack
from cStringIO import StringIO

from BTL.bencode import bencode, bdecode
from BitTorrent.RawServer_twisted import Handler
from BTL.bitfield import Bitfield
from BTL import IPTools
from BTL.obsoletepythonsupport import *
from BitTorrent.ClientIdentifier import identify_client
from BTL.platform import app_name
from BitTorrent import version
import logging

def toint(s):
    return struct.unpack("!i", s)[0]

def tobinary(i):
    return struct.pack("!i", i)


class BTMessages(object):

    def __init__(self, messages):
        self.message_to_chr = {}
        self.chr_to_message = {}
        for o, v in messages.iteritems():
            c = chr(o)
            self.chr_to_message[c] = v
            self.message_to_chr[v] = c

    def __getitem__(self, key):
        return self.chr_to_message.get(key, "UNKNOWN: %r" % key)

message_dict = BTMessages({
0:'CHOKE',
1:'UNCHOKE',
2:'INTERESTED',
3:'NOT_INTERESTED',

4:'HAVE',
# index, bitfield
5:'BITFIELD',
# index, begin, length
6:'REQUEST',
# index, begin, piece
7:'PIECE',
# index, begin, piece
8:'CANCEL',

# 2-byte port message
9:'PORT',

# no args
10:'WANT_METAINFO',
11:'METAINFO',

# index
12:'SUSPECT_PIECE',

# index
13:'SUGGEST_PIECE', # FAST_EXTENSION
# no args
14:'HAVE_ALL', # FAST_EXTENSION
15:'HAVE_NONE', # FAST_EXTENSION

# index, begin, length
16:'REJECT_REQUEST', # FAST_EXTENSION

# index
17:'ALLOWED_FAST', # FAST_EXTENSION

# compact_addr
18:'HOLE_PUNCH', # NAT_TRAVERSAL

# message id, bencoded payload
20:'UTORRENT_MSG', # UTORRENT
})

# put all the message identifiers in the module
locals().update(message_dict.message_to_chr)

# I am not even shitting you.
AZUREUS_SUCKS = CHOKE

UTORRENT_MSG_INFO = chr(0)
# in reality this could be variable
UTORRENT_MSG_PEX = chr(1)

# reserved flags:
#  reserved[0]
#   0x80 Azureus Messaging Protocol
AZUREUS = 0x80
#  reserved[5]
#   0x10 uTorrent extensions: peer exchange, encrypted connections,
#       broadcast listen port.
UTORRENT = 0x10
#  reserved[7]
DHT = 0x01
FAST_EXTENSION = 0x04   # suggest, haveall, havenone, reject request,
                        # and allow fast extensions.
NAT_TRAVERSAL = 0x08 # holepunch

LAST_BYTE = DHT
if not disable_fast_extension:
    LAST_BYTE |= FAST_EXTENSION
LAST_BYTE |= NAT_TRAVERSAL
FLAGS = ['\0'] * 8
#FLAGS[0] = chr( AZUREUS )
FLAGS[5] = chr( UTORRENT )
FLAGS[7] = chr( LAST_BYTE )
FLAGS = ''.join(FLAGS)
protocol_name = 'BitTorrent protocol'

# for crypto
dh_prime = 0xFFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F14374FE1356D6D51C245E485B576625E7EC6F44C42E9A63A36210000000000090563
PAD_MAX = 200 # less than protocol maximum, and later assumed to be < 256
DH_BYTES = 96
def bytetonum(x):
    return long(x.encode('hex'), 16)
def numtobyte(x):
    x = hex(x).lstrip('0x').rstrip('Ll')
    x = '0'*(192 - len(x)) + x
    return x.decode('hex')

if noisy:
    connection_logger = logging.getLogger("BitTorrent.Connector")
    connection_logger.setLevel(logging.DEBUG)
    stream_handler = logging.StreamHandler()
    connection_logger.addHandler(stream_handler)
    log = connection_logger.debug


# Tracker NAT checking:
# Aside: When you start up a Torrent, the first connection after contacting
# the tracker is probably a callback from the tracker to perform a NatCheck.
# (I was a bit confused about where this connection was coming from that
# didn't have any bits set in the handshake's reserved bytes when
# with there were no other peers. Call me stupid.)   --Dave



class Connector(Handler):
    """Implements the syntax of the BitTorrent protocol.
       See Upload.py and Download.py for the connection-level
       semantics."""

    def __init__(self, parent, connection, id, is_local,
                 obfuscate_outgoing=False, log_prefix = "", lan=False):
        self.parent = parent
        self.connection = connection
        self.id = id
        self.ip = connection.ip
        self.port = connection.port
        self.addr = (self.ip, self.port)
        self.hostname = None
        self.locally_initiated = is_local
        if self.locally_initiated:
            self.max_message_length = self.parent.config['max_message_length']
            self.listening_port = self.port
        else:
            self.listening_port = None
        self.complete = False
        self.lan = lan
        self.closed = False
        self.got_anything = False
        self.next_upload = None
        self.upload = None
        self.download = None
        self._buffer = StringIO()
        self._reader = self._read_messages()
        self._next_len = self._reader.next()
        self._message = None
        self._partial_message = None
        self._outqueue = StringIO()
        self._decrypt = None
        self._privkey = None
        self.choke_sent = True

        self.uses_utorrent_extension = False
        self.uses_utorrent_pex = False
        self.utorrent_pex_id = None
        self.uses_azureus_extension = False
        self.uses_azureus_pex = False
        self.uses_dht = False
        self.uses_fast_extension = False
        self.uses_nat_traversal = False

        self.obfuscate_outgoing = obfuscate_outgoing
        self.dht_port = None
        self.local_pex_set = set()
        self.remote_pex_set = set()
        self.sloppy_pre_connection_counter = 0
        self._sent_listeners = set()
        self.received_data = False
        self.log_prefix = log_prefix
        if self.locally_initiated:
            self.logger = logging.getLogger(
                self.log_prefix + '.' + repr(self.parent.infohash) +
                '.peer_id_not_yet')
        else:
            self.logger = logging.getLogger(
                self.log_prefix + '.infohash_not_yet.peer_id_not_yet')
        self.logger.setLevel(logging.DEBUG)

        if noisy:
            self.logger.addHandler(stream_handler)

        if self.locally_initiated:
            self.send_handshake()
        # Greg's comments: ow ow ow
        self.connection.handler = self

    def protocol_violation(self, s):
        msg = "%s %s" % (s, self.addr)
        if self.id:
            msg += " %r" % (identify_client(self.id), )
        if noisy:
            log("FAUX PAS: %s" % msg)
        self.logger.info(msg)

    def send_handshake(self):
        if self.obfuscate_outgoing:
            privkey = bytetonum(urandom(20))
            self._privkey = privkey
            pubkey = pow(2, privkey, dh_prime)
            out = numtobyte(pubkey) + urandom(randrange(PAD_MAX))
            self.connection.write(out)
        else:
            if noisy:
                l = [ c.encode('hex') for c in list(FLAGS) ]
                log("sending reserved: %s" % ' '.join(l))

            self.connection.write(''.join((chr(len(protocol_name)),
                                           protocol_name,
                                           FLAGS,
                                           self.parent.infohash)))
            # if we already have the peer's id, just send ours.
            # otherwise we wait for it.
            if self.id is not None:
                self.connection.write(self.parent.my_id)

    def set_parent(self, parent):
        self.parent = parent
        self.max_message_length = self.parent.config['max_message_length']

    def close(self):
        if noisy: log("CLOSE")
        if not self.closed:
            self.parent.remove_addr_from_cache(self.addr)
            self.connection.close()

    def send_interested(self):
        if noisy:
            log("SEND %s" % message_dict[INTERESTED])
        self._send_message(INTERESTED)

    def send_not_interested(self):
        if noisy:
            log("SEND %s" % message_dict[NOT_INTERESTED])
        self._send_message(NOT_INTERESTED)

    def send_choke(self):
        if self._partial_message is None:
            if noisy:
                log("SEND %s" % message_dict[CHOKE])
            self._send_message(CHOKE)
            self.choke_sent = True
            self.upload.sent_choke()

    def send_unchoke(self):
        if self._partial_message is None:
            if noisy:
                log("SEND %s" % message_dict[UNCHOKE])
            self._send_message(UNCHOKE)
            self.choke_sent = False

    def send_port(self, port):
        if noisy:
            log("SEND %s" % message_dict[PORT])
        self._send_message(PORT, pack('!H', port))

    def send_request(self, index, begin, length):
        if noisy:
            log("SEND %s %d %d %d" % (message_dict[REQUEST], index, begin, length))
        self._send_message(pack("!ciii", REQUEST, index, begin, length))

    def send_cancel(self, index, begin, length):
        self._send_message(pack("!ciii", CANCEL, index, begin, length))

    def send_bitfield(self, bitfield):
        if noisy:
            log("SEND %s" % message_dict[BITFIELD])
        self._send_message(BITFIELD, bitfield)

    def send_have(self, index):
        if noisy:
            log("SEND %s" % message_dict[HAVE])
        self._send_message(pack("!ci", HAVE, index))

    def send_have_all(self):
        assert(self.uses_fast_extension)
        if noisy:
            log("SEND %s" % message_dict[HAVE_ALL])
        self._send_message(pack("!c", HAVE_ALL))

    def send_have_none(self):
        assert(self.uses_fast_extension)
        if noisy:
            log("SEND %s" % message_dict[HAVE_NONE])
        self._send_message(pack("!c", HAVE_NONE))

    def send_reject_request(self, index, begin, length):
        assert(self.uses_fast_extension)
        self._send_message(pack("!ciii", REJECT_REQUEST, index, begin, length))

    def send_allowed_fast(self, index):
        assert(self.uses_fast_extension)
        self._send_message(pack("!ci", ALLOWED_FAST, index))

    def send_keepalive(self):
        self._send_message('')

    def send_holepunch_request(self, addr):
        # disabled, for now.
        return

        if not self.uses_nat_traversal:
            # maybe close?
            return
        d = {'t': 'r'}
        d['p'] = IPTools.compact(*addr)
        self._send_message(HOLE_PUNCH, d)

    def send_pex(self, pex_set):
        if not (self.uses_utorrent_extension and self.uses_utorrent_pex):
            return
        added = pex_set.difference(self.local_pex_set)
        dropped = self.local_pex_set.difference(pex_set)
        self.local_pex_set = pex_set
        if added or dropped:
            d = {}
            d['added'] = IPTools.compact_sequence(added)
            # TODO: set seeds bytes
            d['added.f'] = chr(0) * len(added) # hmm..
            d['dropped'] = IPTools.compact_sequence(dropped)
            self._send_message(UTORRENT_MSG,
                               chr(self.utorrent_pex_id), bencode(d))

    def add_sent_listener(self, listener):
        """Passed a function/functor that accepts a single byte argument,
           which is called everytime this uploader sends a chunk."""
        self._sent_listeners.add(listener)

    def remove_sent_listener(self, listener):
        self._sent_listeners.remove(listener)

    def fire_sent_listeners(self, bytes):
        for f in self._sent_listeners:
           f(bytes)

    def send_partial(self, bytes):
        if self.closed:
            return 0
        if self._partial_message is None and not self.upload.buffer:
            return 0
        if self._partial_message is None:
            buf = StringIO()
            while self.upload.buffer and buf.tell() < bytes:
                t, piece = self.upload.buffer.pop(0)
                index, begin, length = t
                msg = pack("!icii%s" % len(piece), len(piece) + 9, PIECE,
                           index, begin)
                buf.write(msg)
                buf.write(piece)
                if noisy: log("SEND PIECE %d %d" % (index, begin))
            self._partial_message = buf.getvalue()
        if bytes < len(self._partial_message):
            self.fire_sent_listeners(bytes)
            self.connection.write(buffer(self._partial_message, 0, bytes))
            self._partial_message = buffer(self._partial_message, bytes)
            return bytes
        if self.choke_sent != self.upload.choked:
            if self.upload.choked:
                self._outqueue.write(pack("!ic", 1, CHOKE))
                self.upload.sent_choke()
            else:
                self._outqueue.write(pack("!ic", 1, UNCHOKE))
            self.choke_sent = self.upload.choked
        buf = StringIO()
        buf.write(self._partial_message)
        self._partial_message = None
        buf.write(self._outqueue.getvalue())
        # optimize for cpu (reduce mallocs)
        #self._outqueue.truncate(0)
        # optimize for memory (free buffer memory)
        self._outqueue.close()
        self._outqueue = StringIO()
        queue = buf.getvalue()
        self.fire_sent_listeners(len(queue))
        self.connection.write(queue)
        return len(queue)

    # yields the number of bytes it wants next, gets those in self._message
    def _read_messages(self):

        # be compatible with encrypted clients. Thanks Uoti
        yield 1 + len(protocol_name)
        if (self._privkey is not None or
            self._message != chr(len(protocol_name)) + protocol_name):
            if self.locally_initiated:
                if self._privkey is None:
                    return
                dhstr = self._message
                yield DH_BYTES - len(dhstr)
                dhstr += self._message
                pub = bytetonum(dhstr)
                S = numtobyte(pow(pub, self._privkey, dh_prime))
                pub = self._privkey = dhstr = None
                SKEY = self.parent.infohash
                x = sha('req3' + S).digest()
                streamid = sha('req2'+SKEY).digest()
                streamid = ''.join([chr(ord(streamid[i]) ^ ord(x[i]))
                                    for i in range(20)])
                encrypt = ARC4.new(sha('keyA' + S + SKEY).digest()).encrypt
                encrypt('x'*1024)
                padlen = randrange(PAD_MAX)
                x = sha('req1' + S).digest() + streamid + encrypt(
                    '\x00'*8 + '\x00'*3+'\x02'+'\x00'+chr(padlen)+
                    urandom(padlen)+'\x00\x00')
                self.connection.write(x)
                self.connection.encrypt = encrypt
                decrypt = ARC4.new(sha('keyB' + S + SKEY).digest()).decrypt
                decrypt('x'*1024)
                VC = decrypt('\x00'*8) # actually encrypt
                x = ''
                while 1:
                    yield 1
                    x += self._message
                    i = (x + str(self._rest)).find(VC)
                    if i >= 0:
                        break
                    yield len(self._rest)
                    x += self._message
                    if len(x) >= 520:
                        self.protocol_violation('VC not found')
                        return
                yield i + 8 + 4 + 2 - len(x)
                x = decrypt((x + self._message)[-6:])
                self._decrypt = decrypt
                if x[0:4] != '\x00\x00\x00\x02':
                    self.protocol_violation('bad crypto method selected, not 2')
                    return
                padlen = (ord(x[4]) << 8) + ord(x[5])
                if padlen > 512:
                    self.protocol_violation('padlen too long')
                    return
                self.connection.write(''.join((chr(len(protocol_name)),
                                               protocol_name, FLAGS,
                                               self.parent.infohash)))
                yield padlen
            else:
                dhstr = self._message
                yield DH_BYTES - len(dhstr)
                dhstr += self._message
                privkey = bytetonum(urandom(20))
                pub = numtobyte(pow(2, privkey, dh_prime))
                self.connection.write(''.join((pub, urandom(randrange(PAD_MAX)))))
                pub = bytetonum(dhstr)
                S = numtobyte(pow(pub, privkey, dh_prime))
                dhstr = pub = privkey = None
                streamid = sha('req1' + S).digest()
                x = ''
                while 1:
                    yield 1
                    x += self._message
                    i = (x + str(self._rest)).find(streamid)
                    if i >= 0:
                        break
                    yield len(self._rest)
                    x += self._message
                    if len(x) >= 532:
                        self.protocol_violation('incoming VC not found')
                        return
                yield i + 20 + 20 + 8 + 4 + 2 - len(x)
                self._message = (x + self._message)[-34:]
                streamid = self._message[0:20]
                x = sha('req3' + S).digest()
                streamid = ''.join([chr(ord(streamid[i]) ^ ord(x[i]))
                                    for i in range(20)])
                self.parent.select_torrent_obfuscated(self, streamid)
                if self.parent.infohash is None:
                    self.protocol_violation('download id unknown/rejected')
                    return
                self.logger = logging.getLogger(
                    self.log_prefix + '.' + repr(self.parent.infohash) +
                    '.peer_id_not_yet')
                SKEY = self.parent.infohash
                decrypt = ARC4.new(sha('keyA' + S + SKEY).digest()).decrypt
                decrypt('x'*1024)
                s = decrypt(self._message[20:34])
                if s[0:8] != '\x00' * 8:
                    self.protocol_violation('BAD VC')
                    return
                crypto_provide = toint(s[8:12])
                padlen = (ord(s[12]) << 8) + ord(s[13])
                if padlen > 512:
                    self.protocol_violation('BAD padlen, too long')
                    return
                self._decrypt = decrypt
                yield padlen + 2
                s = self._message
                encrypt = ARC4.new(sha('keyB' + S + SKEY).digest()).encrypt
                encrypt('x'*1024)
                self.connection.encrypt = encrypt
                if not crypto_provide & 2:
                    self.protocol_violation("peer doesn't support crypto mode 2")
                    return
                padlen = randrange(PAD_MAX)
                s = '\x00' * 11 + '\x02\x00' + chr(padlen) + urandom(padlen)
                self.connection.write(s)
            S = SKEY = s = x = streamid = VC = padlen = None
            yield 1 + len(protocol_name)
            if self._message != chr(len(protocol_name)) + protocol_name:
                self.protocol_violation('classic handshake fails')
                return

        yield 8  # reserved
        if noisy:
            l = [ c.encode('hex') for c in list(self._message) ]
            log("reserved: %s" % ' '.join(l))

        if ord(self._message[0]) & AZUREUS:
            if noisy: log("Implements Azureus extensions")
            if ord(FLAGS[0]) & AZUREUS:
                self.uses_azureus_extension = True
        if ord(self._message[5]) & UTORRENT:
            if noisy: log("Implements uTorrent extensions")
            if ord(FLAGS[5]) & UTORRENT:
                self.uses_utorrent_extension = True
        if ord(self._message[7]) & DHT:
            if noisy: log("Implements DHT")
            if ord(FLAGS[7]) & DHT:
                self.uses_dht = True
        if ord(self._message[7]) & FAST_EXTENSION:
            if noisy: log("Implements FAST_EXTENSION")
            if not disable_fast_extension:
                self.uses_fast_extension = True
        if ord(self._message[7]) & NAT_TRAVERSAL:
            if noisy: log("Implements NAT_TRAVERSAL")
            if ord(FLAGS[7]) & NAT_TRAVERSAL:
                self.uses_nat_traversal = True


        yield 20 # download id (i.e., infohash)
        if self.parent.infohash is None:  # incoming connection
            # modifies self.parent if successful
            self.parent.select_torrent(self, self._message)
            if self.parent.infohash is None:
                # could be turned away due to connection limits
                #self.protocol_violation("no infohash from parent (peer from a "
                #                        "torrent you're not running: %s)" %
                #                        self._message.encode('hex'))
                return
        elif self._message != self.parent.infohash:
            self.protocol_violation("incorrect infohash from parent")
            return

        if not self.locally_initiated:
            self.connection.write(''.join((chr(len(protocol_name)),
                                           protocol_name, FLAGS,
                                           self.parent.infohash,
                                           self.parent.my_id)))

        yield 20  # peer id
        if noisy: log("peer id: %r" % self._message)
        # if we don't already have the peer's id, send ours
        if not self.id:
            self.id = self._message
            ns = (self.log_prefix + '.' + repr(self.parent.infohash) +
                  '.' + repr(self.id)[1:-1])
            self.logger = logging.getLogger(ns)

            if self.id == self.parent.my_id:
                #self.protocol_violation("talking to self")
                return

            if self.id in self.parent.connector_ids:
                if self.parent.my_id > self.id:
                    #self.protocol_violation("duplicate connection (id collision)")
                    return
            if (self.parent.config['one_connection_per_ip'] and
                self.ip in self.parent.connector_ips):
                self.protocol_violation("duplicate connection (ip collision)")
                return

            if self.locally_initiated:
                self.connection.write(self.parent.my_id)
            else:
                self.parent.everinc = True
        else:
            # assert the id we have and the one we got are the same
            if self._message != self.id:
                self.protocol_violation("incorrect id have:%r got:%r" % (self.id, self._message))
                # this is not critical enough to disconnect. some clients have
                # an option to do this on purpose
                #return

        if self.uses_utorrent_extension:
            response = {'m': {'ut_pex': ord(UTORRENT_MSG_PEX)},
                        'v': ('%s %s' % (app_name, version)).encode('utf8'),
                        'e': 0,
                        'p': self.parent.reported_port,
                        }
            response = bencode(response)
            self._send_message(UTORRENT_MSG,
                               UTORRENT_MSG_INFO, response)

        self.complete = True
        self.parent.connection_handshake_completed(self)

        message_count = 0
        while True:
            yield 4   # message length
            l = toint(self._message)
            if l > self.max_message_length:
                d = '%s%s' % (self._message, self._rest)
                d = d[:10]
                self.protocol_violation("message length exceeds max "
                                        "(%s > %s): %r, count:%d" %
                                        (l, self.max_message_length, d,
                                         message_count))
                return
            if l > 0:
                yield l
                self._got_message(self._message)
                message_count += 1

    def _got_utorrent_msg(self, msg_type, d):
        if msg_type == UTORRENT_MSG_INFO:
            version = d.get('v')
            port = d.get('p')
            if port:
                self.listening_port = int(port)
            encryption = d.get('e')
            messages = d.get('m')
            if 'ut_pex' in messages:
                self.uses_utorrent_pex = True
                self.utorrent_pex_id = messages['ut_pex']
                if not isinstance(self.utorrent_pex_id, (int, long)):
                    try:
                        raise TypeError("LTEX message ids must be int not %r" % self.utorrent_pex_id)
                    except:
                        self.logger.exception("ut_pex support failed")
                        self.uses_utorrent_pex = False
        elif msg_type == UTORRENT_MSG_PEX:
            for i, addr in enumerate(IPTools.uncompact_sequence(d['added'])):
                self.remote_pex_set.add(addr)
                if len(d['added.f']) > i:
                    if (ord(d['added.f'][i]) & 2 and
                        self.parent.downloader.storage.get_amount_left() == 0):
                        # don't connect to seeds if we're done
                        continue
                self.parent.start_connection(addr)
            dropped_gen = IPTools.uncompact_sequence(d['dropped'])
            self.remote_pex_set.difference_update(dropped_gen)

    def _got_azureus_msg(self, msg_type, d):
        port = d.get('tcp_port')
        if port:
            self.listening_port = int(port)
        m = d.get('messages', [])
        for msg in m:
            if msg.get('id') == 'AZ_PEER_EXCHANGE':
                self.uses_azureus_pex = True

    def _got_holepunch_msg(self, d):
        msg_type = d.get('t')
        if msg_type == 'r': # request
            print 'hole punch requested from', self.addr, 'to', d['p']

            d = {'t': 'i'}
            d['p'] = IPTools.compact(addr)
            self._send_message(HOLE_PUNCH, d)

        elif msg_type == 'i': # initiate
            print 'told to initiate connection(s) to:' + str(d['p'])
        else:
            self.protocol_violation("unknown hole punch msg type: %r" %
                                    msg_type)

    def _got_message(self, message):
        t = message[0]
        if t in [BITFIELD, HAVE_ALL, HAVE_NONE] and self.got_anything:
            self.protocol_violation("%s after got anything" % message_dict[t])
            self.close()
            return
        if t == UTORRENT_MSG and self.uses_utorrent_extension:
            msg_type = message[1]
            d = bdecode(message[2:])
            if noisy: log("UTORRENT_MSG: %r:%r" % (msg_type, d))
            self._got_utorrent_msg(msg_type, d)
            return
        if t == AZUREUS_SUCKS and self.uses_azureus_extension:
            magic_intro = 17
            msg_type = message[:magic_intro]
            d = bdecode(message[magic_intro:])
            if noisy: log("AZUREUS_MSG: %r:%r" % (msg_type, d))
            self._got_azureus_msg(msg_type, d)
            return
        if t == HOLE_PUNCH and self.uses_nat_traversal:
            d = ebdecode(message)
            if noisy: log("HOLE_PUNCH: %r" % d)
            self._got_holepunch_msg(d)
            return

        self.got_anything = True
        if (t in (CHOKE, UNCHOKE, INTERESTED, NOT_INTERESTED,
                  HAVE_ALL, HAVE_NONE) and
                len(message) != 1):
            self.protocol_violation("%s with message length %d" %
                                    (message_dict[t], len(message)))
            if noisy: log("UNKNOWN: %r" % message)
            self.close()
            return
        if t == CHOKE:
            if noisy: log("GOT %s" % message_dict[t])
            self.download.got_choke()
        elif t == UNCHOKE:
            if noisy: log("GOT %s" % message_dict[t])
            self.download.got_unchoke()
        elif t == INTERESTED:
            if noisy: log("GOT %s" % message_dict[t])
            self.upload.got_interested()
        elif t == NOT_INTERESTED:
            if noisy: log("GOT %s" % message_dict[t])
            self.upload.got_not_interested()
        elif t == HAVE:
            if len(message) != 5:
                self.protocol_violation("HAVE length: %d != 5" %
                                        len(message))
                self.close()
                return
            i = unpack("!xi", message)[0]
            if noisy: log("GOT HAVE %d" % i)
            if i >= self.parent.numpieces:
                self.protocol_violation("HAVE %d >= %d" %
                                        (i, self.parent.numpieces))
                self.close()
                return
            self.download.got_have(i)
        elif t == BITFIELD:
            try:
                b = Bitfield(self.parent.numpieces, message[1:])
            except ValueError, e:
                self.protocol_violation("BITFIELD %s" %
                                        (e,))
                self.close()
                return
            self.download.got_have_bitfield(b)
        elif t == REQUEST:
            if len(message) != 13:
                self.protocol_violation("REQUEST length %d != 13" %
                                        len(message))
                self.close()
                return
            i, a, b = unpack("!xiii", message)
            if noisy: log("GOT REQUEST %d %d %d" % (i, a, b))
            if i >= self.parent.numpieces:
                self.protocol_violation(
                     "Requested piece index out of range: %d > %d" %
                     (i, self.parent.numpieces))
                self.close()
                return
            if a + b > self.parent.piece_size:
                self.protocol_violation(
                     "Requested range exceeds piece size: "
                     "(b:%d + l:%d == %d) > %d" %
                     (a, b, a + b, self.parent.piece_size))
                self.close()
                return
            if self.download.have[i]:
                self.protocol_violation(
                     "Requested piece index %d which the peer already has" %
                     (i,))
                self.close()
                return
            self.upload.got_request(i, a, b)
        elif t == CANCEL:
            if len(message) != 13:
                self.protocol_violation("CANCEL length %d != 13" %
                                        len(message))
                self.close()
                return
            i, a, b = unpack("!xiii", message)
            if noisy: log("GOT CANCEL %d %d %d" % (i, a, b))
            if i >= self.parent.numpieces:
                self.protocol_violation(
                     "Cancelled piece index %d > numpieces which is %d" %
                     (i,self.parent.numpieces))
                self.close()
                return
            self.upload.got_cancel(i, a, b)
        elif t == PIECE:
            if len(message) <= 9:
                self.protocol_violation("PIECE %d <= 9" %
                                        len(message))
                self.close()
                return
            n = len(message) - 9
            i, a, b = unpack("!xii%ss" % n, message)
            if noisy: log("GOT PIECE %d %d" % (i, a))
            if i >= self.parent.numpieces:
                self.protocol_violation("PIECE %d >= %d" %
                                        (i, self.parent.numpieces))
                self.close()
                return
            self.download.got_piece(i, a, b)
        elif t == PORT:
            if len(message) != 3:
                self.protocol_violation("PORT %d != 3" %
                                        len(message))
                self.close()
                return
            self.dht_port = unpack('!H', message[1:3])[0]
            self.parent.got_port(self)
        elif t == SUGGEST_PIECE:
            if not self.uses_fast_extension:
                self.protocol_violation(
                    "Received 'SUGGEST_PIECE' when fast extension disabled.")
                self.close()
                return
            if len(message) != 5:
                self.protocol_violation("SUGGEST_PIECE length: %d != 5" %
                                        len(message))
                self.close()
                return
            i = unpack("!xi", message)[0]
            if noisy: log("GOT SUGGEST_PIECE %d" % i)
            if i >= self.parent.numpieces:
                self.protocol_violation(
                    "Received 'SUGGEST_PIECE' with piece id %d > numpieces." %
                    self.parent.numpieces)
                self.close()
                return
            self.download.got_suggest_piece(i)
        elif t == HAVE_ALL:
            if noisy: log("GOT %s" % message_dict[t])
            if not self.uses_fast_extension:
                self.protocol_violation(
                    "Received 'HAVE_ALL' when fast extension disabled.")
                self.close()
                return
            self.download.got_have_all()
        elif t == HAVE_NONE:
            if noisy: log("GOT %s" % message_dict[t])
            if not self.uses_fast_extension:
                self.protocol_violation(
                    "Received 'HAVE_NONE' when fast extension disabled.")
                self.close()
                return
            self.download.got_have_none()
        elif t == REJECT_REQUEST:
            if not self.uses_fast_extension:
                self.protocol_violation(
                    "Received 'REJECT_REQUEST' when fast extension disabled.")
                self.close()
                return
            if len(message) != 13:
                self.protocol_violation(
                    "Received 'REJECT_REQUEST' with length %d != 13." %
                    len(message))
                self.close()
                return
            i, a, b = unpack("!xiii", message)
            if noisy: log("GOT REJECT_REQUEST %d %d" % (i,a))
            if i >= self.parent.numpieces:
                self.protocol_violation("REJECT %d >= %d" %
                                        (i, self.parent.numpieces))
                self.close()
                return
            self.download.got_reject_request(i, a, b)
        elif t == ALLOWED_FAST:
            if not self.uses_fast_extension:
                self.protocol_violation(
                    "Received 'ALLOWED_FAST' when fast extension disabled.")
                self.close()
                return
            if len(message) != 5:
                self.protocol_violation("ALLOWED_FAST length: %d != 5" %
                                        len(message))
                self.close()
                return
            i = unpack("!xi", message)[0]
            if noisy: log("GOT ALLOWED_FAST %d" % i)
            self.download.got_allowed_fast(i)
        else:
            if noisy: log("GOT %s length %d" % (message_dict[t], len(message)))
            self.protocol_violation("unhandled message %s" % message_dict[t])
            self.close()

    def _write(self, s):
        if self._partial_message is not None:
            self._outqueue.write(s)
        else:
            self.connection.write(s)

    def _send_message(self, *msg_a):
        if self.closed:
            return
        l = 0
        for e in msg_a:
            l += len(e)
        d = [tobinary(l), ]
        d.extend(msg_a)
        s = ''.join(d)
        self._write(s)

    def data_came_in(self, conn, s):
        self.received_data = True
        if not self.download:
            # this is really annoying.
            self.sloppy_pre_connection_counter += len(s)
        else:
            l = self.sloppy_pre_connection_counter + len(s)
            self.sloppy_pre_connection_counter = 0
            self.download.fire_raw_received_listeners(l)

        if log_data:
            assert self.addr == (conn.ip, conn.port)
            open('%s_%d.log' % self.addr, 'ab').write(s)

        while True:
            if self.closed:
                return
            i = self._next_len - self._buffer.tell()
            if i > len(s):
                # not enough bytes, keep buffering
                self._buffer.write(s)
                return
            if self._buffer.tell() > 0:
                # collect buffer + current for message
                self._buffer.write(buffer(s, 0, i))
                m = self._buffer.getvalue()
                # optimize for cpu (reduce mallocs)
                #self._buffer.truncate(0)
                # optimize for memory (free buffer memory)
                self._buffer.close()
                self._buffer = StringIO()
            else:
                # painful string copy
                m = s[:i]
            s = buffer(s, i)
            if self._decrypt is not None:
                m = self._decrypt(m)
            self._message = m
            self._rest = s
            try:
                self._next_len = self._reader.next()
            except StopIteration:
                self.close()
                return
            except:
                self.protocol_violation("Message parsing failed")
                self.logger.exception("Message parsing failed")
                self.close()
                return

    def _optional_restart(self):
        if (self.locally_initiated and not self.received_data and
            not self.obfuscate_outgoing):
            self.parent.start_connection(self.addr, id=None, encrypt=True)

    def connection_lost(self, conn):
        assert conn is self.connection
        self.closed = True
        self._reader = None
        self.parent.connection_lost(self)

        self._optional_restart()

        self.connection = None
        if self.complete:
            if self.download is not None:
                self.download.disconnected()
            self.upload = None
            self.download = None
        del self._buffer
        del self.parent
        self._sent_listeners.clear()
        del self._message
        del self._partial_message
        self.local_pex_set.clear()

    def connection_flushed(self, connection):
        if (self.complete and self.next_upload is None and
            (self._partial_message is not None
             or (self.upload and self.upload.buffer))):
            if self.lan:
                # bypass upload rate limiter
                self.send_partial(self.parent.ratelimiter.unitsize)
            else:
                self.parent.ratelimiter.queue(self)
