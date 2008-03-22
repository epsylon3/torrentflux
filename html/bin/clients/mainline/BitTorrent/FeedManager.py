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

import libxml2
import BTL.stackthreading as threading
#import gobject
from PluginSupport import PluginManager, BasePlugin

# BUG should be in config
UPDATE_FEED_TIMEOUT = 5 * 60 

def get_content(xpath_result):
    if len(xpath_result) == 1:
        return xpath_result[0].content
    return None

class FeedPlugin(BasePlugin):
    mimetype = 'text/xml'
    subtype = None
    
    def __init__(self, main, url, title, description, doc=None):
        BasePlugin.__init__(self, main)
        self.url = url
        self.title = title
        self.description = description
        self.doc = None
        self.thread = None
        self.update(doc)
        timeout_id = gobject.timeout_add(UPDATE_FEED_TIMEOUT * 1000, self.update)

    def _matches_type(mimetype, subtype):
        raise NotImplementedError

    matches_type = staticmethod(_matches_type)

    def update(self, doc=None):
        if self.thread is not None:
            if self.thread.isAlive():
                return True
        
        self.thread = threading.Thread(target=self._update,
                                       args=(doc,))
        self.thread.setDaemon(True)
        self.main.show_status("Downloading %s" % self.url)
        self.main.ui_wrap_func(self.thread.start)
        return True
        

    def _update(self, doc=None):
        self.main.show_status('_update running')
        if self.doc is not None:
            self.main.show_status('_update freeing doc')
            self.doc.freeDoc()
        if doc is None:
            self.main.show_status('_update parsingFile')
            doc = libxml2.parseFile(self.url)
        self.doc = doc
        self.main.feed_was_updated(self.url)
        self.main.show_status(self.get_items())
        self.main.show_status('_update done, thread should die')


    def get_items(self):
        raise NotImplementedError


class FeedManager(PluginManager):
    kind = 'Feed'

    def __init__(self, config, ui_wrap_func):
        PluginManager.__init__(self, config, ui_wrap_func)
        self.ui_wrap_func = ui_wrap_func
        self.channels = {}
        self.update_all()


    def _check_plugin(self, plugin):
        if not PluginManager._check_plugin(self, plugin):
            return False
        if issubclass(plugin, FeedPlugin):
            if hasattr(plugin, 'get_items'):
                return True
        return False 


    def update_all(self):
        for k in self.config.keys():
            self.update_feed(k)


    def update_feed(self, feed_url):
        if self.channels.has_key(feed_url):
            self.show_status("Updating %s" % feed_url)
            self.channels[feed_url].update()
        else:
            self.new_channel(feed_url)
        return

    def new_channel(self, url):
        thread = threading.Thread(target=self._new_channel,
                                       args=(url,))
        thread.setDaemon(True)
        self.ui_wrap_func(thread.start)
        

    def _new_channel(self, url):
        self.show_status("Adding %s" % url)
        doc = None
        channel = None
        try:
            doc = libxml2.parseFile(url)
        except libxml2.parserError:
            self.show_status("Could not parse \"%s\" as XML, searching for torrents" % url)
        else:
            context = doc.xpathNewContext()
            res = context.xpathEval('/rss/@version')
            if get_content(res) in ('0.91', '0.92', '2.0'):
                self.show_status("Found RSS 0.9x/2.0")
                plugin = self._find_plugin('text/xml', 'rss2')
                if plugin is not None:
                    channel = plugin(self, url, doc)
            
            root = doc.children
            if (root.ns() is not None and
                root.ns().get_content() in ("http://www.w3.org/2005/Atom",)):
                self.show_status("Found Atom")
                plugin = self._find_plugin('text/xml', 'atom')
                if plugin is not None:
                    channel = plugin(self, url, doc)
                
            context.xpathFreeContext()
        if channel is None:
            if doc is not None:
                doc.freeDoc()
            self.show_status("Unknown feed type, using raw")
            plugin = self._find_plugin(None, 'raw')
            if plugin is not None:
                channel = plugin(self, url, url)
            
        self.channels[url] = channel


    def feed_was_updated(self, feed_url):
        self.run_ui_task('feed_was_updated', feed_url)


    def get_feed(self, feed_url):
        if not self.channels.has_key(feed_url):
            self.update_feed(feed_url)
        feed = self.channels[feed_url]
        return feed

    def get_items(self, feed_url):
        channel = self.channels[feed_url]
        items = channel.get_items()
        return items

        
    
