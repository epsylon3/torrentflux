# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Greg Hazel, Matt Chisholm, Uoti Urpala, and David Harrison

# This module is strictly for cross platform compatibility items and
# should not import anything from other BitTorrent modules.

import os
import sys
import locale
import shutil
import socket
import gettext
import tarfile
import traceback

from BTL.platform import efs, efs2, get_filesystem_encoding

# NOTE: intentionally appears in the file before importing anything
# from BitTorrent because it is called when setting --use_factory_defaults.
def get_temp_dir():
    shellvars = ['${TMP}', '${TEMP}']
    dir_root = get_dir_root(shellvars, default_to_home=False)

    #this method is preferred to the envvars
    if os.name == 'nt':
        try_dir_root = win32api.GetTempPath()
        if try_dir_root is not None:
            dir_root = try_dir_root

    if dir_root is None:
        try_dir_root = None
        if os.name == 'nt':
            # this should basically never happen. GetTempPath always returns something
            try_dir_root = r'C:\WINDOWS\Temp'
        elif os.name == 'posix':
            try_dir_root = '/tmp'
        if (try_dir_root is not None and
            os.path.isdir(try_dir_root) and
            os.access(try_dir_root, os.R_OK|os.W_OK)):
            dir_root = try_dir_root
    return dir_root

MAX_DIR = 5
_tmp_subdir = None

# NOTE: intentionally appears in the file before importing anything
# from BitTorrent because it is called when setting --use_factory_defaults.
def get_temp_subdir():
    """Creates a unique subdirectory of the platform temp directory.
       This revolves between MAX_DIR directory names deleting the oldest
       whenever MAX_DIR exist.  Upon return the number of temporary
       subdirectories should never exceed MAX_DIR-1.  If one has already
       been created for this execution, this returns that subdirectory.

       @return the absolute path of the created temporary directory.
       """
    global _tmp_subdir
    if _tmp_subdir is not None:
        return _tmp_subdir
    tmp = get_temp_dir()
    target = None   # holds the name of the directory that will be made.
    for i in xrange(MAX_DIR):
        subdir = efs2(u"BitTorrentTemp%d" % i)
        path = os.path.join(tmp, subdir)
        if not os.path.exists(path):
            target = path
            break

    # subdir should not in normal behavior be None.  It can occur if something
    # prevented a directory from being removed on a previous call or if MAX_DIR
    # is changed.
    if target is None:
       subdir = efs2(u"BitTorrentTemp0")
       path = os.path.join(tmp, subdir)
       shutil.rmtree( path, ignore_errors = True )
       target = path
       i = 0

    # create the temp dir.
    os.mkdir(target)

    # delete the oldest directory.
    oldest_i = ( i + 1 ) % MAX_DIR
    oldest_subdir = efs2(u"BitTorrentTemp%d" % oldest_i)
    oldest_path = os.path.join(tmp, oldest_subdir)
    if os.path.exists( oldest_path ):
        shutil.rmtree( oldest_path, ignore_errors = True )

    _tmp_subdir = target
    return target


_config_dir = None
def set_config_dir(dir):
    """Set the root directory for configuration information."""
    global _config_dir
    # Normally we won't set it this way.  This is called if the
    # --use_factory_defaults command-line option is specfied.  By changing
    # the config directory early in the initialization we can guarantee
    # that the system acts like a fresh install.
    _config_dir = dir

# Set configuration directory before importing any other BitTorrent modules.
if "--use_factory_defaults" in sys.argv or "-u" in sys.argv:
    temp_dir = get_temp_subdir()
    set_config_dir(temp_dir)

import BitTorrent.zurllib as urllib
from BTL import language
from BTL.sparse_set import SparseSet
from BTL.defer import ThreadedDeferred
from BTL.platform import app_name, get_module_filename, is_frozen_exe, get_shell_dir
from BitTorrent import version

if os.name == 'nt':
    import pywintypes
    import _winreg
    #import BTL.likewin32api as win32api
    import win32api
    import win32file
    from win32com.shell import shellcon
    import win32con
    from twisted.python.shortcut import Shortcut
    import ctypes
    import struct
    FILE_ATTRIBUTE_SPARSE_FILE = 0x00000200
    FILE_SUPPORTS_SPARSE_FILES = 0x00000040
    FSCTL_QUERY_ALLOCATED_RANGES = 0x000940CF
