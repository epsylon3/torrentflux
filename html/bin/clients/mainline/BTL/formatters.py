from __future__ import division

import math
from BTL.translation import _


def percentify(fraction, completed):
    if fraction is None:
        return None

    if completed and fraction >= 1.0:
        percent = 100.0
    else:
        percent = min(99.9, math.floor(fraction * 1000.0) / 10.0)

    return percent


class Size(long):
    """displays size in human-readable format"""

    size_labels = ['','K','M','G','T','P','E','Z','Y']
    radix = 2**10

    def __new__(cls, value=None, precision=None):
        if value is None:
            self = long.__new__(cls, 0)
            self.empty = True
        else:
            self = long.__new__(cls, value)
            self.empty = False
        return self

    def __init__(self, value, precision=0):
        long.__init__(self, value)
        self.precision = precision

    def __str__(self, precision=None):
        if self.empty:
            return ''
        if precision is None:
            precision = self.precision
        value = self
        for unitname in self.size_labels:
            if value < self.radix and precision < self.radix:
                break
            value /= self.radix
            precision /= self.radix
        if unitname and value < 10 and precision < 1:
            return '%.1f %sB' % (value, unitname)
        else:
            return '%.0f %sB' % (value, unitname)


class Rate(Size):
    """displays rate in human-readable format"""

    def __init__(self, value=None, precision=2**10):
        Size.__init__(self, value, precision)

    def __str__(self, precision=2**10):
        if self.empty:
            return ''
        return '%s/s'% Size.__str__(self, precision=precision)


class Duration(float):
    """displays duration in human-readable format"""

    def __new__(cls, value=None):
        if value == None:
            self = float.__new__(cls, 0)
            self.empty = True
        else:
            self = float.__new__(cls, value)
            self.empty = False
        return self

    def __str__(self):
        if self.empty or self > 365 * 24 * 60 * 60:
            return ''
        elif self >= 172800:
            return _("%d days") % round(self/86400) # 2 days or longer
        elif self >= 86400:
            return _("1 day %d hours") % ((self-86400)//3600) # 1-2 days
        elif self >= 3600:
            return _("%d:%02d hours") % (self//3600, (self%3600)//60) # 1 h - 1 day
        elif self >= 60:
            return _("%d:%02d minutes") % (self//60, self%60) # 1 minute to 1 hour
        elif self >= 0:
            return _("%d seconds") % int(self)
        else:
            return _("0 seconds")
