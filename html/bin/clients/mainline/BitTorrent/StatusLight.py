# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Matt Chisholm

from BTL.platform import bttime
from BTL.translation import _


class StatusLight(object):

    initial_state = 'stopped'

    states = {
        # state     : (stock icon name, label, tool tip),
        'stopped'   : ('stopped',
                       _("Paused"),
                       _("Paused")),
        'empty'     : ('stopped',
                       _("No torrents"),
                       _("No torrents")),
        'starting'  : ('starting',
                       _("Checking for firewall..."),#_("Starting up..."),
                       _("Starting download")),
        'pre-natted': ('pre-natted',
                       _("Checking for firewall..."),
                       _("Online, checking for firewall")),
        'running'   : ('running',
                       _("Online, ports open"),
                       _("Online, running normally")),
        'natted'    : ('natted',
                       _("Online, maybe firewalled"),
                       _("Online, but downloads may be slow due to firewall/NAT")),
        'broken'    : ('broken',
                       _("No network connection"),
                       _("Check network connection")),
        }

    messages = {
        # message           : default new state,
        'stop'              : 'stopped'   ,
        'empty'             : 'empty'     ,
        'start'             : 'starting'  ,
        'seen_peers'        : 'pre-natted',
        'seen_remote_peers' : 'running'   ,
        'broken'            : 'broken'    ,
        }

    transitions = {
        # state      : { message            : custom new state, },
        'pre-natted' : { 'start'            : 'pre-natted',
                         'seen_peers'       : 'pre-natted',},
        'running'    : { 'start'            : 'running'   ,
                         'seen_peers'       : 'running'   ,},
        'natted'     : { 'start'            : 'natted'    ,
                         'seen_peers'       : 'natted'    ,},
        'broken'     : { 'start'            : 'broken'    ,},
        #TODO: add broken transitions
        }

    time_to_nat = 60 * 5 # 5 minutes

    def __init__(self):
        self.mystate = self.initial_state
        self.start_time = None

    def send_message(self, message):
        if message not in self.messages.keys():
            #print 'bad message', message
            return
        new_state = self.messages[message]
        if self.transitions.has_key(self.mystate):
            if self.transitions[self.mystate].has_key(message):
                new_state = self.transitions[self.mystate][message]

        # special pre-natted timeout logic
        if new_state == 'pre-natted':
            if (self.mystate == 'pre-natted' and
                bttime() - self.start_time > self.time_to_nat):
                # go to natted state after a while
                new_state = 'natted'
            elif self.mystate != 'pre-natted':
                # start pre-natted timer
                self.start_time = bttime()

        if new_state != self.mystate:
            #print 'changing state from', self.mystate, 'to', new_state
            self.mystate = new_state
            self.change_state()


    def change_state(self):
        pass


    def get_tip(self):
        return self.states[self.mystate][2]

    def get_label(self):
        return self.states[self.mystate][1]