elif os.name == 'posix' and os.uname()[0] == 'Darwin':
    has_pyobjc = False
    try:
        from Foundation import NSBundle
        has_pyobjc = True
    except ImportError:
        pass

try:
    import statvfs
except ImportError:
    statvfs = None

def get_dir_root(shellvars, default_to_home=True):
    def check_sysvars(x):
        y = os.path.expandvars(x)
        if y != x and os.path.isdir(y):
            return y
        return None

    dir_root = None
    for d in shellvars:
        dir_root = check_sysvars(d)
        if dir_root is not None:
            break
    else:
        if default_to_home:
            dir_root = os.path.expanduser('~')
            if dir_root == '~' or not os.path.isdir(dir_root):
                dir_root = None

    if get_filesystem_encoding() == None:
        try:
            dir_root = dir_root.decode(sys.getfilesystemencoding())
        except:
            try:
                dir_root = dir_root.decode('utf8')
            except:
                pass


    return dir_root

def get_old_dot_dir():
    return os.path.join(get_config_dir(), efs2(u'.bittorrent'))

def get_dot_dir():
    # So called because on Unix platforms (but not OS X) this returns ~/.bittorrent.
    dot_dir = get_old_dot_dir()

    new_dot_dir = None
    if sys.platform == 'darwin':
        new_dot_dir = os.path.join(get_config_dir(), 'Library', 'Application Support', app_name)
    elif os.name == 'nt':
        new_dot_dir = os.path.join(get_config_dir(), app_name)

    if new_dot_dir:
        if os.path.exists(dot_dir):
            if os.path.exists(new_dot_dir):
                count = 0
                for root, dirs, files in os.walk(new_dot_dir):
                    count = len(dirs) + len(files)
                    break
                if count == 0:
                    shutil.rmtree(new_dot_dir)
                    shutil.move(dot_dir, new_dot_dir)
            else:
                shutil.move(dot_dir, new_dot_dir)
        dot_dir = new_dot_dir

    return dot_dir

old_broken_config_subencoding = 'utf8'
try:
    old_broken_config_subencoding = sys.getfilesystemencoding()
except:
    pass

os_name = os.name
os_version = None
if os_name == 'nt':

    wh = {(1, 4,  0): "95",
          (1, 4, 10): "98",
          (1, 4, 90): "ME",
          (2, 4,  0): "NT",
          (2, 5,  0): "2000",
          (2, 5,  1): "XP"  ,
          (2, 5,  2): "2003",
          (2, 6,  0): "Vista",
          }

    class OSVERSIONINFOEX(ctypes.Structure):
        _fields_ = [("dwOSVersionInfoSize", ctypes.c_ulong),
                    ("dwMajorVersion", ctypes.c_ulong),
                    ("dwMinorVersion", ctypes.c_ulong),
                    ("dwBuildNumber", ctypes.c_ulong),
                    ("dwPlatformId", ctypes.c_ulong),
                    ("szCSDVersion", ctypes.c_char * 128),
                    ("wServicePackMajor", ctypes.c_ushort),
                    ("wServicePackMinor", ctypes.c_ushort),
                    ("wSuiteMask", ctypes.c_ushort),
                    ("wProductType", ctypes.c_byte),
                    ("wReserved", ctypes.c_byte),
                    ]

    class OSVERSIONINFO(ctypes.Structure):
        _fields_ = [("dwOSVersionInfoSize", ctypes.c_ulong),
                    ("dwMajorVersion", ctypes.c_ulong),
                    ("dwMinorVersion", ctypes.c_ulong),
                    ("dwBuildNumber", ctypes.c_ulong),
                    ("dwPlatformId", ctypes.c_ulong),
                    ("szCSDVersion", ctypes.c_char * 128),
                    ]

    o = OSVERSIONINFOEX()
    o.dwOSVersionInfoSize = 156 # sizeof(OSVERSIONINFOEX)

    r = ctypes.windll.kernel32.GetVersionExA(ctypes.byref(o))
    if r:
        win_version_num = (o.dwPlatformId, o.dwMajorVersion, o.dwMinorVersion,
                           o.wServicePackMajor, o.wServicePackMinor, o.dwBuildNumber)
    else:
        o = OSVERSIONINFOEX()
        o.dwOSVersionInfoSize = 148 # sizeof(OSVERSIONINFO)
        r = ctypes.windll.kernel32.GetVersionExA(ctypes.byref(o))
        win_version_num = (o.dwPlatformId, o.dwMajorVersion, o.dwMinorVersion,
                           0, 0, o.dwBuildNumber)

    wk = (o.dwPlatformId, o.dwMajorVersion, o.dwMinorVersion)
    if wh.has_key(wk):
        os_version = wh[wk]
    else:
        os_version = wh[max(wh.keys())]
        sys.stderr.write("Couldn't identify windows version: wk:%s, %s, "
                         "assuming '%s'\n" % (str(wk),
                                              str(win_version_num),
                                              os_version))
    del wh, wk

