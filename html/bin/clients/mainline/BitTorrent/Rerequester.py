# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Greg Hazel
# based on code by Bram Cohen, Uoti Urpala

import sys
import random
import logging
from binascii import b2a_hex
from BTL.translation import _

from BitTorrent import version
from BTL.platform import bttime
from urllib import quote
from BitTorrent.btformats import check_peers
from BTL.bencode import bencode, bdecode
from BTL.exceptions import str_exc
from BTL.defer import Failure
from BTL import IPTools
from BitTorrent import BTFailure
from BitTorrent.prefs import Preferences
from BitTorrent.HTTPDownloader import getPageFactory
import twisted.internet.error

LOG_RESPONSE = False

class Rerequester(object):

    STATES = ['started', 'completed', 'stopped']

    def __init__(self, url, announce_list, config, sched, externalsched, rawserver,
                 howmany, connect,
                 amount_left, up, down, port, myid, infohash, errorfunc, doneflag,
                 upratefunc, downratefunc, ever_got_incoming, diefunc, sfunc,
                 has_dht):
        """
         @param url:       tracker's announce URL.
         @param announce_list: ?
         @param config:    preferences obj storing BitTorrent-wide
                           configuration.
         @param sched:     used to schedule events from inside rawserver's
                           thread.  (Oh boy.  externalsched and sched.
                           We expect Rerequester to
                           recognize the difference between rawserver's
                           thread and yet we go through the trouble of
                           abstracting away rawserver using a callback...
                           So what was the point?  --Dave)
         @param externalsched: see sched.  This one is called from outside
                           rawserver's thread.
         @param howmany:   callback to get the number of complete connections.
         @param connect:   callback to establish a connection to a peer
                           obtained from the tracker.
         @param amount_left: callback to obtain the number of bytes left to
                           download for this torrent.
         @param up:        callback to obtain the total number of bytes sent
                           for this torrent.
         @param down:      callback to obtain the total number of bytes
                           received for this torrent.
         @param port:      port to report to the tracker.  If the local peer
                           is behind a NAT then this is the local peer's port
                           on the NAT facing the outside world.
         @param myid:      local peer's unique (self-generated) id.
         @param infohash:  hash of the info section of the metainfo file.
         @param errorfunc: callback to report errors.
         @param doneflag:  when set all threads cleanup and then terminate.
         @param upratefunc: callback to obtain moving average rate on the
                           uplink for this torrent.
         @param downratefunc: callback to obtain moving average rate on the
                           downlink for this torrent.
         @param ever_got_incoming: callback to determine if this torrent
                           has ever received any mesages from other peers.
         @param diefunc:   callback that is called when announce fails to find
                           any peers.
         @param sfunc:     success function?  With regard to what?  --Dave

        """
        assert isinstance(url, str)
        assert isinstance(config, Preferences)
        assert type(port) in (int,long) and port > 0 and port < 65536, "Port: %s" % repr(port)
        assert callable(connect)
        assert callable(amount_left)
        assert callable(errorfunc)
        assert callable(upratefunc)
        assert callable(downratefunc)
        assert callable(ever_got_incoming)
        assert callable(diefunc)
        assert callable(sfunc)

        self.rawserver = rawserver
        self.dead = False
        self.baseurl = url
        self.announce_list = None
        if announce_list:
            # shuffle a new copy of the whole set only once
            shuffled_announce_list = []
            for tier in announce_list:
                # strip out udp urls
                shuffled_tier = [ u for u in tier if not u.lower().startswith("udp://") ]
                if not shuffled_tier:
                    # strip blank lists
                    continue
                random.shuffle(shuffled_tier)
                shuffled_announce_list.append(shuffled_tier)
            if shuffled_announce_list:
                self.announce_list = shuffled_announce_list
                self.tier = 0
                self.announce_i = 0
                self.baseurl = self.announce_list_next()
        self.announce_infohash = infohash
        self.peerid = None
        self.wanted_peerid = myid
        self.port = port
        self.url = None
        self.config = config
        self.last = None
        self.trackerid = None
        self.announce_interval = 30 * 60
        self.sched = sched
        self.howmany = howmany
        self.connect = connect
        self.amount_left = amount_left
        self.up = up
        self.down = down
        self.errorfunc = errorfunc
        self.doneflag = doneflag
        self.upratefunc = upratefunc
        self.downratefunc = downratefunc
        self.ever_got_incoming = ever_got_incoming
        self.diefunc = diefunc
        self.successfunc = sfunc
        self.finish = False
        self.current_started = None
        self.fail_wait = None
        self.last_time = bttime()
        self.previous_down = 0
        self.previous_up = 0
        self.tracker_num_peers = None
        self.tracker_num_seeds = None
        self.has_dht = has_dht

    def _makeurl(self, peerid, port):
        return ('%s?info_hash=%s&peer_id=%s&port=%s&key=%s' %
                (self.baseurl, quote(self.announce_infohash), quote(peerid), str(port),
                 b2a_hex(''.join([chr(random.randrange(256)) for i in xrange(4)]))))

    def change_port(self, peerid, port):
        self.wanted_peerid = peerid
        self.port = port
        self.last = None
        self.trackerid = None
        self._check()

    def begin(self):
        if self.sched:
            self.sched(10, self.begin)
            self._check()

    def announce_list_success(self):
        tmp = self.announce_list[self.tier].pop(self.announce_i)
        self.announce_list[self.tier].insert(0, tmp)
        self.tier = 0
        self.announce_i = 0

    def announce_list_fail(self):
        """returns True if the announce-list was restarted"""
        self.announce_i += 1
        if self.announce_i == len(self.announce_list[self.tier]):
            self.announce_i = 0
            self.tier += 1
            if self.tier == len(self.announce_list):
                self.tier = 0
                return True
        return False

    def announce_list_next(self):
        return self.announce_list[self.tier][self.announce_i]

    def announce_finish(self):
        if self.dead:
            return
        self.finish = True
        self._check()

    def announce_stop(self):
        if self.dead:
            return
        self._announce('stopped')

    def _check(self):
        assert not self.dead
        #self.errorfunc(logging.INFO, 'check: ' + str(self.current_started))
        if self.current_started is not None:
            if (bttime() - self.current_started) >= 58:
                self.errorfunc(logging.WARNING,
                               _("Tracker announce still not complete "
                                 "%d seconds after starting it") %
                               int(bttime() - self.current_started))
            return
        if self.peerid is None:
            self.peerid = self.wanted_peerid
            self.url = self._makeurl(self.peerid, self.port)
            self._announce('started')
            return
        if self.peerid != self.wanted_peerid:
            # _announce will clean up these
            up = self.up
            down = self.down
            self._announce('stopped')
            self.peerid = None
            self.previous_up = up()
            self.previous_down = down()
            return
        if self.finish:
            self.finish = False
            self._announce('completed')
            return
        if self.fail_wait is not None:
            if self.last_time + self.fail_wait <= bttime():
                self._announce()
            return
        if self.last_time > bttime() - self.config['rerequest_interval']:
            return
        if self.ever_got_incoming():
            getmore = self.howmany() <= self.config['min_peers'] / 3
        else:
            getmore = self.howmany() < self.config['min_peers']
        if getmore or bttime() - self.last_time > self.announce_interval:
            self._announce()

    def get_next_announce_time_est(self):
        # I'm sure this is wrong, but _check is confusing
        return bttime() - (self.last_time + self.announce_interval)

    def _announce(self, event=None):
        assert not self.dead
        self.current_started = bttime()
        #self.errorfunc(logging.INFO, 'announce: ' +
        #               str(bttime() - self.current_started))
        s = ('%s&uploaded=%s&downloaded=%s&left=%s' %
             (self.url, str(self.up()*self.config.get('lie',1) - self.previous_up),
              str(self.down() - self.previous_down), str(self.amount_left())))
        if self.last is not None:
            s += '&last=' + quote(str(self.last))
        if self.trackerid is not None:
            s += '&trackerid=' + quote(str(self.trackerid))
        if self.howmany() >= self.config['max_initiate']:
            s += '&numwant=0'
        else:
            s += '&compact=1'
        if event is not None:
            s += '&event=' + event

        self._rerequest(s)

    # Must destroy all references that could cause reference circles
    def cleanup(self):
        self.dead = True
        self.sched = None
        self.howmany = None
        self.connect = None
        self.amount_left = None
        self.up = None
        self.down = None
        self.upratefunc = None
        self.downratefunc = None
        self.ever_got_incoming = None
        self.diefunc = None
        self.successfunc = None

    def _rerequest(self, url):

        if self.config['ip']:
            df = self.rawserver.reactor.resolve(self.config['ip'])
            def error(f):
                self.errorfunc(logging.WARNING,
                               _("Problem resolving config ip (%r), "
                                 "gethostbyname failed") % self.config['ip'],
                               exc_info=f.exc_info())
            df.addErrback(error)
            df.addCallback(lambda ip : self._rerequest2(url, ip=ip))
        else:
            self._rerequest2(url)
            
    def _rerequest2(self, url, ip=None):
        proxy = self.config.get('tracker_proxy', None)
        if not proxy:
            proxy = None
            
        bind = self.config.get('bind', None)
        if not bind:
            bind = None

        factory = getPageFactory(url,
                                 agent='BitTorrent/' + version,
                                 bindAddress=bind,
                                 proxy=proxy,
                                 timeout=60)
        df = factory.deferred
        df.addCallback(lambda d : self._postrequest(data=d))
        df.addErrback(lambda f : self._postrequest(failure=f))

    def _give_up(self):
        if self.howmany() == 0 and self.amount_left() > 0 and not self.has_dht:
            # sched shouldn't be strictly necessary
            def die():
                self.diefunc(logging.CRITICAL,
                             _("Aborting the torrent as it could not "
                               "connect to the tracker while not "
                               "connected to any peers. "))
            self.sched(0, die)        

    def _fail(self, exc=None, rejected=False):

        if self.announce_list:
            restarted = self.announce_list_fail()
            if restarted:
                self.fail_wait = None
                if rejected:
                    self._give_up()
            else:            
                self.baseurl = self.announce_list_next()
            
                self.peerid = None
                # If it was a dns error, try the new url right away. it's
                # probably not abusive since there was no one there to abuse.
                if isinstance(exc, twisted.internet.error.DNSLookupError):
                    self._check()
                    return
        else:
            if rejected:
                self._give_up()
                    
        if self.fail_wait is None:
            self.fail_wait = 50
        else:
            self.fail_wait *= 1.4 + random.random() * .2
        self.fail_wait = min(self.fail_wait,
                             self.config['max_announce_retry_interval'])

    def _make_errormsg(self, msg):
        proxy = self.config.get('tracker_proxy', None)
        url = self.baseurl
        if proxy:
            url = _("%s through proxy %s") % (url, proxy)
        return (_("Problem connecting to tracker (%s): %s") %
                (url, msg))

    def _postrequest(self, data=None, failure=None):
        #self.errorfunc(logging.INFO, 'postrequest(%s): %s d:%s f:%s' %
        #               (self.__class__.__name__, self.current_started,
        #                bool(data), bool(failure)))
        self.current_started = None
        self.last_time = bttime()
        if self.dead:
            return
        if failure is not None:
            if failure.type == twisted.internet.error.TimeoutError:
                m = _("Timeout while contacting server.")
            else:
                m = failure.getErrorMessage()
            self.errorfunc(logging.WARNING, self._make_errormsg(m))
            self._fail(failure.exc_info())
            return
        try:
            r = bdecode(data)
            if LOG_RESPONSE:
                self.errorfunc(logging.INFO, 'tracker said: %r' % r)
            check_peers(r)
        except BTFailure, e:
            if data:
                self.errorfunc(logging.ERROR,
                               _("bad data from tracker (%r)") % data,
                               exc_info=sys.exc_info())
            self._fail()
            return
        if (isinstance(r.get('complete'), (int, long)) and
            isinstance(r.get('incomplete'), (int, long))):
            self.tracker_num_seeds = r['complete']
            self.tracker_num_peers = r['incomplete']
        else:
            self.tracker_num_seeds = None
            self.tracker_num_peers = None
        if 'failure reason' in r:
            self.errorfunc(logging.ERROR,
                           _("rejected by tracker - %s") % r['failure reason'])
            self._fail(rejected=True)
            return

        self.fail_wait = None
        if 'warning message' in r:
            self.errorfunc(logging.ERROR,
                           _("warning from tracker - %s") % r['warning message'])
        self.announce_interval = r.get('interval', self.announce_interval)
        if 'min interval' in r:
            self.config['rerequest_interval'] = r['min interval']
        self.trackerid = r.get('tracker id', self.trackerid)
        self.last = r.get('last')
        p = r['peers']
        peers = {}
        if isinstance(p, str):
            for (ip, port) in IPTools.uncompact_sequence(p):
                peers[(ip, port)] = None
        else:
            for x in p:
                peers[(x['ip'], x['port'])] = x.get('peer id')
        ps = len(peers) + self.howmany()
        if ps < self.config['max_initiate']:
            if self.doneflag.isSet():
                if r.get('num peers', 1000) - r.get('done peers', 0) > ps * 1.2:
                    self.last = None
            else:
                if r.get('num peers', 1000) > ps * 1.2:
                    self.last = None

        if self.config['log_tracker_info']:
            self.errorfunc(logging.INFO, '%s tracker response: %d peers' %
                           (self.__class__.__name__, len(peers)))
        for addr, pid in peers.iteritems():
            self.connect(addr, pid)
        if self.peerid == self.wanted_peerid:
            self.successfunc()

        if self.announce_list:
            self.announce_list_success()
            
        self._check()


