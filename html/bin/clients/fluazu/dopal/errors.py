# File: errors.py
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
Module containing all errors defined in DOPAL.
'''

def as_error(error, error_class, **kwargs):
    if not isinstance(error, error_class):
        error = error_class(error=error, **kwargs)
    return error

def raise_as(error, error_class, **kwargs):
    import sys
    raise as_error(error, error_class, **kwargs), None, sys.exc_info()[2]

class DopalError(Exception):
    "Subclass of all errors in the Dopal library"

    def __init__(self, *args, **kwargs):

        if len(args) + len(kwargs) > 3:
            raise ValueError, "DopalError.__init__ takes at most 3 arguments - %s positional argument(s) given, %s keyword argument(s) given" % (len(args), len(kwargs))

        # Filters out invalid keywords.
        from dopal.utils import handle_kwargs
        handle_kwargs(kwargs, error=None, obj=None, text=None)

        error = obj = text = None
        has_error = has_object = has_text = False

        import types
        for kwname, kwvalue in kwargs.items():

            if kwname == 'error':
                if not isinstance(kwvalue, Exception):
                    msg = "'error' keyword argument is not Exception: %r"
                    raise TypeError, msg % (kwvalue,)
                has_error = True
                error = kwvalue

            elif kwname == 'text':
                if not isinstance(kwvalue, types.StringTypes):
                    msg = "'text' keyword argument is not a String type: %r"
                    raise TypeError, msg % (kwvalue,)
                has_text = True
                text = kwvalue

            else: # kwname == 'obj'
                has_object = True
                obj = kwvalue

        import types
        for arg in args:
            if isinstance(arg, Exception) and not has_error:
                has_error = True
                error = arg
            elif isinstance(arg, types.StringTypes) and not has_text:
                has_text = True
                text = arg
            else:
                if has_object:
                    msg = "could not determine Dopal argument type for %r"
                    raise TypeError, msg % (arg,)
                has_object = True
                obj = arg

        dopal_arg_tuple = args
        if kwargs:
            dopal_arg_tuple += tuple(kwargs.values())

        dopal_arg_dict = {}
        if has_error:
            dopal_arg_dict['error'] = error
        if has_object:
            dopal_arg_dict['object'] = obj
        if has_text:
            dopal_arg_dict['text'] = text

        self.dopal_arg_tuple = dopal_arg_tuple
        self.dopal_arg_dict = dopal_arg_dict
        self.error = error
        self.obj = obj
        self.text = text
        self.has_object = has_object
        self.has_error = has_error
        self.has_text = has_text

        #super(DopalError, self).__init__(dopal_arg_tuple)
        Exception.__init__(self, *dopal_arg_tuple)

    def __str__(self):

        # Allow the subclass to render the string if:
        #   1) self.args is not the tuple that this class passed to the super
        #      constructor; or
        #   2) We have 2 or more values given to us - the default behaviour for
        #      rendering the string by the superclass for one or no arguments
        #      is fine.
        if self.args != self.dopal_arg_tuple or \
            len(self.args) < 2:
            #return super(DopalError, self).__str__()
            return Exception.__str__(self)

        if not self.has_error:
            tmpl = "%(text)s (%(object)r)"

        elif not self.has_object:
            tmpl = "%(text)s - %(error)s"

        elif not self.has_text:
            tmpl = "%(error)s (%(object)r)"

        else:
            tmpl = "%(text)s - %(error)s (%(object)r)"

        return tmpl % self.dopal_arg_dict

    def __repr__(self):
        arg_parts = ["%s=%r" % item_tpl for item_tpl in self.dopal_arg_dict.items()]
        return "%s(%s)" % (self.__class__.__name__, ', '.join(arg_parts))

    # An alternative to str() of the object - this will make a string of the
    # form:
    #    ErrorClassName: <error output>
    #
    # (This is similar to the last line you see of a traceback). The main
    # difference here is that we will use the class name of the internal error
    # if there is one, otherwise we will use the class name of the object
    # itself.
    #
    # The error output will be the same string as you get when you apply str()
    # to this object.
    #
    # Setting use_error to False will force it to always ignore the internal
    # error.
    def to_error_string(self, use_error=True):
        if use_error and self.has_error:
            error_to_use = self.error
        else:
            error_to_use = self

        error_output = str(self)

        result = error_to_use.__class__.__name__
        if error_output:
            result += ": " + error_output

        return result

#---- core module ----#

class LinkError(DopalError):
    "Error communicating with Azureus (low-level)"

class RemoteError(DopalError): # Base class.
    "Error reported by Azureus"

class RemoteInvocationError(RemoteError): # Base class.
    "Unable to invoke remote method"

class NoSuchMethodError(RemoteInvocationError):
    """
    This error is thrown when Azureus reports that the requested method does
    not exist, or is not allowed.

    A NoSuchMethodError is a representation of the response Azureus returns
    when trying to invoke a method, but is unable to map the requested method
    to a method it can actually invoke.

    Causes
    ======

      There are various reasons why this error may occur, but here are the
      most likely.

      Wrong method requested
      ----------------------
      The wrong method signature was used - this is possible for a variety
      of reasons (though it isn't likely). Check that the method you want to
      use is the same one being reported in the NoSuchMethodError instance.

      Method not available in this version of Azureus
      -----------------------------------------------
      This method may not be available in the version of Azureus you are
      using - although DOPAL normally supports all methods made available in
      the latest beta release, this error will occur if the version of Azureus
      does not support that method.

      XML/HTTP request processor may not support this method
      ------------------------------------------------------
      The request processor used by Azureus may not be able to resolve that
      method. Versions 2.3.0.6 and older only allow a small subset of methods
      defined in the plugin API to be called. Version 2.4.0.0 (as well as some
      later beta versions of 2.3.0.7) have been changed to allow any method to
      be called. To enable this, go to the XML/HTTP plugin configuration page,
      and tick the I{"Advanced Settings -> Use generic classes"} setting.

      Non read-only method requested, but XML/HTTP in view mode
      ---------------------------------------------------------
      The XML/HTTP plugin in Azureus is set up to be in "view" mode, so only
      certain methods are allowed. Note - if you are unable to call a method
      which you think should be allowed in read only mode, contact the
      developers of the XML/HTTP plugin.

    @ivar obj: This will be a string which describes the method signature
      which was requested - for example::
         getDownloads
         setPosition[int]
         setTorrentAttribute[TorrentAttribute,String]
    """

    def __init__(self, method_sig):
        """
        Creates a new NoSuchMethodError instance.
        """
        RemoteInvocationError.__init__(self, obj=method_sig)

class NoObjectIDGivenError(DopalError, ValueError):
    "No object ID given when needed"

class NoEstablishedConnectionError(DopalError, TypeError):
    "Connection object has no valid connection established"

# Raised by generate_remote_error (which means it is indirectly raised in
# AzureusConnection.invoke_remote_method).
#
# These errors are masked by ExtendedAzureusConnection.invoke_remote_method
# who throw one of the subclass errors (InvalidRemoteObjectError and
# InvalidConnectionIDError).
#
# This error shouldn't arise if you are using a ExtendedAzureusConnection or
# higher-level connection object.
class InvalidObjectIDError(RemoteInvocationError):
    "Invalid remote object ID given (bad object or bad connection)"

class InvalidRemoteObjectError(InvalidObjectIDError):
    "Invalid remote object ID used"

class InvalidConnectionIDError(InvalidObjectIDError):
    "Invalid connection ID used"

class MissingObjectIDError(RemoteInvocationError):
    "Missing object ID"

# Raised by generate_remote_error (which means it is indirectly raised in
# AzureusConnection.invoke_remote_method).
#
# Higher-level connections (like AzureusObjectConnection) may raise subclasses
# of this error, if they are able to give a more precise error can be
# determined.
class RemoteMethodError(RemoteError):
    "Error thrown by remote method"

class RemoteInternalError(RemoteError):
    "Internal error occurred during remote method invocation"

class AzureusResponseXMLError(DopalError):
    "Error while parsing XML returned by Azureus"

#---- core module ----#

#---- types module ----#

class ConversionError(DopalError): # Base class.
    "Error converting value (Azureus <--> Python)"

class WrapError(ConversionError):
    "Error converting value to remote method argument"

class UnwrapError(ConversionError):
    "Error converting remote method result to Python value"

class InvalidWrapTypeError(WrapError, TypeError):
    '''
    Invalid wrap type given.

    This error is raised when a value is passed which cannot be converted into
    something that can be represented in Azureus. This either means that the
    value doesn't meet the criteria as something which can be represented, or
    the value doesn't fit the type that it is being wrapped as (e.g. a
    non-integer string as a integer).

    @see: L{wrap_value<dopal.aztypes.wrap_value>}
    @see: L{remote_method_call_to_xml<dopal.core.remote_method_call_to_xml>}
    '''

class InvalidUnwrapTypeError(UnwrapError, TypeError):
    "Invalid unwrap type given."

class InconsistentWrapTypeError(WrapError, TypeError):
    "Object has wrap type different to requested type"

#---- types module ----#

#---- types (AzMethod) module ----#

class AzMethodError(DopalError): # Base class.
    "Error selecting matching AzMethod"

    def __init__(self, obj, *args, **kwargs):
        kwargs['obj'] = obj
        self.method_name = obj
        DopalError.__init__(self, *args, **kwargs)

class IncorrectArgumentCountError(AzMethodError, TypeError):
    "Wrong number of arguments given for AzMethod"

    def __init__(self, obj, given_arg_count, required_arg_count):
        TypeError.__init__(self)

        # self.required_arg_count is a list
        # required_count is used for the 'text' variable

        self.given_arg_count = given_arg_count

        if isinstance(required_arg_count, (int, long)):
            self.required_arg_count = [required_arg_count]
            required_count = required_arg_count

        elif len(required_arg_count) == 1:
            self.required_arg_count = required_arg_count
            required_count = required_arg_count[0]

        else:
            self.required_arg_count = required_arg_count
            required_count = required_arg_count
            required_count = list(required_count)
            required_count.sort()

        text = "wrong number of arguments given (wanted %(required_count)s, given %(given_arg_count)s)" % locals()

        AzMethodError.__init__(self, obj, text=text)

class ArgumentWrapError(AzMethodError):
    "Error wrapping argument for AzMethod"

    def __init__(self, arg_index, value, arg_type, error):
        text = "error converting arg %(arg_index)s to %(arg_type)s" % locals()
        AzMethodError.__init__(self, obj=value, error=error, text=text)
        self.arg_index = arg_index
        self.arg_type = arg_type

class NoSuchAzMethodError(AzMethodError, AttributeError):
    "No method of that name available"
    def __init__(self, *args, **kwargs):
        AttributeError.__init__(self)
        AzMethodError.__init__(self, *args, **kwargs)

class MethodArgumentWrapError(AzMethodError):
    "Error wrapping argument for multiple AzMethods"

    def __init__(self, name, invocation_errors):
        AzMethodError.__init__(self, name)
        self.invocation_errors = invocation_errors

    def __str__(self):
        text = "Error wrapping arguments:"
        error_data = [(str(method_data), str(error.__class__.__name__), str(error)) for (method_data, error) in self.invocation_errors]

        error_data.sort()
        for method_data, err_class, error in error_data:
            text += "\n  %(method_data)s - %(err_class)s: %(error)s" % locals()

        return text



#---- types  (AzMethod) module ----#

#---- objects module ----#

class ConnectionlessObjectError(DopalError):
    "Object has no remote connection"

class NonRefreshableObjectError(DopalError): # Base class
    "Object cannot be refreshed - refresh not implemented"

class NonRefreshableConnectionlessObjectError(NonRefreshableObjectError, ConnectionlessObjectError):
    "Object cannot be refreshed - no connection attached"

    def __init__(self, *args, **kwargs):
        NonRefreshableObjectError.__init__(self)
        ConnectionlessObjectError.__init__(self, *args, **kwargs)
        #_superclass = super(NonRefreshableConnectionlessObjectError, self)
        #_superclass.__init__(*args, **kwargs)

class NonRefreshableObjectTypeError(NonRefreshableObjectError):
    "Object cannot be refreshed - not implemented for this type"

class NonRefreshableIncompleteObjectError(NonRefreshableObjectError):
    "Object cannot be refreshed - insufficient information on object"

class StaleObjectReferenceError(NonRefreshableObjectError):
    "Object used belongs to old connection, which doesn't have persistency enabled"

class MissingRemoteAttributeError(DopalError, AttributeError):
    "Object does not have remote attribute available"

#---- objects module ----#

#---- convert module ----#

class InvalidRemoteClassTypeError(DopalError, TypeError):
    "Invalid remote class type given"

# Base exception class - used when something cannot be converted.
class StructureConversionError(ConversionError): # Base class.
    "Error converting response structure"

# Base class for flow control exceptions.
class ConversionControl(StructureConversionError): # Base class.
    "Base class for structured conversion control"

# Use this class if you want to skip converting the object which
# is being handled.
class SkipConversion(ConversionControl):
    "Structured conversion of object skipped"

# Use this class if you want to stop converting the object which
# is being handled (essentially signalling a somewhat "fatal" error).
class AbortConversion(ConversionControl):
    "Structured conversion of object aborted"

# Use this class if you want to signal that you need more information
# before you can proceed with the conversion - either that you need
# the items lower down to be converted first, or you need the items
# higher up converted first.
class DelayConversion(ConversionControl):
    "Structured conversion of object delayed"

# Use this class if you want to halt conversion completely - this is a more
# severe form of AbortConversion, where it won't be passed to
# Converter.handle_errors.
class HaltConversion(ConversionControl):
    "Structured conversion of object halted"

class DopalDeprecationWarning(DeprecationWarning, DopalError):
    pass

# PendingDeprecationWarning class doesn't exist in Python 2.2.
try:
    class DopalPendingDeprecationWarning(DopalDeprecationWarning, PendingDeprecationWarning):
        pass
except NameError:
    class DopalPendingDeprecationWarning(DopalDeprecationWarning):
        pass

class NoDefaultScriptConnectionError(DopalError):
    pass

class ScriptFunctionError(DopalError):
    "Error occurred inside script function."
