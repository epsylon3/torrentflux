# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# by Benjamin C. Wiley Sittler

import Crypto.Cipher.AES as _AES
import sha as _sha
import os as _os
import hmac as _hmac
import string as _string

_urlbase64 = _string.maketrans('+/-_', '-_+/')

def pad(data, length):
    '''
    PKCS #7-style padding with the given block length
    '''
    assert length < 256
    assert length > 0
    padlen = length - len(data) % length
    assert padlen <= length
    assert padlen > 0
    return data + padlen * chr(padlen)

def unpad(data, length):
    '''
    PKCS #7-style unpadding with the given block length
    '''
    assert length < 256
    assert length > 0
    padlen = ord(data[-1])
    assert padlen <= length
    assert padlen > 0
    assert data[-padlen:] == padlen * chr(padlen)
    return data[:-padlen]

def ascii(data):
    '''
    Encode data as URL-safe variant of Base64.
    '''
    return data.encode('base64').translate(_urlbase64, '\r\n')

def unascii(data):
    '''
    Decode data from URL-safe variant of Base64.
    '''
    decoded = data.translate(_urlbase64).decode('base64')
    assert ascii(decoded) == data
    return decoded

def encode(data, secret, salt = None):
    '''
    Encode and return the data as a random-IV-prefixed AES-encrypted
    HMAC-SHA1-authenticated padded message corresponding to the given
    data string and secret, which should be at least 36 randomly
    chosen bytes agreed upon by the encoding and decoding parties.
    '''
    assert len(secret) >= 36
    if salt is None:
        salt = _os.urandom(16)
    aes = _AES.new(secret[:16], _AES.MODE_CBC, salt)
    padded_data = pad(20 * '\0' + data, 16)[20:]
    mac = _hmac.HMAC(key = secret[16:], msg = padded_data, digestmod = _sha).digest()
    encrypted = aes.encrypt(mac + padded_data)
    return salt + encrypted

def decode(data, secret):
    '''
    Decode and return the data from random-IV-prefixed AES-encrypted
    HMAC-SHA1-authenticated padded message corresponding to the given
    data string and secret, which should be at least 36 randomly
    chosen bytes agreed upon by the encoding and decoding parties.
    '''
    assert len(secret) >= 36
    salt = data[:16]
    encrypted = data[16:]
    aes = _AES.new(secret[:16], _AES.MODE_CBC, salt)
    decrypted = aes.decrypt(encrypted)
    mac = decrypted[:20]
    padded_data = decrypted[20:]
    mac2 = _hmac.HMAC(key = secret[16:], msg = padded_data, digestmod = _sha).digest()
    assert mac == mac2
    return unpad(20 * '\0' + padded_data, 16)[20:]

def test():
    '''
    Trivial smoke test to make sure this module works.
    '''
    secret = unascii('D_4j_P5Fh-UWUuH2U3IYw2erxRab5QX0zOR7eYlucT0GfuuwxgoGcfKI_rnyStbllZTPBbCESbKv0kMsUB9tOnLvAU2k7bCcMy7ylUqFwgc=')
    secret2 = unascii('e3YUIIA3APP66cMJrKNRAHVm0nd7BRAxZqyiYadTML78v2yS')
    salt = unascii('yRja3Cj5qc2xhYoSJtCBSw==')
    for data, message, message2 in (
        ('Hello, world!',
         'yRja3Cj5qc2xhYoSJtCBSxqHihP8mZ8TNuiLv_i41uaHM8jUu4N2cpU_XmlH0raoq-6FLOHE3ScV9aPnQ9Ulsg==',
         'yRja3Cj5qc2xhYoSJtCBS8MPPvak9ZDXydyMlACoQ7WSlM7X4PunKhJa775itirxJPD1eFgSnWHjAjmZn_8bvg==',
         
         ),
        ('',
         'yRja3Cj5qc2xhYoSJtCBS6vWZ3nvvsp3gM2-G-co6fVCvkLv6pRrfLQg2vm1yNzr',
         'yRja3Cj5qc2xhYoSJtCBSy9XX0E8Re0XumS1wMMEJFwSkTIQBGqbWGH4_GPMwdrR',
         ),
        ('\0',
         'yRja3Cj5qc2xhYoSJtCBSyEz2FFkaC3bRhMV03csag5MMIrVaWeWK2J1IXIaK_UQ',
         'yRja3Cj5qc2xhYoSJtCBS-05SxrZqgT9XhcEWp0eTLCrdQpnzBGKLL8qvIsc6nx6',
         ),
        ('Hi there!',
         'yRja3Cj5qc2xhYoSJtCBS8oy34UlBkk3v__LUHTa557U04HT_-M80DunhcKbFh-q',
         'yRja3Cj5qc2xhYoSJtCBS-6M4ylGA0jmaPjWRiEoBy3j1R1o17_KbsAH_0CiZRhx',
         ),
        ):
        assert unascii(ascii(data)) == data
        assert ascii(unascii(message)) == message
        assert len(pad(data, 16)) % 16 == 0
        assert unpad(pad(data, 16), 16) == data
        assert message == ascii(encode(data, secret, salt))
        assert decode(unascii(message), secret) == data
        assert decode(encode(data, secret), secret) == data
        assert message2 == ascii(encode(data, secret2, salt))
        assert decode(unascii(message2), secret2) == data
        assert decode(encode(data, secret2), secret2) == data

test()

def main(sys):
    progname = sys.argv[0]
    secret = _os.urandom(36)
    salt = None
    if len(sys.argv) < 2:
        sys.stderr.write('%s: secret is %s\n' % (progname, ascii(secret)))
        sys.stderr.flush()
    elif len(sys.argv) < 3:
        progname, secret = sys.argv
        secret = unascii(secret)
    else:
        progname, secret, salt = sys.argv
        secret = unascii(secret)
        salt = unascii(salt)
    while True:
        line = sys.stdin.readline()
        if not line:
            break
        try:
            sys.stdout.write('%s' % decode(unascii(line.rstrip('\r\n')), secret))
        except:
            sys.stdout.write('%s\n' % ascii(encode(line, secret, salt)))
        sys.stdout.flush()

if __name__ == '__main__':
    import sys
    main(sys)
