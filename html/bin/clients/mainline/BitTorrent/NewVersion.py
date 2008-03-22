# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# written by Matt Chisholm, destroyed by Greg Hazel

DEBUG = False

from BitTorrent import version

version_host = 'http://version.bittorrent.com/'
download_url = 'http://www.bittorrent.com/download.html'

# based on Version() class from ShellTools package by Matt Chisholm,
# used with permission
class Version(list):
    def __str__(self):
        return '.'.join(map(str, self))

    def is_beta(self):
        return self[1] % 2 == 1

    def from_str(self, text):
        return Version( [int(t) for t in text.split('.')] )

    def name(self):
        if self.is_beta():
            return 'beta'
        else:
            return 'stable'
    
    from_str = classmethod(from_str)

currentversion = Version.from_str(version)

availableversion = None

