#!/usr/bin/env python

# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Henry 'Pi' James, Loring Holden and Matt Chisholm

app_name = "BitTorrent"
from BTL.translation import _

import time
from sys import *
from os.path import *
from sha import *
from BTL.bencode import *
from BitTorrent import version

NAME, EXT = splitext(basename(argv[0]))

print _("%s %s - decode %s metainfo files") % (NAME, version, app_name)
print 

if len(argv) == 1:
    print _("Usage: %s [TORRENTFILE [TORRENTFILE ... ] ]") % basename(argv[0])
    print
    exit(2) # common exit code for syntax error

labels = {'metafile'   : _("metainfo file: %s"       ),
          'infohash'   : _("info hash: %s"           ),
          'filename'   : _("file name: %s"           ),
          'filesize'   : _("file size:"              ),
          'files'      : _("files:"                  ),
          'title'      : _("title: %s"               ),
          'dirname'    : _("directory name: %s"      ),
          'creation date' : _("creation date: %s"    ),
          'archive'    : _("archive size:"           ),
          'announce'   : _("tracker announce url: %s"),
          'announce-list'   : _("tracker announce list: %s"),
          'nodes'      : _("trackerless nodes:"      ),
          'comment'    : _("comment:"                ),
          'content_type' : _("content_type: %s"      ),
          'url-list' : _("url sources: %s"      ),
          }

maxlength = max( [len(v[:v.find(':')]) for v in labels.values()] )
# run through l10n-ed labels and make them all the same length
for k,v in labels.items():
    if ':' in v:
        index = v.index(':')
        newlabel = v.replace(':', '.'*(maxlength-index) + ':')
        labels[k] = newlabel

for metainfo_name in argv[1:]:
    metainfo_file = open(metainfo_name, 'rb')
    metainfo = bdecode(metainfo_file.read())
    metainfo_file.close()
    info = metainfo['info']
    info_hash = sha(bencode(info))

    if metainfo.has_key('title'):
        print labels['title'] % metainfo['title']
    print labels['metafile'] % basename(metainfo_name)
    print labels['infohash']  % info_hash.hexdigest()
    piece_length = info['piece length']
    if info.has_key('length'):
        # let's assume we just have a file
        print labels['filename'] % info['name']
        file_length = info['length']
        name = labels['filesize']
        if info.has_key('content_type'):
            print labels['content_type'] % info['content_type']
    else:
        # let's assume we have a directory structure
        print labels['dirname'] % info['name']
        print labels['files']
        file_length = 0;
        for file in info['files']:
            path = ''
            for item in file['path']:
                if (path != ''):
                   path = path + "/"
                path = path + item
            if file.has_key('content_type'):
                print '   %s (%d,%s)' % (path, file['length'],
                                         file['content_type'])
            else:
                print '   %s (%d)' % (path, file['length'])
            file_length += file['length']
        name = labels['archive']
    piece_number, last_piece_length = divmod(file_length, piece_length)
    print '%s %i (%i * %i + %i)' \
          % (name,file_length, piece_number, piece_length, last_piece_length)

    if metainfo.has_key('announce'):
        print labels['announce'] % metainfo['announce']
    if 'announce-list' in metainfo:
        print labels['announce-list'] % metainfo['announce-list']
        
    if metainfo.has_key('nodes'):
        print labels['nodes']
        for n in metainfo['nodes']:
            print '\t%s\t:%d' % (n[0], n[1])
        
    if metainfo.has_key('comment'):
        print labels['comment'], metainfo['comment']
    else:
        print labels['comment']
        
    if metainfo.has_key('url-list'):
        print labels['url-list'] % '\n'.join(metainfo['url-list'])

    if metainfo.has_key('creation date'):
        fmt = "%a, %d %b %Y %H:%M:%S"
        gm = time.gmtime(metainfo['creation date'])
        s = time.strftime(fmt, gm)
        print labels['creation date'] % s
        
    # DANGER: modifies torrent file
    if False:
        metainfo_file = open(metainfo_name, 'wb')
        metainfo_file.write(bencode(metainfo))
        metainfo_file.close()