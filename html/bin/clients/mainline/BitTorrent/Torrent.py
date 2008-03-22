# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bram Cohen and Uoti Urpala

from __future__ import division
from __future__ import generators

import os
import gc
import sys
import errno
import shutil
import random
import socket
import cPickle
import logging
import itertools
from BTL.translation import _
from BitTorrent.NamedMutex import NamedMutex
import BTL.stackthreading as threading
from BitTorrent.platform import is_path_too_long, no_really_makedirs
from BTL.platform import bttime, get_filesystem_encoding

from BitTorrent.ConnectionManager import ConnectionManager
from BitTorrent import PeerID
from BTL.exceptions import str_exc
from BTL.defer import ThreadedDeferred, Failure, wrap_task
from BTL.yielddefer import launch_coroutine
from BitTorrent.TorrentStats import TorrentStats
from BitTorrent.RateMeasure import RateMeasure
from BitTorrent.PiecePicker import PiecePicker
from BitTorrent.Rerequester import Rerequester, DHTRerequester
from BitTorrent.CurrentRateMeasure import Measure
from BitTorrent.Storage import Storage, UnregisteredFileException
from BitTorrent.HTTPConnector import URLage
from BitTorrent.StorageWrapper import StorageWrapper
from BitTorrent.RequestManager import RequestManager
from BitTorrent.Upload import Upload
from BitTorrent.MultiDownload import MultiDownload
from BitTorrent import BTFailure, UserFailure
from BitTorrent.prefs import Preferences
from khashmir import const

class Feedback(object):
    """Inidivual torrents (Torrent) perform callbacks regarding
       changes of state to the rest of the program via a Feedback
       object."""

    def finished(self, torrent):
        pass

    def failed(self, torrent):
        pass

    def error(self, torrent, level, text):
        pass

    def exception(self, torrent, text):
        self.error(torrent, logging.CRITICAL, text)

    def started(self, torrent):
        pass


class FeedbackMultiplier(object):
    def __init__(self, *a):
        self.chain = list(a)
    def __getattr__(self, attr):
        def multiply_calls(*a, **kw):
            exc_info = None
            for x in self.chain:
                try:
                    getattr(x, attr)(*a, **kw)
                except:
                    exc_info = sys.exc_info()
            if exc_info:
                raise exc_info[0], exc_info[1], exc_info[2]
        return multiply_calls


