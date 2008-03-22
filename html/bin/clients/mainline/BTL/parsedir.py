# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# Written by John Hoffman and Uoti Urpala and David Harrison

import os
import sys #DEBUG
from BTL.translation import _
from BTL.hash import sha

from BTL.bencode import bencode, bdecode
from BTL.btformats import check_message
from BTL.ConvertedMetainfo import ConvertedMetainfo
from BTL.defer import defer_to_thread, wrap_task
from BTL.coro import coroutine
from twisted.internet import reactor

def dtt(f, *a, **k):
    return defer_to_thread(reactor.callFromThread, reactor.callInThread, f, *a, **k )
import logging
log = logging.getLogger("BTL.parsedir")

def like_gettorrent(path):
    data = open(path, 'rb').read()
    b = bdecode(data)
    metainfo = ConvertedMetainfo(b)
    return metainfo



NOISY = False

def parsedir(directory, parsed, files, blocked, errfunc,
             include_metainfo=True):
    """Recurses breadth-first starting from the passed 'directory'
       looking for .torrrent files.

       THIS IS BLOCKING. Run this in a thread if you don't want it to block
       the program.  Or better yet, use async_parsedir.

       The directory, parsed, files, and blocked arguments are passed
       from the previous iteration of parsedir.

       @param directory: root of the breadth-first search for .torrent files.
       @param parsed: dict mapping infohash to (path,ConvertedMetainfo).
       @param files: dict mapping path -> [(modification time, size), infohash]
       @param blocked: dict used as set.  keys are list of paths of files
          that were not parsed on a prior call to parsedir for some reason.
          Valid reasons are that the .torrent file is unparseable or that a
          torrent with a matching infohash is alread in the parsed set.
       @param errfunc: error-reporting callback.
       @param include_metainfo: deprecated?
       @return: The tuple (new parsed, new files, new blocked, added, removed)
          where 'new parsed', 'new files', and 'new blocked' are updated
          versions of 'parsed', 'files', and 'blocked' respectively. 'added'
          and 'removed' contain the changes made to the first three members
          of the tuple.  'added' and 'removed' are dicts mapping from
          infohash on to the same torrent-specific info dict that is in
          or was in parsed.
       """

    if NOISY:
        errfunc('checking dir')
    dirs_to_check = [directory]
    new_files = {}          # maps path -> [(modification time, size),infohash]
    new_blocked = {}        # used as a set.
    while dirs_to_check:    # first, recurse directories and gather torrents
        directory = dirs_to_check.pop()
        errfunc( "parsing directory %s" % directory )
        try:
            dir_contents = os.listdir(directory)
        except (IOError, OSError), e:
            errfunc(_("Could not read directory ") + directory)
            continue
        for f in dir_contents:
            if f.endswith('.torrent'):
                p = os.path.join(directory, f)
                try:
                    new_files[p] = [(os.path.getmtime(p),os.path.getsize(p)),0]
                except (IOError, OSError), e:
                    errfunc(_("Could not stat ") + p + " : " + unicode(e.args[0]))
        for f in dir_contents:
            p = os.path.join(directory, f)
            if os.path.isdir(p):
                dirs_to_check.append(p)
    new_parsed = {}
    to_add = []
    added = {}
    removed = {}
    # files[path] = [(modification_time, size),infohash], hash is 0 if the file
    # has not been successfully parsed
    for p,v in new_files.items():   # re-add old items and check for changes
        oldval = files.get(p)
        if oldval is None:     # new file
            to_add.append(p)
            continue
        h = oldval[1]
        if oldval[0] == v[0]:   # file is unchanged from last parse
            if h:
                if p in blocked:      # parseable + blocked means duplicate
                    to_add.append(p)  # other duplicate may have gone away
                else:
                    new_parsed[h] = parsed[h]
                new_files[p] = oldval
            else:
                new_blocked[p] = None  # same broken unparseable file
            continue
        if p not in blocked and h in parsed:  # modified; remove+add
            if NOISY:
                errfunc(_("removing %s (will re-add)") % p)
            removed[h] = parsed[h]
        to_add.append(p)

    to_add.sort()

    for p in to_add:                # then, parse new and changed torrents
        new_file = new_files[p]
        v = new_file[0]             # new_file[0] is the file's (mod time,sz).
        infohash = new_file[1]
        if infohash in new_parsed:  # duplicate, i.e., have same infohash.
            if p not in blocked or files[p][0] != v:
                errfunc(_("**warning** %s is a duplicate torrent for %s") %
                        (p, new_parsed[infohash][0]))
            new_blocked[p] = None
            continue

        if NOISY:
            errfunc('adding '+p)
        try:
            metainfo = like_gettorrent(p)
            new_file[1] = metainfo.infohash

            if new_parsed.has_key(metainfo.infohash):
                errfunc(_("**warning** %s is a duplicate torrent for %s") %
                        (p, new_parsed[metainfo.infohash][0]))
                new_blocked[p] = None
                continue

        except Exception ,e:
            errfunc(_("**warning** %s has errors") % p)
            new_blocked[p] = None
            continue

        if NOISY:
            errfunc(_("... successful"))
        #new_parsed[h] = a
        #added[h] = a
        new_parsed[metainfo.infohash] = (p,metainfo)
        added[metainfo.infohash] = (p,metainfo)

    for p,v in files.iteritems():       # and finally, mark removed torrents
        if p not in new_files and p not in blocked:
            if NOISY:
                errfunc(_("removing %s") % p)
            removed[v[1]] = parsed[v[1]]

    if NOISY:
        errfunc(_("done checking"))
    return (new_parsed, new_files, new_blocked, added, removed)

