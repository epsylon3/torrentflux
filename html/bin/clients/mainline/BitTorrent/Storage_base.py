import os
from BitTorrent.platform import get_sparse_files_support

class UnregisteredFileException(Exception):
    pass

# Make this a separate function because having this code in Storage.__init__()
# would make python print a SyntaxWarning (uses builtin 'file' before 'global')
def bad_libc_workaround():
    global file
    def file(name, mode = 'r', buffering = None):
        return open(name, mode)

def is_open_for_write(mode):
    if 'w' in mode:
        return True
    if 'r' in mode and '+' in mode:
        return True
    return False

    
if os.name == 'nt':
    import win32file
    FSCTL_SET_SPARSE = 0x900C4

    def _sparse_magic(handle, length=0):
        win32file.DeviceIoControl(handle, FSCTL_SET_SPARSE, '', 0, None)

        win32file.SetFilePointer(handle, length, win32file.FILE_BEGIN)
        win32file.SetEndOfFile(handle)

        win32file.SetFilePointer(handle, 0, win32file.FILE_BEGIN)

    def make_file_sparse(path, f, length=0):
        supported = get_sparse_files_support(path)
        if not supported:
            return

        handle = win32file._get_osfhandle(f.fileno())
        _sparse_magic(handle, length)

else:
    def make_file_sparse(*args, **kwargs):
        return

def open_sparse_file(path, mode, length=0, overlapped=False):
    supported = get_sparse_files_support(path)
    flags = 0
    # some day I might support sparse files elsewhere
    if not supported and os.name != 'nt':
        return file(path, mode, 0)
    
    flags = win32file.FILE_FLAG_RANDOM_ACCESS
    if overlapped:
        flags |= win32file.FILE_FLAG_OVERLAPPED
    
    # If the hFile handle is opened with the
    # FILE_FLAG_NO_BUFFERING flag set, an application can move the
    # file pointer only to sector-aligned positions.  A
    # sector-aligned position is a position that is a whole number
    # multiple of the volume sector size. An application can
    # obtain a volume sector size by calling the GetDiskFreeSpace
    # function.
    #flags |= win32file.FILE_FLAG_NO_BUFFERING

    access = win32file.GENERIC_READ
    # Shared write is necessary because lock is assigned
    # per file handle. --Dave
    share = win32file.FILE_SHARE_READ | win32file.FILE_SHARE_WRITE
    #share = win32file.FILE_SHARE_READ #| win32file.FILE_SHARE_WRITE

    if is_open_for_write(mode):
        access |= win32file.GENERIC_WRITE

    if isinstance(path, unicode):
        CreateFile = win32file.CreateFileW
    else:
        CreateFile = win32file.CreateFile

    handle = CreateFile(path, access, share, None,
                        win32file.OPEN_ALWAYS,
                        flags, None)
    
    if supported and is_open_for_write(mode):
        _sparse_magic(handle, length)
    fd = win32file._open_osfhandle(handle, os.O_BINARY)
    handle.Detach()

    f = os.fdopen(fd, mode)
    return f

