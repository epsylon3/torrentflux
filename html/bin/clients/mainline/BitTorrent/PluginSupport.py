# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# Written by Matt Chisholm
import os
import imp
import traceback
import threading
from BitTorrent.platform import plugin_path
from BitTorrent import version

class BasePlugin(object):

    def __init__(self, main):
        self.main = main
        
    def _supports(version):
        return False

    supports = staticmethod(_supports)


class DownloadPlugin(BasePlugin):
    pass

PLUGIN_EXTENSION = '.py'

class PluginManager(object):
    kind = ''
    def __init__(self, config, ui_wrap_func):
        self.config = config
        self.ui_wrap_func = ui_wrap_func
        self.plugin_path = [os.path.join(x, self.kind) for x in plugin_path]
        self.plugins = []
        self._load_plugins()
        
    def _load_plugins(self):
        for p in self.plugin_path:
            files = os.listdir(p)
            for f in files:
                if f[0] != '_' and f.endswith(PLUGIN_EXTENSION):
                    filename = f[:-len(PLUGIN_EXTENSION)]
                    try:
                        plugin_module = imp.load_module(filename, *imp.find_module(filename, [p]))
                    except ImportError:
                        self.show_status('Could not load %s plugin' % filename)
                        traceback.print_exc()
                        continue
                    for c in dir(plugin_module):
                        if c[0] != '_':                        
                            plugin = getattr(plugin_module, c)
                            if self._check_plugin(plugin):
                                self.plugins.append(plugin)
        self.show_status("Loaded:", self.plugins)

    def _check_plugin(self, plugin):
        if not hasattr(plugin, 'supports'):
            return False
        if not plugin.supports(version):
            return False
        return True        

    def _find_plugin(self, *args):
        for p in self.plugins:
            if p.matches_type(*args):
                self.show_status('Found', p, 'for', args)
                return p
        return None

    def run_ui_task(self, funcname, *args):
        self.show_status('Would run', funcname, 'with', args, 'using', self.ui_wrap_func)

    def show_status(self, *msg):
        if False:
            print ' '.join(map(str, msg))