@coroutine
def async_parsedir(directory, parsed, files, blocked,
             include_metainfo=True):
    """Recurses breadth-first starting from the passed 'directory'
       looking for .torrrent files.  async_parsedir differs from
       parsedir in three ways: it is non-blocking, it returns a deferred,
       and it reports all errors to the logger BTL.parsedir meaning
       it does not use an errfunc.

       The directory, parsed, files, and blocked arguments are passed
       from the previous iteration of parsedir.

       @param directory: root of the breadth-first search for .torrent files.
       @param parsed: dict mapping infohash to (path,ConvertedMetainfo).
       @param files: dict mapping path -> [(modification time, size), infohash]
       @param blocked: dict used as set.  keys are list of paths of files
          that were not parsed on a prior call to parsedir for some reason.
          Valid reasons are that the .torrent file is unparseable or that a
          torrent with a matching infohash is alread in the parsed set.
       @param include_metainfo: deprecated?
       @return: The tuple (new parsed, new files, new blocked, added, removed)
          where 'new parsed', 'new files', and 'new blocked' are updated
          versions of 'parsed', 'files', and 'blocked' respectively. 'added'
          and 'removed' contain the changes made to the first three members
          of the tuple.  'added' and 'removed' are dicts mapping from
          infohash on to the same torrent-specific info dict that is in
          or was in parsed.
       """
    log.info('async_parsedir %s' % directory )
    dirs_to_check = [directory]
    new_files = {}          # maps path -> [(modification time, size),infohash]
    new_blocked = {}        # used as a set.
    while dirs_to_check:    # first, recurse directories and gather torrents
        directory = dirs_to_check.pop()
        if NOISY:
            log.info( "parsing directory %s" % directory )
        try:
            df = dtt(os.listdir,directory)
            yield df
            dir_contents = df.getResult()
        except (IOError, OSError), e:
            log.error(_("Could not read directory ") + directory)
            continue
        for f in dir_contents:
            if f.endswith('.torrent'):
                p = os.path.join(directory, f)
                try:
                    df = dtt(os.path.getmtime,p)
                    yield df
                    tmt = df.getResult()
                    df = dtt(os.path.getsize,p)
                    yield df
                    sz = df.getResult()
                    new_files[p] = [(tmt,sz),0]
                except (IOError, OSError), e:
                    log.error(_("Could not stat ") + p + " : " + unicode(e.args[0]))
        for f in dir_contents:
            p = os.path.join(directory, f)
            df = dtt(os.path.isdir,p)
            yield df
            is_dir = df.getResult()
            if is_dir:
                dirs_to_check.append(p)
    if NOISY:
        log.info( "Finished parsing directories." )
    new_parsed = {}
    to_add = []
    added = {}
    removed = {}
    # files[path] = [(modification_time, size),infohash], hash is 0 if the file
    # has not been successfully parsed
    for p,v in new_files.items():   # re-add old items and check for changes
        oldval = files.get(p)
        if oldval is None:     # new file
            to_add.append(p)
            continue
        h = oldval[1]
        if oldval[0] == v[0]:   # file is unchanged from last parse
            if h:
                if p in blocked:      # parseable + blocked means duplicate
                    to_add.append(p)  # other duplicate may have gone away
                else:
                    new_parsed[h] = parsed[h]
                new_files[p] = oldval
            else:
                new_blocked[p] = None  # same broken unparseable file
            continue
        if p not in blocked and h in parsed:  # modified; remove+add
            if NOISY:
                log.info(_("removing %s (will re-add)") % p)
            removed[h] = parsed[h]
        to_add.append(p)

    to_add.sort()

    for p in to_add:                # then, parse new and changed torrents
        new_file = new_files[p]
        v = new_file[0]             # new_file[0] is the file's (mod time,sz).
        infohash = new_file[1]
        if infohash in new_parsed:  # duplicate, i.e., have same infohash.
            if p not in blocked or files[p][0] != v:
                log.warning(_("%s is a duplicate torrent for %s") %
                        (p, new_parsed[infohash][0]))
            new_blocked[p] = None
            continue

        if NOISY:
            log.info('adding '+p)
        try:
            df = dtt(like_gettorrent,p)
            yield df
            metainfo = df.getResult()
            new_file[1] = metainfo.infohash

            if new_parsed.has_key(metainfo.infohash):
                log.warning(_("%s is a duplicate torrent for %s") %
                        (p, new_parsed[metainfo.infohash][0]))
                new_blocked[p] = None
                continue

        except Exception ,e:
            log.warning(_("%s has errors") % p)
            new_blocked[p] = None
            continue

        if NOISY:
            log.info(_("... successful"))
        new_parsed[metainfo.infohash] = (p,metainfo)
        added[metainfo.infohash] = (p,metainfo)

    for p,v in files.iteritems():       # and finally, mark removed torrents
        if p not in new_files and p not in blocked:
            if NOISY:
                log.info(_("removing %s") % p)
            removed[v[1]] = parsed[v[1]]

    if NOISY:
        log.info(_("done checking"))
    yield (new_parsed, new_files, new_blocked, added, removed)
