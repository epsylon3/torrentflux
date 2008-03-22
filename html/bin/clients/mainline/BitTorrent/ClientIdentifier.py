# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# Written by Matt Chisholm
# Client list updated by Ed Savage-Jones - May 28th 2005

import re

v64p = '[\da-zA-Z.-]{3}'

matches = (
           ('-AZ(?P<version>\d+)-+.+$'       , "Azureus"              ),
           ('-PC(?P<version>\d+)-+.+$'       , "CacheLogic"           ),
           ('M(?P<version>\d-\d+-\d+)-+.+$'  , "BitTorrent"           ),
           ('BI(?P<version>\d-\d+-\d+)-+.+$' , "BitTorrent Seeder"    ),
           ('T(?P<version>%s)0?-+.+$'%v64p   , "BitTornado"           ),
           ('-UT(?P<version>[\dA-F]+)-+.+$'  , u"\xb5Torrent"         ),
           ('-TS(?P<version>\d+)-+.+$'       , "TorrentStorm"         ),
           ('exbc(?P<bcver>.+)LORD.+$'       , "BitLord"              ),
           ('exbc(?P<bcver>[^-][^-]+)(?!---).+$', "BitComet"          ),
           ('-BC0(?P<version>\d+)-.+$'       , "BitComet"             ),
           ('FUTB(?P<bcver>.+).+$'           , "BitComet Mod1"        ),
           ('xUTB(?P<bcver>.+).+$'           , "BitComet Mod2"        ),
           ('A(?P<version>%s)-+.+$'%v64p     , "ABC"                  ),
           ('S(?P<version>%s)-+.+$'%v64p     , "Shadow's"             ),
           (chr(0)*12 + 'aa.+$'              , "Experimental 3.2.1b2" ),
           (chr(0)*12 + '.+$'                , "BitTorrent (obsolete)"),
           ('-G3.+$'                         , "G3Torrent"            ),
           ('-LT(?P<version>[A-Za-z0-9]+)-+.+$' , "libtorrent"        ),
           ('-lt(?P<version>[A-Za-z0-9]+)-+.+$' , "libtorrent rakshasa"),
           ('Mbrst(?P<version>\d-\d-\d).+$'  , "burst!"               ),
           ('eX.+$'                          , "eXeem"                ),
           ('\x00\x02BS.+(?P<strver>UDP0|HTTPBT)$', "BitSpirit v2"    ),
           ('\x00[\x02|\x00]BS.+$'           , "BitSpirit v2"         ),
           ('.*(?P<strver>UDP0|HTTPBT)$'     , "BitSpirit"            ),
           ('-BOWP?(?P<version>[\dA-F]+)-.+$', "Bits on Wheels"       ),
           ('(?P<rsver>.+)RSAnonymous.+$'    , "Rufus Anonymous"      ),
           ('(?P<rsver>.+)RS.+$'             , "Rufus"                ),
           ('-ML(?P<version>(\d\.)+\d)(?:\.(?P<strver>CVS))?-+.+$',"MLDonkey"),
           ('346------.+$'                   , "TorrentTopia 1.70"    ),
           ('OP(?P<strver>\d{4}).+$'         , "Opera"                ),
           ('-KT(?P<version>\d+)(?P<rc>R\d+)-+.+$', "KTorrent"        ),
           ('-KT(?P<version>\d+)-+.+$'       , "KTorrent"             ),
# Unknown but seen in peer lists:
           ('-S(?P<version>10059)-+.+$'      , "S (unknown)"          ),
           ('-TR(?P<version>\d+)-+.+$'       , "transmission"         ),
           ('S\x05\x07\x06\x00{7}.+'         , "S 576 (unknown)"      ),
# Clients I've never actually seen in a peer list:           
           ('exbc..---.+$'                   , "BitVampire 1.3.1"     ),
           ('-BB(?P<version>\d+)-+.+$'       , "BitBuddy"             ),
           ('-CT(?P<version>\d+)-+.+$'       , "CTorrent"             ),
           ('-MT(?P<version>\d+)-+.+$'       , "MoonlightTorrent"     ),
           ('-BX(?P<version>\d+)-+.+$'       , "BitTorrent X"         ),
           ('-TN(?P<version>\d+)-+.+$'       , "TorrentDotNET"        ),
           ('-SS(?P<version>\d+)-+.+$'       , "SwarmScope"           ),
           ('-XT(?P<version>\d+)-+.+$'       , "XanTorrent"           ),
           ('U(?P<version>\d+)-+.+$'         , "UPnP NAT Bit Torrent" ),
           ('-AR(?P<version>\d+)-+.+$'       , "Arctic"               ),
           ('(?P<rsver>.+)BM.+$'             , "BitMagnet"            ),
           ('BG(?P<version>\d+).+$'          , "BTGetit"              ),
           ('-eX(?P<version>[\dA-Fa-f]+)-.+$',"eXeem beta"            ),
           ('Plus12(?P<rc>[\dR]+)-.+$'       , "Plus! II"             ),
           ('XBT(?P<version>\d+)[d-]-.+$'    , "XBT"                  ),
           ('-ZT(?P<version>\d+)-+.+$'       , "ZipTorrent"           ),
           ('-BitE\?(?P<version>\d+)-.+$'    , "BitEruct"             ),
           ('O(?P<version>%s)-+.+$'%v64p     , "Osprey Permaseed"     ),
# Guesses based on Rufus source code, never seen in the wild:
           ('-BS(?P<version>\d+)-+.+$'       , "BTSlave"              ),
           ('-SB(?P<version>\d+)-+.+$'       , "SwiftBit"             ),
           ('-SN(?P<version>\d+)-+.+$'       , "ShareNET"             ),
           ('-bk(?P<version>\d+)-+.+$'       , "BitKitten"            ),
           ('-SZ(?P<version>\d+)-+.+$'       , "Shareaza"             ),
           ('-MP(?P<version>\d+)-+.+$'       , "MooPolice"            ),
           ('Deadman Walking-.+$'            , "Deadman"              ),
           ('270------.+$'                   , "GreedBT 2.7.0"        ),
           ('XTORR302.+$'                    , "TorrenTres 0.0.2"     ),
           ('turbobt(?P<version>\d\.\d).+$'  , "TurboBT"              ),
           ('DansClient.+$'                  , "XanTorrent"           ),
           ('-PO(?P<version>\d+)-+.+$'       , "PO (unknown)"         ),
           ('-UR(?P<version>\d+)-+.+$'       , "UR (unknown)"         ),
# Patterns that should be executed last
           ('.*Azureus.*'                    , "Azureus 2.0.3.2"      ),
           )

