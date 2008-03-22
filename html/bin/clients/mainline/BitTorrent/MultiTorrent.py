# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.0 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Author: Steve Hazel, Bram Cohen, and Uoti Urpala.

import os
import sys
import shutil
import socket
import cPickle
import logging
import traceback
from copy import copy
from BTL.translation import _
from BitTorrent.Choker import Choker
from BTL.platform import bttime, encode_for_filesystem, get_filesystem_encoding
from BitTorrent.platform import old_broken_config_subencoding
from BitTorrent.Torrent import Feedback, Torrent
from BTL.bencode import bdecode
from BTL.ConvertedMetainfo import ConvertedMetainfo
from BTL.exceptions import str_exc
from BitTorrent.prefs import Preferences
from BitTorrent.NatTraversal import NatTraverser
from BitTorrent.BandwidthManager import BandwidthManager
from BitTorrent.InternetWatcher import get_internet_watcher
from BitTorrent.NewRateLimiter import MultiRateLimiter as RateLimiter
from BitTorrent.DownloadRateLimiter import DownloadRateLimiter
from BitTorrent.ConnectionManager import SingleportListener
from BitTorrent.CurrentRateMeasure import Measure
from BitTorrent.Storage import FilePool
from BTL.yielddefer import launch_coroutine
from BTL.defer import Deferred, DeferredEvent, wrap_task
from BitTorrent import BTFailure, InfoHashType
from BitTorrent import configfile
from khashmir.utkhashmir import UTKhashmir

class TorrentException(BTFailure):
    pass
class TorrentAlreadyInQueue(TorrentException):
    pass
class TorrentAlreadyRunning(TorrentException):
    pass
class TorrentNotInitialized(TorrentException):
    pass
class TorrentNotRunning(TorrentException):
    pass
class UnknownInfohash(TorrentException):
    pass
class TorrentShutdownFailed(TorrentException):
    pass
class TooManyTorrents(TorrentException):
    pass

#class DummyTorrent(object):
#    def __init__(self, infohash):
#        self.metainfo = object()
#        self.metainfo.infohash = infohash

BUTLE_INTERVAL = 1

