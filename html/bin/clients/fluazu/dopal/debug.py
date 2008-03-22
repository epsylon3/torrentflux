# File: debug.py
# Library: DOPAL - DO Python Azureus Library
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; version 2 of the License.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details ( see the COPYING file ).
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

'''
Contains functions and objects useful for debugging DOPAL.
'''

class DebugObject(object):
    pass

class LinkDebugObject(DebugObject):
    pass

class ErrorLinkDebug(LinkDebugObject):

    def __init__(self, cgi_path, error):
        self.cgi_path = cgi_path
        self.error = error

class OutgoingExchangeDebug(LinkDebugObject):

    def __init__(self, cgi_path, data_to_send):
        self.cgi_path = cgi_path
        self.data_to_send = data_to_send

class ConnectionExchangeDebug(LinkDebugObject):

    def __init__(self, cgi_path, data_sent, data_received):
        self.cgi_path = cgi_path
        self.data_sent = data_sent
        self.data_received = data_received

class ConnectionDebugObject(DebugObject):
    pass

class MethodRequestDebug(ConnectionDebugObject):

    def __init__(self, object_id, request_method):
        self.object_id = object_id
        self.request_method = request_method

class MethodResponseDebug(ConnectionDebugObject):

    def __init__(self, object_id, request_method, response):
        self.object_id = object_id
        self.request_method = request_method
        self.response = response

def print_everything(debug_object):

    if not isinstance(debug_object, LinkDebugObject):
        return

    print
    print '---------------'
    print

    if isinstance(debug_object, OutgoingExchangeDebug):
        print 'Sending to "%s"' % debug_object.cgi_path
        print
        print debug_object.data_to_send

    elif isinstance(debug_object, ConnectionExchangeDebug):
        print 'Recieved from "%s"' % debug_object.cgi_path
        print
        print debug_object.data_received

    elif isinstance(debug_object, ErrorLinkDebug):
        error = debug_object.error
        print 'Error from "%s"' % debug_object.cgi_path
        print
        print '%s: %s' % (error.__class__.__name__, error)

    print
    print '---------------'
    print

def print_everything_with_stack(debug_object):
    if isinstance(debug_object, OutgoingExchangeDebug):
        import traceback
        print
        print '---------------'
        print
        print 'Stack trace of request:'
        traceback.print_stack()
        print
        print '---------------'
        print
    print_everything(debug_object)

def print_method(debug_object):
    from dopal.utils import make_short_object_id as _sid
    if isinstance(debug_object, MethodRequestDebug):
        print
        print '---------------'
        print '  Object:', debug_object.object_id,
        if debug_object.object_id is not None:
            print "[sid=%s]" % _sid(debug_object.object_id),
        print
        print '  Method:', debug_object.request_method
        print
    elif isinstance(debug_object, MethodResponseDebug):
        import dopal.core
        if isinstance(debug_object.response, dopal.core.ErrorResponse):
            print '  Response Type: ERROR'
            print '  Response Data:', debug_object.response.response_data
        elif isinstance(debug_object.response, dopal.core.AtomicResponse):
            print '  Response Type: VALUE'
            print '  Response Data:', debug_object.response.response_data
        elif isinstance(debug_object.response, dopal.core.NullResponse):
            print '  Response Type: NULL / EMPTY'
            print '  Response Data: None'
        elif isinstance(debug_object.response, dopal.core.StructuredResponse):
            print '  Response Type: STRUCTURE'
            print '  Response Data:',

            obj_id = debug_object.response.get_object_id()
            if obj_id is not None:
                print 'Object [id=%s, sid=%s]' % (obj_id, _sid(obj_id))
            else:
                print 'Non-object value'
        print '---------------'
        print

def print_method_with_stack(debug_object):
    if isinstance(debug_object, MethodResponseDebug):
        import traceback
        print
        print '---------------'
        print
        print 'Stack trace of request:'
        traceback.print_stack()
        print
        print '---------------'
        print
    print_method(debug_object)

class DebugGrabber(object):

    debug_object = None

    def get_in(self):
        if self.debug_object is None:
            raise Exception, "not captured any data yet"
        return self.debug_object.data_sent

    def get_out(self):
        if self.debug_object is None:
            raise Exception, "not captured any data yet"
        return self.debug_object.data_received

    def __call__(self, debug_object):
        if isinstance(debug_object, ConnectionExchangeDebug):
            self.debug_object = debug_object
