# File: convert.py
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
Contains classes used to convert an XML structure back into an object
structure.
'''

# We disable the override checks (subclasses of mixins are allowed to have
# different signatures - arguments they want can be explicitly named, arguments
# they don't want can be left unnamed in kwargs).
#
# We also disable the class attribute checks - converter calls a lot of methods
# which are only defined in mixin classes.
__pychecker__ = 'unusednames=attributes,kwargs,object_id,value,self no-override no-classattr no-objattr'

from dopal.aztypes import is_array_type, get_component_type, \
    is_java_argument_type, is_java_return_type
from dopal.classes import is_azureus_argument_class, is_azureus_return_class
from dopal.errors import AbortConversion, DelayConversion, SkipConversion, \
    InvalidRemoteClassTypeError

import types

from dopal.utils import Sentinel
ATOM_TYPE = Sentinel('atom')
SEQUENCE_TYPE = Sentinel('sequence')
MAPPING_TYPE = Sentinel('mapping')
OBJECT_TYPE = Sentinel('object')
NULL_TYPE = Sentinel('null')
del Sentinel

class Converter(object):

    def __call__(self, value, result_type=None):
        return self.convert(value, source_parent=None, target_parent=None,
            attribute=None, sequence_index=None, suggested_type=result_type)

    def convert(self, value, **kwargs):

        # The keyword arguments we have here include data for the reader and
        # the writer. We separate kwargs into three parts -
        #   1) Reader-only values.
        #   2) Writer-only values.
        #   3) All keyword arguments.
        reader_kwargs = kwargs.copy()
        writer_kwargs = kwargs.copy()
        convert_kwargs = kwargs
        del kwargs

        writer_kwargs['parent'] = writer_kwargs['target_parent']
        reader_kwargs['parent'] = reader_kwargs['source_parent']
        del reader_kwargs['target_parent']
        del reader_kwargs['source_parent']
        del writer_kwargs['target_parent']
        del writer_kwargs['source_parent']

        # Keyword arguments:
        #   attribute
        #   mapping_key
        #   sequence_index
        #   suggested_type
        #
        #   parent (reader_kwargs and writer_kwargs, not in standard kwargs)
        #   source_parent (not in reader_kwargs, not in writer_kwargs)
        #   target_parent (not in reader_kwargs, not in writer_kwargs)
        conversion_type = self.categorise_object(value, **reader_kwargs)
        if conversion_type is NULL_TYPE:
            return None

        elif conversion_type is ATOM_TYPE:
            atomic_value = self.get_atomic_value(value, **reader_kwargs)
            return self.convert_atom(atomic_value, **writer_kwargs)

        elif conversion_type is SEQUENCE_TYPE:
            accepted_seq = []
            rejected_seq = []

            # The item we are currently looking at (value) is ignored.
            # It is a normal sequence which doesn't contain any useful
            # data, so we act as if each item in the sequence is
            # actually an attribute of the source object (where the
            # attribute name is taken from the attribute name of the
            # list).

            # Note - I would use enumerate, but I'm trying to leave this
            # Python 2.2 compatible.
            sequence_items = self.get_sequence_items(value, **reader_kwargs)
            for i in range(len(sequence_items)):
                item = sequence_items[i]

                this_kwargs = convert_kwargs.copy()
                this_kwargs['sequence_index'] = i
                this_kwargs['suggested_type'] = self.get_suggested_type_for_sequence_component(value, **writer_kwargs)

                try:
                    sub_element = self.convert(item, **this_kwargs)
                except SkipConversion, error:
                    pass
                except AbortConversion, error:
                    import sys
                    self.handle_error(item, error, sys.exc_info()[2])
                    rejected_seq.append((item, error, sys.exc_info()[2]))
                else:
                    accepted_seq.append(sub_element)

                del this_kwargs

            if rejected_seq:
                self.handle_errors(rejected_seq, accepted_seq, conversion_type)

            return self.make_sequence(accepted_seq, **writer_kwargs)

        elif conversion_type is MAPPING_TYPE:
            accepted_dict = {}
            rejected_dict = {}

            for map_key, map_value in self.get_mapping_items(value, **reader_kwargs):
                this_kwargs = convert_kwargs.copy()
                this_kwargs['mapping_key'] = map_key
                this_kwargs['suggested_type'] = self.get_suggested_type_for_mapping_component(value, **this_kwargs)
                try:
                    converted_value = self.convert(map_value, **this_kwargs)
                except SkipConversion, error:
                    pass
                except AbortConversion, error:
                    import sys
                    self.handle_error(map_value, error, sys.exc_info()[2])
                    rejected_dict[map_key] = (map_value, error, sys.exc_info()[2])
                else:
                    accepted_dict[map_key] = converted_value

                del this_kwargs

            if rejected_dict:
                self.handle_errors(rejected_dict, accepted_dict, conversion_type)

            return self.make_mapping(accepted_dict, **writer_kwargs)

        elif conversion_type is OBJECT_TYPE:
            object_id = self.get_object_id(value, **reader_kwargs)
            source_attributes = self.get_object_attributes(value, **reader_kwargs)

            # Try and convert the object attributes first.
            #
            # If we can't, because the parent object is requested, then
            # we'll convert that first instead.
            #
            # If the code which converts the parent object requests that
            # the attributes should be defined first, then we just exit
            # with an error - we can't have attributes requesting that the
            # object is converted first, and the object requesting attributes
            # are converted first.
            try:
                attributes = self._get_object_attributes(value, None, source_attributes)
            except DelayConversion:
                # We will allow DelayConversions which arise from this block
                # to propogate.
                new_object = self.make_object(object_id, attributes=None, **writer_kwargs)
                attributes = self._get_object_attributes(value, new_object, source_attributes)
                self.add_attributes_to_object(attributes, new_object, **writer_kwargs)
            else:
                new_object = self.make_object(object_id, attributes, **writer_kwargs)

            return new_object

        else:
            raise ValueError, "bad result from categorise_object: %s" % conversion_type

    def _get_object_attributes(self, value, parent, source_attributes):

        accepted = {}
        rejected = {}

        for attribute_name, attribute_value in source_attributes.items():
            this_kwargs = {}
            this_kwargs['source_parent'] = value
            this_kwargs['target_parent'] = parent
            this_kwargs['attribute'] = attribute_name
            this_kwargs['mapping_key'] = None
            this_kwargs['sequence_index'] = None
            this_kwargs['suggested_type'] = self.get_suggested_type_for_attribute(value=attribute_value, parent=parent, attribute=attribute_name, mapping_key=None)

            try:
                converted_value = self.convert(attribute_value, **this_kwargs)
            except SkipConversion, error:
                pass
            except AbortConversion, error:
                import sys
                self.handle_error(attribute_value, error, sys.exc_info()[2])
                rejected[attribute_name] = (attribute_value, error, sys.exc_info()[2])
            else:
                accepted[attribute_name] = converted_value

        if rejected:
            self.handle_errors(rejected, accepted, OBJECT_TYPE)

        return accepted

    def handle_errors(self, rejected, accepted, conversion_type):
        if isinstance(rejected, dict):
            error_seq = rejected.itervalues()
        else:
            error_seq = iter(rejected)

        attribute, error, traceback = error_seq.next()
        raise error, None, traceback

    def handle_error(self, object_, error, traceback):
        raise error, None, traceback

class ReaderMixin(object):

    # Need to be implemented by subclasses:
    #
    # def categorise_object(self, value, suggested_type, **kwargs):
    # def get_object_id(self, value, **kwargs):
    # def get_object_attributes(self, value, **kwargs):

    # You can raise DelayConversion here.
    def get_atomic_value(self, value, **kwargs):
        return value

    def get_sequence_items(self, value, **kwargs):
        return value

    def get_mapping_items(self, value, **kwargs):
        return value

class WriterMixin(object):

    # Need to be implemented by subclasses:
    #
    # def make_object(self, object_id, attributes, **kwargs):

    # You can raise DelayConversion here.
    def convert_atom(self, atomic_value, suggested_type, **kwargs):
        if suggested_type is None:
            # TODO: Add controls for unknown typed attributes.
            return atomic_value
        else:
            from dopal.aztypes import unwrap_value
            return unwrap_value(atomic_value, suggested_type)

    def make_sequence(self, sequence, **kwargs):
        return sequence

    def make_mapping(self, mapping, **kwargs):
        return mapping

    def add_attributes_to_object(self, attributes, new_object, **kwargs):
        new_object.update_remote_data(attributes)

    def get_suggested_type_for_sequence_component(self, value, **kwargs):
        return None

    def get_suggested_type_for_mapping_component(self, value, **kwargs):
        return None

    def get_suggested_type_for_attribute(self, value, **kwargs):
        return None

class XMLStructureReader(ReaderMixin):

    def categorise_object(self, node, suggested_type, **kwargs):

        from xml.dom import Node

        if node is None:
            number_of_nodes = 0
            null_value = True
        elif isinstance(node, types.StringTypes):
            number_of_nodes = -1 # Means "no-node type".
            null_value = not node
        else:
            number_of_nodes = len(node.childNodes)
            null_value = not number_of_nodes

        # This is a bit ambiguous - how on earth are we meant to determine
        # this? We'll see whether an explicit type is given here, otherwise
        # we'll have to just guess.
        if null_value:
            if suggested_type == 'mapping':
                return MAPPING_TYPE
            elif is_array_type(suggested_type):
                return SEQUENCE_TYPE

            # If the suggested type is atomic, then we inform them that
            # it is an atomic object. Some atomic types make sense with
            # no nodes (like an empty string). Some don't, of course
            # (like an integer), but never mind. It's better to inform
            # the caller code that it is an atom if the desired type is
            # an atom - otherwise for empty strings, we will get None
            # instead.
            elif is_java_return_type(suggested_type):
                # We'll assume it is just an atom. It can't be an object
                # without an object ID.
                return ATOM_TYPE

            # Oh well, let's just say it's null then.
            else:
                return NULL_TYPE

        if number_of_nodes == -1:
            return ATOM_TYPE

        if number_of_nodes == 1 and node.firstChild.nodeType == Node.TEXT_NODE:
            return ATOM_TYPE

        if number_of_nodes and node.firstChild.nodeName == 'ENTRY':
            return SEQUENCE_TYPE

        if suggested_type == 'mapping':
            return MAPPING_TYPE

        return OBJECT_TYPE

    def get_atomic_value(self, node, **kwargs):
        if node is None:
            # The only atomic type which provides an empty response are
            # string types, so we will return an empty string.
            return ''
        elif isinstance(node, types.StringTypes):
            return node
        else:
            from dopal.xmlutils import get_text_content
            return get_text_content(node)

    def get_sequence_items(self, node, **kwargs):
        if node is None:
            return []
        return node.childNodes

    def get_mapping_items(self, node, **kwargs):
        if node is None:
            return {}

        # Not actually used, but just in case...
        result_dict = {}
        for child_node in node.childNodes:
            if result_dict.has_key(child_node.nodeName):
                raise AbortConversion("duplicate attribute - " + child_node.nodeName, child_node)
            result_dict[child_node.nodeName] = child_node
        return result_dict

    def get_object_id(node, **kwargs):
        if node is None:
            return None

        for child_node in node.childNodes:
            if child_node.nodeName == '_object_id':
                from dopal.xmlutils import get_text_content
                return long(get_text_content(child_node))
        else:
            return None

    # Used by StructuredResponse.get_object_id, so we make it static.
    get_object_id = staticmethod(get_object_id)

    def get_object_attributes(self, node, **kwargs):
        result_dict = self.get_mapping_items(node, **kwargs)
        for key in result_dict.keys():
            if key.startswith('azureus_'):
                del result_dict[key]
            elif key in ['_connection_id', '_object_id']:
                del result_dict[key]
        return result_dict

class ObjectWriterMixin(WriterMixin):

    connection = None

    # attributes may be None if not defined at this point.
    #
    # You can raise DelayConversion here.
    def make_object(self, object_id, attributes, suggested_type=None, parent=None, attribute=None, **kwargs):

        class_to_use = None
        if suggested_type is not None:
            class_to_use = self.get_class_for_object(suggested_type, attributes, parent, attribute)

        if class_to_use is None:
            class_to_use = self.get_default_class_for_object()
            if class_to_use is None:
                # TODO: Need to add control values:
                #   - Drop the attribute.
                #   - Put the attribute as is (convert it into a typeless
                #       object)
                #   - Raise an error.
                #
                # For now, we'll avoid creating the attribute altogether.
                #
                # Note - if the object has no parent, then that's a more
                # serious situation. We may actually be returning a blank
                # value instead of a representive object - in my opinion,
                # it is better to fail in these cases.
                #
                # A broken object (missing attributes) is more desirable than
                # having an object missing entirely if it is the actual object
                # being returned.
                if parent is None:
                    cls_to_use = AbortConversion
                else:
                    cls_to_use = SkipConversion

                raise cls_to_use(text="no default class defined by converter", obj=(parent, attribute, suggested_type))

                # Alternative error-based code to use:
                #
                #    err_kwargs = {}
                #    err_kwargs['obj'] = suggested_type
                #    if parent is None:
                #        if attribute is None:
                #            pass
                #        else:
                #            err_kwargs['text'] = 'attr=' + attribute
                #    else:
                #        err_kwargs['text'] = "%(parent)r.%(attribute)s" %
                #           locals()
                #    raise InvalidRemoteClassTypeError(**err_kwargs)

        result = class_to_use(self.connection, object_id)
        if attributes is not None:
            self.add_attributes_to_object(attributes, result)

        return result

    def get_class_for_object(self, suggested_type, attributes=None, parent=None, attribute=None):
        return None

    def get_default_class_for_object(self):
        return None

class RemoteObjectWriterMixin(ObjectWriterMixin):

    class_map = {}

    # XXX: This will need to be changed to something which will:
    #    - If true, raise an error if the parent does not return an appropriate
    #      type for any given attribute.
    #    - If false, will never complain.
    #    - If none (default), complain only when debug mode is on.
    force_attribute_types = False

    def get_class_for_object(self, suggested_type, attributes=None, parent=None, attribute=None):
        if suggested_type is None:
            return None
        return self.class_map.get(suggested_type, None)

    def get_default_class_for_object(self):
        if self.class_map.has_key(None):
            return self.class_map[None]
        _super = super(RemoteObjectWriterMixin, self)
        return _super.get_default_class_for_object()

    def get_suggested_type_for_sequence_component(self, value, suggested_type, **kwargs):
        if suggested_type is None:
            return None
        if is_array_type(suggested_type):
            return get_component_type(suggested_type)
        else:
            raise AbortConversion("parent of value is a sequence, but the suggested type is not an array type", obj=value)

    def _get_suggested_type_for_named_item(self, value, parent, attribute, mapping_key=None, **kwargs):
        if parent is None:
            raise DelayConversion

        result_type = None
        if hasattr(parent, '_get_type_for_attribute'):
            result_type = parent._get_type_for_attribute(attribute, mapping_key)

        if self.force_attribute_types and result_type is None:
            txt = "%(parent)r could not provide type for '%(attribute)s'"
            if mapping_key is not None:
                txt += ", [%(mapping_key)s]"
            raise AbortConversion(txt % locals())

        return result_type

    get_suggested_type_for_mapping_component = \
    get_suggested_type_for_attribute = _get_suggested_type_for_named_item


class RemoteObjectConverter(Converter,
    XMLStructureReader, RemoteObjectWriterMixin):

    def __init__(self, connection=None):
        super(Converter, self).__init__()
        self.connection = connection

def is_azureus_argument_type(java_type):
    return is_java_argument_type(java_type) or \
        is_azureus_argument_class(java_type)

def is_azureus_return_type(java_type):
    return is_java_return_type(java_type) or \
        is_azureus_return_class(java_type)