matches = [(re.compile(pattern, re.DOTALL), name) for pattern, name in matches]

unknown_clients = {}

def identify_client(peerid, log=None):
    #client = 'unknown'
    client = repr(peerid)
    client_str = str(peerid)
    if client_str == client[1:-1]:
        client = client_str
    
    version = ''
    for pat, name in matches:
        m = pat.match(peerid)
        if m:
            client = name
            d = m.groupdict()
            if d.has_key('version'):
                version = d['version']
                version = version.replace('-','.')
                if version.find('.') >= 0:
                    #version = ''.join(version.split('.'))
                    version = version.split('.')

                version = list(version)
                for i,c in enumerate(version):
                    if '0' <= c <= '9':
                        version[i] = c
                    elif 'A' <= c <= 'Z':
                        version[i] = str(ord(c) - 55)
                    elif 'a' <= c <= 'z':
                        version[i] = str(ord(c) - 61)
                    elif c == '.':
                        version[i] = '62'
                    elif c == '-':
                        version[i] = '63'
                    else:
                        break
                version = '.'.join(version)
            elif d.has_key('bcver'):
                bcver = d['bcver']
                version += str(ord(bcver[0])) + '.'
                if len(bcver) > 1:
                    version += str(ord(bcver[1])/10)
                    version += str(ord(bcver[1])%10)
            elif d.has_key('rsver'):
                rsver = d['rsver']
                version += str(ord(rsver[0])) + '.'
                if len(rsver) > 1:
                    version += str(ord(rsver[1])/10) + '.'
                    version += str(ord(rsver[1])%10)
            if d.has_key('strver'):
                if d['strver'] is not None:
                    version += d['strver']
            if d.has_key('rc'):
                rc = 'RC ' + d['rc'][1:]
                if version:
                    version += ' '
                version += rc
            break
    if client == 'unknown':
        # identify Shareaza 2.0 - 2.1
        if len(peerid) == 20 and chr(0) not in peerid[:15]:
            for i in range(16,20):
                 if ord(peerid[i]) != (ord(peerid[i - 16]) ^ ord(peerid[31 - i])):
                     break
            else:
                client = "Shareaza"
        
        
    if log is not None and 'unknown' in client:
        if not unknown_clients.has_key(peerid):
            unknown_clients[peerid] = True
            log.write('%s\n'%peerid)
            log.write('------------------------------\n')
    return client, version

if __name__ == '__main__':
    import sys
    for v in sys.argv[1:]:
        print identify_client(v)