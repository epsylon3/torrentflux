from BTL.obsoletepythonsupport import set
from BitTorrent.TorrentPolicy import Policy
from BitTorrent.Torrent import *

class TorrentButler(Policy):

    def __init__(self, multitorrent):
        Policy.__init__(self, multitorrent)
        self.multitorrent = multitorrent


class EverythingAllOfTheTimeTorrentButler(TorrentButler):

    def __init__(self, multitorrent):
        TorrentButler.__init__(self, multitorrent)

    def butle(self):
        for torrent in self.multitorrent.get_torrents():
            if not self.multitorrent.torrent_running(torrent.metainfo.infohash):
                self.multitorrent.start_torrent(torrent.metainfo.infohash)



class EverythingOneTimeTorrentButler(TorrentButler):

    def __init__(self, multitorrent):
        TorrentButler.__init__(self, multitorrent)
        self.started_torrents = set()

    def butle(self):
        for torrent in self.multitorrent.get_torrents():
            if ((not self.multitorrent.torrent_running(torrent.metainfo.infohash)) and
                (not torrent.metainfo.infohash in self.started_torrents)):
                self.multitorrent.start_torrent(torrent.metainfo.infohash)
                self.started_torrents.add(torrent.metainfo.infohash)


class DownloadTorrentButler(TorrentButler):
    # TODO: this one should probably be configurable, once the new choker works
    SIMULTANEOUS = 3
    GOOD_RATE_THRESHOLD = 0.25  # minimal fraction of the average rate
    PURGATORY_TIME = 60  # seconds before we declare an underperformer "bad"
    LIMIT_MARGIN = 0.9  # consider bandwidth maximized at limit * margin
    REFRACTORY_PERIOD = 30  # seconds we wait after falling below the limit

    def __init__(self, multitorrent):
        TorrentButler.__init__(self, multitorrent)
        self.suspects = {}
        self.time_last_pushed_limit = bttime() - self.REFRACTORY_PERIOD

    def butles(self, torrent):
        return torrent.policy == "auto" and (not torrent.completed) and torrent.state in ["initialized", "running"]

    def butle(self):
        going = []
        waiting = []
        finishing = []
        num_initializing = 0
        for torrent in self.multitorrent.get_torrents():
            if self.butles(torrent):
                if self.multitorrent.torrent_running(torrent.metainfo.infohash):
                    going.append(torrent)
                elif torrent.is_initialized():
                    if torrent.get_percent_complete() < 1.0:
                        waiting.append(torrent)
                    else:
                        finishing.append(torrent)
            elif not torrent.completed:
                if torrent.state in ["created", "initializing"]:
                    if (bttime() - torrent.time_created < 10):
                        num_initializing += 1


        for t in finishing:
            self.multitorrent.start_torrent(t.metainfo.infohash)


        starting = [t for t in going
                    if bttime() - t.time_started < 60]

        transferring = [t for t in going
                        if t not in starting
                        and t.get_downrate() > 0.0
                        and t.get_num_connections() > 0]

        num_good = 0
        num_virtual_good = 0

        if (len(transferring) > 0):
            total_rate = sum([t.get_downrate() for t in transferring])
            good_rate = (total_rate / len(transferring)) * self.GOOD_RATE_THRESHOLD
            if good_rate > 0:
                bad = []
                for t in transferring:
                    if t.get_downrate() >= good_rate:
                        if self.suspects.has_key(t.metainfo.infohash):
                            #print t.working_path + " is good now, popping"
                            self.suspects.pop(t.metainfo.infohash)
                    else:
                        if self.suspects.has_key(t.metainfo.infohash):
                            #print t.working_path, bttime() - self.suspects[t.metainfo.infohash]
                            if (bttime() - self.suspects[t.metainfo.infohash] >=
                                self.PURGATORY_TIME):
                                bad.append(t)
                        else:
                            #print t.working_path + " is bad now, inserting"
                            self.suspects[t.metainfo.infohash] = bttime()

                total_bad_rate = sum([t.get_downrate() for t in bad])
                num_virtual_good = total_bad_rate / good_rate
                num_good = len(transferring) - len(bad)

        uprate, downrate = self.multitorrent.get_total_rates()
        downrate_limit = self.multitorrent.config['max_download_rate']
        #print num_initializing, num_good, num_virtual_good, len(starting)
        #print downrate, '/', downrate_limit
        if (downrate >= downrate_limit * self.LIMIT_MARGIN):
            self.time_last_pushed_limit = bttime()
            #print "pushing limit"

        #print bttime() - self.time_last_pushed_limit
        if ((bttime() - self.time_last_pushed_limit >
             self.REFRACTORY_PERIOD) and
            (num_initializing == 0) and
            (num_good + num_virtual_good + len(starting) < self.SIMULTANEOUS)):
            high = []
            norm = []
            low = []
            for torrent in waiting:
                if torrent.priority == "high":
                    high.append(torrent)
                elif torrent.priority == "normal":
                    norm.append(torrent)
                elif torrent.priority == "low":
                    low.append(torrent)

            for p in (high, norm, low):
                best = None
                for torrent in p:
                    if ((not best) or
                        (best.get_percent_complete() == 0 and
                         torrent.total_bytes < best.total_bytes) or
                        (torrent.get_percent_complete() >
                         best.get_percent_complete())):
                        best = torrent
                if best:
                    break

            if best:
                self.multitorrent.start_torrent(best.metainfo.infohash)


