# extensions for lsprof/cProfile
#
# usage:
#
#    from BTL.profile import Profiler, Stats
#
#    prof = Profiler()
#    prof.enable()
#
#    your_code()
#
#    prof.disable()
#    stats = Stats(prof.getstats())
#    stats.sort("inlinetime")
#    stats.pprint()
#
# by Greg Hazel

from __future__ import division
import sys
from BTL.ebencode import ebencode
from lsprof import Profiler, _fn2mod
from lsprof import Stats as lsprof_Stats

def label(code):
    if isinstance(code, str):
        return code
    try:
        mname = _fn2mod[code.co_filename]
    except KeyError:
        for k, v in sys.modules.items():
            if v is None:
                continue
            if not hasattr(v, '__file__'):
                continue
            if not isinstance(v.__file__, str):
                continue
            if v.__file__.startswith(code.co_filename):
                mname = _fn2mod[code.co_filename] = k
                break
        else:
            mname = _fn2mod[code.co_filename] = '<%s>'%code.co_filename
    
    return '%s (%s:%d)' % (code.co_name, mname, code.co_firstlineno)

class Stats(lsprof_Stats):
    
    def dump(self, file, top=None):
        d = self.data
        if top is not None:
            d = d[:top]
        root = []
        for e in d:
            n = {}
            root.append(n)
            n['l'] = (e.inlinetime, e.callcount, e.totaltime, label(e.code))
            n['c'] = []
            for se in e.calls or []:
                n['c'].append((se.inlinetime, se.callcount,
                               se.totaltime, label(se.code)))
        file.write(ebencode(root))

    def list_stats(self, top=None):
        l = []
        d = self.data
        if top is not None:
            d = d[:top]
        l.append(("CallCount",
                  "Inline(sec)", "Per call(sec)",
                  "Total(sec)", "Per call(sec)",
                  "function (module:lineno)"))

        def make_set(d, child=False):
            if child:
                fmt = "+%s"
            else:
                fmt = "%s"
            return (fmt % d.callcount,
                    '%.3f' % d.inlinetime,
                    '%.3f' % (d.inlinetime / e.callcount),
                    '%.3f' % d.totaltime,
                    '%.3f' % (d.totaltime / e.callcount),
                    fmt % label(d.code))
        
        for e in d:
            l.append(make_set(e))
            if e.calls:
                for se in e.calls:
                    l.append(make_set(se, child=True))
        return l
