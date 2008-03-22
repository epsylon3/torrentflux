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

import greenlet
from BTL import defer


class GreenletWithDeferred(greenlet.greenlet):

    __slots__ = ['root', 'yielded_once', 'finished']

    def __init__(self, root, df, _f, *a, **kw):
        self.root = root
        self.yielded_once = False
        self.finished = False
        greenlet.greenlet.__init__(self,
                                   lambda : self.body(df, _f, *a, **kw))

    def body(self, df, _f, *a, **kw):
        try:
            v = _f(*a, **kw)
        except:
            self.finished = True
            df.errback(defer.Failure())
        else:
            self.finished = True
            df.callback(v)
        return df

    def switch(self, *a):
        g = greenlet.getcurrent()
        if (isinstance(g, GreenletWithDeferred) and
            g.finished and g.parent == self):
            # control will return to the parent anyway, and switching to it
            # causes a memory leak (greenlets don't participate in gc).
            if a:
                return a[0]
            return                
        return greenlet.greenlet.switch(self, *a)


def launch_coroutine(_f, *a, **kw):
    parent = greenlet.getcurrent()
    if isinstance(parent, GreenletWithDeferred):
        parent = parent.root
    df = defer.Deferred()
    g = GreenletWithDeferred(parent, df, _f, *a, **kw)
    g.switch()
    return df

def coroutine(_f):
    def replacement(*a, **kw):
        return launch_coroutine(_f, *a, **kw)
    return replacement

def like_yield(df):
    assert isinstance(df, defer.Deferred)
    if not df.called or df.paused:
        g = greenlet.getcurrent()
        assert isinstance(g, GreenletWithDeferred)
        df.addBoth(g.switch)
        if not g.yielded_once:
            g.yielded_once = True
            g = g.parent
        else:
            g = g.root
        while not df.called or df.paused:
            g.switch()
    assert df.called and not df.paused
    return df.getResult()
