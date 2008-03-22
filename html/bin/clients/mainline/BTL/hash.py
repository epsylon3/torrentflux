# don't use the new fast hash functions if available
# they are slow on linux and windows.
# stupid.

import sha as shalib
sha = shalib.sha
##try:
##    import hashlib
##    sha = hashlib.sha1
##except ImportError:
##    import sha as shalib
##    sha = shalib.sha