elif os_name == 'posix':
    os_version = os.uname()[0]

app_root = os.path.split(get_module_filename())[0]
doc_root = app_root
osx = False
if os.name == 'posix':
    if os.uname()[0] == "Darwin":
        doc_root = app_root = app_root.encode('utf8')
        if has_pyobjc:
            doc_root = NSBundle.mainBundle().resourcePath()
            osx = True

def calc_unix_dirs():
    appdir = '%s-%s' % (app_name, version)
    ip = os.path.join(efs2(u'share'), efs2(u'pixmaps'), appdir)
    dp = os.path.join(efs2(u'share'), efs2(u'doc'), appdir)
    lp = os.path.join(efs2(u'share'), efs2(u'locale'))
    return ip, dp, lp

def no_really_makedirs(path):
    # the deal here is, directories like "C:\" exist but can not be created
    # (access denied). We check for the exception anyway because of the race
    # condition.
    if not os.path.exists(path):
        try:
            os.makedirs(path)
        except OSError, e:
            if e.errno != 17: # already exists
                raise

def get_config_dir():
    """a cross-platform way to get user's config directory.
    """
    if _config_dir is not None:
        return _config_dir

    shellvars = ['${APPDATA}', '${HOME}', '${USERPROFILE}']
    dir_root = get_dir_root(shellvars)

    if dir_root is None and os.name == 'nt':
        app_dir = get_shell_dir(shellcon.CSIDL_APPDATA)
        if app_dir is not None:
            dir_root = app_dir

    if dir_root is None and os.name == 'nt':
        tmp_dir_root = os.path.split(sys.executable)[0]
        if os.access(tmp_dir_root, os.R_OK|os.W_OK):
            dir_root = tmp_dir_root

    return dir_root



# For string literal subdirectories, starting with unicode and then
# converting to filesystem encoding may not always be necessary, but it seems
# safer to do so.  --Dave
image_root  = os.path.join(app_root, efs2(u'images'))
locale_root = os.path.join(get_dot_dir(), efs2(u'locale'))
no_really_makedirs(locale_root)

plugin_path = []
internal_plugin = os.path.join(app_root, efs2(u'BitTorrent'),
                                         efs2(u'Plugins'))

local_plugin = os.path.join(get_dot_dir(), efs2(u'Plugins'))
if os.access(local_plugin, os.F_OK):
    plugin_path.append(local_plugin)

if os.access(internal_plugin, os.F_OK):
    plugin_path.append(internal_plugin)

if not os.access(image_root, os.F_OK) or not os.access(locale_root, os.F_OK):
    # we guess that probably we are installed on *nix in this case
    # (I have no idea whether this is right or not -- matt)
    if app_root[-4:] == '/bin':
        # yep, installed on *nix
        installed_prefix = app_root[:-4]
        image_root, doc_root, locale_root = map(
            lambda p: os.path.join(installed_prefix, p), calc_unix_dirs()
            )
        systemwide_plugin = os.path.join(installed_prefix, efs2(u'lib'),
                                         efs2(u'BitTorrent'))
        if os.access(systemwide_plugin, os.F_OK):
            plugin_path.append(systemwide_plugin)

if os.name == 'nt':
    def GetDiskFreeSpaceEx(s):
        if isinstance(s, unicode):
            GDFS = ctypes.windll.kernel32.GetDiskFreeSpaceExW
        else:
            GDFS = ctypes.windll.kernel32.GetDiskFreeSpaceExA
        FreeBytesAvailable = ctypes.c_ulonglong(0)
        TotalNumberOfBytes = ctypes.c_ulonglong(0)
        TotalNumberOfFreeBytes = ctypes.c_ulonglong(0)
        r = GDFS(s,
                 ctypes.pointer(FreeBytesAvailable),
                 ctypes.pointer(TotalNumberOfBytes),
                 ctypes.pointer(TotalNumberOfFreeBytes))
        return FreeBytesAvailable.value, TotalNumberOfBytes.value, TotalNumberOfFreeBytes.value