class Torrent(object):
    """Represents a single file transfer or transfer for a batch of files
       in the case of a batch torrent.  During the course of a single
       transfer, a Torrent may have many different connections to peers."""

    STATES = ["created", "initializing", "initialized", "running",
              "finishing", "failed"]
    POLICIES = ["stop", "start", "auto"]
    PRIORITIES = ["low", "normal", "high"]

    def __init__(self, metainfo, working_path, destination_path, config,
                 data_dir, rawserver, choker,
                 singleport_listener, ratelimiter, down_ratelimiter,
                 total_downmeasure,
                 filepool, dht, feedback, log_root,
                 hidden=False, is_auto_update=False):
        # The passed working path and destination_path should be filesystem
        # encoded or should be unicode if the filesystem supports unicode.
        fs_encoding = get_filesystem_encoding()
        assert (
            (fs_encoding == None and isinstance(working_path, unicode)) or
            (fs_encoding != None and isinstance(working_path, str))
            ), "working path encoding problem"
        assert (
            (fs_encoding == None and isinstance(destination_path, unicode)) or
            (fs_encoding != None and isinstance(destination_path, str))
            ), "destination path encoding problem"

        self.state = "created"
        self.data_dir = data_dir
        self.feedback = FeedbackMultiplier(feedback)
        self.finished_this_session = False
        self._rawserver = rawserver
        self._singleport_listener = singleport_listener
        self._ratelimiter = ratelimiter
        self._down_ratelimiter = down_ratelimiter
        self._filepool = filepool
        self._dht = dht
        self._choker = choker
        self._total_downmeasure = total_downmeasure
        self._init()
        self._announced = False
        self._listening = False
        self.reserved_ports = []
        self.reported_port = None
        self._myfiles = None
        self._last_myfiles = None
        self.total_bytes = None
        self._doneflag = threading.Event()
        self.finflag = threading.Event()
        self._contfunc = None
        self._activity = (_("Initial startup"), 0)
        self._pending_file_priorities = []
        self._mutex = None
        self.time_created = bttime()
        self.time_started = None

        self.metainfo = metainfo
        self.infohash = metainfo.infohash

        self.log_root = log_root
        self.logger = logging.getLogger(log_root + '.' + repr(self.infohash))
        self.logger.setLevel(logging.DEBUG)

        self.total_bytes = metainfo.total_bytes
        if not metainfo.reported_errors:
            metainfo.show_encoding_errors(self._error)

        self.config = Preferences(config)#, persist_callback=self._dump_torrent_config)
        self.working_path = working_path #sets in config. See _set_working_path

        self.destination_path = destination_path # sets in config.
        self.priority = "normal"
        self.policy = "auto"

        self.hidden = hidden #sets in config
        self.is_auto_update = is_auto_update #sets in config

        self._completed = False
        self.config['finishtime'] = 0
        self.uptotal = 0
        self.uptotal_old = 0
        self.downtotal = 0
        self.downtotal_old = 0
        self.context_valid = True

    def _init(self):
        self._picker = None
        self._storage = None
        self._storagewrapper = None
        self._ratemeasure = None
        self._upmeasure = None
        self._downmeasure = None
        self._connection_manager = None
        self._rerequest = None
        self._dht_rerequest = None
        self._statuscollector = None
        self._rm = None
        self.multidownload = None

    def update_config(self, config):
        self.config.update(config)

        d = self.config.get('file_priorities', {})
        for k, v in d.iteritems():
            self.set_file_priority(k, v)

        if self.policy not in self.POLICIES:
            self.policy = "auto"

        if self.priority not in self.PRIORITIES:
            self.priority = "normal"

    def _set_state(self, value):
        assert value in self.STATES, ("value %s not in STATES %s" %
                                      (value, self.STATES))
        self._state = value
    def _get_state(self):
        return self._state
    state = property(_get_state, _set_state)

    def _set_policy(self, value):
        assert value in self.POLICIES, ("value %s not in POLICIES %s" %
                                        (value, self.POLICIES))
        self.config['policy'] = value
    def _get_policy(self):
        return self.config['policy']
    policy = property(_get_policy, _set_policy)

    def _set_priority(self, value):
        assert value in self.PRIORITIES, ("value %s not in PRIORITIES %s" %
                                          (value, self.PRIORITIES))
        self.config['priority'] = value
    def _get_priority(self):
        return self.config['priority']
    priority = property(_get_priority, _set_priority)

    def _set_hidden(self, value):
        self.config['hidden'] = value
    def _get_hidden(self):
        return self.config['hidden']
    hidden = property(_get_hidden, _set_hidden)

    def _set_is_auto_update(self, value):
        self.config['is_auto_update'] = value
    def _get_is_auto_update(self):
        return self.config['is_auto_update']
    is_auto_update = property(_get_is_auto_update, _set_is_auto_update)

    def _set_completed(self, val):
        self._completed = val
        if val:
            self.config['finishtime'] = bttime()
    def _get_completed(self):
        return self._completed
    completed = property(_get_completed, _set_completed)

    def _set_sent_completed(self, value):
        self.config['sent_completed'] = value
    def _get_sent_completed(self):
        return self.config['sent_completed']
    sent_completed = property(_get_sent_completed, _set_sent_completed)

    def _get_finishtime(self):
        return self.config['finishtime']
    finishtime = property(_get_finishtime)

    def _set_destination_path(self, value):
        # The following assertion will not always work.  Consider
        # Torrent.py: self.working_path = self.destination_path
        # This assignment retrieves a unicode path from
        # config['destination_path'].
        #assert isinstance(value,str) # assume filesystem encoding.
        #
        # The following if statement is not necessary because config here
        # is not really a config file, but rather state that is pickled when
        # the Torrent shuts down.
        #if isinstance(value, str):
        #    value = decode_from_filesystem(value)
        self.config['destination_path'] = value
    def _get_destination_path(self):
        return self.config['destination_path']
    destination_path = property(_get_destination_path, _set_destination_path)

    def _set_working_path(self, value):
        # See comments for _set_destination_path.
        self.config['working_path'] = value
    def _get_working_path(self):
        return self.config['working_path']
    working_path = property(_get_working_path, _set_working_path)

    def __cmp__(self, other):
        if not isinstance(other, Torrent):
            raise TypeError("Torrent.__cmp__(x,y) requires y to be a 'Torrent',"
                            " not a '%s'" % type(other))
        return cmp(self.metainfo.infohash, other.metainfo.infohash)

    def is_initialized(self):
        return self.state not in ["created", "initializing", "failed"]

    def is_running(self):
        return self.state == "running"

    def is_context_valid(self):
        return self.context_valid

    def _context_wrap(self, _f, *a, **kw):
        # this filters out calls
        # to an invalid torrent
        # sloppy technique
        if not self.context_valid:
            return
        try:
            _f(*a, **kw)
        except KeyboardInterrupt:
            raise
        except:
            self.got_exception(Failure())

    # these wrappers add _context_wrap to the chain, so that calls on a dying
    # object are filtered, and errors on a valid call are logged.
    def add_task(self, delay, func, *a, **kw):
        return self._rawserver.add_task(delay, self._context_wrap,
                                        func, *a, **kw)
    def external_add_task(self, delay, func, *a, **kw):
        return self._rawserver.external_add_task(delay, self._context_wrap,
                                                 func, *a, **kw)

    def _register_files(self):
        if self.metainfo.is_batch:
            myfiles = [os.path.join(self.destination_path, f) for f in
                       self.metainfo.files_fs]
        else:
            myfiles = [self.destination_path, ]

        for filename in myfiles:
            if is_path_too_long(filename):
                raise BTFailure("Filename path exceeds platform limit: %s" % filename)

        # if the destination path contains any of the files in the torrent
        # then use the destination path instead of the working path.
        if len([x for x in myfiles if os.path.exists(x)]) > 0:
            self.working_path = self.destination_path
        else:
            if self.metainfo.is_batch:
                myfiles = [os.path.join(self.working_path, f) for f in
                           self.metainfo.files_fs]
            else:
                myfiles = [self.working_path, ]

        assert self._myfiles == None, '_myfiles should be None!'
        self._filepool.add_files(myfiles, self)
        self._myfiles = myfiles

    def _build_url_mapping(self):
        # TODO: support non [-1] == '/' urls
        url_suffixes = []

        if self.metainfo.is_batch:
            for filename in self.metainfo.orig_files:
                path = '%s/%s' % (self.metainfo.name, filename)
                # am I right that orig_files could have windows paths?
                path = path.replace('\\', '/')
                url_suffixes.append(path)
        else:
            url_suffixes = [self.metainfo.name, ]
        self._url_suffixes = url_suffixes

        total = 0
        piece_size = self.metainfo.piece_length
        self._urls = zip(self._url_suffixes, self.metainfo.sizes)

    def _unregister_files(self):
        if self._myfiles is not None:
            self._filepool.remove_files(self._myfiles)
            self._last_myfiles = self._myfiles
            self._myfiles = None

    def initialize(self):
        self.context_valid = True
        assert self.state in ["created", "failed", "finishing"], "state not in set"
        self.state = "initializing"
        df = launch_coroutine(wrap_task(self.add_task), self._initialize)
        df.addErrback(self.got_exception)
        return df

    # this function is so nasty!
    def _initialize(self):

        self._doneflag = threading.Event()

        # only one torrent object for of a particular infohash at a time.
        # Note: This must be done after doneflag is created if shutdown()
        # is to be called from got_exception().
        if self.config["one_download_per_torrent"]:
            self._mutex = NamedMutex(self.infohash.encode("hex"))
            if not self._mutex.acquire(False):
               try:
                   raise UserFailure(_("Torrent already being downloaded or "
                                     "seeded." ))
               except UserFailure, e:
                   # perform exception handling including shutting down
                   # the torrent.
                   self.got_exception(Failure(),
                                      cannot_shutdown=True)
                   return
        self.reported_port = self.config['forwarded_port']
        if not self.reported_port:
            self.reported_port = \
                self._singleport_listener.get_port(self.change_port)
            if self.reported_port:
                self.reserved_ports.append(self.reported_port)

        # backward compatibility with older 5.0 development versions
        if self.destination_path == "":
            try:
                self.destination_path = self.config['save_as']
            except:
                pass
        if self.working_path == "":
            self.working_path = self.destination_path

        self._myid = self._make_id()
        random.seed(self._myid)
        self._build_url_mapping()
        self._urlage = URLage(self._urls)

        self._register_files()
        self.logger.debug("_initialize: self.working_path=%s", self.working_path)

        self._storage = Storage(self.config, self._filepool, self.working_path,
                                zip(self._myfiles, self.metainfo.sizes),
                                self.add_task, self.external_add_task,
                                self._doneflag)
        df = self._storage.startup_df
        yield df
        if df.getResult() != True:
            # initialization was aborted
            self.logger.debug("_initialize: initialization aborted")
            return
        self.logger.debug("_initialize: returned from Storage startup.")
        resumefile = None
        if self.data_dir:
            filename = os.path.join(self.data_dir, 'resume',
                                    self.infohash.encode('hex'))
            if os.path.exists(filename):
                try:
                    resumefile = file(filename, 'rb')
                except Exception, e:
                    self._error(logging.WARNING,
                        _("Could not load fastresume data: %s") % str_exc(e)
                        + ' ' + _("Will perform full hash check."))
                    if resumefile is not None:
                        resumefile.close()
                    resumefile = None
        def data_flunked(amount, index):
            self._ratemeasure.data_rejected(amount)
            self._error(logging.INFO,
                        _("piece %d failed hash check, re-downloading it")
                        % index)
        def errorfunc(level, text):
            def e():
                self._error(level, text)
            self.external_add_task(0, e)

        def statusfunc(activity = None, fractionDone = 0):
            if activity is None:
                activity = self._activity[0]
            self._activity = (activity, fractionDone)

        numpieces = len(self.metainfo.hashes)

        self._rm = RequestManager(self.config['download_chunk_size'],
                                  self.metainfo.piece_length, numpieces,
                                  self._storage.get_total_length())

        self._storagewrapper = StorageWrapper(self._storage, self._rm,
                                              self.config,
                                              self.metainfo.hashes,
                                              self.metainfo.piece_length,
                                              statusfunc, self._doneflag,
                                              data_flunked, self.infohash,
                                              self.metainfo.is_batch,
                                              errorfunc, self.working_path,
                                              self.destination_path,
                                              resumefile,
                                              self.add_task,
                                              self.external_add_task)

        self._rm.set_storage(self._storagewrapper)

        df = self._storagewrapper.done_checking_df
        yield df
        if df.getResult() != True:
            # initialization was aborted
            return

        if resumefile is not None:
            resumefile.close()

        self._upmeasure = Measure(self.config['max_rate_period'])
        self._downmeasure = Measure(self.config['max_rate_period'])
        self._ratemeasure = RateMeasure(self._storagewrapper.amount_left_with_partials)
        self._picker = PiecePicker(self.config, numpieces,
            self._storagewrapper.have_set.iterneg(0, numpieces))

        self._periodic_save_fastresume()

        while self._pending_file_priorities:
            self.set_file_priority(*self._pending_file_priorities.pop())

        def kickpeer(connection):
            def kick():
                connection.close()
            self.add_task(0, kick)
        def banpeer(ip):
            self._connection_manager.ban(ip)
        md = MultiDownload(self.config, self._storagewrapper, self._rm,
                           self._urlage, self._picker, numpieces,
                           self.finished, self.got_exception, kickpeer, banpeer,
                           self._downmeasure.get_rate)
        md.add_useful_received_listener(self._total_downmeasure.update_rate)
        md.add_useful_received_listener(self._downmeasure.update_rate)
        md.add_useful_received_listener(self._ratemeasure.data_came_in)
        md.add_raw_received_listener(self._down_ratelimiter.update_rate)
        self.multidownload = md

        # HERE. Yipee! Uploads are created by callback while Download
        # objects are created by MultiDownload.   --Dave
        def make_upload(connector):
            up = Upload(self.multidownload, connector, self._ratelimiter,
                        self._choker,
                        self._storagewrapper, self.config['max_chunk_length'],
                        self.config['max_rate_period'],
                        self.config['num_fast'], self.infohash)
            connector.add_sent_listener(self._upmeasure.update_rate)
            return up

        if self._dht:
            addContact = self._dht.addContact
        else:
            addContact = None

        df = self.metainfo.get_tracker_ips(wrap_task(self.external_add_task))
        yield df
        tracker_ips = df.getResult()
        self._connection_manager = \
            ConnectionManager(make_upload, self.multidownload, self._choker,
                     numpieces, self._ratelimiter,
                     self._rawserver, self.config, self.metainfo.is_private,
                     self._myid, self.add_task, self.infohash, self, addContact,
                     0, tracker_ips, self.log_root)
        self.multidownload.attach_connection_manager(self._connection_manager)

        self._statuscollector = TorrentStats(self.logger, self._choker,
            self.get_uprate, self.get_downrate, self._upmeasure.get_total,
            self._downmeasure.get_total, self._ratemeasure.get_time_left,
            self.get_percent_complete, self.multidownload.aggregate_piece_states,
            self.finflag, self._connection_manager, self.multidownload,
            self.get_file_priorities, self._myfiles,
            self._connection_manager.ever_got_incoming, None)

        self.state = "initialized"

    def _rerequest_op(self):
        # weee hee hee
        class Caller(object):
            def __getattr__(s, attr):
                def rerequest_function(*a, **kw):
                    if self._rerequest:
                        f = getattr(self._rerequest, attr)
                        f(*a, **kw)
                    if self._dht_rerequest:
                        f = getattr(self._dht_rerequest, attr)
                        f(*a, **kw)
                return rerequest_function
        return Caller()

    def start_download(self):
        assert self.state == "initialized", "state not initialized"

        self.time_started = bttime()

        self._down_ratelimiter.add_throttle_listener(self._connection_manager)
        self._connection_manager.reopen(self.reported_port)

        self._singleport_listener.add_torrent(self.infohash,
                                              self._connection_manager)
        self._listening = True

        # the DHT is broken
        if self.metainfo.is_trackerless or not self.metainfo.is_private:
        #if self.metainfo.is_trackerless:
            if not self._dht and self.metainfo.is_trackerless:
                self._error(self, logging.CRITICAL,
                   _("Attempt to download a trackerless torrent "
                     "with trackerless client turned off."))
                return
            else:
                if self._dht:
                    nodes = self._dht.table.findNodes(self.metainfo.infohash,
                                                      invalid=False)
                    if len(nodes) < const.K:
                        for host, port in self.metainfo.nodes:
                            df = self._rawserver.gethostbyname(host)
                            df.addCallback(self._dht.addContact, port)
                            df.addLogback(self.logger.warning, "Resolve failed")
                        self._dht_rerequest = DHTRerequester(self.config,
                        self.add_task,
                        self._connection_manager.how_many_connections,
                        self._connection_manager.start_connection,
                        self.external_add_task,
                        self._rawserver,
                        self._storagewrapper.get_amount_left,
                        self._upmeasure.get_total,
                        self._downmeasure.get_total, self.reported_port,
                        self._myid,
                        self.infohash, self._error, self.finflag,
                        self._upmeasure.get_rate,
                        self._downmeasure.get_rate,
                        self._connection_manager.ever_got_incoming,
                        self._no_announce_shutdown, self._announce_done,
                        self._dht)

        if not self.metainfo.is_trackerless:
            self._rerequest = Rerequester(self.metainfo.announce,
                self.metainfo.announce_list, self.config,
                self.add_task, self.external_add_task, self._rawserver,
                self._connection_manager.how_many_connections,
                self._connection_manager.start_connection,
                self._storagewrapper.get_amount_left,
                self._upmeasure.get_total, self._downmeasure.get_total,
                self.reported_port, self._myid,
                self.infohash, self._error, self.finflag,
                self._upmeasure.get_rate,
                self._downmeasure.get_rate,
                self._connection_manager.ever_got_incoming,
                self._no_announce_shutdown, self._announce_done,
                bool(self._dht_rerequest))

        self._statuscollector.rerequester = self._rerequest or self._dht_rerequest
        self.multidownload.rerequester = self._rerequest or self._dht_rerequest

        self._announced = True
        if self._dht and len(self._dht.table.findNodes(self.infohash)) == 0:
            self.add_task(5, self._dht.findCloseNodes)
        self._rerequest_op().begin()

        for url_prefix in self.metainfo.url_list:
            self._connection_manager.start_http_connection(url_prefix)

        self.state = "running"
        if not self.finflag.isSet():
            self._activity = (_("downloading"), 0)

        self.feedback.started(self)

        if self._storagewrapper.amount_left == 0 and not self.completed:
            # By default, self.finished() resets the policy to "auto",
            # but if we discover on startup that we are already finished,
            # we don't want to reset it.
            # Also, if we discover on startup that we are already finished,
            # don't set finished_this_session.
            self.finished(policy=self.policy, finished_this_session=False)

    def stop_download(self, pause=False):
        assert self.state == "running", "state not running"

        self.state = "initialized"

        if not self.finflag.isSet():
            self._activity = (_("stopped"), 0)

        if self._announced:
            self._rerequest_op().announce_stop()
            self._announced = False
        self._statuscollector.rerequester = None
        self.multidownload.rerequester = None

        if self._listening:
            self._singleport_listener.remove_torrent(self.infohash)
            self._listening = False

        for port in self.reserved_ports:
            self._singleport_listener.release_port(port, self.change_port)
        del self.reserved_ports[:]

        if self._connection_manager is not None:
            if pause:
                self._down_ratelimiter.remove_throttle_listener(
                    self._connection_manager )
                self._connection_manager.throttle_connections()
            else:
                self._connection_manager.close_connections()

        if self.config['check_hashes']:
            self._save_fastresume()

    def shutdown(self):
        # use _rawserver.add_task directly here, because we want the callbacks
        # to happen even though _shutdown is about to invalidate this torrent's
        # context
        df = launch_coroutine(wrap_task(self._rawserver.add_task), self._shutdown)
        df.addErrback(self.got_exception, cannot_shutdown=True)
        return df

    def _shutdown(self):
        self._doneflag.set()

        if self.state == "running":
            self.stop_download()
        # above is the last thing to set.

        if self._storagewrapper is not None:
            df = self._storagewrapper.done_checking_df
            yield df
            df.getResult()

        if self._storage is not None:
            df = self._storage.close()
            if df is not None:
                yield df
                df.getResult()

        self._unregister_files()
        if self._connection_manager is not None:
            self._down_ratelimiter.remove_throttle_listener(
                self._connection_manager )
            self._connection_manager.cleanup()
        self.context_valid = False

        self._init()

        self.state = "created"

        # release mutex on this torrent.
        if self.config["one_download_per_torrent"]:
           if self._mutex is not None and self._mutex.owner():
               self._mutex.release()

        self._rawserver.add_task(0, gc.collect)

    def _no_announce_shutdown(self, level, text):
        # This is only called when announce fails with no peers,
        # don't try to announce again telling we're leaving the torrent
        self._announced = False
        self._error(level, text)
        self.failed()

    def set_file_priority(self, filename, priority):
        if self._storagewrapper is None or self._picker is None:
            self._pending_file_priorities.append((filename, priority))
        else:
            begin, end = self._storagewrapper.get_piece_range_for_filename(filename)
            self._picker.set_priority(xrange(begin, end + 1), priority)
        self.config.setdefault('file_priorities', {})
        self.config['file_priorities'][filename] = priority
        self._dump_torrent_config()

    def get_file_priorities(self):
        return self.config.get('file_priorities', {})

    def get_file_priority(self, filename):
        fp = self.get_file_priorities()
        return fp.get(filename, 0)

    def add_feedback(self, feedback):
        self.feedback.chain.append(feedback)

    def remove_feedback(self, feedback):
        self.feedback.chain.remove(feedback)

    def got_exception(self, failure, cannot_shutdown=False):
        type, e = failure.exc_info()[0:2]
        severity = logging.CRITICAL
        msg = "Torrent got exception: %s" % type
        e_str = str_exc(e)
        if isinstance(e, UnregisteredFileException):
            # not an error, a pending disk op was aborted because the torrent
            # has unregistered files.
            return
        if isinstance(e, BTFailure):
            self._activity = ( _("download failed: ") + e_str, 0)
        elif isinstance(e, IOError):
            if e.errno == errno.ENOSPC:
                msg = _("IO Error: No space left on disk, "
                        "or cannot create a file that large")
            self._activity = (_("killed by IO error: ") + e_str, 0)
        elif isinstance(e, OSError):
            self._activity = (_("killed by OS error: ") + e_str, 0)
        else:
            self._activity = (_("killed by internal exception: ") + e_str, 0)

        if isinstance(e, UserFailure):
            self.logger.log(severity, e_str )
        else:
            self.logger.log(severity, msg, exc_info=failure.exc_info())
        # steve wanted this too
        # Dave doesn't want it.
        #type, e, stack = failure.exc_info()
        #traceback.print_exception(type, e, stack, file=sys.stdout)

        self.failed(cannot_shutdown)

    def failed(self, cannot_shutdown=False):
        if cannot_shutdown:
            self.state = "failed"
            self.feedback.failed(self)
            return

        try:
            # this could complete later. sorry that's just the way it is.
            df = self.shutdown()
            def cb(r):
                self.state = "failed"
                self.feedback.failed(self)
            df.addBoth(cb)
        except:
            self.logger.exception(_("Additional error when closing down due"
                                    " to error: "))
            self.feedback.failed(self)


    def _error(self, level, text, exception=False, exc_info=None):
        if level > logging.WARNING:
            self.logger.log(level,
                            _('Error regarding "%s":\n')%self.metainfo.name + text,
                            exc_info=exc_info)
        if exception:
            self.feedback.exception(self, text)
        else:
            self.feedback.error(self, level, text)

    def finished(self, policy="auto", finished_this_session=True):
        assert self.state == "running", "state not running"
        self.logger.debug("done downloading, preparing to wrap up")
        # because _finished() calls shutdown(), which invalidates the torrent
        # context, we need to use _rawserver.add_task directly here
        df = launch_coroutine(wrap_task(self._rawserver.add_task), self._finished, policy=policy, finished_this_session=finished_this_session)
        df.addErrback(self.got_exception)
        return df

    def _finished(self, policy="auto", finished_this_session=True):
        self.logger.debug("wrapping up")
        if self.state != "running":
            return

        self.finflag.set()
        # Call self._storage.close() to flush buffers and change files to
        # read-only mode (when they're possibly reopened). Let exceptions
        # from self._storage.close() kill the torrent since files might not
        # be correct on disk if file.close() failed.
        self._storage.close()

        # don't bother trailing off the rate when we know we're done downloading
        self._downmeasure.rate = 0.0

        # If we haven't announced yet, normal first announce done later will
        # tell the tracker about seed status.
        # Only send completed the first time! Torrents transition to finished
        # everytime.
        if self._announced and not self.sent_completed:
            self._rerequest_op().announce_finish()
        self.sent_completed = True
        self._activity = (_("seeding"), 1)
        if self.config['check_hashes']:
            self._save_fastresume()

        # the old policy applied to downloading -- now that we are finished,
        # optionally reset it
        self.policy = policy

        self.feedback.finishing(self)

        config = self.config

        if finished_this_session:
            self.finished_this_session = True

        def move(working_path, destination_path):
            # this function is called from another thread, so don't do anything
            # that isn't thread safe in here

            self.logger.debug("deleting any file that might be in the way")
            try:
                os.remove(destination_path)
                self.logger.debug("successfully deleted file " +
                                  destination_path)
            except Exception, e:
                if os.path.exists(destination_path):
                    self.logger.debug(str_exc(e))

            self.logger.debug("deleting any directory that might be in the way")
            try:
                shutil.rmtree(destination_path)
                self.logger.debug("successfully deleted directory " +
                                  destination_path)
            except Exception, e:
                if os.path.exists(destination_path):
                    self.logger.debug(str_exc(e))

            self.logger.debug("ensuring destination exists")
            path, name = os.path.split(destination_path)
            no_really_makedirs(path)
            self.logger.debug("actually moving file")
            shutil.move(working_path, destination_path)
            self.logger.debug("returned from move")


        if self.working_path != self.destination_path:
