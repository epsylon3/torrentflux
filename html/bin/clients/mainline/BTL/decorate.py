# usage:
#
# o.method = decorate_func(somefunc, o.method)
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

def decorate_func(new, old):
    def runner(*a, **kw):
        new(*a, **kw)
        return old(*a, **kw)
    return runner