def get_free_space(path):
    # optimistic if we can't tell
    free_to_user = 2**64

    path, file = os.path.split(path)
    if os.name == 'nt':
        while not os.path.exists(path):
            path, top = os.path.split(path)
        free_to_user, total, total_free = GetDiskFreeSpaceEx(path)
    elif hasattr(os, "statvfs") and statvfs:
        s = os.statvfs(path)
        free_to_user = s[statvfs.F_BAVAIL] * long(s[statvfs.F_BSIZE])

    return free_to_user

def get_sparse_files_support(path):
    supported = False

    if os.name == 'nt':
        drive, path = os.path.splitdrive(os.path.abspath(path))
        if len(drive) > 0: # might be a network path
            if drive[-1] != '\\':
                drive += '\\'
            volumename, serialnumber, maxpath, fsflags, fs_name = win32api.GetVolumeInformation(drive)
            if fsflags & FILE_SUPPORTS_SPARSE_FILES:
                supported = True

    return supported

# is there a linux max path?
def is_path_too_long(path):
    if os.name == 'nt':
        if len(path) > win32con.MAX_PATH:
            return True

    return False

def is_sparse(path):
    supported = get_sparse_files_support(path)
    if not supported:
        return False
    if os.name == 'nt':
        return bool(win32file.GetFileAttributes(path) & FILE_ATTRIBUTE_SPARSE_FILE)
    return False

def get_allocated_regions(path, f=None, begin=0, length=None):
    supported = get_sparse_files_support(path)
    if not supported:
        return
    if os.name == 'nt':
        if not os.path.exists(path):
            return False
        if f is None:
            f = file(path, 'r')
        handle = win32file._get_osfhandle(f.fileno())
        if length is None:
            length = os.path.getsize(path) - begin
        a = SparseSet()
        interval = 10000000
        i = begin
        end = begin + length
        while i < end:
            d = struct.pack("<QQ", i, interval)
            try:
                r = win32file.DeviceIoControl(handle, FSCTL_QUERY_ALLOCATED_RANGES,
                                              d, interval, None)
            except pywintypes.error, e:
                # I've seen:
                # error: (1784, 'DeviceIoControl', 'The supplied user buffer is not valid for the requested operation.')
                return
            for c in xrange(0, len(r), 16):
                qq = struct.unpack("<QQ", r[c:c+16])
                b = qq[0]
                e = b + qq[1]
                a.add(b, e)
            i += interval

        return a
    return

def get_max_filesize(path):
    fs_name = None
    # optimistic if we can't tell
    max_filesize = 2**64

    if os.name == 'nt':
        drive, path = os.path.splitdrive(os.path.abspath(path))
        if len(drive) > 0: # might be a network path
            if drive[-1] != '\\':
                drive += '\\'
            volumename, serialnumber, maxpath, fsflags, fs_name = win32api.GetVolumeInformation(drive)
            if fs_name == "FAT32":
                max_filesize = 2**32 - 1
            elif (fs_name == "FAT" or
                  fs_name == "FAT16"):
                # information on this varies, so I chose the description from
                # MS: http://support.microsoft.com/kb/q118335/
                # which happens to also be the most conservative.
                max_clusters = 2**16 - 11
                max_cluster_size = 2**15
                max_filesize = max_clusters * max_cluster_size
    else:
        path = os.path.realpath(path)
        # not implemented yet
        #fsname = crawl_path_for_mount_entry(path)

    return fs_name, max_filesize

def get_torrents_dir():
    return os.path.join(get_dot_dir(), efs2(u'torrents'))

def get_nebula_file():
    return os.path.join(get_dot_dir(), efs2(u'nebula'))

def get_home_dir():
    shellvars = ['${HOME}', '${USERPROFILE}']
    dir_root = get_dir_root(shellvars)

    if (dir_root is None) and (os.name == 'nt'):
        dir = get_shell_dir(shellcon.CSIDL_PROFILE)
        if dir is None:
            # there's no clear best fallback here
            # MS discourages you from writing directly in the home dir,
            # and sometimes (i.e. win98) there isn't one
            dir = get_shell_dir(shellcon.CSIDL_DESKTOPDIRECTORY)

        dir_root = dir

    return dir_root


