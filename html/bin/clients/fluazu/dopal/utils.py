# File: utils.py
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
General utility functions.
'''

__pychecker__ = 'unusednames=metaclass_,attr_,no-unreachable'

# Converts a binary string character into a hexidecimal equivalent.
#
# >>> byte_to_hex_form('N')
# u'4E'
def byte_to_hex_form(byte):
    # ord is used to convert the byte into it's numeric equivalent.
    # hex will turn the number into a string like "0x5" or "0xa3"
    # the slicing will chop off the "0x" prefix.
    # zfill will change any single digit values to have a leading 0 character.
    # upper will force all the A-F characters to be uppercase.
    return unicode(hex(ord(byte))[2:].zfill(2).upper())

# Converts a binary string into a hexidecimal equivalent.
#
# >>> string_to_hex_form('xK3-')
# u'784B332D'
def string_to_hex_form(chars):
    if not chars:
        raise ValueError, "cannot convert empty string"
    return ''.join([byte_to_hex_form(char) for char in chars])

# Converts a 2 character hexidecimal string into a binary character.
#
# >>> hex_form_to_byte(u'4E')
# 'L'
def hex_pair_to_byte(hex_pair):
    return chr(int(hex_pair.encode('utf-8'), 16))

# Converts a hexidecimal string (a string containing only characters valid
# in use for displaying a hexidecimal number, i.e. 0123456789ABCDEF) into
# a binary string.
#
# >>> hex_string_to_binary(u'784B332D')
# 'xK3-'
def hex_string_to_binary(hex_string):
    if len(hex_string) % 2:
        raise ValueError, "string given has odd-number of characters, must be even"
    if not hex_string:
        raise ValueError, "cannot convert empty string"
    return ''.join([hex_pair_to_byte(hex_string[i:i+2]) for i in range(0, len(hex_string), 2)])

def make_short_object_id(object_id):
    # Due to the way Azureus generates object ID's (it tends to be a
    # long integer which is then incremented for each object it
    # generates), we don't bother rendering the entire object ID
    # as it would be too long (it can be as long as 20 characters).
    #
    # So instead, we only use the last 6 digits - turning the ID into
    # hexidecimal. This gives us a range of 16**6 = 16.7 million. So
    # Azureus would have to generate more than 16 million objects
    # before it generates an ID which has the same short ID form as
    # another object.
    #
    # We show the short ID as a way of easily seeing whether two
    # objects represent the same remote object or not.
    hex_id = hex(object_id)

    if hex_id[-1] == 'L':
        hex_short_id = hex_id[-7:-1]
    else:
        hex_short_id = hex_id[-6:]

    return hex_short_id

def parse_azureus_version_string(ver_string):
    ver_bits = ver_string.split('_', 2)
    if len(ver_bits) == 1:
        major_ver, minor_ver = ver_string, None
    else:
        major_ver, minor_ver = ver_bits

    ver_segments = [int(bit) for bit in major_ver.split('.')]
    if minor_ver:
        if minor_ver[0].lower() == 'b':
            ver_segments.append('b')
            try:
                beta_ver = int(minor_ver[1:])
            except ValueError:
                pass
            else:
                ver_segments.append(beta_ver)

    return tuple(ver_segments)

#
# I love this code. :)
#
# I might turn it into something more generic, and use it elsewhere..
#
# Would be nicer if there was a better API for doing this, but given the amount
# of hackery that I'm doing right now, I won't complain. :)
#
# What a lot of effort just to act as if these methods were defined in the
# class itself.
import new
class MethodFactory(object):

    def __init__(self, method_object):
        _codeobj = method_object.func_code
        code_arguments = [
            _codeobj.co_argcount, _codeobj.co_nlocals, _codeobj.co_stacksize,
            _codeobj.co_flags, _codeobj.co_code, _codeobj.co_consts,
            _codeobj.co_names, _codeobj.co_varnames, _codeobj.co_filename,
            _codeobj.co_name, _codeobj.co_firstlineno, _codeobj.co_lnotab,
        ]
        self.code_arguments = code_arguments

    def _build_function(self, name):
        code_args = self.code_arguments[:]
        code_args[9] = name
        # code_args[8] = <modulename>
        codeobj = new.code(*code_args)
        return new.function(codeobj, {'__funcname__': name, '__builtins__': __builtins__}, name)

    def make_instance_method(self, name, instanceobj):
        method = self._build_function(name)
        return new.instancemethod(method, instanceobj, type(instanceobj))

    def make_class_method(self, name, classobj):
        method = self._build_function(name)
        return new.instancemethod(method, None, classobj)

def _not_implemented(self, *args, **kwargs):
    class_name = self.__class__.__name__
    funcname = __funcname__
    raise NotImplementedError, "%(class_name)s.%(funcname)s" % locals()

not_implemented_factory = MethodFactory(_not_implemented)
make_not_implemented_class_method = not_implemented_factory.make_class_method
del _not_implemented, not_implemented_factory

def handle_kwargs(kwargs, *required, **optional):
    result = {}
    result.update(optional)

    required_args = dict([(x, None) for x in required])

    for kwarg_key, kwarg_value in kwargs.iteritems():
        if optional.has_key(kwarg_key):
            pass
        else:
            try:
                required_args.pop(kwarg_key)
            except KeyError:
                raise TypeError, "unexpected keyword argument: %r" % kwarg_key

        result[kwarg_key] = kwarg_value

    if required_args:
        missing_key = required_args.popitem()[0]
        raise TypeError, "missing keyword argument: %r" % missing_key

    return result

class Sentinel(object):

    def __init__(self, value):
        self.value = value

    def __str__(self):
        return str(self.value)

    def __repr__(self):
        return '<sentinel object (%r) at 0x%08X>' % (self.value, id(self))
