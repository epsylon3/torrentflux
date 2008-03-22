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

from Queue import Queue

class LIFOQueue(Queue):
    
    # Get an item from the queue
    def _get(self):
        return self.queue.pop()

if __name__ == '__main__':
    l = LIFOQueue()
    for i in xrange(10):
        l.put(i)
    j = 9
    for i in xrange(10):
        assert l.get() == j - i