def get_local_data_dir():
    if os.name == 'nt':
        # this results in paths that are too long
        # 86 characters: 'C:\Documents and Settings\Some Guy\Local Settings\Application Data\BitTorrent\incoming'
        #return os.path.join(get_shell_dir(shellcon.CSIDL_LOCAL_APPDATA), app_name)
        # I'm even a little nervous about this one
        return get_dot_dir()
    else:
        # BUG: there might be a better place to save incomplete files in under OSX
        return get_dot_dir()

def get_old_incomplete_data_dir():
    incomplete = efs2(u'incomplete')
    return os.path.join(get_old_dot_dir(), incomplete)

def get_incomplete_data_dir():
    # 'incomplete' is a directory name and should not be localized
    incomplete = efs2(u'incomplete')
    return os.path.join(get_local_data_dir(), incomplete)

def get_save_dir():
    dirname = u'%s Downloads' % unicode(app_name)
    dirname = efs2(dirname)
    if os.name == 'nt':
        d = get_shell_dir(shellcon.CSIDL_PERSONAL)
        if d is None:
            d = desktop
    else:
        d = desktop
    return os.path.join(d, dirname)

def get_startup_dir():
    """get directory where symlinks/shortcuts to be run at startup belong"""
    dir = None
    if os.name == 'nt':
        dir = get_shell_dir(shellcon.CSIDL_STARTUP)
    return dir

def create_shortcut(source, dest, *args):
    if os.name == 'nt':
        if len(args) == 0:
            args = None
        path, file = os.path.split(source)        
        sc = Shortcut(source,
                      arguments=args,
                      workingdir=path)
        sc.save(dest)                  
    else:
        # some other os may not support this, but throwing an error is good since
        # the function couldn't do what was requested
        os.symlink(source, dest)
        # linux also can't do args... maybe we should spit out a shell script?
        assert not args

def resolve_shortcut(path):
    if os.name == 'nt':
        sc = Shortcut()
        sc.load(path)
        return sc.GetPath(0)[0]
    else:
        # boy, I don't know
        return path

def remove_shortcut(dest):
    if os.name == 'nt':
        dest += ".lnk"
    os.unlink(dest)


def enforce_shortcut(config, log_func):
    if os.name != 'nt':
        return

    path = win32api.GetModuleFileName(0)

    if 'python' in path.lower():
        # oops, running the .py too lazy to make that work
        path = r"C:\Program Files\BitTorrent\bittorrent.exe"

    root_key = _winreg.HKEY_CURRENT_USER
    subkey = r'Software\Microsoft\Windows\CurrentVersion\run'
    key = _winreg.CreateKey(root_key, subkey)
    if config['launch_on_startup']:
        _winreg.SetValueEx(key, app_name, 0, _winreg.REG_SZ,
                           '"%s" --force_start_minimized' % path)
    else:
        try:
            _winreg.DeleteValue(key, app_name)
        except WindowsError, e:
            # value doesn't exist
            pass

def enforce_association():
    if os.name != 'nt':
        return

    try:
        _enforce_association()
    except WindowsError:
        # access denied. not much we can do.
        traceback.print_exc()

