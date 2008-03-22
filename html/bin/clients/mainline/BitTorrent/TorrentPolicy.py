from BitTorrent.Torrent import Feedback

class Policy(Feedback):
    def __init__(self, multitorrent):
        self.multitorrent = multitorrent

    def butle(self):
        pass
