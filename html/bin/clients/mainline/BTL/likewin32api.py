import ctypes

DWORD = ctypes.c_ulong
MAX_PATH = ctypes.c_int(260)
MAX_PATH_NULL = int(MAX_PATH.value) + 1


def decode(s):
    if isinstance(s, unicode):
        return s
    return s.decode('mbcs')


def GetModuleFileName(handle):
    r = 0
    if hasattr(ctypes.windll.kernel32, "GetModuleFileNameW"):
        name = ctypes.create_unicode_buffer(MAX_PATH_NULL)
        r = ctypes.windll.kernel32.GetModuleFileNameW(handle, name, MAX_PATH_NULL)
    if r == 0:
        name = ctypes.create_string_buffer(MAX_PATH_NULL)
        ctypes.windll.kernel32.GetModuleFileNameA(handle, name, MAX_PATH_NULL)
    return decode(name.value)


def GetTempPath():
    r = 0
    if hasattr(ctypes.windll.kernel32, "GetTempPathW"):
        name = ctypes.create_unicode_buffer(MAX_PATH_NULL)
        r = ctypes.windll.kernel32.GetTempPathW(MAX_PATH_NULL, name)
    if r == 0:
        name = ctypes.create_string_buffer(MAX_PATH_NULL)
        ctypes.windll.kernel32.GetTempPathA(MAX_PATH_NULL, name)
    return decode(name.value)


def ShellExecute(hwnd, operation, file, parameters, directory, showCmd):
    if hasattr(ctypes.windll.shell32, 'ShellExecuteW'):
        SW = ctypes.windll.shell32.ShellExecuteW
        operation = decode(operation)
        file = decode(file)
        parameters = decode(parameters)
        directory = decode(directory)
    else:
        SW = ctypes.windll.shell32.ShellExecuteA
    return SW(hwnd, operation, file, parameters, directory, showCmd)


def GetVolumeInformation(rootPathName):
    volumeSerialNumber = DWORD()
    maximumComponentLength = DWORD()
    fileSystemFlags = DWORD()

    if hasattr(ctypes.windll.kernel32, "GetVolumeInformationW"):
        rootPathName = decode(rootPathName)
        volumeNameBuffer = ctypes.create_unicode_buffer(MAX_PATH_NULL)
        fileSystemNameBuffer = ctypes.create_unicode_buffer(MAX_PATH_NULL)
        GVI = ctypes.windll.kernel32.GetVolumeInformationW
    else:
        volumeNameBuffer = ctypes.create_string_buffer(MAX_PATH_NULL)
        fileSystemNameBuffer = ctypes.create_string_buffer(MAX_PATH_NULL)
        GVI = ctypes.windll.kernel32.GetVolumeInformationA
    GVI(rootPathName, volumeNameBuffer, MAX_PATH_NULL,
        ctypes.byref(volumeSerialNumber), ctypes.byref(maximumComponentLength),
        ctypes.byref(fileSystemFlags), fileSystemNameBuffer, MAX_PATH_NULL)
    return (volumeNameBuffer.value, volumeSerialNumber.value,
            maximumComponentLength.value, fileSystemFlags.value,
            fileSystemNameBuffer.value)

CloseHandle = ctypes.windll.kernel32.CloseHandle
GetLastError = ctypes.windll.kernel32.GetLastError
GetCurrentProcessId = ctypes.windll.kernel32.GetCurrentProcessId
OpenProcess = ctypes.windll.kernel32.OpenProcess
TerminateProcess = ctypes.windll.kernel32.TerminateProcess