class SeedTorrentButler(TorrentButler):

    FREQUENCY = 15
    MIN_RATE = 2000.0
    THRESHOLD = 1.25
    MIN_TRANSFERRING = 1

    def __init__(self, multitorrent):
        TorrentButler.__init__(self, multitorrent)
        self.counter = 0

    def butles(self, torrent):
        return torrent.policy == "auto" and torrent.completed and torrent.state in ["initialized", "running"]

    def butle(self):
        self.counter += 1
        self.counter %= self.FREQUENCY
        if self.counter != 0:
            return

        num_connections = 0
        transferring = []
        stopped = []
        total_rate = 0.0
        for torrent in self.multitorrent.get_torrents():
            if self.butles(torrent):
                if self.multitorrent.torrent_running(torrent.metainfo.infohash):
                    #print "found running torrent: ", torrent.get_uprate(), torrent.get_num_connections(), torrent._activity
                    if (torrent.get_uprate() > 0.0
                        and torrent.get_num_connections() > 0):
                        transferring.append(torrent)
                        for c in torrent.get_connections():
                            total_rate += c.upload.measure.get_rate()
                            num_connections += 1
                else:
                    #print "found stopped torrent: ", torrent._activity
                    stopped.append(torrent)

        #print num_connections, len(transferring), len(stopped)

        if (len(transferring) < self.MIN_TRANSFERRING
            or total_rate / num_connections > self.MIN_RATE * self.THRESHOLD):
            if len(stopped):
                r = random.randint(0, len(stopped) - 1)
                #print "starting torrent"
                self.multitorrent.start_torrent(stopped[r].metainfo.infohash)
        elif total_rate / num_connections < self.MIN_RATE:
            if len(transferring) > self.MIN_TRANSFERRING:

                def lambda_dammit(x, y):
                    try:
                        x_contribution = x.get_uprate() / x.get_avg_peer_downrate()
                    except ZeroDivisionError:
                        x_contribution = 1.0

                    try:
                        y_contribution = y.get_uprate() / y.get_avg_peer_downrate()
                    except ZeroDivisionError:
                        y_contribution = 1.0

                    return cmp(x_contribution, y_contribution)

                transferring.sort(lambda_dammit)
                self.multitorrent.stop_torrent(transferring[0].metainfo.infohash)