##            self.logger.debug("torrent finishing: shutting down, moving file, and restarting")
##            df = self.shutdown()
##            yield df
##            df.getResult()
            self.logger.debug("torrent finishing: pausing, moving file, and restarting")
            self.stop_download(pause=True)
            self._unregister_files()

            self.logger.debug("successfully paused torrent, moving file")

            self.state = "finishing"
            df = ThreadedDeferred(wrap_task(self._rawserver.external_add_task),
                                  move, self.working_path, self.destination_path)
            yield df
            df.getResult()

            self.logger.debug("moved file, restarting")

            assert self.state == "finishing", "state not finishing"
            self.working_path = self.destination_path
##            self.state = "created"
##            df = self.initialize()
##            yield df
##            df.getResult()
            self.completed = True
            self.feedback.finished(self)

            self.state = "initializing"
            self._register_files()
            df = self._storage.initialize(self.working_path,
                                          zip(self._myfiles,
                                              self.metainfo.sizes))
            yield df
            df.getResult()

            # so we store new path names
            self._storagewrapper.fastresume_dirty = True
            self._statuscollector.files = self._myfiles

            self.state = "initialized"
            self.logger.debug("attempting restart")
            self.start_download()
            self.logger.debug("re-started torrent")
        else:
            self.completed = True
            self.feedback.finished(self)

        self._dump_torrent_config()

    def fastresume_file_path(self):
        # HEREDAVE: should probably be self.data_dir?
        return os.path.join(self.config['data_dir'], 'resume',
                            self.infohash.encode('hex'))

    def config_file_path(self):
        return os.path.join(self.data_dir, 'torrents',
                            self.metainfo.infohash.encode('hex'))

    def _periodic_save_fastresume(self):
        self._save_fastresume()
        if not self.finflag.isSet():
            self.add_task(30, self._periodic_save_fastresume)

    def _save_fastresume(self):
        if not self.is_initialized():
            return
        # HEREDAVE: should probably be self.data_dir?
        if not self.config['data_dir']:
            return
        filename = self.fastresume_file_path()
        if os.path.exists(filename) and not self._storagewrapper.fastresume_dirty:
            return
        resumefile = None
        try:
            resumefile = file(filename, 'wb')
            self._storagewrapper.write_fastresume(resumefile)
            resumefile.close()
        except Exception, e:
            self._error(logging.WARNING, _("Could not write fastresume data: ") +
                        str_exc(e))
            if resumefile is not None:
                resumefile.close()

    def _dump_torrent_config(self):
        d = self.config.getDict()
