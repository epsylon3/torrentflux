# File: classes.py
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
Contains basic class definitions for objects - use this module when doing
instance checking.

This module contains a few utility functions in determining what classes are supported by DOPAL - it has an internal list of all classes that DOPAL is aware of.
'''

from dopal.aztypes import get_component_type as _get_component_type

#
# List of classes created by classes_make.py.
#
azureus_class_list = [
    ('org.gudy.azureus2.plugins', 'LaunchablePlugin'),
    ('org.gudy.azureus2.plugins', 'Plugin'),
    ('org.gudy.azureus2.plugins', 'PluginConfig'),
    ('org.gudy.azureus2.plugins', 'PluginConfigListener'),
    ('org.gudy.azureus2.plugins', 'PluginEvent'),
    ('org.gudy.azureus2.plugins', 'PluginEventListener'),
    ('org.gudy.azureus2.plugins', 'PluginInterface'),
    ('org.gudy.azureus2.plugins', 'PluginListener'),
    ('org.gudy.azureus2.plugins', 'PluginManager'),
    ('org.gudy.azureus2.plugins', 'PluginManagerArgumentHandler'),
    ('org.gudy.azureus2.plugins', 'PluginManagerDefaults'),
    ('org.gudy.azureus2.plugins', 'UnloadablePlugin'),
    ('org.gudy.azureus2.plugins.clientid', 'ClientIDGenerator'),
    ('org.gudy.azureus2.plugins.clientid', 'ClientIDManager'),
    ('org.gudy.azureus2.plugins.config', 'ConfigParameter'),
    ('org.gudy.azureus2.plugins.config', 'ConfigParameterListener'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabase'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseContact'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseEvent'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseKey'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseListener'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseProgressListener'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseTransferHandler'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseTransferType'),
    ('org.gudy.azureus2.plugins.ddb', 'DistributedDatabaseValue'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManager'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManagerChannel'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManagerEvent'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManagerFileInfo'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManagerListener'),
    ('org.gudy.azureus2.plugins.disk', 'DiskManagerRequest'),
    ('org.gudy.azureus2.plugins.download', 'Download'),
    ('org.gudy.azureus2.plugins.download', 'DownloadAnnounceResult'),
    ('org.gudy.azureus2.plugins.download', 'DownloadAnnounceResultPeer'),
    ('org.gudy.azureus2.plugins.download', 'DownloadListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadManager'),
    ('org.gudy.azureus2.plugins.download', 'DownloadManagerListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadManagerStats'),
    ('org.gudy.azureus2.plugins.download', 'DownloadPeerListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadPropertyEvent'),
    ('org.gudy.azureus2.plugins.download', 'DownloadPropertyListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadScrapeResult'),
    ('org.gudy.azureus2.plugins.download', 'DownloadStats'),
    ('org.gudy.azureus2.plugins.download', 'DownloadTrackerListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadWillBeAddedListener'),
    ('org.gudy.azureus2.plugins.download', 'DownloadWillBeRemovedListener'),
    ('org.gudy.azureus2.plugins.download.session', 'SessionAuthenticator'),
    ('org.gudy.azureus2.plugins.installer', 'FilePluginInstaller'),
    ('org.gudy.azureus2.plugins.installer', 'InstallablePlugin'),
    ('org.gudy.azureus2.plugins.installer', 'PluginInstaller'),
    ('org.gudy.azureus2.plugins.installer', 'PluginInstallerListener'),
    ('org.gudy.azureus2.plugins.installer', 'StandardPlugin'),
    ('org.gudy.azureus2.plugins.ipc', 'IPCInterface'),
    ('org.gudy.azureus2.plugins.ipfilter', 'IPBlocked'),
    ('org.gudy.azureus2.plugins.ipfilter', 'IPFilter'),
    ('org.gudy.azureus2.plugins.ipfilter', 'IPRange'),
    ('org.gudy.azureus2.plugins.logging', 'Logger'),
    ('org.gudy.azureus2.plugins.logging', 'LoggerAlertListener'),
    ('org.gudy.azureus2.plugins.logging', 'LoggerChannel'),
    ('org.gudy.azureus2.plugins.logging', 'LoggerChannelListener'),
    ('org.gudy.azureus2.plugins.messaging', 'Message'),
    ('org.gudy.azureus2.plugins.messaging', 'MessageManager'),
    ('org.gudy.azureus2.plugins.messaging', 'MessageManagerListener'),
    ('org.gudy.azureus2.plugins.messaging', 'MessageStreamDecoder'),
    ('org.gudy.azureus2.plugins.messaging', 'MessageStreamEncoder'),
    ('org.gudy.azureus2.plugins.network', 'Connection'),
    ('org.gudy.azureus2.plugins.network', 'ConnectionListener'),
    ('org.gudy.azureus2.plugins.network', 'ConnectionManager'),
    ('org.gudy.azureus2.plugins.network', 'IncomingMessageQueue'),
    ('org.gudy.azureus2.plugins.network', 'IncomingMessageQueueListener'),
    ('org.gudy.azureus2.plugins.network', 'OutgoingMessageQueue'),
    ('org.gudy.azureus2.plugins.network', 'OutgoingMessageQueueListener'),
    ('org.gudy.azureus2.plugins.network', 'RawMessage'),
    ('org.gudy.azureus2.plugins.network', 'Transport'),
    ('org.gudy.azureus2.plugins.peers', 'Peer'),
    ('org.gudy.azureus2.plugins.peers', 'PeerEvent'),
    ('org.gudy.azureus2.plugins.peers', 'PeerListener'),
    ('org.gudy.azureus2.plugins.peers', 'PeerListener2'),
    ('org.gudy.azureus2.plugins.peers', 'PeerManager'),
    ('org.gudy.azureus2.plugins.peers', 'PeerManagerListener'),
    ('org.gudy.azureus2.plugins.peers', 'PeerManagerStats'),
    ('org.gudy.azureus2.plugins.peers', 'PeerReadRequest'),
    ('org.gudy.azureus2.plugins.peers', 'PeerStats'),
    ('org.gudy.azureus2.plugins.peers.protocol', 'PeerProtocolBT'),
    ('org.gudy.azureus2.plugins.peers.protocol', 'PeerProtocolExtensionHandler'),
    ('org.gudy.azureus2.plugins.peers.protocol', 'PeerProtocolManager'),
    ('org.gudy.azureus2.plugins.platform', 'PlatformManager'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareItem'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareManager'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareManagerListener'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResource'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceDir'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceDirContents'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceEvent'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceFile'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceListener'),
    ('org.gudy.azureus2.plugins.sharing', 'ShareResourceWillBeDeletedListener'),
    ('org.gudy.azureus2.plugins.torrent', 'Torrent'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentAnnounceURLList'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentAnnounceURLListSet'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentAttribute'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentAttributeEvent'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentAttributeListener'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentDownloader'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentFile'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentManager'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentManagerEvent'),
    ('org.gudy.azureus2.plugins.torrent', 'TorrentManagerListener'),
    ('org.gudy.azureus2.plugins.tracker', 'Tracker'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerListener'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerPeer'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerPeerEvent'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerPeerListener'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerTorrent'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerTorrentListener'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerTorrentRequest'),
    ('org.gudy.azureus2.plugins.tracker', 'TrackerTorrentWillBeRemovedListener'),
    ('org.gudy.azureus2.plugins.tracker.web', 'TrackerAuthenticationListener'),
    ('org.gudy.azureus2.plugins.tracker.web', 'TrackerWebContext'),
    ('org.gudy.azureus2.plugins.tracker.web', 'TrackerWebPageGenerator'),
    ('org.gudy.azureus2.plugins.tracker.web', 'TrackerWebPageRequest'),
    ('org.gudy.azureus2.plugins.tracker.web', 'TrackerWebPageResponse'),
    ('org.gudy.azureus2.plugins.ui', 'Graphic'),
    ('org.gudy.azureus2.plugins.ui', 'UIInstance'),
    ('org.gudy.azureus2.plugins.ui', 'UIInstanceFactory'),
    ('org.gudy.azureus2.plugins.ui', 'UIManager'),
    ('org.gudy.azureus2.plugins.ui', 'UIManagerEvent'),
    ('org.gudy.azureus2.plugins.ui', 'UIManagerEventListener'),
    ('org.gudy.azureus2.plugins.ui', 'UIManagerListener'),
    ('org.gudy.azureus2.plugins.ui', 'UIPluginView'),
    ('org.gudy.azureus2.plugins.ui.components', 'UIComponent'),
    ('org.gudy.azureus2.plugins.ui.components', 'UIProgressBar'),
    ('org.gudy.azureus2.plugins.ui.components', 'UIPropertyChangeEvent'),
    ('org.gudy.azureus2.plugins.ui.components', 'UIPropertyChangeListener'),
    ('org.gudy.azureus2.plugins.ui.components', 'UITextArea'),
    ('org.gudy.azureus2.plugins.ui.components', 'UITextField'),
    ('org.gudy.azureus2.plugins.ui.config', 'ActionParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'BooleanParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'ConfigSection'),
    ('org.gudy.azureus2.plugins.ui.config', 'ConfigSectionSWT'),
    ('org.gudy.azureus2.plugins.ui.config', 'DirectoryParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'EnablerParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'IntParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'LabelParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'Parameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'ParameterGroup'),
    ('org.gudy.azureus2.plugins.ui.config', 'ParameterListener'),
    ('org.gudy.azureus2.plugins.ui.config', 'PasswordParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'PluginConfigUIFactory'),
    ('org.gudy.azureus2.plugins.ui.config', 'StringListParameter'),
    ('org.gudy.azureus2.plugins.ui.config', 'StringParameter'),
    ('org.gudy.azureus2.plugins.ui.menus', 'MenuItem'),
    ('org.gudy.azureus2.plugins.ui.menus', 'MenuItemFillListener'),
    ('org.gudy.azureus2.plugins.ui.menus', 'MenuItemListener'),
    ('org.gudy.azureus2.plugins.ui.model', 'BasicPluginConfigModel'),
    ('org.gudy.azureus2.plugins.ui.model', 'BasicPluginViewModel'),
    ('org.gudy.azureus2.plugins.ui.model', 'PluginConfigModel'),
    ('org.gudy.azureus2.plugins.ui.model', 'PluginViewModel'),
    ('org.gudy.azureus2.plugins.ui.SWT', 'GraphicSWT'),
    ('org.gudy.azureus2.plugins.ui.SWT', 'SWTManager'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCell'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCellAddedListener'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCellDisposeListener'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCellMouseListener'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCellRefreshListener'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableCellToolTipListener'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableColumn'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableContextMenuItem'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableManager'),
    ('org.gudy.azureus2.plugins.ui.tables', 'TableRow'),
    ('org.gudy.azureus2.plugins.ui.tables.mytorrents', 'MyTorrentsTableItem'),
    ('org.gudy.azureus2.plugins.ui.tables.mytorrents', 'PluginMyTorrentsItem'),
    ('org.gudy.azureus2.plugins.ui.tables.mytorrents', 'PluginMyTorrentsItemFactory'),
    ('org.gudy.azureus2.plugins.ui.tables.peers', 'PeerTableItem'),
    ('org.gudy.azureus2.plugins.ui.tables.peers', 'PluginPeerItem'),
    ('org.gudy.azureus2.plugins.ui.tables.peers', 'PluginPeerItemFactory'),
    ('org.gudy.azureus2.plugins.update', 'UpdatableComponent'),
    ('org.gudy.azureus2.plugins.update', 'Update'),
    ('org.gudy.azureus2.plugins.update', 'UpdateChecker'),
    ('org.gudy.azureus2.plugins.update', 'UpdateCheckerListener'),
    ('org.gudy.azureus2.plugins.update', 'UpdateCheckInstance'),
    ('org.gudy.azureus2.plugins.update', 'UpdateCheckInstanceListener'),
    ('org.gudy.azureus2.plugins.update', 'UpdateInstaller'),
    ('org.gudy.azureus2.plugins.update', 'UpdateListener'),
    ('org.gudy.azureus2.plugins.update', 'UpdateManager'),
    ('org.gudy.azureus2.plugins.update', 'UpdateManagerDecisionListener'),
    ('org.gudy.azureus2.plugins.update', 'UpdateManagerListener'),
    ('org.gudy.azureus2.plugins.update', 'UpdateProgressListener'),
    ('org.gudy.azureus2.plugins.utils', 'AggregatedDispatcher'),
    ('org.gudy.azureus2.plugins.utils', 'AggregatedList'),
    ('org.gudy.azureus2.plugins.utils', 'AggregatedListAcceptor'),
    ('org.gudy.azureus2.plugins.utils', 'ByteArrayWrapper'),
    ('org.gudy.azureus2.plugins.utils', 'Formatters'),
    ('org.gudy.azureus2.plugins.utils', 'LocaleDecoder'),
    ('org.gudy.azureus2.plugins.utils', 'LocaleListener'),
    ('org.gudy.azureus2.plugins.utils', 'LocaleUtilities'),
    ('org.gudy.azureus2.plugins.utils', 'Monitor'),
    ('org.gudy.azureus2.plugins.utils', 'PooledByteBuffer'),
    ('org.gudy.azureus2.plugins.utils', 'Semaphore'),
    ('org.gudy.azureus2.plugins.utils', 'ShortCuts'),
    ('org.gudy.azureus2.plugins.utils', 'Utilities'),
    ('org.gudy.azureus2.plugins.utils', 'UTTimer'),
    ('org.gudy.azureus2.plugins.utils', 'UTTimerEvent'),
    ('org.gudy.azureus2.plugins.utils', 'UTTimerEventPerformer'),
    ('org.gudy.azureus2.plugins.utils.resourcedownloader', 'ResourceDownloader'),
    ('org.gudy.azureus2.plugins.utils.resourcedownloader', 'ResourceDownloaderDelayedFactory'),
    ('org.gudy.azureus2.plugins.utils.resourcedownloader', 'ResourceDownloaderFactory'),
    ('org.gudy.azureus2.plugins.utils.resourcedownloader', 'ResourceDownloaderListener'),
    ('org.gudy.azureus2.plugins.utils.resourceuploader', 'ResourceUploader'),
    ('org.gudy.azureus2.plugins.utils.resourceuploader', 'ResourceUploaderFactory'),
    ('org.gudy.azureus2.plugins.utils.security', 'CertificateListener'),
    ('org.gudy.azureus2.plugins.utils.security', 'PasswordListener'),
    ('org.gudy.azureus2.plugins.utils.security', 'SESecurityManager'),
    ('org.gudy.azureus2.plugins.utils.xml.rss', 'RSSChannel'),
    ('org.gudy.azureus2.plugins.utils.xml.rss', 'RSSFeed'),
    ('org.gudy.azureus2.plugins.utils.xml.rss', 'RSSItem'),
    ('org.gudy.azureus2.plugins.utils.xml.simpleparser', 'SimpleXMLParserDocument'),
    ('org.gudy.azureus2.plugins.utils.xml.simpleparser', 'SimpleXMLParserDocumentAttribute'),
    ('org.gudy.azureus2.plugins.utils.xml.simpleparser', 'SimpleXMLParserDocumentFactory'),
    ('org.gudy.azureus2.plugins.utils.xml.simpleparser', 'SimpleXMLParserDocumentNode'),
]

# Record the existance of the classes which are mentioned above.
# (We need this for lookups.)
_known_class_names = dict([(cls_tpl[1], None) for cls_tpl in azureus_class_list]).keys()

import dopal
if dopal.__dopal_mode__ == 1:
    # Check we don't get any nameclashes.
    if len(azureus_class_list) != len(_known_class_names):
        raise RuntimeError, 'difference in class sizes'

# We do more to generate a nice docstring in epydoc mode.
# Bugfix for tf-b4rt: don't try to use/change __doc__ if it's
# empty, which is the case if Python was invoked with -OO
# (except for early Python 2.5 releases where -OO is broken:
# http://mail.python.org/pipermail/python-bugs-list/2007-June/038590.html).
if __doc__ is not None and dopal.__dopal_mode__ == 2:
    grouped_classes = {}
    for package_name, class_name in azureus_class_list:
        grouped_classes.setdefault(package_name, []).append(class_name)

    ordered_grouped_packages = grouped_classes.keys()
    ordered_grouped_packages.sort()

    generated_lines = []

    base_url = 'http://azureus.sourceforge.net/plugins/docCVS/'
    package_tmpl = base_url + '%s/package-summary.html'
    class_tmpl = base_url + '%s/%s.html'
    for package_name in ordered_grouped_packages:
        package_path = package_name.replace('.', '/')
        full_package_url = package_tmpl % package_path
        generated_lines.append(
            '\n  - Package C{U{%(package_name)s<%(full_package_url)s>}}' % vars()
        )
        for class_name in grouped_classes[package_name]:
            full_class_url = class_tmpl % (package_path, class_name)
            generated_lines.append(
                '    - Class C{U{%(class_name)s<%(full_class_url)s>}}' % vars()
            )

    __doc__ += "\n\nThe following classes are well-supported by DOPAL (the "
    __doc__ += 'links below link to the Azureus\'s own '
    __doc__ += 'U{Javadoc API documentation<%(base_url)s>}):\n' % vars()
    __doc__ +=  '\n'.join(generated_lines)

    del package_path, full_package_url, full_class_url
    del base_url, package_tmpl, class_tmpl
    del package_name, class_name, generated_lines
    del grouped_classes, ordered_grouped_packages
del azureus_class_list

def is_azureus_class(class_name):
    return class_name in _known_class_names

is_azureus_argument_class = is_azureus_class

def is_azureus_return_class(class_name):

    if is_azureus_class(class_name):
        return True

    class_component_type = _get_component_type(class_name)
    if class_component_type is not None:
        if is_azureus_class(class_name):
            return True

    return False
