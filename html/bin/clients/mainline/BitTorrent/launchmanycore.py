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

# Original version written by John Hoffman, heavily modified for different
# multitorrent architecture by Uoti Urpala (over 40% shorter than original),
# ported to new MultiTorrent (circa 4.20) by David Harrison.

from __future__ import division
from BTL.translation import _

import os
from cStringIO import StringIO
from traceback import print_exc
import logging
from BitTorrent import configfile
from BTL.parsedir import async_parsedir
from BitTorrent.MultiTorrent import MultiTorrent, Feedback
from BitTorrent import BTFailure, UserFailure
from BitTorrent.RawServer_twisted import RawServer
from BTL.ConvertedMetainfo import ConvertedMetainfo
from BTL.defer import DeferredEvent
from BTL.exceptions import str_exc
from BTL.platform import efs2
from time import time
from BTL.yielddefer import launch_coroutine
from BTL.defer import wrap_task, ThreadedDeferred
from twisted.internet import reactor
### PROFILER
#from BTL.profile import Profiler, Stats
#prof = Profiler()
#prof.enable()
### END PROFILER


def coro(f, *args, **kwargs):
    return launch_coroutine(wrap_task(reactor.callLater), f, *args, **kwargs)

class LaunchMany(object):

    def __init__(self, config, display, configfile_key):
      """Starts torrents for all .torrent files in a directory tree.

         All errors are logged using Python logging to 'configfile_key' logger.

         @param config: Preferences object storing config.
         @param display: output function for stats.
      """

      # 4.4.x version of LaunchMany output exceptions to a displayer.
      # This version only outputs stats to the displayer.  We do not use
      # the logger to output stats so that a caller-provided object
      # can provide stats formatting as opposed to using the
      # logger Formatter, which is specific to exceptions, warnings, and
      # info messages.
      self.logger = logging.getLogger(configfile_key)
      try:
        self.multitorrent = None
        self.rawserver = None
        self.config = config
        self.configfile_key = configfile_key
        self.display = display

        self.torrent_dir = efs2(config['torrent_dir'])

        # Ex: torrent_cache = infohash -> (path,metainfo)
        self.torrent_cache = {}

        # maps path -> [(modification time, size), infohash]
        self.file_cache = {}

        # used as set containing paths of files that do not have separate
        # entries in torrent_cache either because torrent_cache already
        # contains the torrent or because the torrent file is corrupt.
        self.blocked_files = {}

        #self.torrent_list = []
        #self.downloads = {}

        self.hashcheck_queue = []
        #self.hashcheck_store = {}
        self.hashcheck_current = None

        self.core_doneflag = DeferredEvent()
        self.rawserver = RawServer(self.config)
        try:

            # set up shut-down procedure before we begin doing things that
            # can throw exceptions.
            def shutdown():
                self.logger.critical(_("shutting down"))
                if self.multitorrent:
                    if len(self.multitorrent.get_torrents()) > 0:
                        for t in self.multitorrent.get_torrents():
                            self.logger.info(_('dropped "%s"') % self.torrent_cache[t.infohash][0])
                    def after_mt(r):
                        self.logger.critical("multitorrent shutdown completed. Calling rawserver.stop")
                        self.rawserver.stop()
                    self.logger.critical( "calling multitorrent shutdown" )
                    df = self.multitorrent.shutdown()
                    #set_flag = lambda *a : self.rawserver.stop()
                    df.addCallbacks(after_mt, after_mt)
                else:
                    self.rawserver.stop()

                ### PROFILER POSTPROCESSING.
                #self.logger.critical( "Disabling profiles" )
                #prof.disable()
                #self.logger.critical( "Running profiler post-processing" )
                #stats = Stats(prof.getstats())
                #stats.sort("inlinetime")
                #self.logger.info( "Calling stats.pprint")
                #stats.pprint()
                #self.logger.info( "After stats.pprint")
                ### PROFILER POSTPROCESSING

            # It is safe to addCallback here, because there is only one thread,
            # but even if the code were multi-threaded, core_doneflag has not
            # been passed to anyone.  There is no chance of a race condition
            # between the DeferredEvent's callback and addCallback.
            self.core_doneflag.addCallback(
                lambda r: self.rawserver.external_add_task(0, shutdown))

            self.rawserver.install_sigint_handler(self.core_doneflag)

            data_dir = config['data_dir']
            self.multitorrent = MultiTorrent(config, self.rawserver, data_dir,
                                             resume_from_torrent_config=False)

            self.rawserver.add_task(0, self.scan)
            self.rawserver.add_task(0.5, self.periodic_check_hashcheck_queue)
            self.rawserver.add_task(self.config['display_interval'],
                                    self.periodic_stats)

            try:
                import signal
                def handler(signum, frame):
                    self.rawserver.external_add_task(0, self.read_config)
                if hasattr(signal, 'SIGHUP'):
                    signal.signal(signal.SIGHUP, handler)
            except Exception, e:
                self.logger.error(_("Could not set signal handler: ") +
                                    str_exc(e))
                self.rawserver.add_task(0, self.core_doneflag.set)

        except UserFailure, e:
            self.logger.error(str_exc(e))
            self.rawserver.add_task(0, self.core_doneflag.set)
        except:
            #data = StringIO()
            #print_exc(file = data)
            #self.logger.error(data.getvalue())
            self.logger.exception("Exception raised while initializing LaunchMany")
            self.rawserver.add_task(0, self.core_doneflag.set)

        # always make sure events get processed even if only for
        # shutting down.
        self.rawserver.listen_forever()
        self.logger.info( "After rawserver.listen_forever" )

      except:
        self.logger.exception("Exception raised early in LaunchMany intialization")

    def scan(self):
        return coro(self._scan)

    def _scan(self):
        try:
            # asynchronous parse.
            df = async_parsedir(self.torrent_dir, self.torrent_cache,
                                 self.file_cache, self.blocked_files)
            yield df
            r = df.getResult()
            ( self.torrent_cache, self.file_cache, self.blocked_files,
                added, removed ) = r
            for infohash, (path, metainfo) in removed.items():
                self.logger.info(_('dropped "%s"') % path)
                self.remove(infohash)
            for infohash, (path, metainfo) in added.items():
                self.logger.info(_('added "%s"'  ) % path)
                if self.config['launch_delay'] > 0:
                    self.rawserver.add_task(self.config['launch_delay'],
                                            self.add, metainfo)

                # torrent may have been known from resume state.
                else:
                    self.add(metainfo)

        except:
            self.logger.exception("scan threw exception")

        # register the call to parse a dir.
        self.rawserver.add_task(self.config['parse_dir_interval'], self.scan)

    def periodic_stats(self):
        df = ThreadedDeferred(wrap_task(self.rawserver.external_add_task),
                              self.stats, daemon = True)
        df.addCallback(lambda r : self.rawserver.add_task(self.config['display_interval'],
                                                          self.periodic_stats))

    def stats(self):
        data = []
        for d in self.multitorrent.get_torrents():
            infohash = d.infohash
            path, metainfo = self.torrent_cache[infohash]
            if self.config['display_path']:
                name = path
            else:
                name = metainfo.name
            size = metainfo.total_bytes
            #d = self.downloads[infohash]
            progress = '0.0%'
            peers = 0
            seeds = 0
            seedsmsg = "S"
            dist = 0.0
            uprate = 0.0
            dnrate = 0.0
            upamt = 0
            dnamt = 0
            t = 0
            msg = ''
            #if d.state in ["created", "initializing"]:
            #    status = _("waiting for hash check")
            #else:
            stats = d.get_status()
            status = stats['activity']
            progress = '%.1f%%' % (int(stats['fractionDone']*1000)/10.0)
            if d.is_running():
                s = stats
                #dist = s['numCopies']
                if d.get_percent_complete()==1.0:
                    seeds = 0 # s['numOldSeeds']
                    seedsmsg = "s"
                else:
                    if s['numSeeds'] + s['numPeers']:
                        t = stats['timeEst']
                        if t is None:
                            t = -1
                        if t == 0:  # unlikely
                            t = 0.01
                        #status = _("downloading")
                    else:
                        t = -1
                        status = _("connecting to peers")
                    seeds = s['numSeeds']
                    dnrate = stats['downRate']
                peers = s['numPeers']
                uprate = stats['upRate']
                upamt = s['upTotal']
                dnamt = s['downTotal']

            data.append(( name, status, progress, peers, seeds, seedsmsg,
                          uprate, dnrate, upamt, dnamt, size, t, msg ))
        stop = self.display(data)
        if stop:
            self.core_doneflag.set()

    def remove(self, infohash):
        df = self.multitorrent.remove_torrent(infohash)
        df.addCallback(lambda *a : self.was_stopped(infohash) )
        df.addErrback(lambda e : self.logger.error(_("Remove failed: "),
                                                   exc_info=e))

    def add(self, metainfo):
        assert isinstance(metainfo, ConvertedMetainfo)
        self.hashcheck_queue.append(metainfo.infohash)
        #self.hashcheck_store[metainfo.infohash] = metainfo

    def periodic_check_hashcheck_queue(self):
        self.check_hashcheck_queue()
        self.rawserver.add_task(5,self.periodic_check_hashcheck_queue)

    def check_hashcheck_queue(self):
        #for t in self.multitorrent.get_torrents():
        #    print t.get_status(True)
        if not self.hashcheck_queue: return

        # if all torrents are initialized then start another.
        for t in self.multitorrent.get_torrents():
            if not t.is_initialized(): return
        infohash = self.hashcheck_queue.pop(0)
        filename = self.determine_filename(infohash)
        torrent_path,metainfo = self.torrent_cache[infohash]
        self.start_torrent(metainfo, filename, filename)

    def start_torrent(self,metainfo, save_incomplete_as, save_as):
        assert isinstance(metainfo, ConvertedMetainfo)
        def create_finished(*args):
            self.multitorrent.start_torrent(metainfo.infohash)

        if self.multitorrent.torrent_known(metainfo.infohash):
            t = self.multitorrent.get_torrent(metainfo.infohash)
            if t.is_initialized():
                create_finished()
            else:
                t.policy = "start" # ensure that the torrent will be started
                                   # by a butler when it finished initializing.
        else:
            df = self.multitorrent.create_torrent(metainfo,
                                                  save_incomplete_as, save_as,
                                                  feedback=self)
            df.addErrback(lambda e : self.logger.error(_("DIED: "),exc_info=e))
            df.addCallback(create_finished)

    def determine_filename(self, infohash):
        # THIS FUNCTION IS PARTICULARLY CONVOLUTED. BLECH! --Dave
        path, metainfo = self.torrent_cache[infohash]
        #path = efs2(path)
        name = metainfo.name_fs
        savein = efs2(self.config['save_in'])
        isdir = metainfo.is_batch
        style = self.config['saveas_style']
        if style == 4:
            torrentname   = os.path.split(path[:-8])[1]
            suggestedname = name
            if torrentname == suggestedname:
                style = 1
            else:
                style = 3

        if style == 1 or style == 3:
            if savein:
                file = os.path.basename(path)
                saveas= \
                  os.path.join(savein, file[:-8]) #strip '.torrent'
            else:
                saveas = path[:-8] # strip '.torrent'
            if style == 3 and not isdir:
                saveas = os.path.join(saveas, name)
        else:
            if savein:
                saveas = os.path.join(savein, name)
            else:
                saveas = os.path.join(os.path.split(path)[0], name)
        return saveas

    def was_stopped(self, infohash):
        try:
            self.hashcheck_queue.remove(infohash)
        except:
            pass
        #else:
        #    del self.hashcheck_store[infohash]
        if self.hashcheck_current == infohash:
            self.hashcheck_current = None

    # Exceptions are now reported via loggers.
    #def global_error(self, level, text):
    #    self.output.message(text)

    # Exceptions are now reported via loggers.
    #def exchandler(self, s):
    #    self.output.exception(s)

    def read_config(self):
        try:
            newvalues = configfile.get_config(self.config, self.configfile_key)
        except Exception, e:
            self.logger.error(_("Error reading config: ") + str_exc(e) )
            return
        self.logger.info(_("Rereading config file"))
        self.config.update(newvalues)
        # The set_option call can potentially trigger something that kills
        # the torrent (when writing this the only possibility is a change in
        # max_files_open causing an IOError while closing files), and so
        # the self.failed() callback can run during this loop.
        for option, value in newvalues.iteritems():
            self.multitorrent.set_option(option, value)
        for torrent in self.downloads.values():
            if torrent is not None:
                for option, value in newvalues.iteritems():
                    torrent.set_option(option, value)

    # rest are callbacks from torrent instances
    def started(self, torrent):
        path, metainfo = self.torrent_cache[torrent.infohash]
        self.logger.info( "started %s (num torrents=%d)" % (path, len(self.torrent_cache) ))
        self.check_hashcheck_queue()

    def failed(self, torrent):
        path, metainfo = self.torrent_cache[torrent.infohash]
        self.logger.info( "failed %s (num torrents=%d)" % (path, len(self.torrent_cache) ))
        self.check_hashcheck_queue()

    def finished(self, torrent):
        path, metainfo = self.torrent_cache[torrent.infohash]
        self.logger.info( "finished %s (num torrents=%d)" % (path, len(self.torrent_cache) ))
        self.check_hashcheck_queue()
        pass

    def finishing(self, torrent):
        #path, metainfo = self.torrent_cache[torrent.infohash]
        #self.logger.info( "finishing %s" % path )
        pass

    # error handling reported via logging.
    def error(self, torrent, level, text):
        pass

    def exception(self, torrent, text):
        pass

