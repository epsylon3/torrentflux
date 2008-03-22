# File: persistency.py
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
Support module containing code which supports the persistency functionality offered by DOPAL.
'''
_refresh_methods = {

    # Simple ones.
    'PluginConfig': lambda pi, obj: \
        pi.getPluginconfig(),
    'DownloadManager': lambda pi, obj: \
        pi.getDownloadManager(),
    'IPFilter': lambda pi, obj: \
        pi.getIPFilter(),
    'ShortCuts': lambda pi, obj: \
        pi.getShortCuts(),
    'TorrentManager': lambda pi, obj: \
        pi.getTorrentManager(),
    'PluginInterface': lambda pi, obj: \
        pi,

    # Not so simple ones.
    'Download': lambda pi, obj: \
        pi.getShortCuts().getDownload(obj.torrent.hash),
    'Torrent': lambda pi, obj: \
        pi.getShortCuts().getDownload(obj.hash).torrent,
}

# XXX: Test and document.
def get_equivalent_object_from_root(original_object, plugin_interface):

    import dopal.objects
    if not isinstance(original_object, dopal.objects.RemoteObject):
        raise ValueError, "%s is not a RemoteObject" % (original_object,)

    from dopal.errors import NonRefreshableObjectTypeError, \
        MissingRemoteAttributeError, NonRefreshableIncompleteObjectError
    remote_type = original_object.get_remote_type()
    try:
        refresh_function = _refresh_methods[remote_type]
    except KeyError:
        raise NonRefreshableObjectTypeError(obj=original_object)

    try:
        return refresh_function(plugin_interface, original_object)
    except MissingRemoteAttributeError, error:
        raise NonRefreshableIncompleteObjectError(obj=original_object, error=error)
