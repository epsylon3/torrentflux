

LOCALE_URL = "http://translations.bittorrent.com/"

class BTFailure(Exception):
    pass

# this class is weak sauce
class InfoHashType(str):
    def __repr__(self):
        return self.encode('hex')
    def short(self):
        return repr(self)[:8]

# soon.
## InfoHashType breaks bencode, so just don't do it.
#def InfoHashType(s):
#    return s

def infohash_short(s):
    return s.encode('hex')[:8]
