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

# Written by Bram Cohen

app_name = "BitTorrent"
from BTL.translation import _

import sys
import locale
from BitTorrent.defaultargs import get_defaults
from BitTorrent import configfile
from BitTorrent.makemetafile import make_meta_files
from BitTorrent.parseargs import parseargs, printHelp
from BitTorrent import BTFailure

defaults = get_defaults('maketorrent-console')
defaults.extend([
    ('target', '',
     _("optional target file for the torrent")),
    ])

defconfig = dict([(name, value) for (name, value, doc) in defaults])
del name, value, doc

def dc(v):
    print v

def prog(amount):
    print '%.1f%% complete\r' % (amount * 100),

if __name__ == '__main__':

    config, args = configfile.parse_configuration_and_args(defaults,
                                                    'maketorrent-console',
                                                    sys.argv[1:], minargs=2)

    le = locale.getpreferredencoding()

    try:
        make_meta_files(args[0],
                        [s.decode(le) for s in args[1:]],
                        progressfunc=prog,
                        filefunc=dc,
                        piece_len_pow2=config['piece_size_pow2'],
                        title=config['title'],
                        comment=config['comment'],
                        content_type=config['content_type'], # what to do in
                                                             # multifile case?
                        target=config['target'],
                        use_tracker=config['use_tracker'],
                        data_dir=config['data_dir'])
    except BTFailure, e:
        print unicode(e.args[0])
        sys.exit(1)
