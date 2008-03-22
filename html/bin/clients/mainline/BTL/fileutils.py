from BTL.ConvertedMetainfo import ConvertedMetainfo
from BTL.bencode import bencode, bdecode
import os


def file_from_path(path):
    assert os.path.splitext(path)[1].lower() == '.torrent'
    return open(path, 'rb').read()

def metainfo_from_file(f):
    metainfo = ConvertedMetainfo(bdecode(f))
    return metainfo

def metainfo_from_path(path):
    return metainfo_from_file(file_from_path(path))

def infohash_from_path(path):
    return str(metainfo_from_path(path).infohash)

def parse_infohash(ihash):
    """takes a hex-encoded infohash and returns an infohash or None
       if the infohash is invalid."""
    try:
        x = ihash.decode('hex')
    except ValueError:
        return None
    except TypeError:
        return None
    return x

def is_valid_infohash(x):
    """Determine whether this is a valid hex-encoded infohash."""
    if not x or not len(x) == 40:
        return False
    return (parse_infohash(x) != None)


def parse_uuid(uuid):
    """takes a hex-encoded uuid and returns an uuid or None
       if the uuid is invalid."""
    try:
        # Remove the '-'s at specific points
        uuidhash = uuid[:8] + uuid[9:13] + uuid[14:18] + uuid[19:23] + uuid[24:]
        if len(uuidhash) != 32:
            return None
        x = uuidhash.decode('hex')
        return uuid
    except:
        return None


def is_valid_uuid(x):
    """Determine whether this is a valid hex-encoded uuid."""
    if not x or len(x) != 36:
        return False
    return (parse_uuid(x) != None)


def infohash_from_infohash_or_path(x):
    """Expects a valid path to a .torrent file or a hex-encoded infohash.
       Returns a binary infohash."""
    if not len(x) == 40:
        return infohash_from_path(x)
    n = parse_infohash(x)
    if n:
        return n
    ## path happens to be 40 chars, or bad infohash
    return infohash_from_path(x)


if __name__ == "__main__":
    # Test is_valid_infohash()
   assert is_valid_infohash("") == False
   assert is_valid_infohash("12345") == False
   assert is_valid_infohash("12345678901234567890123456789012345678901") == False
   assert is_valid_infohash("abcdefghijklmnopqrstuvwxyzabcdefghijklmn") == False
   assert is_valid_infohash("1234567890123456789012345678901234567890") == True
   assert is_valid_infohash("deadbeefdeadbeefdeadbeefdeadbeefdeadbeef") == True

