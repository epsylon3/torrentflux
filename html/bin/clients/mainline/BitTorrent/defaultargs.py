# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.


# Needs redesign.  Many if's on uiname.  Blech. --Dave

import os
import sys
from BTL.translation import _

### add your favorite here
BAD_LIBC_WORKAROUND_DEFAULT = False
if os.name == 'posix':
    if os.uname()[0] in ['Darwin', 'FreeBSD']:
        BAD_LIBC_WORKAROUND_DEFAULT = True

MAX_INCOMPLETE = 100
MAX_FILES_OPEN = 50
if os.name == 'nt':
    import ctypes
    from BitTorrent.platform import win_version_num
    # starting in XP SP2 the incomplete outgoing connection limit was set to 10
    if win_version_num >= (2, 5, 1, 2, 0):
        MAX_INCOMPLETE = 10

    # try to set it as high as possible
    # technically 2048 is max, but I see 512 sometimes, and I think win98
    # defaults to 50. If we're not the last person to call it, I think we get
    # errors, so screw it for now.
    #ctypes.cdll.msvcrt._setmaxstdio(512)
    # -3 for stdin, stdout, and stderr
    # -15 for a buffer
    MAX_FILES_OPEN = ctypes.cdll.msvcrt._getmaxstdio() - 3 - 15

if os.name == 'nt':
    CONFIRM_QUIT_DEFAULT = True
else:
    CONFIRM_QUIT_DEFAULT = False

from BTL.language import languages
from BTL.platform import app_name


basic_options = [
    ('data_dir', u'',
     _("directory under which variable data such as fastresume information "
       "and GUI state is saved. Defaults to subdirectory 'data' of the "
       "bittorrent config directory.")),
    ('language', '',
     _("ISO Language code to use") + ': ' + ', '.join(languages)),
    ('use_factory_defaults', False,
     _("Starts the application in a debug mode.  All settings revert to "
       "default except those provided as command-line options.  Creates "
       "temporary directories for dot, data, incomplete torrents and "
       "complete torrents.  Allows multiple clients on the same machine to "
       "communicate with each other." )),
    ('tf_owner', '',
     _("The tf-user to run the torrent as." )),
    ('seed_limit', '0',
     _("Die when ratio reaches this amount." )),
    ('die_when_done', 'False',
     _("Die when the torrent is finished. Please seed your Torrents !" )),
    ]

common_options = [
    ('ip', '',
     _("ip to report to the tracker (has no effect unless you are on the same "
       "local network as the tracker)")),
    ('forwarded_port', 0,
     _("world-visible port number if it's different from the one the client "
       "listens on locally")),
    ('minport', 6881,
     _("minimum port to listen on, counts up if unavailable")),
    ('maxport', 6999,
     _("maximum port to listen on")),
    ('bind', '',
     _("ip to bind to locally")),
    ('display_interval', 1.0,
     _("seconds between updates of displayed information")),
    ('rerequest_interval', 5 * 60,
     _("minutes to wait between requesting more peers")),
    ('min_peers', 40,
     _("minimum number of peers to not do rerequesting")),
    ('max_initiate', 200,
     _("number of peers at which to stop initiating new connections")),
    ('max_incomplete', MAX_INCOMPLETE,
     _("max number of outgoing incomplete connections")),
    ('max_allow_in', 80,
     _("maximum number of connections to allow, after this new incoming "
       "connections will be immediately closed")),
    ('check_hashes', True,
     _("whether to check hashes on disk")),
    ('max_upload_rate', 125000000, # 1 GBit local net = 125MB/s
     _("maximum B/s to upload at")),
    ('max_download_rate', 125000000, # 1 GBit local net = 125MB/s
     _("average maximum B/s to download at")),
    ("download_rate_limiter_interval", 0.25,
    _("download rate limiter's leaky bucket update interval.")),
    ('bandwidth_management', os.name == 'nt',
     _("automatic bandwidth management (Windows only)")),
    ('min_uploads', 2,
     _("the number of uploads to fill out to with extra optimistic unchokes")),
    ('max_files_open', MAX_FILES_OPEN,
     _("the maximum number of files in a multifile torrent to keep open at a "
       "time, 0 means no limit. Used to avoid running out of file descriptors.")),
    ('start_trackerless_client', True,
     _("Initialize a trackerless client.  This must be enabled in order to download trackerless torrents.")),
    ('upnp', False,
     _("Enable automatic port mapping")+' (UPnP)'),
    ('resolve_hostnames', True,
     _("Resolve hostnames in peer list")),
    ("use_local_discovery", True, _("Scan local network for other BitTorrent clients "
                                    "with the desired content.")),
    # Not currently used.
    #('xmlrpc_port', -1,
    #_("Start the XMLRPC interface on the specified port. This "
    #  "XML-RPC-based RPC allows a remote program to control the client "
    #  "to enable automated hosting, conformance testing, and benchmarking.")),
    ]

