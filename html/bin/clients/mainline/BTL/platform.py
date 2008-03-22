# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

import os
import sys
import time
import codecs
import urllib

if os.name == 'nt':
    #import BTL.likewin32api as win32api
    import win32api
    from win32com.shell import shellcon, shell

is_frozen_exe = getattr(sys, 'frozen', '') == 'windows_exe'

def get_module_filename():
    if is_frozen_exe:
        return os.path.abspath(win32api.GetModuleFileName(0))
    else:
        return os.path.abspath(sys.argv[0])

try:
    from __main__ import app_name
except:
    # ok, I'm sick of this. Everyone gets BTL if they don't
    # specify otherwise.
    app_name = "BTL"


if sys.platform.startswith('win'):
    bttime = time.clock
else:
    bttime = time.time


def urlquote_error(error):
     s = error.object[error.start:error.end]
     s = s.encode('utf8')
     s = urllib.quote(s)
     s = s.decode('ascii')
     return (s, error.end)

codecs.register_error('urlquote', urlquote_error)


def get_filesystem_encoding(errorfunc=None):
    def dummy_log(e):
        print e
        pass
    if not errorfunc:
        errorfunc = dummy_log


    default_encoding = 'utf8'

    if os.path.supports_unicode_filenames:
        encoding = None
    else:
        try:
            encoding = sys.getfilesystemencoding()
        except AttributeError:
            errorfunc("This version of Python cannot detect filesystem encoding.")


        if encoding is None:
            encoding = default_encoding
            errorfunc("Python failed to detect filesystem encoding. "
                      "Assuming '%s' instead." % default_encoding)
        else:
            try:
                'a1'.decode(encoding)
            except:
                errorfunc("Filesystem encoding '%s' is not supported. Using '%s' instead." %
                          (encoding, default_encoding))
                encoding = default_encoding

    return encoding

def encode_for_filesystem(path):
    assert isinstance(path, unicode), "Path should be unicode not %s" % type(path)

    bad = False
    encoding = get_filesystem_encoding()
    if encoding == None:
        encoded_path = path
    else:
        try:
            encoded_path = path.encode(encoding)
        except:
            bad = True
            path.replace(u"%", urllib.quote(u"%"))
            encoded_path = path.encode(encoding, 'urlquote')

    return (encoded_path, bad)

def decode_from_filesystem(path):
    encoding = get_filesystem_encoding()
    if encoding == None:
        assert isinstance(path, unicode), "Path should be unicode not %s" % type(path)
        decoded_path = path
    else:
        assert isinstance(path, str), "Path should be str not %s" % type(path)
        decoded_path = path.decode(encoding)

    return decoded_path

efs = encode_for_filesystem

def efs2(path):
    # same as encode_for_filesystem, but doesn't bother returning "bad"
    return encode_for_filesystem(path)[0]

# this function is the preferred way to get windows' paths
def get_shell_dir(value):
    dir = None
    if os.name == 'nt':
        try:
            dir = shell.SHGetFolderPath(0, value, 0, 0)
        except:
            pass
    return dir

def get_cache_dir():
    dir = None
    if os.name == 'nt':
        dir = get_shell_dir(shellcon.CSIDL_INTERNET_CACHE)
    return dir

