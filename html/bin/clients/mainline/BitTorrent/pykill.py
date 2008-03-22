import os
import win32process
import win32con
#import BTL.likewin32api as win32api
import win32api

def compare(x, y):
    """ tries to fuzzy match process names """
    if x.lower() == y.lower():
        return True
    y = '.'.join(y.split('.')[:-1])
    if x.lower() == y.lower():
        return True
    return False

def kill_process(name):
    
    for pid in win32process.EnumProcesses():
        
        # do try not to kill yourself
        if pid == win32api.GetCurrentProcessId():
            continue
        
        try:
            p = win32api.OpenProcess(win32con.PROCESS_QUERY_INFORMATION
                                     | win32con.PROCESS_VM_READ
                                     | win32con.PROCESS_TERMINATE,
                                     False, pid)
        except:
            continue

        if not p:
            continue
        
        try:
            hl = win32process.EnumProcessModules(p)
        except:
            win32api.CloseHandle(p)
            continue

        h = hl[0]
        pname = win32process.GetModuleFileNameEx(p, h)
        root, pname = os.path.split(pname)
        #print name, pname
        if compare(name, pname):
            #print "KILL", pname
            win32api.TerminateProcess(p, 0)
            win32api.CloseHandle(p)
            return True

        win32api.CloseHandle(p)
    return False

if __name__ == '__main__':
    import sys
    n = sys.argv[1]
    r = kill_process(n)
    if r:
        print n, "killed"
    else:
        print n, "not killed"