# In anticipation of running a large number of tests for tuning, I thought it
# worthwhile to make these values settable.  We will probably
# remove these options later, because we won't want users
# messing with them.  --Dave
bandwidth_management_options = [
    ('xicmp_port', 19669, _("port number upon which xicmp should sit.")),
    ('congestion_estimator', 'variance', #'chebyshev',
     "method for estimating congestion levels."),
    ('control_law', 'aimd', # allowed values = [aimd, aiad]
     "method for adjusting rates." ),
    #('propagation_estimator', 'median_of_window',
    # "method for estimating the round-trip propagation delay."),
    #('delay_on_full_estimator', 'median_of_window',
    # "method for estimating the round-trip time when the bottleneck "
    # "buffer is full (for bandwidth management)."),
    #('rtt_estimator', 'average_of_window',
    # # allowed values = [average_of_window, median_of_window, ewma]
    # 'method for estimating ping round-trip time'),
    ('increase_delta', 1000, "additive increase in bytes per second."),
    ('decrease_delta', 1000,
     "additive decrease in bytes per second (for aiad only)."),
    ('decrease_factor', 0.8, "multiplicative decrease (for aimd only)."),
    ('window_size', 10, "window used in averaging round-trip times"),
    ('ewma', 0.1, "averaging weight, smaller is slower convention."),
    ('cheby_max_consecutive', 10,
     ("maximum number of consecutive samples above the threshold before "
      "signalling congestion.")),
    ('cheby_max_threshold', 0.9,
     ("maximum delay threshold expressed as fraction of the distance between "
      "propagation delay and buffer-full delay.")),
    ('cheby_false_positive_probability', 0.05,
     "target upper bound on the probability of a false positive."),
    ('min_upload_rate_limit', 10000,
     "minimum upload rate limit to prevent starvation of BitTorrent traffic."),
    ]

rare_options = [
    ('keepalive_interval', 120.0,
     _("number of seconds to pause between sending keepalives")),
    ('pex_interval', 60.0,
     _("number of seconds to pause between sending peer exchange messages")),
    ('download_chunk_size', 2 ** 14,
     _("how many bytes to query for per request.")),
    ('max_message_length', 2 ** 23,
     _("maximum length prefix encoding you'll accept over the wire - larger "
       "values get the connection dropped.")),
    ('socket_timeout', 300.0,
     _("seconds to wait between closing sockets which nothing has been "
       "received on")),
    ('max_chunk_length', 32768,
     _("maximum length chunk to send to peers, close connection if a larger "
       "request is received")),
    ('max_rate_period', 20.0,
     _("maximum time interval over which to estimate the current upload and download rates")),
    ('max_announce_retry_interval', 1800,
     _("maximum time to wait between retrying announces if they keep failing")),
    ('snub_time', 30.0,
     _("seconds to wait for data to come in over a connection before assuming "
       "it's semi-permanently choked")),
    ('rarest_first_cutoff', 4,
     _("number of downloads at which to switch from random to rarest first")),
    ('upload_unit_size', 1380,
     _("how many bytes to write into network buffers at once.")),
    ('retaliate_to_garbled_data', True,
     _("refuse further connections from addresses with broken or intentionally "
       "hostile peers that send incorrect data")),
    ('one_connection_per_ip', True,
     _("do not connect to several peers that have the same IP address")),
    ('one_download_per_torrent', True,
     _("do not allow simultaneous downloads of the same torrent.")),
    ('peer_socket_tos', 8,
     _("if nonzero, set the TOS option for peer connections to this value")),
    ('bad_libc_workaround', BAD_LIBC_WORKAROUND_DEFAULT,
     _("enable workaround for a bug in BSD libc that makes file reads very slow.")),
    ('tracker_proxy', '',
     _("address of HTTP proxy to use for tracker connections")),
    ('close_with_rst', 0,
     _("close connections with RST and avoid the TCP TIME_WAIT state")),
    ('num_disk_threads', 3,
     _("number of read threads to use in the storage object")),
    ('num_piece_checks', 2,
     _("number of simultaneous piece checks to run per torrent, set to a low number like 2 or 3")),
    ('num_fast', 10,
     _("Number of pieces allowed fast.")),
    ('show_hidden_torrents', False,
     _("Show hidden torrents in the UI.")),
    ('show_variance_line', False,
     _("Show variance line in bandwidth graph.")),
    # Future.
    #('stream_priority', 2,
    # _("Priority for pieces that are needed soon.")),
    ]