def _enforce_association():
    INSTDIR, EXENAME = os.path.split(win32api.GetModuleFileName(0))
    if 'python' in EXENAME.lower():
        # oops, running the .py too lazy to make that work
        INSTDIR = r"C:\Program Files\BitTorrent"
        EXENAME = "bittorrent.exe"

    # owie
    edit_flags = chr(0x00) + chr(0x00) + chr(0x10) + chr(0x00)

    # lots of wrappers for more direct NSIS mapping
    HKCR = _winreg.HKEY_CLASSES_ROOT
    HKCU = _winreg.HKEY_CURRENT_USER

    def filter_vars(s):
        s = s.replace("$INSTDIR", INSTDIR)
        s = s.replace("${EXENAME}", EXENAME)
        return s

    def WriteReg(root_key, subkey, key_name, type, value):
        subkey = filter_vars(subkey)
        key_name = filter_vars(key_name)
        value = filter_vars(value)
        # CreateKey opens the key for us and creates it if it does not exist
        #key = _winreg.OpenKey(root_key, subkey, 0, _winreg.KEY_ALL_ACCESS)
        key = _winreg.CreateKey(root_key, subkey)
        _winreg.SetValueEx(key, key_name, 0, type, value)
    def WriteRegStr(root_key, subkey, key_name, value):
        WriteReg(root_key, subkey, key_name, _winreg.REG_SZ, value)
    def WriteRegBin(root_key, subkey, key_name, value):
        WriteReg(root_key, subkey, key_name, _winreg.REG_BINARY, value)

    def DeleteRegKey(root_key, subkey):
        try:
            _winreg.DeleteKey(root_key, subkey)
        except WindowsError:
            # key doesn't exist
            pass

    ## Begin NSIS copy/paste/translate

    WriteRegStr(HKCR, '.torrent', "", "bittorrent")
    DeleteRegKey(HKCR, r".torrent\Content Type")
    # This line maks it so that BT sticks around as an option
    # after installing some other default handler for torrent files
    WriteRegStr(HKCR, r".torrent\OpenWithProgids", "bittorrent", "")

    # this prevents user-preference from generating "Invalid Menu Handle" by looking for an app
    # that no longer exists, and instead points it at us.
    WriteRegStr(HKCU, r"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\.torrent", "Application", EXENAME)
    WriteRegStr(HKCR, r"Applications\${EXENAME}\shell", "", "open")
    WriteRegStr(HKCR, r"Applications\${EXENAME}\shell\open\command", "", r'"$INSTDIR\${EXENAME}" "%1"')

    # Add a mime type
    WriteRegStr(HKCR, r"MIME\Database\Content Type\application/x-bittorrent", "Extension", ".torrent")

    # Add a shell command to match the 'bittorrent' handler described above
    WriteRegStr(HKCR, "bittorrent", "", "TORRENT File")

    WriteRegBin(HKCR, "bittorrent", "EditFlags", edit_flags)
    # make us the default handler for bittorrent://
    WriteRegBin(HKCR, "bittorrent", "URL Protocol", chr(0x0))
    WriteRegStr(HKCR, r"bittorrent\Content Type", "", "application/x-bittorrent")
    WriteRegStr(HKCR, r"bittorrent\DefaultIcon", "", r"$INSTDIR\${EXENAME},0")
    WriteRegStr(HKCR, r"bittorrent\shell", "", "open")

##    ReadRegStr $R1 HKCR "bittorrent\shell\open\command" ""
##    StrCmp $R1 "" continue
##
##    WriteRegStr HKCR "bittorrent\shell\open\command" "backup" $R1
##
##    continue:
    WriteRegStr(HKCR, r"bittorrent\shell\open\command", "", r'"$INSTDIR\${EXENAME}" "%1"')

    # Add a shell command to handle torrent:// stuff
    WriteRegStr(HKCR, "torrent", "", "TORRENT File")
    WriteRegBin(HKCR, "torrent", "EditFlags", edit_flags)
    # make us the default handler for torrent://
    WriteRegBin(HKCR, "torrent", "URL Protocol", chr(0x0))
    WriteRegStr(HKCR, r"torrent\Content Type", "", "application/x-bittorrent")
    WriteRegStr(HKCR, r"torrent\DefaultIcon", "", "$INSTDIR\${EXENAME},0")
    WriteRegStr(HKCR, r"torrent\shell", "", "open")

##    ReadRegStr $R1 HKCR "torrent\shell\open\command" ""
##    WriteRegStr HKCR "torrent\shell\open\command" "backup" $R1

    WriteRegStr(HKCR, r"torrent\shell\open\command", "", r'"$INSTDIR\${EXENAME}" "%1"')



def btspawn(cmd, *args):
    ext = ''
    if is_frozen_exe:
        ext = '.exe'
    path = os.path.join(app_root, cmd+ext)
    if not os.access(path, os.F_OK):
        if os.access(path+'.py', os.F_OK):
            path = path+'.py'
    args = [path] + list(args) # $0
    spawn(*args)

def spawn(*args):
    if os.name == 'nt':
        # do proper argument quoting since exec/spawn on Windows doesn't
        bargs = args
        args = []
        for a in bargs:
            if not a.startswith("/"):
                a.replace('"', '\"')
                a = '"%s"' % a
            args.append(a)

        argstr = ' '.join(args[1:])
        # use ShellExecute instead of spawn*() because we don't want
        # handles (like the controlsocket) to be duplicated
        win32api.ShellExecute(0, "open", args[0], argstr, None, 1) # 1 == SW_SHOW
    else:
        if os.access(args[0], os.X_OK):
            forkback = os.fork()
            if forkback == 0:
                # BUG: stop IPC!
                print "execl ", args[0], args
                os.execl(args[0], *args)
        else:
            #BUG: what should we do here?
            pass


