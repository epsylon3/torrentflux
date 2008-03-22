# File: core.py
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
Contains the main objects and functions required for DOPAL to be useful.
'''

# PyChecker seems to be complaining a lot about inconsistent return types
# (especially returning different types of responses and returning different
# types of exceptions), so we're switching it off.
__pychecker__ = 'no-returnvalues'

from dopal.aztypes import unwrap_value

from dopal.errors import AzureusResponseXMLError, MissingObjectIDError, \
    RemoteInternalError, RemoteMethodError, LinkError, InvalidObjectIDError, \
    NoSuchMethodError, InvalidWrapTypeError, InvalidRemoteObjectError, \
    NoObjectIDGivenError, NoEstablishedConnectionError, \
    InvalidConnectionIDError, raise_as, DopalPendingDeprecationWarning

#
# Low-level class, representing a link to a remote Azureus instance.
#
class AzureusLink(object):

    def __init__(self): # AzureusLink
        # Default values, can be changed via set_connection_details.
        self.link_data = {
            'host': '127.0.0.1', 'port': 6884, 'secure': False,
            'user': None, 'password': ''}
        self.debug = None

    def get_cgi_path(self, auth_details=False, include_password=False): # AzureusLink
        path_template = "%(protocol)s://%(auth)s%(host)s:%(port)s/process.cgi"
        path_data = {}
        path_data['host'] = self.link_data['host']
        path_data['port'] = self.link_data['port']
        path_data['user'] = self.link_data['user']

        if self.link_data['secure']:
            path_data['protocol'] = 'https'
        else:
            path_data['protocol'] = 'http'

        if auth_details and self.link_data['user']:
            if include_password:
                #path_data['password'] = '*' * len(self.link_data['password'])
                path_data['password'] = '*' * 4
            else:
                path_data['password'] = self.link_data['password']

            path_data['auth'] = '%(user)s:%(password)s@' % path_data
        else:
            path_data['auth'] = ''

        return path_template % path_data

    def _send_data(self, request):
        import urllib2
        return urllib2.urlopen(request)

    def _send_method_exchange(self, xml_data): # AzureusLink
        from dopal.debug import ConnectionExchangeDebug, \
            ErrorLinkDebug, OutgoingExchangeDebug

        cgi_path = self.get_cgi_path(auth_details=False)
        printable_path = self.get_cgi_path(auth_details=True, include_password=False)

        if self.debug is not None:
            debug_data = OutgoingExchangeDebug(printable_path, xml_data)
            self.debug(debug_data)

        import socket, urllib2
        request = urllib2.Request(cgi_path, xml_data)

        # Add User-Agent string.
        from dopal import __user_agent__
        request.add_header("User-agent", __user_agent__)

        # Add authorisation data.
        if self.link_data['user']:
            auth_string = ("%(user)s:%(password)s" % self.link_data)
            base64_string = auth_string.encode('base64').strip()
            request.add_header("Authorization", "Basic " + base64_string)
            del auth_string, base64_string

        try:
            data = self._send_data(request).read()
        except (urllib2.URLError, socket.error, LinkError), error:

            # Log the error, if enabled.
            if self.debug is not None:
                debug_data = ErrorLinkDebug(printable_path, error)
                self.debug(debug_data)

            # Error raised here.
            raise_as(error, LinkError, obj=cgi_path)

        # Log the exchange, if enabled.
        if self.debug is not None:
            debug_data = ConnectionExchangeDebug(printable_path, xml_data, data)
            self.debug(debug_data)

        return data

    def send_method_exchange(self, xml_data): # AzureusLink
        retry_count = 0
        retry_namespace = None
        while True:
            try:
                result = self._send_method_exchange(xml_data)
            except LinkError, error:
                if retry_namespace is not None:
                    retry_namespace = {}
                if self.handle_link_error(error, retry_count, retry_namespace):
                    retry_count = 1
                else:
                    raise error
            else:
                if retry_count:
                    self.handle_link_repair(error, retry_count, retry_namespace)
                return result

        # This won't happen, but it keeps PyChecker happy.
        return None

    def handle_link_error(self, error, retry_count, saved):
        return False # Don't bother retrying.

    def handle_link_repair(self, error, retry_count, saved):
        pass

    def set_link_details(self, **kwargs): # AzureusLink
        """
        Sets the details of where the Azureus server to connect to is located.

        @rtype: None
        @keyword host: Host name of the machine to connect to (default is
            C{127.0.0.1}).
        @keyword port: Server port that Azureus is accepting XML/HTTP
            connections on (default is C{6884}).
        @keyword secure: Set to a true value if the Azureus is only
            accepting secure connections (default is C{False}).
        @keyword user: For authenticated connections - the user name to
            connect as (default is to use no authentication).
        @keyword password: For authenticated connections - the password to
            connect with (default is to use no authentication).
        """

        # Smart use of handle_kwargs, I think. :)
        from dopal.utils import handle_kwargs
        kwargs = handle_kwargs(kwargs, **self.link_data)

        #for key, value in kwargs.items():
        #    if key not in self.link_data:
        #        raise TypeError, "invalid keyword argument: %s" % key
        self.link_data.update(kwargs)

    def __str__(self): # AzureusLink
        return "%s for %s" % (self.__class__.__name__, self.link_data['host'])

#
# Method generation.
#
def remote_method_call_to_xml(method_name, method_args, request_id,
    object_id=None, connection_id=None):

    '''
    Generates an XML block which can be sent to Azureus to invoke a remote
    method.

    An example of the output generated by this method::
      >>> remote_method_call_to_xml('getDownloads', [True], request_id=123, object_id=456, connection_id=789)

      <REQUEST>
        <OBJECT>
          <_object_id>456</_object_id>
        </OBJECT>
        <METHOD>getDownloads[boolean]</METHOD>
        <PARAMS>
          <ENTRY index="0">true</ENTRY>
        </PARAMS>
        <CONNECTION_ID>789</CONNECTION_ID>
        <REQUEST_ID>123</REQUEST_ID>
      </REQUEST>

    The I{method_args} parameter needs to be a sequence of items representing
    the method you want to invoke. Each argument needs to be one of the
    following types:
      - C{boolean} (represented in Java as a boolean)
      - C{int} (represented in Java as an int)
      - C{long} (represented in Java as a long)
      - C{str} or C{unicode} (represented in Java as a String)
      - C{float} (represented in Java as a float)
      - An object with a I{get_xml_type} method, which returns a string
        representing the name of the Java data type that it represents. It
        needs to also have one other method on it:
          - I{get_object_id} - needs to return the remote ID of the Azureus
            object it is representing; or
          - I{as_xml} - returns an object which can be converted into a string
            containing XML representing the value of this object. Several other
            types are supported using this method, defined in the L{aztypes}
            module (such as C{java.net.URL}, C{byte[]} etc.)

    @attention: B{Deprecated:} This method is not unicode-safe, nor does it
    define what happens when dealing with unicode data. Use
    L{remote_method_call_as_xml} instead.

    @param method_name: A string representing the name of the method you want
    to invoke (which must either be a method available on the object with the
    given object ID, or a special global method which Azureus has some special
    case behaviour for).

    @type method_name: str

    @param method_args: A sequence of items representing the arguments you want
    to pass to the method (definition of what types are accepted are explained
    above).

    @param request_id: The unique ID to be given to this invocation request
    (each invocation on a connection must be unique).
    @type request_id: str / int / long

    @param object_id: The object on which to invoke the method on. There are
    some methods which are special cased which don't require an object ID - in
    these cases, this can be left as I{None}.
    @type object_id: str / int / long / None

    @param connection_id: The ID of the connection you are using - this value
    is given to you once you have initiated a connection with Azureus - this
    can be left blank if you haven't yet initiated the connection.
    @type connection_id: str / int / long / None

    @return: A string representing the XML block to send.

    @raise InvalidWrapTypeError: Raised if one of the items in the
    method_args sequence does not match one of the accepted types.

    @see: L{aztypes}
    @see: L{InvalidWrapTypeError}

    @summary: B{Deprecated:} Use L{remote_method_call_as_xml} instead.
    '''

    import warnings
    warnings.warn("remote_method_call_to_xml is deprecated, use remote_method_call_as_xml instead", DopalPendingDeprecationWarning)

    import types
    from dopal.xmlutils import XMLObject, make_xml_ref_for_az_object

    # We check the argument types here,
    arg_types = []
    arg_values = []
    for method_arg in method_args:

        # The value has methods on it to tell us how we should handle it.
        if hasattr(method_arg, 'get_xml_type'):
            arg_type = method_arg.get_xml_type()

            # Either the value generates the XML itself (like below), or we
            # are able to determine how to generate the XML for it.
            if hasattr(method_arg, 'as_xml'):
                arg_value = method_arg.as_xml()

            # The value represents a remote object...
            elif hasattr(method_arg, 'get_object_id'):
                arg_value = make_xml_ref_for_az_object(method_arg.get_object_id())

            # If we get here, we don't know how to handle this object.
            else:
                raise InvalidWrapTypeError(obj=method_arg)

        # We must check boolean types before integers, as booleans are
        # a type of integer.
        #
        # The first check is just to ensure that booleans exist on the
        # system (to retain compatibility with Python 2.2)
        elif hasattr(types, 'BooleanType') and isinstance(method_arg, bool):
            arg_type = 'boolean'

            # lower - the Java booleans are lower case.
            arg_value = str(method_arg).lower()

        elif isinstance(method_arg, int):
            arg_type = 'int'
            arg_value = str(method_arg)

        elif isinstance(method_arg, types.StringTypes):
            arg_type = 'String'
            arg_value = method_arg

        elif isinstance(method_arg, long):
            arg_type = 'long'
            arg_value = str(method_arg)

        elif isinstance(method_arg, float):
            arg_type = 'float'
            arg_value = str(method_arg)

        else:
            raise InvalidWrapTypeError(obj=method_arg)

        arg_types.append(arg_type)
        arg_values.append(arg_value)
        del arg_type, arg_value, method_arg

    # We don't need to refer to method_args again, as we have arg_types and
    # arg_values. This prevents the code below accessing method_args
    # accidently.
    del method_args

    # Now we start to generate the XML.
    request_block = XMLObject('REQUEST')

    # Add the object ID (if we have one).
    if object_id:

        # We are just using this object to generate the XML block, the name
        # we give the type is not used, so does not matter.
        object_block = make_xml_ref_for_az_object(object_id)
        request_block.add_content(object_block)
        del object_block

    # Add the method identifier.
    method_block = XMLObject('METHOD')
    method_content = method_name
    if arg_types:
        method_content += '[' + ','.join(arg_types) + ']'
    method_block.add_content(method_content)
    request_block.add_content(method_block)
    del method_block, method_content

    # Add method arguments.
    if arg_values:
        params_block = XMLObject('PARAMS')
        for index_pos, xml_value in zip(range(len(arg_values)), arg_values):
            entry_block = XMLObject('ENTRY')
            entry_block.add_attribute('index', str(index_pos))
            entry_block.add_content(xml_value)
            params_block.add_content(entry_block)

        request_block.add_content(params_block)
        del index_pos, xml_value, entry_block, params_block

    # Add the connection ID (if we have one).
    if connection_id:
        connection_id_block = XMLObject('CONNECTION_ID')
        connection_id_block.add_content(str(connection_id))
        request_block.add_content(connection_id_block)
        del connection_id_block

    # Add a "unique" request ID.
    request_id_block = XMLObject('REQUEST_ID')
    request_id_block.add_content(str(request_id))
    request_block.add_content(request_id_block)

    return request_block.to_string()

def remote_method_call_as_xml(method_name, method_args, request_id,
    object_id=None, connection_id=None):

    '''
    Generates an XML block which can be sent to Azureus to invoke a remote
    method - this is returned as an object which can be turned into a unicode
    string.

    An example of the output generated by this method::
      >>> remote_method_call_as_xml('getDownloads', [True], request_id=123, object_id=456, connection_id=789).encode('UTF-8')

      <?xml version="1.0" encoding="UTF-8"?>
      <REQUEST>
        <OBJECT>
          <_object_id>456</_object_id>
        </OBJECT>
        <METHOD>getDownloads[boolean]</METHOD>
        <PARAMS>
          <ENTRY index="0">true</ENTRY>
        </PARAMS>
        <CONNECTION_ID>789</CONNECTION_ID>
        <REQUEST_ID>123</REQUEST_ID>
      </REQUEST>

    The I{method_args} parameter needs to be a sequence of items representing
    the method you want to invoke. Each argument needs to be one of the
    following types:
      - C{boolean} (represented in Java as a boolean)
      - C{int} (represented in Java as an int)
      - C{long} (represented in Java as a long)
      - C{str} or C{unicode} (represented in Java as a String)
      - C{float} (represented in Java as a float)
      - An object with a I{get_xml_type} method, which returns a string
        representing the name of the Java data type that it represents. It
        needs to also have one other method on it:
          - I{get_object_id} - needs to return the remote ID of the Azureus
            object it is representing; or
          - I{as_xml} - returns an object which can be converted into a string
            containing XML representing the value of this object. Several other
            types are supported using this method, defined in the L{aztypes}
            module (such as C{java.net.URL}, C{byte[]} etc.)

    @attention: Any byte strings passed to this function will be treated as if
    they are text strings, and they can be converted to unicode using the
    default system encoding. If the strings represented encoded content, you
    must decode them to unicode strings before passing to this function.

    @note: This function will return an object which has an C{encode} method
    (to convert the XML into the specified bytestring representation. The
    object can also be converted into a unicode string via the C{unicode}
    function. Currently, this object will be an instance of
    L{UXMLObject<dopal.xmlutils.UXMLObject>}, but this behaviour may change in
    future - the only guarantees this function makes is the fact that the
    resulting object can be converted into unicode,
    and that it will have an encode method on it.

    @param method_name: A string representing the name of the method you want
    to invoke (which must either be a method available on the object with the
    given object ID, or a special global method which Azureus has some special
    case behaviour for).

    @type method_name: str / unicode

    @param method_args: A sequence of items representing the arguments you want
    to pass to the method (definition of what types are accepted are explained
    above).

    @param request_id: The unique ID to be given to this invocation request
    (each invocation on a connection must be unique).
    @type request_id: str / unicode / int / long

    @param object_id: The object on which to invoke the method on. There are
    some methods which are special cased which don't require an object ID - in
    these cases, this can be left as I{None}.
    @type object_id: str / unicode / int / long / None

    @param connection_id: The ID of the connection you are using - this value
    is given to you once you have initiated a connection with Azureus - this
    can be left blank if you haven't yet initiated the connection.
    @type connection_id: str / unicode / int / long / None

    @return: An object which has an C{encode} method (to convert the XML into
    the specified bytestring representation. The object can also be converted
    into a unicode string via the C{unicode} function. Currently, this object
    will be an instance of L{UXMLObject<dopal.xmlutils.UXMLObject>}, but this
    behaviour may change in future - the only guarantees this function makes is
    the fact that the resulting object can be converted into unicode, and that
    it will have an encode method on it.

    @raise InvalidWrapTypeError: Raised if one of the items in the
    method_args sequence does not match one of the accepted types.

    @see: L{aztypes}
    @see: L{InvalidWrapTypeError}
    @see: L{UXMLObject<dopal.xmlutils.UXMLObject>}
    '''

    import types
    from dopal.xmlutils import UXMLObject, make_xml_ref_for_az_object

    # We check the argument types here,
    arg_types = []
    arg_values = []
    for method_arg in method_args:

        # The value has methods on it to tell us how we should handle it.
        if hasattr(method_arg, 'get_xml_type'):
            arg_type = method_arg.get_xml_type()

            # Either the value generates the XML itself (like below), or we
            # are able to determine how to generate the XML for it.
            if hasattr(method_arg, 'as_xml'):
                arg_value = method_arg.as_xml()

            # The value represents a remote object...
            elif hasattr(method_arg, 'get_object_id'):
                arg_value = make_xml_ref_for_az_object(method_arg.get_object_id())

            # If we get here, we don't know how to handle this object.
            else:
                raise InvalidWrapTypeError(obj=method_arg)

        # We must check boolean types before integers, as booleans are
        # a type of integer.
        #
        # The first check is just to ensure that booleans exist on the
        # system (to retain compatibility with Python 2.2)
        elif hasattr(types, 'BooleanType') and isinstance(method_arg, bool):
            arg_type = 'boolean'

            # lower - the Java booleans are lower case.
            arg_value = str(method_arg).lower()

        elif isinstance(method_arg, int):
            arg_type = 'int'
            arg_value = str(method_arg)

        elif isinstance(method_arg, types.StringTypes):
            arg_type = 'String'
            arg_value = method_arg

        elif isinstance(method_arg, long):
            arg_type = 'long'
            arg_value = str(method_arg)

        elif isinstance(method_arg, float):
            arg_type = 'float'
            arg_value = str(method_arg)

        else:
            raise InvalidWrapTypeError(obj=method_arg)

        arg_types.append(arg_type)
        arg_values.append(arg_value)
        del arg_type, arg_value, method_arg

    # We don't need to refer to method_args again, as we have arg_types and
    # arg_values. This prevents the code below accessing method_args
    # accidently.
    del method_args

    # Now we start to generate the XML.
    request_block = UXMLObject('REQUEST')

    # Add the object ID (if we have one).
    if object_id:

        # We are just using this object to generate the XML block, the name
        # we give the type is not used, so does not matter.
        object_block = make_xml_ref_for_az_object(object_id)
        request_block.add_content(object_block)
        del object_block

    # Add the method identifier.
    method_block = UXMLObject('METHOD')
    method_content = method_name
    if arg_types:
        method_content += '[' + ','.join(arg_types) + ']'
    method_block.add_content(method_content)
    request_block.add_content(method_block)

    # Make this easily accessible for the debugger.
    request_block.request_method = method_content
    del method_block, method_content

    # Add method arguments.
    if arg_values:
        params_block = UXMLObject('PARAMS')
        for index_pos, xml_value in zip(range(len(arg_values)), arg_values):
            entry_block = UXMLObject('ENTRY')
            entry_block.add_attribute('index', str(index_pos))
            entry_block.add_content(xml_value)
            params_block.add_content(entry_block)

        request_block.add_content(params_block)
        del index_pos, xml_value, entry_block, params_block

    # Add the connection ID (if we have one).
    if connection_id:
        connection_id_block = UXMLObject('CONNECTION_ID')
        connection_id_block.add_content(str(connection_id))
        request_block.add_content(connection_id_block)
        del connection_id_block

    # Add a "unique" request ID.
    request_id_block = UXMLObject('REQUEST_ID')
    request_id_block.add_content(str(request_id))
    request_block.add_content(request_id_block)

    return request_block


#
# Incoming method handling.
#

# Processes an XML response returned by Azureus, returning an AzureusResponse
# instance.
#
# xml_node must be an instance of xml.dom.Node which has been normalised using
# the normalise_xml_structure function.
#
# This function will raise a AzureusResponseXMLError if the XML is not in the
# format expected.
def process_xml_response(xml_node):

    if len(xml_node.childNodes) != 1:
        err = "expected one main block inside document, had %s"
        raise AzureusResponseXMLError, err % len(xml_node.childNodes)

    block_name = xml_node.firstChild.localName
    if block_name != 'RESPONSE':
        err = "expected a RESPONSE block, got %s block instead"
        raise AzureusResponseXMLError, err % block_name

    response_block = xml_node.firstChild
    az_dict = {}

    # We get an empty response block when the remote method doesn't return a
    # result (e.g. void), or returns a reponse which is effectively empty
    # (empty sequence, empty string) - or perhaps null itself.
    if not response_block.hasChildNodes():
        return NullResponse(az_dict)

    # If we detect any child block with the name ERROR, then we'll raise an
    # error and ignore the rest of the content (it is possible for the block
    # to be embedded alongside other values - normally if something has gone
    # wrong during processing.
    #
    # XXX: Perhaps this could occur anywhere in the tree structure, what should
    # we do?
    from xml.dom import Node
    from dopal.xmlutils import get_text_content

    for child_block in response_block.childNodes:
        if child_block.nodeType == Node.ELEMENT_NODE and \
            child_block.nodeName == 'ERROR':
            return ErrorResponse(az_dict, get_text_content(child_block))

    if len(response_block.childNodes) == 1:
        node = response_block.firstChild
        if node.nodeType == Node.TEXT_NODE:
            return AtomicResponse(az_dict, get_text_content(node))

    # We will have some "complex" XML structure. It may contain the definition
    # of one remote object, one remote object with other remote objects
    # branching off it, or no remote objects at all.
    #
    # We will return the XML as-is, but we will try to extract important data
    # so that it is more conveniently retrievable.
    #
    # Nodes which are categorised as being "important" are currently defined
    # as information about Azureus and any connection information.
    conn_node = None
    azureus_nodes = []

    for node in response_block.childNodes:
        if node.nodeName.startswith('azureus_'):
            azureus_nodes.append(node)
        elif node.nodeName == '_connection_id':
            conn_node = node
        else:
            pass

    # Extract the data from the Azureus nodes.
    az_dict = {}
    for az_node in azureus_nodes:
        name = az_node.nodeName[8:] # (remove "azureus_" prefix)
        value = get_text_content(az_node)
        az_dict[name] = value

    # Extract the connection ID.
    if conn_node:
        connection_id = long(get_text_content(conn_node))
    else:
        connection_id = None

    # We've got a structured definition.
    return StructuredResponse(az_dict, response_block, connection_id)


#
# Base class of all types of response which can be returned by Azureus.
#
# It will have at least the following attributes:
#
#   azureus_data - dictionary containng information about the instance of
#                  Azureus which is running.
#
#   response_data - The value of the response object. The type of this value
#                   will differ between different Response implementations.
#
#   connection_id - ID of the connection given in the response. Will be None
#                   if none was given.
#
class AzureusResponse(object):

    def __init__(self, azureus_data, response_data=None, connection_id=None):
        self.azureus_data = azureus_data
        self.response_data = response_data
        self.connection_id = connection_id

class ErrorResponse(AzureusResponse):

    def raise_error(self):
        raise generate_remote_error(self.response_data)

class StructuredResponse(AzureusResponse):

    def get_object_id(self):
        # Doesn't matter that this is an abstract class, the method still
        # works. :)
        from dopal.convert import XMLStructureReader
        return XMLStructureReader.get_object_id(self.response_data)

class AtomicResponse(AzureusResponse):

    def get_value(self, value_type=None):
        if value_type is None:
            return self.response_data
        else:
            return unwrap_value(self.response_data, value_type)

    def as_string(self):
        return self.get_value("String")

    def as_int(self):
        return self.get_value("int")

    def as_long(self):
        return self.get_value("long")

    def as_float(self):
        return self.get_value("float")

    def as_bool(self):
        return self.get_value("boolean")

    def as_bytes(self):
        return self.get_value("byte[]")

class NullResponse(AzureusResponse):

    '''
    A response class which is used when Azureus returns a response which
    contains no content at all.

    This is normally returned when:
      - C{null} is returned by the remote method.
      - An empty sequence.
      - An empty string.
      - The return type of the method is C{void}.
    '''

    def get_value(self, value_type=None):
        if value_type is None:
            return None
        elif value_type in ['byte[]', 'String']:
            return ''
        else:
            return InvalidUnwrapTypeError(obj=value_type)

#
# Error-handling.
#

#
# This method takes a string returned in a response and generates an instance
# of RemoteMethodError - it doesn't take into account of any reported class
# type or any other data.
#
def generate_remote_error(message):

    # Bad method?
    bad_method_prefix = 'Unknown method: '
    if message.startswith(bad_method_prefix):
        return NoSuchMethodError(message[len(bad_method_prefix):])

    # Bad object ID?
    if message == 'Object no longer exists':
        return InvalidObjectIDError()

    # Missing object ID?
    if message == 'Object identifier missing from request':
        return MissingObjectIDError()

    # Perhaps a Java exception has occurred remotely. Not always easy to
    # detect - it'll mention the Java exception class though. For example,
    # passing an non-integer object ID got this error:
    #
    # u'java.lang.RuntimeException: java.lang.NumberFormatException: For
    # input string: "3536f63"'
    #
    # So we'll try and test to see if a Java exception occurred, by seeing
    # if there appears to be a Java-esque exception mentioned.
    parts = message.split(':', 1)
    if len(parts) == 2:
        exception_name = parts[0]

        # A Java-esque exception name: We'll take anything which is defined
        # in a package with java. at the start, and ends with Error or
        # Exception, then we'll take it.
        if exception_name.startswith('java.') and \
            (exception_name.endswith('Error') or \
            exception_name.endswith('Exception')):

                return RemoteInternalError(message)

    # Something went wrong - don't know what...
    return RemoteMethodError(message)

#
# Higher-level version of AzureusLink - this class maintains an active
# connection with the remote server - it also utilises other components
# defined by this module.
#

class AzureusConnection(AzureusLink):

    def __init__(self): # AzureusConnection
        AzureusLink.__init__(self)
        self.connection_id = None
        self.request_id = None # Will be initialised later.

    def update_connection_details(self, connection_id=None, connection_data={}): # AzureusConnection
        if connection_id is not None:
            self.connection_id = connection_id

    # Return true if the specified method can be called without passing an
    # object ID or connection ID.
    #
    # XXX: Would it be safe to have ExtendedAzureusConnection handle this?
    # I would say yes, but look at the invoke_remote_method method, I don't
    # think it would behave well if we either return True or False all the
    # time...
    def _is_global_method_call(self, method_name, method_args): # AzureusConnection
        return method_name in ['getDownloads', 'getSingleton'] and not method_args

    def invoke_remote_method(self, object_id, method_name, method_args, raise_errors=True): # AzureusConnection
        # We require a connection ID and an object ID, unless we are calling
        # a "global" method - methods which don't need either, and which will
        # actually return that data to you in the result.
        if self._is_global_method_call(method_name, method_args):
            connection_id = None
        else:
            connection_id = self.connection_id
            if object_id is None:
                raise NoObjectIDGivenError
            if connection_id is None:
                raise NoEstablishedConnectionError

        from xml.dom.minidom import parseString
        from dopal.xmlutils import normalise_xml_structure, get_text_content

        # First step, convert the method data to XML.
        xml_data = remote_method_call_as_xml(method_name, method_args,
            self.get_new_request_id(), object_id, connection_id)

        xml_data_as_string = xml_data.encode('UTF-8')

        from dopal.debug import MethodRequestDebug, MethodResponseDebug

        # Log a debug message, if appropriate.
        if self.debug is not None:
            self.debug(MethodRequestDebug(object_id, xml_data.request_method))

        # Second step, send this to Azureus and get a response back.
        xml_response_string = self.send_method_exchange(xml_data_as_string)

        # Third step, convert the string into a xml.dom.Node structure.
        xml_structure = parseString(xml_response_string)

        # Fourth step, sanitise the XML structure for easier parsing.
        normalise_xml_structure(xml_structure)

        # Fifth step, calculate the Azureus response instance represented by
        # this XML structure.
        response = process_xml_response(xml_structure)

        # Send another debug message with the response.
        if self.debug is not None:
            self.debug(MethodResponseDebug(object_id, xml_data.request_method, response))

        # Sixth step - update our own connection data given in this response.
        connection_id = response.connection_id
        azureus_data = response.azureus_data
        self.update_connection_details(connection_id, azureus_data)

        # Seventh step - return the response (or raise an error, if it's an
        # error response).
        if raise_errors and isinstance(response, ErrorResponse):
            response.raise_error()

        return response

    def get_new_request_id(self): # AzureusConnection
        if self.request_id is None:

            # We use long to force it to be an integer.
            import time
            self.request_id = long(time.time())

        self.request_id += 1
        return self.request_id

class ExtendedAzureusConnection(AzureusConnection):

    def __init__(self): # ExtendedAzureusConnection
        AzureusConnection.__init__(self)
        self.connection_data = {}
        self._plugin_interface_id = None

    def invoke_remote_method(self, object_id, method_name, method_args, raise_errors=True): # ExtendedAzureusConnection
        try:
            response = AzureusConnection.invoke_remote_method(self, object_id, method_name, method_args, raise_errors)
        except InvalidObjectIDError:
            if self.is_connection_valid():
                raise InvalidRemoteObjectError
            else:
                raise InvalidConnectionIDError

        if object_id is None and method_name == 'getSingleton' and not method_args and isinstance(response, StructuredResponse):
            self._plugin_interface_id = response.get_object_id()

        return response

    def establish_connection(self, force=True): # ExtendedAzureusConnection
        '''
        Establishes a connection with the Azureus server.

        By invoking this method, this will ensure that other methods defined
        by this class work correctly, as it will have references both to a
        connection ID, and the ID for the plugin interface.

        The C{force} argument determines whether server communication must
        take place or not. If C{True}, then this object will communicate with
        the Azureus server - if C{False}, then this object will only
        communicate with the Azureus server if it has no recorded information
        about the plugin interface.

        This method has two uses, depending on the value of the C{force}
        argument - it can be used to ensure that there is a valid recorded
        connection in place (if force is C{True}), or it can be used just
        to ensure that other methods on this class will behave properly (if
        force is C{False}).

        If a new connection is established, then the L{_on_reconnect} method
        will be invoked.

        @param force: Boolean value indicating if communication with the server
           I{must} take place or not (default is C{True}).
        @return: None
        '''

        # If 'force' is not true - then we only make a call if we don't have
        # any stored reference to a plugin interface ID.
        if (not force) and (self._plugin_interface_id is not None):
            return

        # Our overridden implementation of this method will set
        # what we need.
        old_interface_id = self._plugin_interface_id
        response = self.invoke_remote_method(None, 'getSingleton', ())

        # If we had the ID for an old PluginInterface object, then
        # that probably signals a reconnection. So let's signal that.
        if old_interface_id is not None and \
            old_interface_id != self._plugin_interface_id:
            self._on_reconnect()

        return

    def _on_reconnect(self): # ExtendedAzureusConnection
        '''
        Hook for subclasses to be notified whenever a new connection has been
        made.
        '''
        pass

    def is_connection_valid(self): # ExtendedAzureusConnection

        '''
        Returns a boolean value indicating if the current connection is
        still valid.

        @invariant: This connection to have already been I{established}.
        @raise NoEstablishedConnectionError: If this connection has not been
           established.
        @return: C{True} if the current established connection is still valid,
           C{False} otherwise.
        '''

        if self._plugin_interface_id is None:
            raise NoEstablishedConnectionError

        # Try to invoke this method on the remote PluginInterface object.
        try:
            AzureusConnection.invoke_remote_method(self, self._plugin_interface_id, '_refresh', ())
        except InvalidObjectIDError:
            return False
        else:
            return True

    def __str__(self): # ExtendedAzureusConnection
        result = super(ExtendedAzureusConnection, self).__str__()
        if self.connection_data.has_key('name') and \
            self.connection_data.has_key('version'):
            result += " [%(name)s %(version)s]" % self.connection_data
        return result

    def update_connection_details(self, connection_id=None, connection_data={}): # ExtendedAzureusConnection
        super(ExtendedAzureusConnection, self).update_connection_details(connection_id)
        self.connection_data.update(connection_data)

    def get_azureus_version(self): # ExtendedAzureusConnection
        '''
        @since: DOPAL 0.56
        '''
        try:
            az_version = self.connection_data['version']
        except KeyError:
            raise NoEstablishedConnectionError
        else:
            import dopal.utils
            return dopal.utils.parse_azureus_version_string(az_version)


# Use of this name is deprecated, and this alias will be removed in later
# versions of DOPAL.
ReusableAzureusConnection = ExtendedAzureusConnection
