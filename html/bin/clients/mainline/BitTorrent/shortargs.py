
# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by David Harrison

shortforms = { "-p" : "--port",
               "-u" : "--use_factory_defaults",
               "-h" : "--help",
               "-?" : "--help",
               "--usage" : "--help"
             }

def convert_from_shortforms(argv):
    """
       Converts short-form arguments onto the corresponding long-form, e.g.,
       -p becomes --port.
    """
    assert type(argv)==list
    newargv = []
    for arg in argv:
      if arg in shortforms:
          newargv.append(shortforms[arg])
      else:
          newargv.append(arg)
    return newargv
