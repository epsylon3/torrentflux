# this is here until the client imports the correct module everywhere.
# when that's done, this file can go away.
# BTL code that imports it should get the BitTorrent version if it's the client,
# or a pass-through if it's not. Since I'm not sure how best to implement that,
# it just grabs the client's function if I see it
import sys
if 'BitTorrent' in sys.modules:
    from BitTorrent.translation import _
else:
    def _(i): return i