class DHTRerequester(Rerequester):

    def __init__(self, config, sched, howmany, connect, externalsched, rawserver,
            amount_left, up, down, port, myid, infohash, errorfunc, doneflag,
            upratefunc, downratefunc, ever_got_incoming, diefunc, sfunc, dht):
        self.dht = dht
        Rerequester.__init__(self, "http://localhost/announce", [], config, sched, externalsched, rawserver,
                             howmany, connect,
                             amount_left, up, down, port, myid, infohash, errorfunc, doneflag,
                             upratefunc, downratefunc, ever_got_incoming, diefunc, sfunc, True)

    def _announce(self, event=None):
        self.current_started = bttime()
        self._rerequest("")

    def _make_errormsg(self, msg):
        return _("Trackerless lookup failed: %s") % msg                

    def _rerequest(self, url):
        self.peers = ""
        try:
            self.dht.getPeersAndAnnounce(str(self.announce_infohash), self.port,
                                         self._got_peers)
        except Exception, e:
            self._postrequest(failure=Failure())

    def _got_peers(self, peers):
        if not self.howmany:
            return
        if not peers:
            self.peerid = self.wanted_peerid
            self._postrequest(bencode({'peers':''}))
        else:
            self.peerid = None
            self._postrequest(bencode({'peers':peers[0]}))

    def _announced_peers(self, nodes):
        pass

    def announce_stop(self):
        # don't do anything
        pass
