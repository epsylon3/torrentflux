# File: xmlutils.py
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
XML utility functions.
'''

# Given an object which has the same interface as xml.dom.Node:
#   a) Join all concurrent text nodes together.
#   b) Strip all trailing and leading whitespace from each text node.
#
# This function will recursively process the tree structure given in the node
# object. No value will be returned by this function, instead the given object
# will be modified.
def normalise_xml_structure(xml_node):

    # Concurrent text nodes should be joined together.
    xml_node.normalize()

    # Strip all text nodes which are empty of content (whitespace is not
    # content).
    from xml.dom import Node
    nodes_to_delete = []

    for node in xml_node.childNodes:
        if node.nodeType == Node.TEXT_NODE:
            stripped_text = node.nodeValue.strip()
            if stripped_text:
                node.nodeValue = stripped_text
            else:
                nodes_to_delete.append(node)
        else:
            normalise_xml_structure(node)

    for node in nodes_to_delete:
        xml_node.removeChild(node)
        node.unlink()

def get_text_content(node):
    from xml.dom import Node

    # Text content is stored directly in this node.
    if node.nodeType == Node.TEXT_NODE:
        return node.nodeValue

    # Otherwise, must be in a child node.
    #elif len(node.childNodes) == 1 and \
    #    node.firstChild.nodeType == Node.TEXT_NODE:
    #    return node.firstChild.nodeValue

    # Sometimes happens for attributes with no real value.
    elif len(node.childNodes) == 0:
        return ''

    text_node = None
    err_text = None
    for child in node.childNodes:
        if child.nodeType == Node.TEXT_NODE:
            if text_node is None:
                text_node = child
            else:
                err_text = "contained multiple text nodes"
                break
    else:
        if text_node is None:
            if len(node.childNodes) != 1:
                err_text = "contained multiple nodes, but none were text"
            else:
                err_text = "did not contain a character string as its value"
        else:
            return text_node.nodeValue

    raise ValueError, ("the node %s " % node.nodeName) + err_text

from xml.sax.saxutils import quoteattr, escape

# This base class will be removed when XMLObject is removed.
class _XMLObjectBase(object):

    def __init__(self, tag_name):
        self.tag_name = tag_name
        self.attributes = {}
        self.contents = []

    def add_attribute(self, attribute_name, attribute_value):
        self.attributes[attribute_name] = attribute_value

    def add_content(self, content):
        self.contents.append(content)

    def to_string(self, out=None, indent=0):
        if out is None:
            # We use StringIO instead of cStringIO not to lose unicode strings.
            import StringIO
            out = StringIO.StringIO()
            return_as_string = True
        else:
            return_as_string = False

        indent_string = ' ' * indent
        out.write(indent_string)
        out.write('<')
        out.write(self.tag_name)
        for attr_name, attr_value in self.attributes.items():
            out.write(' ')
            out.write(attr_name)
            out.write('=')
            out.write(quoteattr(attr_value))

        # If we have no contents, we'll close the tag here.
        if not self.contents:
            out.write(' />\n')

        else:
            out.write('>')

        # If we have one piece of content, which is just a string, then
        # we'll put it on the same line as the opening tag is on.
        if len(self.contents) == 1 and not hasattr(self.contents[0], 'to_string'):
            out.write(escape(self.contents[0]))

        # Otherwise, we assume we have some more XML blocks to write out,
        # so we'll indent them and put them on newlines.
        elif self.contents:
            out.write('\n')
            for content in self.contents:
                content.to_string(out, indent+2)
            out.write(indent_string)

        # Write out the closing tag (if we haven't written it already).
        if self.contents:
            out.write('</')
            out.write(self.tag_name)
            out.write('>\n')

        # If the invocation of this method was not passed a buffer to write
        # into, then we return the string representation.
        if return_as_string:
            return out.getvalue()

        return None

class XMLObject(_XMLObjectBase):
    '''
    B{Deprecated:} An object representing a block of XML.

    @attention: B{Deprecated:} This class does not provide any guarantees in
       the way that byte strings are handled. Use L{UXMLObject} instead.
    '''
    def __init__(self, tag_name):
        from dopal.errors import DopalPendingDeprecationWarning

        import warnings
        warnings.warn("XMLObject is deprecated - use UXMLObject instead", DopalPendingDeprecationWarning)

        _XMLObjectBase.__init__(self, tag_name)

class UXMLObject(_XMLObjectBase):
    '''
    An object representing a block of XML.

    Any string which is added to this block (either through the L{add_content}
    or L{add_attribute} methods should be a unicode string, rather than a byte
    string. If it is a byte string, then it must be a string which contains
    text in the system's default encoding - attempting to add text encoding in
    other formats is not allowed.
    '''

    def to_string(self, out=None, indent=0):
        result = _XMLObjectBase.to_string(self, out, indent)
        if result is None:
            return None
        return unicode(result)

    def encode(self, encoding='UTF-8'):
        return (('<?xml version="1.0" encoding="%s"?>\n' % encoding) + self.to_string()).encode(encoding)

    def __unicode__(self):
        return self.to_string()

def make_xml_ref_for_az_object(object_id):
    '''
    Creates an XML block which represents a remote object in Azureus with the given object ID.

    @param object_id: The object ID to reference.
    @type object_id: int / long
    @return: A L{UXMLObject} instance.
    '''
    object_id_block = UXMLObject('_object_id')
    object_id_block.add_content(str(object_id))

    object_block = UXMLObject('OBJECT')
    object_block.add_content(object_id_block)
    return object_block
