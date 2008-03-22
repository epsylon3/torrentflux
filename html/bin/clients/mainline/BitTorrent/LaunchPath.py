# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# LaunchPath -- a cross platform way to "open," "launch," or "start"
# files and directories

# written by Matt Chisholm

import os
import sys

can_launch_dirs  = False
can_launch_files = False
posix_browsers = ('gnome-open','konqueror',)
posix_dir_browsers = ('gmc', 'gentoo',) # these only work on dirs
default_posix_browser = ''

def launchpath_nt(path):
    os.startfile(path)

def launchfile_nt(path):
    do_launchdir = True
    if can_launch_files and not os.path.isdir(path):
        f, ext = os.path.splitext(path)
        ext = ext.upper()
        path_ext = os.environ.get('PATH_EXT')
        blacklist = []
        if path_ext:
            blacklist = path_ext.split(';')
        if ext not in blacklist:
            try:
                launchpath_nt(path)
            except: # WindowsError
                pass
            else:
                do_launchdir = False
                
    if do_launchdir:
        p, f = os.path.split(path)
        launchdir(p)

def launchpath_mac(path):
    os.spawnlp(os.P_NOWAIT, 'open', 'open', path)

def launchpath_posix(path):
    if default_posix_browser:
        os.spawnlp(os.P_NOWAIT, default_posix_browser,
                   default_posix_browser, path)

def launchpath(path):
    pass

def launchdir(path):
    if can_launch_dirs and os.path.isdir(path):
        launchpath(path)

def launchfile(path):
    if can_launch_files and not os.path.isdir(path):
        launchpath(path)
    else:
        p, f = os.path.split(path)
        launchdir(p)

if os.name == 'nt':
    can_launch_dirs  = True
    can_launch_files = True
    launchpath = launchpath_nt
    launchfile = launchfile_nt
elif sys.platform == "darwin":
    can_launch_dirs  = True
    can_launch_files = True
    launchpath = launchpath_mac
elif os.name == 'posix':
    for b in posix_browsers:
        if os.system("which '%s' >/dev/null 2>&1" % b.replace("'","\\'")) == 0:
            can_launch_dirs  = True
            can_launch_files = True
            default_posix_browser = b
            launchpath = launchpath_posix
            break
    else:
        for b in posix_dir_browsers:
            can_launch_dirs = True
            default_posix_browser = b
            launchpath = launchpath_posix
            break

