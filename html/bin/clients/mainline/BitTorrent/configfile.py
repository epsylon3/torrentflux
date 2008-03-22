# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Uoti Urpala and Matt Chisholm

###########
# Needs redesign.  Adding an app requires modifying a lot of if's. Blech.
#     --Dave
############

import os
import sys
import traceback

from BitTorrent.translation import _

from ConfigParser import RawConfigParser
from ConfigParser import MissingSectionHeaderError, ParsingError
from BitTorrent import parseargs, version, BTFailure
from BTL.platform import app_name
from BTL.platform import encode_for_filesystem, decode_from_filesystem
from BitTorrent.platform import get_save_dir, locale_root, is_frozen_exe
from BitTorrent.platform import get_dot_dir, get_incomplete_data_dir
from BitTorrent.platform import enforce_shortcut, enforce_association
from BitTorrent.platform import smart_gettext_and_install
from BitTorrent.platform import get_old_incomplete_data_dir
from BitTorrent.platform import get_temp_subdir
from BitTorrent.platform import old_broken_config_subencoding
from BitTorrent.zurllib import bind_tracker_connection
from BTL.exceptions import str_exc
from BitTorrent.shortargs import convert_from_shortforms

downloader_save_options = [
    # General
    'confirm_quit'          ,

    # Appearance
    'progressbar_style'     ,
    'toolbar_text'          ,
    'toolbar_size'          ,

    # Bandwidth
    'max_upload_rate'       ,
    'max_download_rate'     ,

    # Saving
    'save_in'               ,
    'save_incomplete_in'    ,
    'ask_for_save'          ,

    # Network
    'minport'               ,
    'maxport'               ,
    'upnp'                  ,
    'ip'                    ,
    'resolve_hostnames'     ,

    # Misc
    'open_from'             ,
    'geometry'              ,
    'start_maximized'       ,
    'column_order'          ,
    'enabled_columns'       ,
    'column_widths'         ,
    'sort_column'           ,
    'sort_ascending'        ,
    'show_details'          ,
    'settings_tab'          ,
    'details_tab'           ,
    'splitter_height'       ,
    'theme'                 ,

    'donated'               ,
    'notified'              ,
    ]

if os.name == 'nt':
    downloader_save_options.extend([
        # General
        'enforce_association' ,
        'launch_on_startup'   ,
        'minimize_to_tray'    ,
        'start_minimized'     ,
        'close_to_tray'       ,

        # Bandwidth
        'bandwidth_management',
        'show_variance_line'  ,
        ])

MAIN_CONFIG_FILE = 'ui_config'
TORRENT_CONFIG_FILE = 'torrent_config'

alt_uiname = {'bittorrent':'btdownloadgui',
              'maketorrent':'btmaketorrentgui',}


def _read_config(filename):
    """Returns a RawConfigParser that has parsed the config file specified by
       the passed filename."""

    # check for bad config files
    p = RawConfigParser()
    fp = None
    try:
        fp = open(filename)
    except IOError:
        pass

    if fp is not None:
        try:
            p.readfp(fp, filename=filename)
        except MissingSectionHeaderError:
            fp.close()
            del fp
            bad_config(filename)
        except ParsingError:
            fp.close()
            del fp
            bad_config(filename)
        else:
            fp.close()

    return p


def _write_config(error_callback, filename, p):
    if not p.has_section('format'):
        p.add_section('format')
    p.set('format', 'encoding', 'utf-8')
    try:
        f = file(filename, 'wb')
        p.write(f)
        f.close()
    except Exception, e:
        try:
            f.close()
        except:
            pass
        error_callback(_("Could not permanently save options: ")+str_exc(e))


def bad_config(filename):
    base_bad_filename = filename + '.broken'
    bad_filename = base_bad_filename
    i = 0
    while os.access(bad_filename, os.F_OK):
        bad_filename = base_bad_filename + str(i)
        i+=1
    os.rename(filename, bad_filename)
    sys.stderr.write(_("Error reading config file. "
                       "Old config file stored in \"%s\"") % bad_filename)


def get_config(defaults, section):
    """This reads the key-value pairs from the specified section in the
       config file and from the common section.  It then places those
       appearing in the defaults into a dict, which is then returned.

       @type defaults: dict
       @param defaults: dict of name-value pairs derived from the
          defaults list for this application (see defaultargs.py).
          Only the names in the name-value pairs are used.  get_config
          only reads variables from the config file with matching names.
       @type section: str
       @param section: in the configuration from which to read options.
          So far, the sections have been named after applications, e.g.,
          bittorrent, bittorrent-console, etc.
       @return: a dict containing option-value pairs.
       """
    assert type(defaults)==dict
    assert type(section)==str

    configdir = get_dot_dir()

    if configdir is None:
        return {}

    if not os.path.isdir(configdir):
        try:
            os.mkdir(configdir, 0700)
        except:
            pass

    p = _read_config(os.path.join(configdir, 'config'))  # returns parser.

    if p.has_section('format'):
        encoding = p.get('format', 'encoding')
    else:
        encoding = old_broken_config_subencoding

    values = {}
    if p.has_section(section):
        for name, value in p.items(section):
            if name in defaults:
                values[name] = value
    if p.has_section('common'):
        for name, value in p.items('common'):
            if name in defaults and name not in values:
                values[name] = value
    if defaults.get('data_dir') == '' and \
           'data_dir' not in values and os.path.isdir(configdir):
        datadir = os.path.join(configdir, 'data')
        values['data_dir'] = decode_from_filesystem(datadir)

    parseargs.parse_options(defaults, values, encoding)
    return values