def language_path():
    dot_dir = get_dot_dir()
    lang_file_name = os.path.join(dot_dir, efs(u'data')[0],
                                           efs(u'language')[0])
    return lang_file_name

def get_language(name):
  from BTL import LOCALE_URL
  url = LOCALE_URL + name + ".tar.gz"
  socket.setdefaulttimeout(5)
  r = urllib.urlopen(url)
  # urllib seems to ungzip for us
  tarname = os.path.join(locale_root, name + ".tar")
  f = file(tarname, 'wb')
  f.write(r.read())
  f.close()
  tar = tarfile.open(tarname, "r")
  for tarinfo in tar:
      tar.extract(tarinfo, path=locale_root)
  tar.close()


##def smart_gettext_translation(domain, localedir, languages, fallback=False):
##    try:
##        t = gettext.translation(domain, localedir, languages=languages)
##    except Exception, e:
##        for lang in languages:
##            try:
##                get_language(lang)
##            except Exception, e:
##                #print "Failed on", lang, e
##                pass
##        t = gettext.translation(domain, localedir, languages=languages,
##                                fallback=fallback)
##    return t


def blocking_smart_gettext_and_install(domain, localedir, languages,
                                       fallback=False, unicode=False):
    try:
        t = gettext.translation(domain, localedir, languages=languages,
                                fallback=fallback)
    except Exception, e:
        # if we failed to find the language, fetch it from the web
        running_count = 0
        running_deferred = {}

        # Get some reasonable defaults for arguments that were not supplied
        if languages is None:
            languages = []
            for envar in ('LANGUAGE', 'LC_ALL', 'LC_MESSAGES', 'LANG'):
                val = os.environ.get(envar)
                if val:
                    languages = val.split(':')
                    break
            if 'C' not in languages:
                languages.append('C')

        # now normalize and expand the languages
        nelangs = []
        for lang in languages:
            for nelang in gettext._expand_lang(lang):
                if nelang not in nelangs:
                    nelangs.append(nelang)
        languages = nelangs

        for lang in languages:
            # HACK
            if lang.startswith('en'):
                continue
            if lang.startswith('C'):
                continue
            try:
                get_language(lang)
            except: #urllib.HTTPError:
                pass

        t = gettext.translation(domain, localedir,
                                languages=languages,
                                fallback=True)
    t.install(unicode)


def smart_gettext_and_install(domain, localedir, languages,
                              fallback=False, unicode=False):
    try:
        t = gettext.translation(domain, localedir, languages=languages,
                                fallback=fallback)
    except Exception, e:
        # if we failed to find the language, fetch it from the web async-style
        running_count = 0
        running_deferred = {}

        # Get some reasonable defaults for arguments that were not supplied
        if languages is None:
            languages = []
            for envar in ('LANGUAGE', 'LC_ALL', 'LC_MESSAGES', 'LANG'):
                val = os.environ.get(envar)
                if val:
                    languages = val.split(':')
                    break
            if 'C' not in languages:
                languages.append('C')

        # now normalize and expand the languages
        nelangs = []
        for lang in languages:
            for nelang in gettext._expand_lang(lang):
                if nelang not in nelangs:
                    nelangs.append(nelang)
        languages = nelangs

        for lang in languages:
            d = ThreadedDeferred(None, get_language, lang)
            def translate_and_install(r, td=d):
                running_deferred.pop(td)
                # only let the last one try to install
                if len(running_deferred) == 0:
                    t = gettext.translation(domain, localedir,
                                            languages=languages,
                                            fallback=True)
                    t.install(unicode)
            def failed(e, tlang=lang, td=d):
                if td in running_deferred:
                    running_deferred.pop(td)
                # don't raise an error, just continue untranslated
                sys.stderr.write('Could not find translation for language "%s"\n' %
                                 tlang)
                #traceback.print_exc(e)
            d.addCallback(translate_and_install)
            d.addErrback(failed)
            # accumulate all the deferreds first
            running_deferred[d] = 1

        # start them all, the last one finished will install the language
        for d in running_deferred:
            d.start()

        return

    # install it if we got it the first time
    t.install(unicode)



