import re
from BTL.translation import _

from BTL import BTFailure

allowed_path_re = re.compile(r'^[^/\\.~][^/\\]*$')

ints = (long, int)

def check_info(info, check_paths=True):
    if not isinstance(info, dict):
        raise BTFailure, _("bad metainfo - not a dictionary")
    pieces = info.get('pieces')
    if type(pieces) != str or len(pieces) % 20 != 0 or len(pieces) == 0:
        raise BTFailure, _("bad metainfo - bad pieces key")
    piecelength = info.get('piece length')
    if type(piecelength) not in ints or piecelength <= 0:
        raise BTFailure, _("bad metainfo - illegal piece length")
    name = info.get('name')
    if not isinstance(name, str):
        raise BTFailure, _("bad metainfo - bad name")
    #if not allowed_path_re.match(name):
    #    raise BTFailure, _("name %s disallowed for security reasons") % name
    if info.has_key('files') == info.has_key('length'):
        raise BTFailure, _("single/multiple file mix") 
    if info.has_key('length'): 
        length = info.get('length')
        if type(length) not in ints or length < 0:
            raise BTFailure, _("bad metainfo - bad length") 
    else:
        files = info.get('files')
        if type(files) != list:
            raise BTFailure, _('bad metainfo - "files" is not a list of files')
        for f in files:
            if type(f) != dict:
                raise BTFailure, _("bad metainfo - file entry must be a dict") 
            length = f.get('length')
            if type(length) not in ints or length < 0:
                raise BTFailure, _("bad metainfo - bad length")
            path = f.get('path')
            if type(path) != list or path == []:
                raise BTFailure, _("bad metainfo - bad path")
            for p in path:
                if type(p) != str:
                    raise BTFailure, _("bad metainfo - bad path dir")
                if check_paths and not allowed_path_re.match(p):
                    raise BTFailure, _("path %s disallowed for security reasons") % p
        f = ['/'.join(x['path']) for x in files]
        f.sort()
        i = iter(f)
        try:
            name2 = i.next()
            while True:
                name1 = name2
                name2 = i.next()
                if name2.startswith(name1):
                    if name1 == name2:
                        raise BTFailure, _("bad metainfo - duplicate path")
                    elif name2[len(name1)] == '/':
                        raise BTFailure(_("bad metainfo - name used as both"
                                          "file and subdirectory name"))
        except StopIteration:
            pass

def check_message(message, check_paths=True):
    if type(message) != dict:
        raise BTFailure, _("bad metainfo - wrong object type")
    check_info(message.get('info'), check_paths)
    if type(message.get('announce')) != str and type(message.get('nodes')) != list:
        raise BTFailure, _("bad metainfo - no announce URL string")
    if message.has_key('title') and type(message.get('title')) != str:
        raise BTFailure, _("bad metainfo - bad title - should be a string" )

    if message.has_key('nodes'):
        check_nodes(message.get('nodes'))

def check_nodes(nodes):
    ## note, these strings need changing
    for node in nodes:
        if type(node) != list:
            raise BTFailure, _("bad metainfo - node is not a list")
        if len(node) != 2:
            raise BTFailure, _("bad metainfo - node list must have only two elements")
        host, port = node
        if type(host) != str:
            raise BTFailure, _("bad metainfo - node host must be a string")
        if type(port) != int:
            raise BTFailure, _("bad metainfo - node port must be an integer")
