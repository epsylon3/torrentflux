# File: aztypes.py
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
Utilities used to convert basic values between DOPAL and Azureus.
'''

__pychecker__ = 'no-unreachable'

import types
from dopal.errors import ArgumentWrapError, WrapError, UnwrapError, \
    InvalidWrapTypeError, InvalidUnwrapTypeError, InconsistentWrapTypeError, \
    NoSuchAzMethodError, IncorrectArgumentCountError, MethodArgumentWrapError,\
    raise_as

#
# Methods / classes related to type wrappers
#
class TypeWrapper:

    def __init__(self, xml_type, xml_value): # TypeWrapper
        self.xml_type = xml_type
        self.xml_value = xml_value

    def get_xml_type(self): # TypeWrapper
        return self.xml_type

    def as_xml(self): # TypeWrapper
        return self.xml_value

# Stores the type name to _wrapobject mappings.
_wrap_value_dict = {}
class _wrapobject(object):

    # If it is a native type, then it means that you can wrap the
    # object without having to return a TypeWrapper instance.
    native_type = False

    def __init__(self, type_name, instance_type): # _wrapobject
        self.type_name = type_name
        self.instance_type = instance_type
        _wrap_value_dict[type_name] = self

    def __call__(self, value): # _wrapobject
        return self.wrap(value)

    # Only used if the value is not an instance of the type specified in
    # instance_type.
    def normalise_for_wrapping(self, value): # _wrapobject
        raise WrapError("invalid type of object for %s: %s" % (self.type_name, type(value)), obj=value)

    # Converts the value into a form where it can be added to a wrapper
    # (usually as a string). For certain types (native types, like String,
    # integer, long, float, boolean), it can return a value with one of those
    # types instead.
    def wrap_value(self, value): # _wrapobject
        if not isinstance(value, types.StringTypes):
            value = str(value)
        return value

    def wrap(self, value, force_wrapper=True): # _wrapobject

        if self.instance_type is None or not isinstance(value, self.instance_type):
            try:
                value = self.normalise_for_wrapping(value)
            except (TypeError, ValueError), error:
                raise_as(error, WrapError)

        if self.native_type and not force_wrapper:
            return value

        if self.wrap_value is None:
            raise InvalidWrapTypeError(obj=self.type_name)

        value = self.wrap_value(value)
        return TypeWrapper(self.type_name, self.wrap_value(value))

    def unwrap(self, value): # _wrapobject
        if self.unwrap_value is None:
            raise InvalidUnwrapTypeError(obj=self.type_name)

        try:
            value = self.unwrap_value(value)
        except (TypeError, ValueError), error:
            raise UnwrapError, error

        return value

    def unwrap_value(self, value): # _wrapobject
        return value

    def __str__(self): # _wrapobject
        return '<wrapper object for type "%s">' % self.type_name

class _wrapnative(_wrapobject):
    native_type = True

    # I know that it is confusing that the arguments are swapped round...
    def __init__(self, type_obj):
        _wrapobject.__init__(self, type_obj.__name__, type_obj)
        self.normalise_for_wrapping = type_obj
        self.unwrap_value = type_obj
        self.wrap_value = type_obj

def _value_as_boolean(value):

    # Although this library is intended to be Python 2.2 compatible (i.e.
    # before booleans were introduced), we only support versions of Python 2.2
    # where False and True global constants are defined.
    if value in [False, 0, 'False', 'false']:
        return False
    elif value in [True, 1, 'True', 'true']:
        return True
    else:
        raise ValueError, "does not represent a boolean value"

def _boolean_as_string(value):
    if value:
        return 'true'
    else:
        return 'false'

wrap_int   = _wrapnative(int)
wrap_long  = _wrapnative(long)
wrap_float = _wrapnative(float)

# We create it without passing an instance type, and then attempt to assign it
# afterwards - Python 2.2 compatibility.
wrap_boolean = _wrapobject('boolean', None)
import sys
if sys.version_info >= (2, 3):
    wrap_boolean.instance_type = bool
    wrap_boolean.native_type = True
wrap_boolean.normalise_for_wrapping = _value_as_boolean
wrap_boolean.wrap_value = _boolean_as_string

# This function is a bit overkill for our needs, but it will suffice for now.
wrap_boolean.unwrap_value = _value_as_boolean

# We don't set normalise_for_wrapping, we want to impose a string type here.
wrap_url = _wrapobject('URL', types.StringTypes)
wrap_file = _wrapobject('File', types.StringTypes)

wrap_short = _wrapobject('short', int)
wrap_short.normalise_for_wrapping = int
wrap_short.wrap_value = int
wrap_short.unwrap_value = int

from dopal.utils import string_to_hex_form, hex_string_to_binary

# None - let the below functions deal with non string values.
wrap_byte_array = _wrapobject('byte[]', None)
wrap_byte_array.normalise_for_wrapping = string_to_hex_form
wrap_byte_array.unwrap_value = hex_string_to_binary
del string_to_hex_form, hex_string_to_binary

wrap_string = _wrapobject('String', types.StringTypes)
wrap_string.normalise_for_wrapping = str
wrap_string.native_type = True

def _unwrap_void(value):
    if value == '':
        return None
    else:
        raise ValueError, "non-null return value: %s" % value

wrap_void = _wrapobject('void', None)
wrap_void.wrap_value = None # Not an argument type.
wrap_void.unwrap_value = _unwrap_void

def wrap_value(value, value_type, force_wrapper=False):
    if hasattr(value, 'get_xml_type'):
        stored_type = value.get_xml_type()
        if stored_type != value_type:
            raise InconsistentWrapTypeError(stored_type, value_type)
        return value

    try:
        converter = _wrap_value_dict[value_type]
    except KeyError:
        raise InvalidWrapTypeError(obj=value_type)
    else:
        return converter.wrap(value, force_wrapper=force_wrapper)

def unwrap_value(value, value_type):
    try:
        converter = _wrap_value_dict[value_type]
    except KeyError:
        raise InvalidUnwrapTypeError(obj=value_type)
    else:
        return converter.unwrap(value)

def is_java_argument_type(java_type):
    return getattr(_wrap_value_dict.get(java_type), 'wrap_value', None) is not None

def is_java_return_type(java_type):
    return getattr(_wrap_value_dict.get(java_type), 'unwrap_value', None) is not None

def get_component_type(java_type):
    if isinstance(java_type, str):
        if java_type[-2:] == '[]':
            return java_type[:-2]
    return None

def get_basic_component_type(java_type):
    while True:
        component_type = get_component_type(java_type)
        if component_type is None:
            return java_type
        java_type = component_type

    # This can't happen, but it just stops PyChecker getting worried about it.
    return None

def is_array_type(java_type):
    return get_component_type(java_type) is not None

class AzMethod(object):

    def __init__(self, name, arguments=(), return_type='void'): # AzMethod
        object.__init__(super)
        self.name = name
        self.arg_types = arguments
        self.arg_count = len(arguments)
        self.return_type = return_type

    def has_return_type(self): # AzMethod
        return self.return_type == 'void'

    def wrap_args(self, *args): # AzMethod
        if len(args) != self.arg_count:
            raise IncorrectArgumentCountError, (self.name, len(args), self.arg_count)

        result = []
        for i in range(self.arg_count):
            try:
                result.append(wrap_value(args[i], self.arg_types[i]))
            except WrapError, error:
                raise ArgumentWrapError(i, args[i], self.arg_types[i], error)

        return result

    def __eq__(self, other): # AzMethod
        if not isinstance(other, AzMethod):
            return False
        for attr in ['name', 'return_type', 'arg_types']:
            if getattr(self, attr) != getattr(other, attr):
                return False
        return True

    def __ne__(self, other): # AzMethod
        return not (self == other)

    def __str__(self): # AzMethod
        arg_string = ', '.join(self.arg_types)
        return "%s %s(%s)" % (self.return_type, self.name, arg_string)

    def __repr__(self): # AzMethod
        return "AzMethod [%s]" % self

class AzureusMethods(object):

    def __init__(self, methods=None):
        object.__init__(self)
        self.__data = {}
        if methods:
            map(self.add_method, methods)

    def add_method(self, az_method):
        methods_dict = self.__data.setdefault(az_method.name, {})
        method_args_seq = methods_dict.setdefault(az_method.arg_count, [])

        if az_method not in method_args_seq:
            method_args_seq.append(az_method)

    def get_method_names(self):
        names = self.__data.keys()
        names.sort()
        return names

    def get_method_arg_count(self, name):
        try:
            result = self.__data[name].keys()
        except KeyError:
            return []
        else:
            result.sort()
            return result

    def match_method_spec(self, name, argcount):
        try:
            return self.__data[name][argcount]
        except KeyError:
            try:
                argcounts_dict = self.__data[name]
            except KeyError:
                raise NoSuchAzMethodError, name
            argcounts = argcounts_dict.keys()
            argcounts.sort()
            raise IncorrectArgumentCountError, (name, argcount, argcounts_dict.keys())

    def get_matching_methods(self, name, args):
        methods = self.match_method_spec(name, len(args))
        accepted_methods = {}
        rejected_methods = {}
        for methodobj in methods:
            try:
                accepted_methods[methodobj] = methodobj.wrap_args(*args)
            except ArgumentWrapError, error:
                rejected_methods[methodobj] = error

        if not accepted_methods:
            raise MethodArgumentWrapError, (name, rejected_methods.items())

        return accepted_methods.items()

    def wrap_args(self, name, args):
        method_data_tpls = self.get_matching_methods(name, args)
        if len(method_data_tpls) > 1:
            method_data = self.resolve_ambiguous_method(args, method_data_tpls)
        else:
            method_data = method_data_tpls[0]

        return method_data[1], method_data[0].return_type

    def resolve_ambiguous_method(self, args, method_data_tpls):
        # XXX: We'll implement something cleverer at a later date.
        return method_data_tpls[0]

    def get_all_methods(self):
        result = []
        for method_name, method_dict in self.__data.items():
            for argcount, method_seq in method_dict.items():
                result.extend(method_seq)
        return result

    def update(self, azmethodsobj):
        for azmethod in azmethodsobj.get_all_methods():
            self.add_method(azmethod)
