# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# GetTorrent -- abstraction which can get a .torrent file from multiple
# sources: local file, url, etc.

# written by Matt Chisholm and David Harrison

import os
import re
from BitTorrent import zurllib
from BTL.translation import _

from BTL.bencode import bdecode, bencode
from BTL.ConvertedMetainfo import ConvertedMetainfo
from BTL.platform import get_cache_dir

urlpat = re.compile('^\w+://')
urlpat_torrent = re.compile('^torrent://')
urlpat_bittorrent = re.compile('^bittorrent://')

class GetTorrentException(Exception):
    pass

class UnknownArgument(GetTorrentException):
    pass

class URLException(GetTorrentException):
    pass

class FileException(GetTorrentException):
    pass

class MetainfoException(GetTorrentException):
    pass

def get_quietly(arg):
    """Wrapper for GetTorrent.get() which works around an IE bug.  If
    there's an error opening a file from the IE cache, act like we
    simply didn't get a file (because we didn't).  This happens when
    IE passes us a path to a file in its cache that has already been
    deleted because it came from a website which set Pragma:No-Cache
    on it.
    """
    try:
        data = get(arg)
    except FileException, e:
        cache = get_cache_dir()
        if (cache is not None) and (cache in unicode(e.args[0])):
            data = None
        else:
            raise

    return data

def _get(arg):
    """Obtains the contents of the .torrent metainfo file either from
       the local filesystem or from a remote server. 'arg' is either
       a filename or an URL.

       Returns data, the raw contents of the .torrent file. Any
       exception raised while obtaining the .torrent file or parsing
       its contents is caught and wrapped in one of the following
       errors: GetTorrent.URLException, GetTorrent.FileException,
       GetTorrent.MetainfoException, or GetTorrent.UnknownArgument.
       (All have the base class GetTorrent.GetTorrentException)
       """
    data = None
    arg_stripped = arg.strip()
    if os.access(arg, os.F_OK):
        data = get_file(arg)
    elif urlpat.match(arg_stripped):
        data = get_url(arg_stripped)
    else:
        raise UnknownArgument(_('Could not read "%s", it is neither a file nor a URL.') % arg)

    return data


def get(arg):
    """Obtains the contents of the .torrent metainfo file either from
       the local filesystem or from a remote server. 'arg' is either
       a filename or an URL.

       Returns a ConvertedMetainfo object which is the parsed metainfo
       from the contents of the .torrent file.  Any exception raised
       while obtaining the .torrent file or parsing its contents is
       caught and wrapped in one of the following errors:
       GetTorrent.URLException, GetTorrent.FileException, 
       GetTorrent.MetainfoException, or GetTorrent.UnknownArgument.
       (All have the base class GetTorrent.GetTorrentException)
       """
    data = _get(arg)
    metainfo = None
    try:
        b = bdecode(data)
        metainfo = ConvertedMetainfo(b)
    except Exception, e:
        raise MetainfoException(
            (_('"%s" is not a valid torrent file (%s).') % (arg, unicode(e)))
            )

    return metainfo


def get_url(url):
    """Downloads the .torrent metainfo file specified by the passed
       URL and returns data, the raw contents of the metainfo file.
       Any exception raised while trying to obtain the metainfo file
       is caught and GetTorrent.URLException is raised instead.
       """

    data = None
    err_str = ((_('Could not download or open "%s"')% url) + '\n' +
               _("Try using a web browser to download the torrent file."))
    u = None

    # pending protocol changes, convert:
    #   torrent://http://path.to/file
    # and:
    #   bittorrent://http://path.to/file
    # to:
    #   http://path.to/file
    url = urlpat_torrent.sub('', url)
    url = urlpat_bittorrent.sub('', url)
    
    try:
        u = zurllib.urlopen(url)
        data = u.read()
        u.close()
    except Exception, e:
        if u is not None:
            u.close()
        raise URLException(err_str + "\n(%s)" % e)
    else:
        if u is not None:
            u.close()

    return data
    

def get_file(filename):
    """Reads the .torrent metainfo file specified by the passed
       filename and returns data, the raw contents of the metainfo file.
       Any exception raised while trying to obtain the metainfo file is
       caught and GetTorrent.FileException is raised instead.
       """
    data = None
    f = None
    try:
        f = file(filename, 'rb')
        data = f.read()
        f.close()
    except Exception, e:
        if f is not None:
            f.close()
        raise FileException((_("Could not read %s") % filename) + (': %s' % unicode(e.args[0])))

    return data
