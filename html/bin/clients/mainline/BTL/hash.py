# don't use the new fast hash functions if available
# they are slow on linux and windows.
# stupid.

try:
    from hashlib import sha1
    sha = hashlib.sha1
except ImportError:
    from shalib import sha
    sha = shalib.sha