class MultiTorrent(Feedback):
    """A MultiTorrent object represents a set of BitTorrent file transfers.
       It acts as a factory for Torrent objects, and it acts as
       the interface through which communication is performed to and from
       torrent file transfers.

       If you wish to instantiate MultiTorrent to download only a single
       torrent then pass is_single_torrent=True.

       If you want to avoid resuming from prior torrent config state then
       pass resume_from_torrent_config = False.
       It will still use fast resume if available.

       """

    def __init__(self, config, rawserver,
                 data_dir, listen_fail_ok=False, init_torrents=True,
                 is_single_torrent=False, resume_from_torrent_config=True):
        """
         @param config: program-wide configuration object.
         @param rawserver: object that manages main event loop and event
           scheduling.
         @param data_dir: where variable data such as fastresume information
           and GUI state is saved.
         @param listen_fail_ok: if false, a BTFailure is raised if
           a server socket cannot be opened to accept incoming peer
           connections.
         @param init_torrents: restore fast resume state from prior
           instantiations of MultiTorrent.
         @param is_single_torrent: if true then allow only one torrent
           at a time in this MultiTorrent.
         @param resume_from_torrent_config: resume from ui_state files.
        """
        # is_single_torrent will go away when we move MultiTorrent into
        # a separate process, in which case, single torrent applications like
        # curses and console will act as a client to the MultiTorrent daemon.
        #   --Dave

        # init_torrents refers to fast resume rather than torrent config.
        # If init_torrents is set to False, the UI state file is still
        # read and the paths to existing downloads still used. This is
        # not what we want for launchmany.
        #
        # resume_from_torrent_config is separate from
        # is_single_torrent because launchmany must be able to have
        # multiple torrents while not resuming from torrent config
        # state.  If launchmany resumes from torrent config then it
        # saves or seeds from the path in the torrent config even if
        # the file has moved in the directory tree.  Because
        # launchmany has no mechanism for removing torrents other than
        # to change the directory tree, the only way for the user to
        # eliminate the old state is to wipe out the files in the
        # .bittorrent/launchmany-*/ui_state directory.  This is highly
        # counterintuitive.  Best to simply ignore the ui_state
        # directory altogether.  --Dave

        assert isinstance(config, Preferences)
        #assert isinstance(data_dir, unicode)  # temporarily commented -Dave
        assert isinstance(listen_fail_ok, bool)
        assert not (is_single_torrent and resume_from_torrent_config)

        # flag for done
        self.isDone = False

        self.config = config
        self.data_dir = data_dir
        self.last_save_time = 0
        self.policies = []
        self.torrents = {}
        self.running = {}
        self.log_root = "core.MultiTorrent"
        self.logger = logging.getLogger(self.log_root)
        self.is_single_torrent = is_single_torrent
        self.resume_from_torrent_config = resume_from_torrent_config
        self.auto_update_policy_index = None
        self.dht = None
        self.rawserver = rawserver
        nattraverser = NatTraverser(self.rawserver)
        self.internet_watcher = get_internet_watcher(self.rawserver)
        self.singleport_listener = SingleportListener(self.rawserver,
                                                      nattraverser,
                                                      self.log_root,
                                                      config['use_local_discovery'])
        self.choker = Choker(self.config, self.rawserver.add_task)
        self.up_ratelimiter = RateLimiter(self.rawserver.add_task)
        self.up_ratelimiter.set_parameters(config['max_upload_rate'],
                                           config['upload_unit_size'])
        self.down_ratelimiter = DownloadRateLimiter(
                                           config['download_rate_limiter_interval'],
                                           self.config['max_download_rate'])
        self.total_downmeasure = Measure(config['max_rate_period'])

        self._find_port(listen_fail_ok)

        self.filepool_doneflag = DeferredEvent()
        self.filepool = FilePool(self.filepool_doneflag,
                                 self.rawserver.add_task,
                                 self.rawserver.external_add_task,
                                 config['max_files_open'],
                                 config['num_disk_threads'])

        if self.resume_from_torrent_config:
            try:
                self._restore_state(init_torrents)
            except BTFailure:
                # don't be retarted.
                self.logger.exception("_restore_state failed")

        def no_dump_set_option(option, value):
            self.set_option(option, value, dump=False)

        self.bandwidth_manager = BandwidthManager(
            self.rawserver.external_add_task, config,
            no_dump_set_option, self.rawserver.get_remote_endpoints,
            get_rates=self.get_total_rates )

        self.rawserver.add_task(0, self.butle)


    def butle(self):
        policy = None
        try:
            for policy in self.policies:
                policy.butle()
        except:
            # You had something to hide, should have hidden it shouldn't you?
            self.logger.error("Butler error", exc_info=sys.exc_info())
            # Should we remove policies?
            #if policy:
            #    self.policies.remove(policy)
        self.rawserver.add_task(BUTLE_INTERVAL, self.butle)


    def _find_port(self, listen_fail_ok=True):
        """Run BitTorrent on the first available port found starting
           from minport in the range [minport, maxport]."""

        exc_info = None

        self.config['minport'] = max(1024, self.config['minport'])

        self.config['maxport'] = max(self.config['minport'],
                                     self.config['maxport'])

        e = (_("maxport less than minport - no ports to check") +
             (": %s %s" % (self.config['minport'], self.config['maxport'])))

        for port in xrange(self.config['minport'], self.config['maxport'] + 1):
            try:
                self.singleport_listener.open_port(port, self.config)
                if self.config['start_trackerless_client']:
                    self.dht = UTKhashmir(self.config['bind'],
                                   self.singleport_listener.get_port(),
                                   self.data_dir, self.rawserver,
                                   int(self.config['max_upload_rate'] * 0.01),
                                   rlcount=self.up_ratelimiter.increase_offset,
                                   config=self.config)
                break
            except socket.error, e:
                exc_info = sys.exc_info()
        else:
            if not listen_fail_ok:
                raise BTFailure, (_("Could not open a listening port: %s.") %
                                  str_exc(e) )
            self.global_error(logging.CRITICAL,
                              (_("Could not open a listening port: %s. ") % e) +
                              (_("Check your port range settings (%s:%s-%s).") %
                               (self.config['bind'], self.config['minport'],
                                self.config['maxport'])),
                              exc_info=exc_info)

    def shutdown(self):
        df = launch_coroutine(wrap_task(self.rawserver.add_task), self._shutdown)
        df.addErrback(lambda f : self.logger.error('shutdown failed!',
                                                   exc_info=f.exc_info()))
        return df

    def _shutdown(self):
        self.choker.shutdown()
        self.singleport_listener.close_sockets()
        for t in self.torrents.itervalues():
            try:
                df = t.shutdown()
                yield df
                df.getResult()
                totals = t.get_total_transfer()
                t.uptotal = t.uptotal_old + totals[0]
                t.downtotal = t.downtotal_old + totals[1]
            except:
                t.logger.debug("Torrent shutdown failed in state: %s", t.state)
                print "Torrent shutdown failed in state:", t.state
                traceback.print_exc()

        # the filepool must be shut down after the torrents,
        # or pending ops could never complete
        self.filepool_doneflag.set()

        if self.resume_from_torrent_config:
            self._dump_torrents()


    def set_option(self, option, value, infohash=None, dump=True):
        if infohash is not None:
            t = self.get_torrent(infohash)
            t.config[option] = value
            if dump:
                t._dump_torrent_config()
        else:
            self.config[option] = value
            if dump:
                self._dump_global_config()

        if option in ['max_upload_rate', 'upload_unit_size']:
            self.up_ratelimiter.set_parameters(self.config['max_upload_rate'],
                                            self.config['upload_unit_size'])
        elif option == 'max_download_rate':
            self.down_ratelimiter.set_parameters(
                self.config['max_download_rate'])
            #pass # polled from the config automatically by MultiDownload
        elif option == 'max_files_open':
            self.filepool.set_max_files_open(value)
        elif option == 'maxport':
            if not self.config['minport'] <= self.singleport_listener.port <= \
                   self.config['maxport']:
                self._find_port()

    def add_policy(self, policy):
        self.policies.append(policy)

    def add_auto_update_policy(self, policy):
        self.add_policy(policy)
        self.auto_update_policy_index = self.policies.index(policy)

    def global_error(self, severity, message, exc_info=None):
        self.logger.log(severity, message, exc_info=exc_info)

    def create_torrent_non_suck(self, torrent_filename, path_to_data,
                                hidden=False, feedback=None):
        data = open(torrent_filename, 'rb').read()
        metainfo = ConvertedMetainfo(bdecode(data))
        return self.create_torrent(metainfo, path_to_data, path_to_data,
                                   hidden=hidden, feedback=feedback)

    def create_torrent(self, metainfo, save_incomplete_as, save_as,
                       hidden=False, is_auto_update=False, feedback=None):
        if self.is_single_torrent and len(self.torrents) > 0:
            raise TooManyTorrents(_("MultiTorrent is set to download only "
                 "a single torrent, but tried to create more than one."))

        infohash = metainfo.infohash
        if self.torrent_known(infohash):
            if self.torrent_running(infohash):
                msg = _("This torrent (or one with the same contents) is "
                        "already running.")
                raise TorrentAlreadyRunning(msg)
            else:
                raise TorrentAlreadyInQueue(_("This torrent (or one with "
                                              "the same contents) is "
                                              "already waiting to run."))
        self._dump_metainfo(metainfo)

        #BUG.  Use _read_torrent_config for 5.0?  --Dave
        config = configfile.read_torrent_config(self.config,
                                                self.data_dir,
                                                infohash,
                                                lambda s : self.global_error(logging.ERROR, s))

        t = Torrent(metainfo, save_incomplete_as, save_as, self.config,
                    self.data_dir, self.rawserver, self.choker,
                    self.singleport_listener, self.up_ratelimiter,
                    self.down_ratelimiter, self.total_downmeasure,
                    self.filepool, self.dht, self,
                    self.log_root, hidden=hidden,
                    is_auto_update=is_auto_update)
        if feedback:
            t.add_feedback(feedback)

        retdf = Deferred()

        def torrent_started(*args):
            if config:
                t.update_config(config)

            t._dump_torrent_config()
            if self.resume_from_torrent_config:
                self._dump_torrents()
            t.metainfo.show_encoding_errors(self.logger.log)

            retdf.callback(t)

        df = self._init_torrent(t, use_policy=False)
        df.addCallback(torrent_started)

        return retdf

    def remove_torrent(self, ihash, del_files=False):
        # this feels redundant. the torrent will stop the download itself,
        # can't we accomplish the rest through a callback or something?
        if self.torrent_running(ihash):
            self.stop_torrent(ihash)

        t = self.torrents[ihash]

        # super carefully determine whether these are really incomplete files
        fs_save_incomplete_in, junk = encode_for_filesystem(
            self.config['save_incomplete_in']
            )
        inco = ((not t.completed) and
                (t.working_path != t.destination_path) and
                t.working_path.startswith(fs_save_incomplete_in))
        del_files = del_files and inco

        df = t.shutdown()

        df.addCallback(lambda *args: t.remove_state_files(del_files=del_files))

        if ihash in self.running:
            del self.running[ihash]

        # give the torrent a blank feedback, so post-mortem errors don't
        # confuse multitorrent
        t.feedback = Feedback()
        del self.torrents[ihash]
        if self.resume_from_torrent_config:
            self._dump_torrents()

        return df


    def reinitialize_torrent(self, infohash):
        t = self.get_torrent(infohash)
        if self.torrent_running(infohash):
            assert t.is_running(), "torrent not running, but in running set"
            raise TorrentAlreadyRunning(infohash.encode("hex"))
        assert t.state == "failed", "state not failed"

        df = self._init_torrent(t, use_policy=False)
        return df


    def start_torrent(self, infohash):
        if self.is_single_torrent and len(self.torrents) > 1:
            raise TooManyTorrents(_("MultiTorrent is set to download only "
                 "a single torrent, but tried to create more than one."))
        t = self.get_torrent(infohash)
        if self.torrent_running(infohash):
            assert t.is_running()
            raise TorrentAlreadyRunning(infohash.encode("hex"))
        if not t.is_initialized():
            raise TorrentNotInitialized(infohash.encode("hex"))

        t.logger.debug("starting torrent")

        self.running[infohash] = t
        t.start_download()
        t._dump_torrent_config()
        return t.state


    def stop_torrent(self, infohash, pause=False):
        if not self.torrent_running(infohash):
            raise TorrentNotRunning()
        t = self.get_torrent(infohash)
        assert t.is_running()

        t.logger.debug("stopping torrent")

        t.stop_download(pause=pause)
        del self.running[infohash]
        t._dump_torrent_config()
        return t.state

    def torrent_status(self, infohash, spew=False, fileinfo=False):
        torrent = self.get_torrent(infohash)
        status = torrent.get_status(spew, fileinfo)
        return torrent, status

    def get_torrent(self, infohash):
        try:
            t = self.torrents[infohash]
        except KeyError:
            raise UnknownInfohash(infohash.encode("hex"))
        return t

    def get_torrents(self):
        return self.torrents.values()

    def get_running(self):
        return self.running.keys()

    def get_visible_torrents(self):
        return [t for t in self.torrents.values() if not t.hidden]

    def get_visible_running(self):
        return [i for i in self.running.keys() if not self.torrents[i].hidden]

    def torrent_running(self, ihash):
        return ihash in self.running

    def torrent_known(self, ihash):
        return ihash in self.torrents

    def pause(self):
        for i in self.running.keys():
            self.stop_torrent(i, pause=True)

    def unpause(self):
        for i in [t.metainfo.infohash for t in self.torrents.values() if t.is_initialized()]:
            self.start_torrent(i)

    def set_file_priority(self, infohash, filename, priority):
        torrent = self.get_torrent(infohash)
        if torrent is None or not self.torrent_running(infohash):
            return
        torrent.set_file_priority(filename, priority)

    def set_torrent_priority(self, infohash, priority):
        torrent = self.get_torrent(infohash)
        if torrent is None:
            return
        torrent.priority = priority
        torrent._dump_torrent_config()

    def set_torrent_policy(self, infohash, policy):
        torrent = self.get_torrent(infohash)
        if torrent is None:
            return
        torrent.policy = policy
        torrent._dump_torrent_config()

    def get_all_rates(self):
        rates = {}
        for infohash, torrent in self.torrents.iteritems():
            rates[infohash] = (torrent.get_uprate() or 0,
                               torrent.get_downrate() or 0)
        return rates

    def get_variance(self):
        return self.bandwidth_manager.current_std, self.bandwidth_manager.max_std

    def get_total_rates(self):
        u = 0.0
        d = 0.0
        for torrent in self.torrents.itervalues():
            u += torrent.get_uprate() or 0
            d += torrent.get_downrate() or 0
        return u, d

    def get_total_totals(self):
        u = 0.0
        d = 0.0
        for torrent in self.torrents.itervalues():
            u += torrent.get_uptotal() or 0
            d += torrent.get_downtotal() or 0
        return u, d

    def auto_update_status(self):
        if self.auto_update_policy_index is not None:
            aub = self.policies[self.auto_update_policy_index]
            return aub.get_auto_update_status()
        return None, None, None

    def remove_auto_updates_except(self, infohash):
        for t in self.torrents.values():
            if t.is_auto_update and t.metainfo.infohash != infohash:
                self.logger.warning(_("Cleaning up old autoupdate %s") % t.metainfo.name)
                self.remove_torrent(t.metainfo.infohash, del_files=True)


    ## singletorrent callbacks
    def started(self, torrent):
        torrent.logger.debug("started torrent")
        assert torrent.infohash in self.torrents
        torrent._dump_torrent_config()
        for policy in self.policies:
            policy.started(torrent)

    def failed(self, torrent):
        torrent.logger.debug("torrent failed")
        if torrent.infohash not in self.running:
            return
        del self.running[torrent.infohash]
        t = self.get_torrent(torrent.infohash)
        for policy in self.policies:
            policy.failed(t)

    def finishing(self, torrent):
        torrent.logger.debug("torrent finishing")
        t = self.get_torrent(torrent.infohash)

    def finished(self, torrent):
        # set done-flag
        self.isDone = True
        #
        torrent.logger.debug("torrent finished")
        t = self.get_torrent(torrent.infohash)
        t._dump_torrent_config()
        for policy in self.policies:
            policy.finished(t)

    def exception(self, torrent, text):
        torrent.logger.debug("torrent threw exception: " + text)
        if torrent.infohash not in self.torrents:
            return
        for policy in self.policies:
            policy.exception(torrent, text)

    def error(self, torrent, level, text):
        torrent.logger.log(level, text)
        if torrent.infohash not in self.torrents:
            return
        for policy in self.policies:
            policy.error(torrent, level, text)


    ### persistence

    ## These should be the .torrent file!
    #################
    def _dump_metainfo(self, metainfo):
        infohash = metainfo.infohash
        path = os.path.join(self.data_dir, 'metainfo',
                            infohash.encode('hex'))
        f = file(path+'.new', 'wb')
        f.write(metainfo.to_data())
        f.close()
        shutil.move(path+'.new', path)

    def _read_metainfo(self, infohash):
        path = os.path.join(self.data_dir, 'metainfo',
                            infohash.encode('hex'))
        f = file(path, 'rb')
        data = f.read()
        f.close()
        return ConvertedMetainfo(bdecode(data))
    #################

    def _read_torrent_config(self, infohash):
        path = os.path.join(self.data_dir, 'torrents', infohash.encode('hex'))
        if not os.path.exists(path):
            raise BTFailure,_("Coult not open the torrent config: " + infohash.encode('hex'))
        f = file(path, 'rb')
        data = f.read()
        f.close()
        try:
            torrent_config = cPickle.loads(data)
        except:
            # backward compatibility with <= 4.9.3
            torrent_config = bdecode(data)
            for k, v in torrent_config.iteritems():
                try:
                    torrent_config[k] = v.decode('utf8')
                    if k in ('destination_path', 'working_path'):
                        torrent_config[k] = encode_for_filesystem(torrent_config[k])[0]
                except:
                    pass
        if not torrent_config.get('destination_path'):
            raise BTFailure( _("Invalid torrent config file"))
        if not torrent_config.get('working_path'):
            raise BTFailure( _("Invalid torrent config file"))

        if get_filesystem_encoding() == None:
            # These paths should both be unicode.  If they aren't, they are the
            # broken product of some old version, and probably are in the
            # encoding we used to use in config files.  Attempt to recover.
            dp = torrent_config['destination_path']
            if isinstance(dp, str):
                try:
                    dp = dp.decode(old_broken_config_subencoding)
                    torrent_config['destination_path'] = dp
                except:
                    raise BTFailure( _("Invalid torrent config file"))

            wp = torrent_config['working_path']
            if isinstance(wp, str):
                try:
                    wp = wp.decode(old_broken_config_subencoding)
                    torrent_config['working_path'] = wp
                except:
                    raise BTFailure( _("Invalid torrent config file"))

        return torrent_config

    def _dump_global_config(self):
        # BUG: we can save to different sections later
        section = 'bittorrent'
        configfile.save_global_config(self.config, section,
                                      lambda *e : self.logger.error(*e))

    def _dump_torrents(self):
        assert self.resume_from_torrent_config

        self.last_save_time = bttime()
        r = []
        def write_entry(infohash, t):
            r.append(' '.join((infohash.encode('hex'),
                               str(t.uptotal), str(t.downtotal))))
        r.append('BitTorrent UI state file, version 5')
        r.append('Queued torrents')
        for t in self.torrents.values():
            write_entry(t.metainfo.infohash, self.torrents[t.metainfo.infohash])
        r.append('End')
        f = None
        try:
            path = os.path.join(self.data_dir, 'ui_state')
            f = file(path+'.new', 'wb')
            f.write('\n'.join(r) + '\n')
            f.close()
            shutil.move(path+'.new', path)
        except Exception, e:
            self.logger.error(_("Could not save UI state: ") + str_exc(e))
            if f is not None:
                f.close()

    def _init_torrent(self, t, initialize=True, use_policy=True):
        self.torrents[t.infohash] = t
        if not initialize:
            t.logger.debug("created torrent")
            return
        t.logger.debug("created torrent, initializing")
        df = t.initialize()
        if use_policy and t.policy == "start":
            df.addCallback(lambda r, t: self.start_torrent(t.infohash), t)
        return df

    def initialize_torrents(self):
        df = launch_coroutine(wrap_task(self.rawserver.add_task), self._initialize_torrents)
        df.addErrback(lambda f : self.logger.error('initialize_torrents failed!',
                                                   exc_info=f.exc_info()))
        return df

    def _initialize_torrents(self):
        self.logger.debug("initializing torrents")
        for t in copy(self.torrents).itervalues():
            if t in self.torrents.values() and t.state == "created":
                df = self._init_torrent(t)
                # HACK
                #yield df
                #df.getResult()

    # this function is so nasty!
    def _restore_state(self, init_torrents):
        def decode_line(line):
            hashtext = line[:40]
            try:
                infohash = InfoHashType(hashtext.decode('hex'))
            except:
                raise BTFailure(_("Invalid state file contents"))
            if len(infohash) != 20:
                raise BTFailure(_("Invalid state file contents"))
            if infohash in self.torrents:
                raise BTFailure(_("Invalid state file (duplicate entry)"))

            try:
                metainfo = self._read_metainfo(infohash)
            except OSError, e:
                try:
                    f.close()
                except:
                    pass
                self.logger.error((_("Error reading metainfo file \"%s\".") %
                                  hashtext) + " (" + str_exc(e)+ "), " +
                                  _("cannot restore state completely"))
                return None
            except Exception, e:
                self.logger.error((_("Corrupt data in metainfo \"%s\", cannot restore torrent.") % hashtext) +
                                  '('+str_exc(e)+')')
                return None

            b = encode_for_filesystem(u'')[0]
            t = Torrent(metainfo, b, b, self.config, self.data_dir,
                        self.rawserver, self.choker,
                        self.singleport_listener, self.up_ratelimiter,
                        self.down_ratelimiter,
                        self.total_downmeasure, self.filepool, self.dht, self,
                        self.log_root)
            t.metainfo.reported_errors = True # suppress redisplay on restart
            if infohash != t.metainfo.infohash:
                self.logger.error((_("Corrupt data in \"%s\", cannot restore torrent.") % hashtext) +
                                  _("(infohash mismatch)"))
                return None
            if len(line) == 41:
                t.working_path = None
                t.destination_path = None
                return infohash, t
            try:
                if version < 2:
                    t.working_path = line[41:-1].decode('string_escape')
                    t.working_path = t.working_path.decode('utf-8')
                    t.working_path = encode_for_filesystem(t.working_path)[0]
                    t.destination_path = t.working_path
                elif version == 3:
                    up, down, working_path = line[41:-1].split(' ', 2)
                    t.uptotal = t.uptotal_old = int(up)
                    t.downtotal = t.downtotal_old = int(down)
                    t.working_path = working_path.decode('string_escape')
                    t.working_path = t.working_path.decode('utf-8')
                    t.working_path = encode_for_filesystem(t.working_path)[0]
                    t.destination_path = t.working_path
                elif version >= 4:
                    up, down = line[41:-1].split(' ', 1)
                    t.uptotal = t.uptotal_old = int(up)
                    t.downtotal = t.downtotal_old = int(down)
            except ValueError:  # unpack, int(), decode()
                raise BTFailure(_("Invalid state file (bad entry)"))

            torrent_config = self.config
            try:
                if version < 5:
                    torrent_config = configfile.read_torrent_config(
                                                           self.config,
                                                           self.data_dir,
                                                           infohash,
                                                           lambda s : self.global_error(logging.ERROR, s))
                else:
                    torrent_config = self._read_torrent_config(infohash)
                t.update_config(torrent_config)
            except BTFailure, e:
                self.logger.error("Read torrent config failed",
                                  exc_info=sys.exc_info())
                # if read_torrent_config fails then ignore the torrent...
                return None

            return infohash, t
        # BEGIN _restore_state
        assert self.resume_from_torrent_config
        filename = os.path.join(self.data_dir, 'ui_state')
        if not os.path.exists(filename):
            return
        f = None
        try:
            f = file(filename, 'rb')
            lines = f.readlines()
            f.close()
        except Exception, e:
            if f is not None:
                f.close()
            raise BTFailure(str_exc(e))
        i = iter(lines)
        try:
            txt = 'BitTorrent UI state file, version '
            version = i.next()
            if not version.startswith(txt):
                raise BTFailure(_("Bad UI state file"))
            try:
                version = int(version[len(txt):-1])
            except:
                raise BTFailure(_("Bad UI state file version"))
            if version > 5:
                raise BTFailure(_("Unsupported UI state file version (from "
                                  "newer client version?)"))
            if version < 3:
                if i.next() != 'Running/queued torrents\n':
                    raise BTFailure(_("Invalid state file contents"))
            else:
                if i.next() != 'Running torrents\n' and version != 5:
                    raise BTFailure(_("Invalid state file contents"))
                while version < 5:
                    line = i.next()
                    if line == 'Queued torrents\n':
                        break
                    t = decode_line(line)
                    if t is None:
                        continue
                    infohash, t = t
                    df = self._init_torrent(t, initialize=init_torrents)
            while True:
                line = i.next()
                if (version < 5 and line == 'Known torrents\n') or (version == 5 and line == 'End\n'):
                    break
                t = decode_line(line)
                if t is None:
                    continue
                infohash, t = t
                if t.destination_path is None:
                    raise BTFailure(_("Invalid state file contents"))
                df = self._init_torrent(t, initialize=init_torrents)

            while version < 5:
                line = i.next()
                if line == 'End\n':
                    break
                t = decode_line(line)
                if t is None:
                    continue
                infohash, t = t
                df = self._init_torrent(t, initialize=init_torrents)
        except StopIteration:
            raise BTFailure(_("Invalid state file contents"))