def _gettext_install(domain, localedir=None, languages=None, unicode=False):
    # gettext on win32 does not use locale.getdefaultlocale() by default
    # other os's will fall through and gettext.find() will do this task
    if os_name == 'nt':
        # this code is straight out of gettext.find()
        if languages is None:
            languages = []
            for envar in ('LANGUAGE', 'LC_ALL', 'LC_MESSAGES', 'LANG'):
                val = os.environ.get(envar)
                if val:
                    languages = val.split(':')
                    break

            # this is the important addition - since win32 does not typically
            # have any enironment variable set, append the default locale before 'C'
            languages.append(locale.getdefaultlocale()[0])

            if 'C' not in languages:
                languages.append('C')

    # we call the smart version, because anyone calling this needs it
    # before they can continue. yes, we do block on network IO. there is no
    # alternative (installing post-startup causes already loaded strings not
    # to be re-loaded)
    blocking_smart_gettext_and_install(domain, localedir,
                                       languages=languages,
                                       unicode=unicode)


def read_language_file():
    """Reads the language file.  The language file contains the
       name of the selected language, not any translations."""
    lang = None

    if os.name == 'nt':
        # this pulls user-preference language from the installer location
        try:
            regko = _winreg.OpenKey(_winreg.HKEY_CURRENT_USER, "Software\\BitTorrent")
            lang_num = _winreg.QueryValueEx(regko, "Language")[0]
            lang_num = int(lang_num)
            lang = language.locale_sucks[lang_num]
        except:
            pass
    else:
        lang_file_name = language_path()
        if os.access(lang_file_name, os.F_OK|os.R_OK):
            mode = 'r'
            if sys.version_info >= (2, 3):
                mode = 'U'
            lang_file = open(lang_file_name, mode)
            lang_line = lang_file.readline()
            lang_file.close()
            if lang_line:
                lang = ''
                for i in lang_line[:5]:
                    if not i.isalpha() and i != '_':
                        break
                    lang += i
                if lang == '':
                    lang = None

    return lang

def write_language_file(lang):
    """Writes the language file.  The language file contains the
       name of the selected language, not any translations."""

    if lang != '': # system default
        get_language(lang)

    if os.name == 'nt':
        regko = _winreg.CreateKey(_winreg.HKEY_CURRENT_USER, "Software\\BitTorrent")
        if lang == '':
            _winreg.DeleteValue(regko, "Language")
        else:
            lcid = None

            # I want two-way dicts
            for id, code in language.locale_sucks.iteritems():
                if code.lower() == lang.lower():
                    lcid = id
                    break
            if not lcid:
                raise KeyError(lang)

            _winreg.SetValueEx(regko, "Language", 0, _winreg.REG_SZ, str(lcid))

    else:
        lang_file_name = language_path()
        lang_file = open(lang_file_name, 'w')
        lang_file.write(lang)
        lang_file.close()

def install_translation(unicode=False):
    languages = None
    try:
        lang = read_language_file()
        if lang is not None:
            languages = [lang, ]
    except:
        #pass
        traceback.print_exc()
    _gettext_install('bittorrent', locale_root, languages=languages, unicode=unicode)


def write_pid_file(fname, errorfunc = None):
    """Creates a pid file on platforms that typically create such files;
       otherwise, this returns without doing anything.  The fname should
       not include a path.  The file will be placed in the appropriate
       platform-specific directory (/var/run in linux).
       """
    assert type(fname) == str
    assert errorfunc == None or callable(errorfunc)

    if os.name == 'nt': return

    try:
        pid_fname = os.path.join(efs2(u'/var/run'),fname)
        file(pid_fname, 'w').write(str(os.getpid()))
    except:
        try:
            pid_fname = os.path.join(efs2(u'/etc/tmp'),fname)
        except:
            if errorfunc:
                errorfunc("Couldn't open pid file. Continuing without one.")
            else:
                pass  # just continue without reporting warning.

desktop = None

if os.name == 'nt':
    desktop = get_shell_dir(shellcon.CSIDL_DESKTOPDIRECTORY)
else:
    homedir = get_home_dir()
    if homedir == None :
        desktop = '/tmp/'
    else:
        desktop = homedir
        if os.name in ('mac', 'posix'):
            tmp_desktop = os.path.join(homedir, efs2(u'Desktop'))
            if os.access(tmp_desktop, os.R_OK|os.W_OK):
                desktop = tmp_desktop + os.sep