tracker_options = [
    ('port', 80,
     _("Port to listen on.")),
    ('dfile', u'',
     _("file to store recent downloader info in")),
    ('bind', '',
     _("ip to bind to locally")),
    ('socket_timeout', 15,
     _("timeout for closing connections")),
    ('close_with_rst', 0,
     _("close connections with RST and avoid the TCP TIME_WAIT state")),
    ('save_dfile_interval', 5 * 60,
     _("seconds between saving dfile")),
    ('timeout_downloaders_interval', 45 * 60,
     _("seconds between expiring downloaders")),
    ('reannounce_interval', 30 * 60,
     _("seconds downloaders should wait between reannouncements")),
    ('response_size', 50,
     _("default number of peers to send an info message to if the "
       "client does not specify a number")),
    ('nat_check', 3,
     _("how many times to check if a downloader is behind a NAT "
       "(0 = don't check)")),
    ('log_nat_checks', 0,
     _("whether to add entries to the log for nat-check results")),
    ('min_time_between_log_flushes', 3.0,
     _("minimum time it must have been since the last flush to do "
       "another one")),
    ('min_time_between_cache_refreshes', 600.0,
     _("minimum time in seconds before a cache is considered stale "
       "and is flushed")),
    ('allowed_dir', u'',
     _("only allow downloads for .torrents in this dir (and recursively in "
       "subdirectories of directories that have no .torrent files "
       "themselves). If set, torrents in this directory show up on "
       "infopage/scrape whether they have peers or not")),
    ('parse_dir_interval', 60,
     _("how often to rescan the torrent directory, in seconds")),
    ('allowed_controls', 0,
     _("allow special keys in torrents in the allowed_dir to affect "
       "tracker access")),
    ('hupmonitor', 0,
     _("whether to reopen the log file upon receipt of HUP signal")),
    ('show_infopage', 1,
     _("whether to display an info page when the tracker's root dir "
       "is loaded")),
    ('infopage_redirect', '',
     _("a URL to redirect the info page to")),
    ('show_names', 1,
     _("whether to display names from allowed dir")),
    ('favicon', '',
     _("file containing x-icon data to return when browser requests "
       "favicon.ico")),
    ('only_local_override_ip', 2,
     _("ignore the ip GET parameter from machines which aren't on "
       "local network IPs (0 = never, 1 = always, 2 = ignore if NAT "
       "checking is not enabled). HTTP proxy headers giving address "
       "of original client are treated the same as --ip.")),
    ('logfile', '',
     _("file to write the tracker logs, use - for stdout (default)")),
    ('allow_get', 0,
     _("use with allowed_dir; adds a /file?hash={hash} url that "
       "allows users to download the torrent file")),
    ('keep_dead', 0,
     _("keep dead torrents after they expire (so they still show up on your "
       "/scrape and web page). Only matters if allowed_dir is not set")),
    ('scrape_allowed', 'full',
     _("scrape access allowed (can be none, specific or full)")),
    ('max_give', 200,
     _("maximum number of peers to give with any one request")),
    ('twisted', -1,
     _("Use Twisted network libraries for network connections. 1 means use twisted, 0 means do not use twisted, -1 means autodetect, and prefer twisted")),
    ('pid', 'bittorrent-tracker.pid',
     "Path to PID file"),
    ('max_incomplete', MAX_INCOMPLETE,
     _("max number of outgoing incomplete connections")),
    ]


