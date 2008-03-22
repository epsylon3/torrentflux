# File: main.py
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

'''
Main module for using DOPAL with little effort.
'''

def make_connection(persistent=False, **kwargs):

    '''
    Generate a L{DopalConnection} to an Azureus server, whose location is
    specified by keyword arguments.

    To see what keywords are accepted, see the
    L{set_link_details<dopal.core.AzureusLink.set_link_details>} method. This
    method also takes an additional keyword - C{persistent}, which determines
    whether the connection should be persistent or not (by default, it is not
    persistent).

    This function will return a DopalConnection instance.

    @rtype: L{DopalConnection}
    @see: L{set_link_details<dopal.core.AzureusLink.set_link_details>}
    '''
    connection = DopalConnection()
    connection.set_link_details(**kwargs)

    if not persistent:
        connection.is_persistent_connection = False
    return connection

from dopal.objects import AzureusObjectConnection
class DopalConnection(AzureusObjectConnection):

    '''
    A subclass of
    L{AzureusObjectConnection<dopal.objects.AzureusObjectConnection>} which
    contains an extended API.

    This class defines an extended API, similar to the way that C{Dopal}
    classes contain additional methods compared to their C{Azureus}
    counterparts. It also sets up some different default behaviours (compared
    to L{AzureusObjectConnection<dopal.objects.AzureusObjectConnection>}):
      - All instances are I{persistent} connections by default.
      - A L{RemoteObjectConverter<dopal.convert.RemoteObjectConverter>}
        instance is installed as the default handler for converting XML to
        its appropriate object representation.
      - The L{DOPAL class map<dopal.obj_impl.DOPAL_CLASS_MAP>} is used as the
        standard class mapping.

    @see: The L{obj_impl<dopal.obj_impl>} module documentation.
    '''

    def __init__(self):
        super(DopalConnection, self).__init__()

        from dopal.convert import RemoteObjectConverter
        converter = RemoteObjectConverter(self)

        from dopal.obj_impl import DOPAL_CLASS_MAP
        converter.class_map = DOPAL_CLASS_MAP
        self.converter = converter

        self.is_persistent_connection = True

del AzureusObjectConnection
