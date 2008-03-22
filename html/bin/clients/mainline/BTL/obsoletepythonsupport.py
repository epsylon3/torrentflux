# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

from __future__ import generators


def import_curses():
    import curses
    if not hasattr(curses, 'use_default_colors'):
        def use_default_colors():
            return
        curses.use_default_colors = use_default_colors
    return curses

has_set = False
try:
    # python 2.4
    from __builtin__ import set
    has_set = True
except (ImportError, NameError): # I don't know if NameError ever gets raised
    try:
        # python 2.3
        from sets import Set
        set = Set
        has_set = True
    except ImportError:
        # python 2.2
        set = None 
        pass

try:
    from os import urandom
except:
    import random
    def urandom(n):
        return ''.join([ chr(random.randint(0, 255)) for x in xrange(n)])
