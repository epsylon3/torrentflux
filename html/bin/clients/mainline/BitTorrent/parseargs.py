# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by Bill Bumgarner and Bram Cohen

# Dave's comments:
# makeHelp has no less than 4 elif's based on uiname. Doh.
#
# I like the function parseargs.  It makes no UI-specific assumptions.


from types import *
from cStringIO import StringIO

from BTL.translation import _

from BTL.obsoletepythonsupport import *

from BitTorrent import BTFailure
from BTL.bencode import bdecode
from BitTorrent.platform import is_frozen_exe
from BTL.exceptions import str_exc


class UsageException(BTFailure):
    pass

def makeHelp(uiname, defaults):
    ret = u''
    ret += (_("Usage: %s ") % uiname)
    if uiname.startswith('launchmany'):
        ret += _("[OPTIONS] [TORRENTDIRECTORY]\n\n")
        ret += _("If a non-option argument is present it's taken as the value\n"
                 "of the torrent_dir option.\n")
    elif uiname == 'bittorrent-tracker' or uiname == 'test-client':
        ret += _("OPTIONS")
    elif uiname == 'bittorrent':
        ret += _("[OPTIONS] [TORRENTFILES]\n")
    elif uiname.startswith('bittorrent'):
        ret += _("[OPTIONS] [TORRENTFILE]\n")
    elif uiname.startswith('maketorrent'):
        ret += _("[OPTION] TRACKER_URL FILE [FILE]\n")
    ret += '\n'
    ret += _("arguments are -\n") + formatDefinitions(defaults, 80)
    return ret

def printHelp(uiname, defaults):
    print makeHelp(uiname, defaults)

def formatDefinitions(options, COLS):
    s = u''
    indent = u" " * 10
    width = COLS - 11

    if width < 15:
        width = COLS - 2
        indent = " "

    for option in options:
        (longname, default, doc) = option
        if doc == '':
            continue
        s += u'--' + longname
        is_boolean = type(default) is bool
        if is_boolean:
            s += u', --no_' + longname
        else:
            s += u' <arg>'
        s += u'\n'
        if default is not None:
            doc += _(u" (defaults to ") + repr(default) + u')'
        i = 0
        for word in doc.split():
            if i == 0:
                s += indent + word
                i = len(word)
            elif i + len(word) >= width:
                s += u'\n' + indent + word
                i = len(word)
            else:
                s += u' ' + word
                i += len(word) + 1
        s += u'\n\n'
    return s

def usage(str):
    raise UsageException(str)

def format_key(key):
    if len(key) == 1:
        return '-%s'%key
    else:
        return '--%s'%key

          
def parseargs(argv, defaults, minargs=None, maxargs=None, presets=None ):
    """This function parses command-line arguments and uses them to override
       the presets which in turn override the defaults (see defaultargs.py).
       As currently used, the presets come from a config file (see
       configfile.py).

       Options have the form:
          --option value
       where the word option is replaced with the option name, etc.

       If a string or number appears on the line without being preceeded
       by a --option, then the string or number is an argument.

       @param argv: command-line arguments. Command-line options override
          defaults and presets.
       @param defaults: list of (optionname,value,documentation) 3-tuples.
       @param minargs: minimum number of arguments in argv.
       @param maxargs: maximum number of arguments in argv.
       @param presets: a dict containing option-value pairs.  Presets
          typically come from a config file.  Presets override defaults.
       @return: the pair (config,args) where config is a dict containing
          option-value pairs, and args is a list of the arguments in the
          order they appeared in argv.
       """
    assert type(argv)==list
    assert type(defaults)==list
    assert minargs is None or type(minargs) in (int,long) and minargs>=0
    assert maxargs is None or type(maxargs) in (int,long) and maxargs>=minargs
    assert presets is None or type(presets)==dict
        
    config = {}
    for option in defaults:
        longname, default, doc = option
        config[longname] = default
    args = []
    pos = 0
    if presets is None:
        presets = {}
    else:
        presets = presets.copy()
    while pos < len(argv):
        if argv[pos][:1] != '-':             # not a cmdline option
            args.append(argv[pos])
            pos += 1
        else:
            key, value = None, None
            if argv[pos].startswith('--'):        # --aaa 1
                if argv[pos].startswith('--no_'):
                    key = argv[pos][5:]
                    boolval = False
                else:
                    key = argv[pos][2:]
                    boolval = True
                if key not in config:
                    raise UsageException(_("unknown option ") + format_key(key))
                if type(config[key]) is bool: # boolean cmd line switch, no value
                    value = boolval
                    pos += 1
                else: # --argument value
                    if pos == len(argv) - 1:
                        usage(_("parameter passed in at end with no value"))
                    key, value = argv[pos][2:], argv[pos+1]
                    pos += 2
            elif argv[pos][:1] == '-':
                key = argv[pos][1:2]
                if len(argv[pos]) > 2:       # -a1
                    value = argv[pos][2:]
                    pos += 1
                else:                        # -a 1
                    if pos == len(argv) - 1:
                        usage(_("parameter passed in at end with no value"))
                    value = argv[pos+1]
                    pos += 2
            else:
                raise UsageException(_("command line parsing failed at ")+argv[pos])

            presets[key] = value
    parse_options(config, presets, None)
    config.update(presets)

    # if a key appears in the config with a None value then this is because
    # the key appears in the defaults with a None value and the value was
    # not provided by the user.  keys appearing in defaults with a none
    # value are REQUIRED arguments.
    for key, value in config.items():
        if value is None:
            usage(_("Option %s is required.") % format_key(key))
    if minargs is not None and len(args) < minargs:
        usage(_("Must supply at least %d arguments.") % minargs)
    if maxargs is not None and len(args) > maxargs:
        usage(_("Too many arguments - %d maximum.") % maxargs)

    return (config, args)

def parse_options(defaults, newvalues, encoding):
    """Given the type provided by the default value, this tries to cast/convert
       the corresponding newvalue to the type of the default value.
       By calling eval() on it, in some cases!

       Entertainly, newvalue sometimes holds strings and, apparently,
       sometimes holds values which have already been cast appropriately.

       This function is like a boat made of shit, floating on a river of shit.

       @param defaults: dict of key-value pairs where value is the default.
       @param newvalues: dict of key-value pairs which override the default.
       """
    assert type(defaults) == dict
    assert type(newvalues) == dict
    for key, value in newvalues.iteritems():
        if not defaults.has_key(key):
            raise UsageException(_("unknown option ") + format_key(key))
        try:
            t = type(defaults[key])
            if t is bool:
                if value in ('True', '1', True):
                    value = True
                else:
                    value = False
                newvalues[key] = value
            elif t in (StringType, NoneType):
                # force ASCII
                newvalues[key] = value.decode('ascii').encode('ascii')
            elif t in (IntType, LongType):
                if value == 'False':
                    newvalues[key] == 0
                elif value == 'True':
                    newvalues[key] == 1
                else:
                    newvalues[key] = int(value)
            elif t is FloatType:
                newvalues[key] = float(value)
            elif t in (ListType, TupleType, DictType):
                if type(value) == StringType:
                    try:
                        n = eval(value)
                        assert type(n) == t
                        newvalues[key] = n
                    except:
                        newvalues[key] = t()
            elif t is UnicodeType:
                if type(value) == StringType:
                    try:
                        newvalues[key] = value.decode(encoding)
                    except:
                        newvalues[key] = value.decode('ascii')
            else:
                raise TypeError, str(t)

        except ValueError, e:
            raise UsageException(_("wrong format of %s - %s") % (format_key(key), str_exc(e)))