##        nd = {}
##        for k,v in d.iteritems():
##            # can't bencode floats!
##            if not isinstance(v, float):
##                if isinstance(v, unicode):
##                    # FIXME -- what is the right thing to do here?
##                    v = v.encode('utf8')
##                nd[k] = v
##        s = bencode(nd)
        s = cPickle.dumps(d)
        path = self.config_file_path()
        f = file(path+'.new', 'wb')
        f.write(s)
        f.close()
        shutil.move(path+'.new', path)

    def remove_state_files(self, del_files=False):
        assert self.state == "created", "state not created"

        try:
            os.remove(self.config_file_path())
        except Exception, e:
            self.logger.debug("error removing config file: %s", str_exc(e))

        try:
            os.remove(self.fastresume_file_path())
        except Exception, e:
            self.logger.debug("error removing fastresume file: %s", str_exc(e))

        if del_files:
            try:
                for file in self._last_myfiles:
                    try:
                        os.remove(file)
                    except OSError:
                        pass
                    d, f = os.path.split(file)
                    try:
                        os.rmdir(d)
                    except OSError:
                        pass

                try:
                    os.rmdir(self.working_path)
                except OSError:
                    pass
            except Exception, e:
                self.logger.debug("error removing incomplete files: %s", str_exc(e))

    def get_downrate(self):
        if self.is_running():
            return self._downmeasure.get_rate()

    def get_uprate(self):
        if self.is_running():
            return self._upmeasure.get_rate()

    def get_rates(self):
        return (self.get_uprate(), self.get_downrate())

    def get_downtotal(self):
        if self.is_running():
            return self._downmeasure.get_total()

    def get_uptotal(self):
        if self.is_running():
            return self._upmeasure.get_total()

    def get_percent_complete(self):
        if self.is_initialized():
            if self.total_bytes > 0:
                r = 1 - self._ratemeasure.get_size_left() / self.total_bytes
            else:
                r = 1.0
        else:
            r = 0.0

        return r

    def get_num_connections(self):
        if self._connection_manager:
            return self._connection_manager.how_many_connections()
        return 0

    def get_connections(self):
        return self._connection_manager.complete_connectors

    def get_avg_peer_downrate(self):
        cs = self._connection_manager.complete_connectors

        if len(cs) == 0:
            return 0.0

        total = 0.0
        for c in cs:
            total += c.download.connector.download.peermeasure.get_rate()

        return total / len(cs)

    def get_status(self, spew = False, fileinfo=False):
        if self.is_initialized():
            r = self._statuscollector.get_statistics(spew, fileinfo)
            r['activity'] = self._activity[0]
            r['priority'] = self.priority
            if not self.is_running():
                r['timeEst'] = None
        else:
            r = dict(itertools.izip(('activity', 'fractionDone'), self._activity))
            r['pieceStates'] = (0, 0, {})
            r['priority'] = self.priority
        return r

    def get_total_transfer(self):
        if self._upmeasure is None:
            return (0, 0)
        return (self._upmeasure.get_total(), self._downmeasure.get_total())

    def set_option(self, option, value):
        if self.config.has_key(option) and self.config[option] == value:
            return
        self.config[option] = value

    def change_port(self, new_port = None):
        r = self.config['forwarded_port']
        if r:
            for port in self.reserved_ports:
                self._singleport_listener.release_port(port)
            del self.reserved_ports[:]
            if self.rescrewedported_port == r:
                return
        elif new_port is not None:
            r = new_port
            self.reserved_ports.remove(self.reported_port)
            self.reserved_ports.append(r)
        elif self._singleport_listener.port != self.reported_port:
            r = self._singleport_listener.get_port(self.change_port)
            self.reserved_ports.append(r)
        else:
            return
        self.reported_port = r
        self._myid = self._make_id()
        if self._connection_manager:
            self._connection_manager.my_id = self._myid
        if self._announced:
            self._rerequest_op().change_port(self._myid, r)

    def _announce_done(self):
        for port in self.reserved_ports[:-1]:
            self._singleport_listener.release_port(port, self.change_port)
        del self.reserved_ports[:-1]

    def _make_id(self):
        return PeerID.make_id()
