# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Uoti Urpala

# required for Python 2.2
from __future__ import generators

import os
import sys
import logging
import urlparse
from BTL.hash import sha
import socket

#debug=True
global_logger = logging.getLogger("BTL.ConvertedMetainfo")

from BTL.translation import _
from BTL.obsoletepythonsupport import *

from BTL.bencode import bencode
from BTL import btformats
from BTL import BTFailure, InfoHashType
from BTL.platform import get_filesystem_encoding, encode_for_filesystem
from BTL.defer import ThreadedDeferred

WINDOWS_UNSUPPORTED_CHARS = u'"*/:<>?\|'
windows_translate = {}
for x in WINDOWS_UNSUPPORTED_CHARS:
    windows_translate[ord(x)] = u'-'

noncharacter_translate = {}
for i in xrange(0xD800, 0xE000):
    noncharacter_translate[i] = ord('-')
for i in xrange(0xFDD0, 0xFDF0):
    noncharacter_translate[i] = ord('-')
for i in (0xFFFE, 0xFFFF):
    noncharacter_translate[i] = ord('-')

del x, i

def generate_names(name, is_dir):
    if is_dir:
        prefix = name + '.'
        suffix = ''
    else:
        pos = name.rfind('.')
        if pos == -1:
            pos = len(name)
        prefix = name[:pos] + '.'
        suffix = name[pos:]
    i = 0
    while True:
        yield prefix + str(i) + suffix
        i += 1

