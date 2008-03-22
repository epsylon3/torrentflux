import sys
import codecs

# we do this first, since BitTorrent/__init__.py installs a stderr proxy.

# py2exe'd Blackholes don't have encoding
encoding = getattr(sys.stdout, "encoding", None) 
# and sometimes encoding is None anyway
if encoding is not None:
    stdout_writer = codecs.getwriter(encoding)
    # don't fail if we can't write a value in the sydout encoding
    sys.stdout = stdout_writer(sys.stdout, 'replace')
    stderr_writer = codecs.getwriter(encoding)
    sys.stderr = stderr_writer(sys.stderr, 'replace')

from BitTorrent.platform import install_translation
install_translation(unicode=True)
_ = _ # not a typo
