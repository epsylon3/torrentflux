# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen

from BTL.translation import _

from BitTorrent import BTFailure

ints = (long, int)


def check_peers(message):
    if type(message) != dict:
        raise BTFailure
    if message.has_key('failure reason'):
        if type(message['failure reason']) != str:
            raise BTFailure, _("failure reason must be a string")
        return
    if message.has_key('warning message'):
        if type(message['warning message']) != str:
            raise BTFailure, _("warning message must be a string")
    peers = message.get('peers')
    if type(peers) == list:
        for p in peers:
            if type(p) != dict:
                raise BTFailure, _("invalid entry in peer list - peer info must be a dict")
            if type(p.get('ip')) != str:
                raise BTFailure, _("invalid entry in peer list - peer ip must be a string")
            port = p.get('port')
            if type(port) not in ints or p <= 0:
                raise BTFailure, _("invalid entry in peer list - peer port must be an integer")
            if p.has_key('peer id'):
                peerid = p.get('peer id')
                if type(peerid) != str or len(peerid) != 20:
                    raise BTFailure, _("invalid entry in peer list - invalid peerid")
    elif type(peers) != str or len(peers) % 6 != 0:
        raise BTFailure, _("invalid peer list")
    interval = message.get('interval', 1)
    if type(interval) not in ints or interval <= 0:
        raise BTFailure, _("invalid announce interval")
    minint = message.get('min interval', 1)
    if type(minint) not in ints or minint <= 0:
        raise BTFailure, _("invalid min announce interval")
    if type(message.get('tracker id', '')) != str:
        raise BTFailure, _("invalid tracker id")
    npeers = message.get('num peers', 0)
    if type(npeers) not in ints or npeers < 0:
        raise BTFailure, _("invalid peer count")
    dpeers = message.get('done peers', 0)
    if type(dpeers) not in ints or dpeers < 0:
        raise BTFailure, _("invalid seed count")
    last = message.get('last', 0)
    if type(last) not in ints or last < 0:
        raise BTFailure, _('invalid "last" entry')