class ConvertedMetainfo(object):

    def __init__(self, metainfo):
        """metainfo is a dict.  When read from a metainfo (i.e.,
           .torrent file), the file must first be bdecoded before
           being passed to ConvertedMetainfo."""
        self.bad_torrent_wrongfield = False
        self.bad_torrent_unsolvable = False
        self.bad_torrent_noncharacter = False
        self.bad_conversion = False
        self.bad_windows = False
        self.bad_path = False
        self.reported_errors = False

        # All of the following values should be considered READONLY.
        # Modifications to the metainfo that should be written should
        # occur to the underlying metainfo dict directly.
        self.is_batch = False
        self.orig_files = None
        self.files_fs = None
        self.total_bytes = 0
        self.sizes = []
        self.comment = None
        self.title = None          # descriptive title text for whole torrent
        self.creation_date = None
        self.metainfo = metainfo
        self.encoding = None
        self.caches = None

        btformats.check_message(metainfo, check_paths=False)
        info = metainfo['info']
        self.is_private = info.has_key("private") and info['private']
        if 'encoding' in metainfo:
            self.encoding = metainfo['encoding']
        elif 'codepage' in metainfo:
            self.encoding = 'cp%s' % metainfo['codepage']
        if self.encoding is not None:
            try:
                for s in u'this is a test', u'these should also work in any encoding: 0123456789\0':
                    assert s.encode(self.encoding).decode(self.encoding) == s
            except:
                self.encoding = 'iso-8859-1'
                self.bad_torrent_unsolvable = True
        if info.has_key('length'):
            self.total_bytes = info['length']
            self.sizes.append(self.total_bytes)
            if info.has_key('content_type'):
                self.content_type = info['content_type']
            else:
                self.content_type = None  # hasattr or None.  Which is better?
        else:
            self.is_batch = True
            r = []
            self.orig_files = []
            self.sizes = []
            self.content_types = []
            i = 0
            # info['files'] is a list of dicts containing keys:
            # 'length', 'path', and 'content_type'.  The 'content_type'
            # key is optional.
            for f in info['files']:
                l = f['length']
                self.total_bytes += l
                self.sizes.append(l)
                self.content_types.append(f.get('content_type'))
                path = self._get_attr(f, 'path')
                if len(path[-1]) == 0:
                    if l > 0:
                        raise BTFailure(_("Bad file path component: ")+x)
                    # BitComet makes .torrent files with directories
                    # listed along with the files, which we don't support
                    # yet, in part because some idiot interpreted this as
                    # a bug in BitComet rather than a feature.
                    path.pop(-1)

                for x in path:
                    if not btformats.allowed_path_re.match(x):
                        raise BTFailure(_("Bad file path component: ")+x)

                self.orig_files.append('/'.join(path))
                k = []
                for u in path:
                    tf2 = self._to_fs_2(u)
                    k.append((tf2, u))
                r.append((k,i))
                i += 1
            # If two or more file/subdirectory names in the same directory
            # would map to the same name after encoding conversions + Windows
            # workarounds, change them. Files are changed as
            # 'a.b.c'->'a.b.0.c', 'a.b.1.c' etc, directories or files without
            # '.' as 'a'->'a.0', 'a.1' etc. If one of the multiple original
            # names was a "clean" conversion, that one is always unchanged
            # and the rest are adjusted.
            r.sort()
            self.files_fs = [None] * len(r)
            prev = [None]
            res = []
            stack = [{}]
            for x in r:
                j = 0
                x, i = x
                while x[j] == prev[j]:
                    j += 1
                del res[j:]
                del stack[j+1:]
                name = x[j][0][1]
                if name in stack[-1]:
                    for name in generate_names(x[j][1], j != len(x) - 1):
                        name = self._to_fs(name)
                        if name not in stack[-1]:
                            break
                stack[-1][name] = None
                res.append(name)
                for j in xrange(j + 1, len(x)):
                    name = x[j][0][1]
                    stack.append({name: None})
                    res.append(name)
                self.files_fs[i] = os.path.join(*res)
                prev = x

        self.name = self._get_attr(info, 'name')
        self.name_fs = self._to_fs(self.name)
        self.piece_length = info['piece length']

        self.announce = metainfo.get('announce')
        self.announce_list = metainfo.get('announce-list')
        if 'announce-list' not in metainfo and 'announce' not in metainfo:
            self.is_trackerless = True
        else:
            self.is_trackerless = False

        self.nodes = metainfo.get('nodes', [('router.bittorrent.com', 6881)])

        self.title = metainfo.get('title')
        self.comment = metainfo.get('comment')
        self.creation_date = metainfo.get('creation date')
        self.locale = metainfo.get('locale')

        self.safe = metainfo.get('safe')

        self.url_list = metainfo.get('url-list', [])
        if not isinstance(self.url_list, list):
            self.url_list = [self.url_list, ]

        self.caches = metainfo.get('caches')

        self.hashes = [info['pieces'][x:x+20] for x in xrange(0,
            len(info['pieces']), 20)]
        self.infohash = InfoHashType(sha(bencode(info)).digest())


    def show_encoding_errors(self, errorfunc):
        self.reported_errors = True
        if self.bad_torrent_unsolvable:
            errorfunc(logging.ERROR,
                      _("This .torrent file has been created with a broken "
                        "tool and has incorrectly encoded filenames. Some or "
                        "all of the filenames may appear different from what "
                        "the creator of the .torrent file intended."))
        elif self.bad_torrent_noncharacter:
            errorfunc(logging.ERROR,
                      _("This .torrent file has been created with a broken "
                        "tool and has bad character values that do not "
                        "correspond to any real character. Some or all of the "
                        "filenames may appear different from what the creator "
                        "of the .torrent file intended."))
        elif self.bad_torrent_wrongfield:
            errorfunc(logging.ERROR,
                      _("This .torrent file has been created with a broken "
                        "tool and has incorrectly encoded filenames. The "
                        "names used may still be correct."))
        elif self.bad_conversion:
            errorfunc(logging.WARNING,
                      _('The character set used on the local filesystem ("%s") '
                        'cannot represent all characters used in the '
                        'filename(s) of this torrent. Filenames have been '
                        'changed from the original.') % get_filesystem_encoding())
        elif self.bad_windows:
            errorfunc(logging.WARNING,
                      _("The Windows filesystem cannot handle some "
                        "characters used in the filename(s) of this torrent. "
                        "Filenames have been changed from the original."))
        elif self.bad_path:
            errorfunc(logging.WARNING,
                      _("This .torrent file has been created with a broken "
                        "tool and has at least 1 file with an invalid file "
                        "or directory name. However since all such files "
                        "were marked as having length 0 those files are "
                        "just ignored."))

    # At least BitComet seems to make bad .torrent files that have
    # fields in an unspecified non-utf8 encoding.  Some of those have separate
    # 'field.utf-8' attributes.  Less broken .torrent files have an integer
    # 'codepage' key or a string 'encoding' key at the root level.
    def _get_attr(self, d, attrib):
        def _decode(o, encoding):
            if encoding is None:
                encoding = 'utf8'
            if isinstance(o, str):
                try:
                    s = o.decode(encoding)
                except:
                    self.bad_torrent_wrongfield = True
                    s = o.decode(encoding, 'replace')
                t = s.translate(noncharacter_translate)
                if t != s:
                    self.bad_torrent_noncharacter = True
                return t
            if isinstance(o, dict):
                return dict([ (k, _decode(v, k.endswith('.utf-8') and None or encoding)) for k, v in o.iteritems() ])
            if isinstance(o, list):
                return [ _decode(i, encoding) for i in o ]
            return o
        # we prefer utf8 if we can find it. at least it declares its encoding
        v = _decode(d.get(attrib + '.utf-8'), 'utf8')
        if v is None:
            v = _decode(d[attrib], self.encoding)
        return v

    def _fix_windows(self, name, t=windows_translate):
        bad = False
        r = name.translate(t)
        # for some reason name cannot end with '.' or space
        if r[-1] in '. ':
            r = r + '-'
        if r != name:
            self.bad_windows = True
            bad = True
        return (r, bad)

    def _to_fs(self, name):
        return self._to_fs_2(name)[1]

    def _to_fs_2(self, name):
        if sys.platform.startswith('win'):
            name, bad = self._fix_windows(name)

        r, bad = encode_for_filesystem(name)
        self.bad_conversion = bad

        return (bad, r)


    def to_data(self):
        return bencode(self.metainfo)


    def check_for_resume(self, path):
        """
        Determine whether this torrent was previously downloaded to
        path.  Returns:

        -1: STOP! gross mismatch of files
         0: MAYBE a resume, maybe not
         1: almost definitely a RESUME - file contents, sizes, and count match exactly
        """
        STOP   = -1
        MAYBE  =  0
        RESUME =  1

        if self.is_batch != os.path.isdir(path):
            return STOP

        disk_files = {}
        if self.is_batch:
            metainfo_files = dict(zip(self.files_fs, self.sizes))
            metainfo_dirs = set()
            for f in self.files_fs:
                metainfo_dirs.add(os.path.split(f)[0])

            # BUG: do this in a thread, so it doesn't block the UI
            for (dirname, dirs, files) in os.walk(path):
                here = dirname[len(path)+1:]
                for f in files:
                    p = os.path.join(here, f)
                    if p in metainfo_files:
                        disk_files[p] = os.stat(os.path.join(dirname, f))[6]
                        if disk_files[p] > metainfo_files[p]:
                            # file on disk that's bigger than the
                            # corresponding one in the torrent
                            return STOP
                    else:
                        # file on disk that's not in the torrent
                        return STOP
                for i, d in enumerate(dirs):
                    if d not in metainfo_dirs:
                        # directory on disk that's not in the torrent
                        return STOP

        else:
            if os.access(path, os.F_OK):
                disk_files[self.name_fs] = os.stat(path)[6]
            metainfo_files = {self.name_fs : self.sizes[0]}

        if len(disk_files) == 0:
            # no files on disk, definitely not a resume
            return STOP

        if set(disk_files.keys()) != set(metainfo_files.keys()):
            # check files
            if len(metainfo_files) > len(disk_files):
                #file in the torrent that's not on disk
                return MAYBE
        else:
            # check sizes
            ret = RESUME
            for f, s in disk_files.iteritems():

                if disk_files[f] < metainfo_files[f]:
                    # file on disk that's smaller than the
                    # corresponding one in the torrent
                    ret = MAYBE
                else:
                    # file sizes match exactly
                    continue
            return ret

    def get_tracker_ips(self, wrap_task):
        """Returns the list of tracker IP addresses or the empty list if the
           torrent is trackerless.  This extracts the tracker ip addresses
           from the urls in the announce or announce list."""
        df = ThreadedDeferred(wrap_task, self._get_tracker_ips, daemon=True)
        return df

    def _get_tracker_ips(self):
        if hasattr(self, "_tracker_ips"):     # cache result.
            return self._tracker_ips

        if self.announce is not None:
            urls = [self.announce]
        elif self.announce_list is not None:  # list of lists.
            urls = []
            for ulst in self.announce_list:
                urls.extend(ulst)
        else:  # trackerless
            assert self.is_trackerless
            return []

        tracker_ports = [urlparse.urlparse(url)[1] for url in urls]
        trackers = [tp.split(':')[0] for tp in tracker_ports]
        self._tracker_ips = []
        for t in trackers:
            try:
                ip_list = socket.gethostbyname_ex(t)[2]
                self._tracker_ips.extend(ip_list)
            except socket.gaierror:
                global_logger.error( _("Cannot find tracker with name %s") % t )
        return self._tracker_ips



