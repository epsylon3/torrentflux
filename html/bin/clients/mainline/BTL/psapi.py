# Windows PSAPI function wrappers.
# http://msdn.microsoft.com/library/default.asp?url=/library/en-us/dllproc/base/psapi_functions.asp
#
# The contents of this file are subject to the Python Software Foundation
# License Version 2.3 (the License).  You may not copy or use this file, in
# either source code or executable form, except in compliance with the License.
# You may obtain a copy of the License at http://www.python.org/license.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.
#
# by Greg Hazel

import ctypes

DWORD = ctypes.c_ulong
SIZE_T = ctypes.c_ulong

psapi = ctypes.windll.psapi
Kernel32 = ctypes.windll.Kernel32

class PROCESS_MEMORY_COUNTERS(ctypes.Structure):
    _fields_ = [("cb", DWORD),
                ("PageFaultCount", DWORD),
                ("PeakWorkingSetSize", SIZE_T),
                ("WorkingSetSize", SIZE_T),
                ("QuotaPeakPagedPoolUsage", SIZE_T),
                ("QuotaPagedPoolUsage", SIZE_T),
                ("QuotaPeakNonPagedPoolUsage", SIZE_T),
                ("QuotaNonPagedPoolUsage", SIZE_T),
                ("PagefileUsage", SIZE_T),
                ("PeakPagefileUsage", SIZE_T),
                ]

class PROCESS_MEMORY_COUNTERS_EX(ctypes.Structure):
    _fields_ = [("cb", DWORD),
                ("PageFaultCount", DWORD),
                ("PeakWorkingSetSize", SIZE_T),
                ("WorkingSetSize", SIZE_T),
                ("QuotaPeakPagedPoolUsage", SIZE_T),
                ("QuotaPagedPoolUsage", SIZE_T),
                ("QuotaPeakNonPagedPoolUsage", SIZE_T),
                ("QuotaNonPagedPoolUsage", SIZE_T),
                ("PagefileUsage", SIZE_T),
                ("PeakPagefileUsage", SIZE_T),
                ("PrivateUsage", SIZE_T),
                ]

def GetCurrentProcess():
    return Kernel32.GetCurrentProcess()

def GetProcessMemoryInfo(handle):
    psmemCounters = PROCESS_MEMORY_COUNTERS_EX()
    cb = DWORD(ctypes.sizeof(psmemCounters))
    b = psapi.GetProcessMemoryInfo(handle, ctypes.byref(psmemCounters), cb)
    if not b:
        psmemCounters = PROCESS_MEMORY_COUNTERS()
        cb = DWORD(ctypes.sizeof(psmemCounters))
        b = psapi.GetProcessMemoryInfo(handle, ctypes.byref(psmemCounters), cb)
        if not b:
            raise ctypes.WinError()
    d = {}
    for k, t in psmemCounters._fields_:
        d[k] = getattr(psmemCounters, k)
    return d
