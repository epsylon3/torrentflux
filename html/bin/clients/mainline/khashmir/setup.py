# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

#!/usr/bin/env python

import os
import sys

try:
    import distutils.core
    import distutils.command.build_ext
except ImportError:
    raise SystemExit, """\
You don't have the python development modules installed.  

If you have Debian you can install it by running
    apt-get install python-dev

If you have RedHat and know how to install this from an RPM please
email us so we can put instructions here.
"""

try:
    import twisted
except ImportError:
    raise SystemExit, """\
You don't have Twisted installed.

Twisted can be downloaded from 
    http://twistedmatrix.com/products/download

Anything later that version 1.0.3 should work
"""

try:
    import sqlite
except ImportError:
    raise SystemExit, """\
You don't have PySQLite installed.

PySQLite can be downloaded from 
    http://sourceforge.net/project/showfiles.php?group_id=54058&release_id=139482
"""

setup_args = {
    'name': 'khashmir',
    'author': 'Andrew Loewenstern',
    'author_email': 'burris@users.sourceforge.net',
    'licence': 'MIT',
    'package_dir': {'khashmir': '.'},
    'packages': [
        'khashmir', 
    ],
}

if hasattr(distutils.dist.DistributionMetadata, 'get_keywords'):
    setup_args['keywords'] = "internet tcp p2p"

if hasattr(distutils.dist.DistributionMetadata, 'get_platforms'):
    setup_args['platforms'] = "win32 posix"

if __name__ == '__main__':
    apply(distutils.core.setup, (), setup_args)
