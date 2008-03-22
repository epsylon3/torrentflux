# Author: David Harrison
# Multi-cpu and Windows version: Greg Hazel

import os
if os.name == "nt":
    import win32pdh
    import win32api

class CPUMeterBase(object):

    def __init__(self, update_interval = 2):
        from twisted.internet import reactor
        self.reactor = reactor
        self._util = 0.0
        self._util_each = []
        self._interval = update_interval
        self.reactor.callLater(self._interval, self._update)

    def _update(self):
        self.update()
        self.reactor.callLater(self._interval, self._update)
        
    def update(self):
        raise NotImplementedError

    def get_utilization(self):
        return self._util

    def get_utilization_each(self):
        return self._util_each

    def get_interval(self):
        return self._interval


class CPUMeterUnix(CPUMeterBase):
    """Averages CPU utilization over an update_interval."""
    
    def __init__(self, update_interval = 2):
        self._old_stats = self._get_stats()
        CPUMeterBase.__init__(self, update_interval)

    def _get_stats(self):
        fp = open("/proc/stat")
        ln = fp.readline()
        stats = ln[4:].strip().split()[:4]
        total = [long(x) for x in stats]
        cpus = []
        ln = fp.readline()
        while ln.startswith("cpu"):
            stats = ln[4:].strip().split()[:4]
            cpu = [long(x) for x in stats]
            cpus.append(cpu)
            ln = fp.readline()
        return total, cpus

    def _get_util(self, oldl, newl):
        old_user, old_nice, old_sys, old_idle = oldl
        user, nice, sys, idle = newl
        user -= old_user
        nice -= old_nice
        sys -= old_sys
        idle -= old_idle
        total = user + nice + sys + idle
        return float((user + nice + sys)) / total        
        
    def update(self):
        old_total, old_cpus = self._old_stats
        total, cpus = self._old_stats = self._get_stats()
        self._util = self._get_util(old_total, total)
        self._util_each = []
        for old_cpu, cpu in zip(old_cpus, cpus):
            self._util_each.append(self._get_util(old_cpu, cpu))


class CPUMeterWin32(CPUMeterBase):
    """Averages CPU utilization over an update_interval."""
    
    def __init__(self, update_interval = 2):
        self.format = win32pdh.PDH_FMT_DOUBLE
        self.hcs = []
        self.hqs = []
        self._setup_query("_Total")
        num_cpus = win32api.GetSystemInfo()[4]
        for x in xrange(num_cpus):
            self._setup_query(x)
        CPUMeterBase.__init__(self, update_interval)

    def __del__(self):
        self.close()

    def _setup_query(self, which):
        inum = -1
        instance = None
        machine = None
        object = "Processor(%s)" % which
        counter = "% Processor Time"
        path = win32pdh.MakeCounterPath( (machine, object, instance,
                                          None, inum, counter) )
        hq = win32pdh.OpenQuery()
        self.hqs.append(hq)
        try:
            hc = win32pdh.AddCounter(hq, path)
            self.hcs.append(hc)
        except:
            self.close()
            raise

    def close(self):
        for hc in self.hcs:
            if not hc:
                continue
            try:
                win32pdh.RemoveCounter(hc)
            except:
                pass
        self.hcs = []
        for hq in self.hqs:
            if not hq:
                continue
            try:
                win32pdh.CloseQuery(hq)
            except:
                pass
        self.hqs = []

    def _get_util(self, i):
        win32pdh.CollectQueryData(self.hqs[i])
        type, val = win32pdh.GetFormattedCounterValue(self.hcs[i], self.format)
        val = val / 100.0
        return val

    def update(self):
        self._util = self._get_util(0)
        self._util_each = []
        for i in xrange(1, len(self.hcs)):
            self._util_each.append(self._get_util(i))


if os.name == "nt":
    CPUMeter = CPUMeterWin32
else:
    CPUMeter = CPUMeterUnix
        
if __name__ == "__main__":

    from twisted.internet import reactor
    cpu = CPUMeter(1)

    def print_util():
        print cpu.get_utilization()
        print cpu.get_utilization_each()
        reactor.callLater(1, print_util)
    
    reactor.callLater(1, print_util)
    reactor.run()