def save_global_config(defaults, section, error_callback,
                       save_options=downloader_save_options):
    filename = os.path.join(defaults['data_dir'], MAIN_CONFIG_FILE)
    p = _read_config(filename)
    p.remove_section(section)
    if p.has_section(alt_uiname[section]):
        p.remove_section(alt_uiname[section])
    p.add_section(section)
    for name in save_options:
        name.decode('ascii').encode('utf-8') # just to make sure we can
        if defaults.has_key(name):
            value = defaults[name]
            if isinstance(value, str):
                value = value.decode('ascii').encode('utf-8')
            elif isinstance(value, unicode):
                value = value.encode('utf-8')
            p.set(section, name, value)
        else:
            err_str = _("Configuration option mismatch: '%s'") % name
            if is_frozen_exe:
                err_str = _("You must quit %s and reinstall it. (%s)") % (app_name, err_str)
            error_callback(err_str)
    _write_config(error_callback, filename, p)


def save_torrent_config(path, infohash, config, error_callback):
    section = infohash.encode('hex')
    filename = os.path.join(path, TORRENT_CONFIG_FILE)
    p = _read_config(filename)
    p.remove_section(section)
    p.add_section(section)
    for key, value in config.items():
        p.set(section, key, value)
    _write_config(error_callback, filename, p)

def read_torrent_config(global_config, path, infohash, error_callback):
    section = infohash.encode('hex')
    filename = os.path.join(path, TORRENT_CONFIG_FILE)
    p = _read_config(filename)
    if not p.has_section(section):
        return {}
    else:
        c = {}
        for name, value in p.items(section):
            if global_config.has_key(name):
                t = type(global_config[name])
                if t == bool:
                    c[name] = value in ('1', 'True', True)
                else:
                    try:
                        c[name] = type(global_config[name])(value)
                    except ValueError, e:
                        error_callback('%s (name:%s value:%s type:%s global:%s)' %
                                       (str_exc(e), name, repr(value),
                                        type(global_config[name]), global_config[name]))
                        # is this reasonable?
                        c[name] = global_config[name]
            elif name == 'save_as':
                # Backwards compatibility for BitTorrent 4.4 torrent_config file
                c[name] = value
            try:
                c[name] = c[name].decode('utf-8')
            except:
                pass
        return c

def remove_torrent_config(path, infohash, error_callback):
    section = infohash.encode('hex')
    filename = os.path.join(path, TORRENT_CONFIG_FILE)
    p = _read_config(filename)
    if p.has_section(section):
        p.remove_section(section)
    _write_config(error_callback, filename, p)