def get_defaults(ui):
    assert ui in ("bittorrent" , "bittorrent-curses", "bittorrent-console" ,
                  "maketorrent",                      "maketorrent-console",
                                 "launchmany-curses", "launchmany-console" ,
                                                      "bittorrent-tracker" ,
                  )
    r = []

    if ui == "bittorrent-tracker":
        r.extend(tracker_options)
    elif ui.startswith('bittorrent') or ui.startswith('launchmany'):
        r.extend(common_options)

    if ui == 'bittorrent':
        r.extend([
            ('publish', '',
             _("path to the file that you are publishing (seeding).")),
            ('verbose', False,
             _("display verbose information in user interface")),
            ('debug', False,
             _("provide debugging tools in user interface")),
            ('pause', False,
             _("start downloader in paused state")),
            ('open_from', u'',
             'local directory to look in for .torrent files to open'),
            ('start_minimized', False,
             _("Start %s minimized")%app_name),
            ('force_start_minimized', False,
             _("Start %s minimized (but do not save that preference)")%app_name),
            ('confirm_quit', CONFIRM_QUIT_DEFAULT,
             _("Confirm before quitting %s")%app_name),
            ('new_version', '',
             _("override the version provided by the http version check "
               "and enable version check debugging mode")),
            ('current_version', '',
             _("override the current version used in the version check "
               "and enable version check debugging mode")),

            # remember GUI state
            ('geometry', '',
             _("specify window size and position, in the format: "
               "WIDTHxHEIGHT+XOFFSET+YOFFSET")),
            ('start_maximized', False,
             _("Start %s maximized")%app_name),
            ('column_widths', {},
             _("Widths of columns in torrent list in main window")),
            ('column_order', ['name', 'progress', 'eta', 'drate',
                              'urate', 'peers', 'priority', 'state'],
             _("Order of columns in torrent list in main window")),
            ('enabled_columns', ['name', 'progress', 'eta', 'drate',
                                 'priority'],
             _("Enabled columns in torrent list in main window")),
            ('sort_column', 'name',
             _("Default sort column in torrent list in main window")),
            ('sort_ascending', True,
             _("Default sort order in torrent list in main window")),
            ('toolbar_text', True,
             _("Whether to show text on the toolbar or not")),
            ('toolbar_size', 24,
             _("Size in pixels of toolbar icons")),
            ('show_details', False,
             _("Show details panel on startup")),
            ('settings_tab', 0,
             _("Which tab in the settings window to show by default")),
            ('details_tab', 0,
             _("Which tab in the details panel to show by default")),
            ('splitter_height', 300,
             _("Height of the details splitter when it is enabled")),
            ('ask_for_save', True,
             _("whether or not to ask for a location to save downloaded "
               "files in")),
            ('max_upload_rate', 40960, # 40KB/s up
             _("maximum B/s to upload at")),
            ])

        if os.name == 'nt':
            r.extend([
                ('launch_on_startup', True,
                 _("Launch %s when Windows starts") % app_name),
                ('minimize_to_tray', True,
                 _("Minimize to the system tray")),
                ('close_to_tray', True,
                 _("Close to the system tray")),
                ('enforce_association', True,
                 _("Enforce .torrent file associations on startup")),
            ])

        progress_bar = ['progressbar_style', 3,
                        _("The style of progressbar to show.  0 means no progress "
                          "bar.  1 is an ordinary progress bar.  2 is a progress "
                          "bar that shows transferring, available and missing "
                          "percentages as well.  3 is a piece bar which "
                          "color-codes each piece in the torrent based on its "
                          "availability.")]

        if sys.platform == "darwin":
            # listctrl placement of the progress bars does not work on Carbon
            progress_bar[1] = 0

        r.extend([ progress_bar,
                   ])


    if ui in ('bittorrent', 'maketorrent'):
        r.append(
            ('theme', 'default',
             _("Icon theme to use"))
            )

    if ui.startswith('bittorrent') and ui != "bittorrent-tracker":
        r.extend([
            ('max_uploads', -1,
             _("the maximum number of uploads to allow at once. -1 means a "
               "(hopefully) reasonable number based on --max_upload_rate. "
               "The automatic values are only sensible when running one "
               "torrent at a time.")),
            ('save_in', u'',
             _("local directory where the torrent contents will be saved. The "
               "file (single-file torrents) or directory (batch torrents) will "
               "be created under this directory using the default name "
               "specified in the .torrent file. See also --save_as.")),
            ('save_incomplete_in', u'',
             _("local directory where the incomplete torrent downloads will be "
               "stored until completion.  Upon completion, downloads will be "
               "moved to the directory specified by --save_in.")),
            ])
        r.extend(bandwidth_management_options)

    if ui.startswith('launchmany'):
        r.extend([
            ('max_uploads', 6,
             _("the maximum number of uploads to allow at once. -1 means a "
               "(hopefully) reasonable number based on --max_upload_rate. The "
               "automatic values are only sensible when running one torrent at "
               "a time.")),
            ('save_in', u'',
             _("local directory where the torrents will be saved, using a "
               "name determined by --saveas_style. If this is left empty "
               "each torrent will be saved under the directory of the "
               "corresponding .torrent file")),
            ('save_incomplete_in', u'',
             _("local directory where the incomplete torrent downloads will be "
               "stored until completion.  Upon completion, downloads will be "
               "moved to the directory specified by --save_in.")),
            ('parse_dir_interval', 60,
              _("how often to rescan the torrent directory, in seconds") ),
            ('launch_delay', 0,
             _("wait this many seconds after noticing a torrent before starting it, to avoid race with tracker")),
            ('saveas_style', 4,
              _("How to name torrent downloads: "
                "1: use name OF torrent file (minus .torrent);  "
                "2: use name encoded IN torrent file;  "
                "3: create a directory with name OF torrent file "
                "(minus .torrent) and save in that directory using name "
                "encoded IN torrent file;  "
                "4: if name OF torrent file (minus .torrent) and name "
                "encoded IN torrent file are identical, use that "
                "name (style 1/2), otherwise create an intermediate "
                "directory as in style 3;  "
                "CAUTION: options 1 and 2 have the ability to "
                "overwrite files without warning and may present "
                "security issues."
                ) ),
            ('display_path', ui == 'launchmany-console' and True or False,
              _("whether to display the full path or the torrent contents for "
                "each torrent") ),
            ])

    if ui.startswith('launchmany') or ui == 'maketorrent':
        r.append(
            ('torrent_dir', u'',
             _("directory to look for .torrent files (semi-recursive)")),)
    if ui.startswith('maketorrent'):
        r.append(
            ('content_type', '',_("file's default mime type.")))
            # HEREDAVE batch torrents must be handled differently.

    if ui in ('bittorrent-curses', 'bittorrent-console'):
        r.extend([
            ('save_as', u'',
             _("file name (for single-file torrents) or directory name (for "
               "batch torrents) to save the torrent as, overriding the "
               "default name in the torrent. See also --save_in")),
            ('spew', False,
             _("whether to display diagnostic info to stdout")),])

    if ui == 'bittorrent-console' :
        r.extend([
            ('display_interval', 1,
            _("seconds between updates of displayed information")),
            ] )
    elif ui.startswith('launchmany-console'):
        r.extend([
            ('display_interval', 60,
            _("seconds between updates of displayed information")),
            ] )
    elif ui.startswith('launchmany-curses'):
        r.extend([
            ('display_interval', 3,
            _("seconds between updates of displayed information")),
            ] )

    if ui.startswith('maketorrent'):
        r.extend([
            ('title', '',
             _("optional human-readable title for entire .torrent")),
            ('comment', '',
             _("optional human-readable comment to put in .torrent")),
            ('piece_size_pow2', 0,
             _("which power of two to set the piece size to, "
               "0 means pick a good piece size")),
            ('tracker_name', '',
             _("default tracker name")),
            ('tracker_list', '', ''),
            ('use_tracker', True,
             _("if false then make a trackerless torrent, instead of "
               "announce URL, use reliable node in form of <ip>:<port> or an "
               "empty string to pull some nodes from your routing table")),
            ('verbose', False,
             _("display verbose information in user interface")),
            ('debug', False,
             _("provide debugging tools in user interface")),
            ])

    r.extend(basic_options)

    if (ui.startswith('bittorrent') or ui.startswith('launchmany')) \
           and ui != "bittorrent-tracker":
        r.extend(rare_options)

    return r
