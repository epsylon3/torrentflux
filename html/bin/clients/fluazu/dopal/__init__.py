# File: __init__.py
# Library: DOPAL - DO Python Azureus Library
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; version 2 of the License.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details ( see the COPYING file ).
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

__version__ = (0, 6, 0)
__version_str__ = '%s.%s' % (__version__[0], ''.join([str(part) for part in __version__[1:]]))
__user_agent__ = 'DOPAL/' + __version_str__

__all__ = [

    # Module variables.
    '__version__', '__version_str__', '__user_agent__',

    # Front-end modules.
    'interact', 'main', 'scripting',

    # Core-level modules.
    'aztypes', 'core', 'debug', 'errors', 'utils', 'xmlutils',

    # Object-level modules.
    'classes', 'class_defs', 'convert', 'objects', 'obj_impl', 'persistency',
    'logutils',
]

# Mode definitions:
#   0 - Normal behaviour - should always be distributed with this value.
#   1 - Debug mode - raise debug errors when appropriate.
#   2 - Epydoc mode - used when Epydoc API documentation is being generated.
__dopal_mode__ = 0

__doc__ = '''
DOPAL - DO Python Azureus Library (version %(__version_str__)s)

@var __version__: DOPAL version as a tuple.
@var __version_str__: DOPAL version as a string.
@var __user_agent__: User agent string used by DOPAL when communicating with
   Azureus.
@var __dopal_mode__: Debug internal variable which controls some of the
   behaviour of how DOPAL works - not meant for external use.

@group Front-end modules: interact, main, scripting
@group Core-level modules: aztypes, core, debug, errors, utils, xmlutils
@group Object-level modules: classes, class_defs, convert, objects, obj_impl,
          persistency, logutils
''' % vars()

# If we are in debug mode, auto-detect whether Epydoc is running and adjust the
# mode accordingly.
import sys
if __dopal_mode__ == 1 and 'epydoc' in sys.modules:
   __dopal_mode__ = 2
del sys