def parse_configuration_and_args(defaults, uiname, arglist=[], minargs=None,
                                 maxargs=None):
    """Given the default option settings and overrides these defaults
       from values read from the config file, and again overrides the
       config file with the arguments that appear in the arglist.

       'defaults' is a list of tuples of the form (optname, value,
       desc) where 'optname' is a string containing the option's name,
       value is the option's default value, and desc is the option's
       description.

       'uiname' is a string specifying the user interface that has been
       created by the caller.  Ex: bittorrent, maketorrent.

       arglist is usually argv[1:], i.e., excluding the name used to
       execute the program.

       minargs specifies the minimum number of arguments that must appear in
       arglist.  If the number of arguments is less than the minimum then
       a BTFailure exception is raised.

       maxargs specifies the maximum number of arguments that can appear
       in arglist.  If the number of arguments exceeds the maximum then
       a BTFailure exception is raised.

       This returns the tuple (config,args) where config is
       a dictionary of (option, value) pairs, and args is the list
       of arguments in arglist after the command-line arguments have
       been removed.

       For example:

          bittorrent-curses.py --save_as lx-2.6.rpm lx-2.6.rpm.torrent --max_upload_rate 0

          returns a (config,args) pair where the
          config dictionary contains many defaults plus
          the mappings
            'save_as': 'linux-2.6.15.tar.gz'
          and
            'max_upload_rate': 0

          The args in the returned pair is
            args= ['linux-2.6.15.tar.gz.torrent']
    """
    assert type(defaults)==list
    assert type(uiname)==str
    assert type(arglist)==list
    assert minargs is None or type(minargs) in (int,long) and minargs>=0
    assert maxargs is None or type(maxargs) in (int,long) and maxargs>=minargs

    # remap shortform arguments to their long-forms.
    arglist = convert_from_shortforms(arglist)

    defconfig = dict([(name, value) for (name, value, doc) in defaults])
    if arglist[0:] == ['--version']:
        print version
        sys.exit(0)

    if arglist[0:] == '--help':
        parseargs.printHelp(uiname, defaults)
        sys.exit(0)

    if "--use_factory_defaults" not in arglist:
        presets = get_config(defconfig, uiname)  # read from .bittorrent dir.

    # run as if fresh install using temporary directories.
    else:
        presets = {}
        temp_dir = get_temp_subdir()
        #set_config_dir(temp_dir)  # is already set in platform.py.
        save_in = encode_for_filesystem( u"save_in" )[0]
        presets["save_in"] = \
            decode_from_filesystem(os.path.join(temp_dir,save_in))
        data = encode_for_filesystem( u"data" )[0]
        presets["data_dir"] = \
            decode_from_filesystem(os.path.join(temp_dir,data))
        incomplete = encode_for_filesystem( u"incomplete" )[0]
        presets["save_incomplete_in"] = \
            decode_from_filesystem(os.path.join(temp_dir,incomplete))
        presets["one_connection_per_ip"] = False

    config = args = None
    try:
        config, args = parseargs.parseargs(arglist, defaults, minargs, maxargs,
                                           presets)
    except parseargs.UsageException, e:
        print e
        parseargs.printHelp(uiname, defaults)
        sys.exit(0)

    datadir = config.get('data_dir')
    found_4x_config = False

    if datadir:
        datadir,bad = encode_for_filesystem(datadir)
        if bad:
            raise BTFailure(_("Invalid path encoding."))
        if not os.path.exists(datadir):
            os.mkdir(datadir)
        if uiname in ('bittorrent', 'maketorrent'):
            values = {}

            p = _read_config(os.path.join(datadir, MAIN_CONFIG_FILE))

            if p.has_section('format'):
                encoding = p.get('format', 'encoding')
            else:
                encoding = old_broken_config_subencoding

            if not p.has_section(uiname) and p.has_section(alt_uiname[uiname]):
                uiname = alt_uiname[uiname]
            if p.has_section(uiname):
                for name, value in p.items(uiname):
                    if name in defconfig:
                        values[name] = value
                    elif not found_4x_config:
                        # identify 4.x version config file
                        if name in ('start_torrent_behavior',
                                    'seed_forever',
                                    'progressbar_hack',
                                    'seed_last_forever',
                                    'next_torrent_ratio',
                                    'next_torrent_time',
                                    'last_torrent_ratio',
                                    ):
                            found_4x_config = True
            parseargs.parse_options(defconfig, values, encoding)
            presets.update(values)
            config, args = parseargs.parseargs(arglist, defaults, minargs,
                                               maxargs, presets)

        for d in ('', 'resume', 'metainfo', 'torrents'):
            ddir = os.path.join(datadir, d)
            if not os.path.exists(ddir):
                os.mkdir(ddir, 0700)
            else:
                assert(os.path.isdir(ddir))

    if found_4x_config:
        # version 4.x stored KB/s, < version 4.x stores B/s
        config['max_upload_rate'] *= 1024

    if config.get('language'):
        # this is non-blocking if the language does not exist
        smart_gettext_and_install('bittorrent', locale_root,
                                  languages=[config['language']])

    if config.has_key('bind') and config['bind'] != '':
        bind_tracker_connection(config['bind'])

    if config.has_key('launch_on_startup'):
        enforce_shortcut(config, log_func=sys.stderr.write)

    if os.name == 'nt' and config.has_key('enforce_association'):
        enforce_association()

    if config.has_key('save_in') and config['save_in'] == '' and \
       (not config.has_key("save_as") or config['save_as'] == '' ) \
       and uiname != 'bittorrent':
        config['save_in'] = decode_from_filesystem(get_save_dir())

    incomplete = decode_from_filesystem(get_incomplete_data_dir())
    if config.get('save_incomplete_in') == '':
        config['save_incomplete_in'] = incomplete
    if config.get('save_incomplete_in') == get_old_incomplete_data_dir():
        config['save_incomplete_in'] = incomplete

    if uiname == "test-client" or (uiname.startswith("bittorrent")
                                   and uiname != 'bittorrent-tracker'):
        if not config.get('ask_for_save'):
            # we check for existance, so things like "D:\" don't trip us up.
            if (config['save_in'] and
                not os.path.exists(config['save_in'])):
                try:
                    os.makedirs(config['save_in'])
                except OSError, e:
                    if (e.errno == 2 or # no such file or directory
                        e.errno == 13): # permission denied
                        traceback.print_exc()
                        print >> sys.stderr, "save_in could not be created. Falling back to prompting."
                        config['ask_for_save'] = True
                    elif e.errno != 17: # path already exists
                        raise
            if (config['save_incomplete_in'] and
                not os.path.exists(config['save_incomplete_in'])):
                try:
                    os.makedirs(config['save_incomplete_in'])
                except OSError, e:
                    if e.errno != 17: # path already exists
                        traceback.print_exc()
                        print >> sys.stderr, "save_incomplete_in could not be created. Falling back to default incomplete path."
                        config['save_incomplete_in'] = incomplete
                
    return config, args
