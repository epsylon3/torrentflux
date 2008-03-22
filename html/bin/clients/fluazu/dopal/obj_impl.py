# File: obj_impl.py
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
Implementation of classes defined by Azureus's plugin API.

Not all classes are defined here are mentioned in the API documentation (simply
because too much documentation will be generated).

For each class that DOPAL has explicit support for, there will be two classes
defined in this module. The list of classes supported in this version is
described in L{classes}.

For each class supported, there will be a class named
I{AzureusE{<}classnameE{>}}, and another class named I{DopalE{<}classnameE{>}}.

The I{Azureus*} class is a subclass of L{AzureusObject} mixed in with the
I{*DataType} class in the L{class_defs} module - the API closely resembles
that of the actual object in Azureus.

The I{Dopal*} class is a subclass of the I{Azureus*} class, mixed in with the
L{DopalObjectMixin} class. These classes exist to define an extended API of
convenience functions beyond the API supplied by Azureus itself. Although all
plugin classes have a I{Dopal*} representation, only those classes mentioned
in the API documentation have any extended behaviour defined for them.

@group Standard class implementations: %(standard_classes)s
@group DOPAL class implementation: %(dopal_classes)s
'''

import dopal.class_defs as _cdefs
from dopal.objects import AzureusObject, AzureusObjectMetaclass, TypelessRemoteObject
from dopal.errors import MissingRemoteAttributeError

# Temporary value - should be removed once the import has finished.
import dopal
__epydoc_mode = dopal.__dopal_mode__ == 2
del dopal

# Imported just for the __str__ method of DopalObjectMixin.
import sys

# The code here is used to create representive classes of each of the remote
# Azureus class types that we support.
import new
def _make_class(common_cls, data_type_cls, name_prefix, class_map_dict=None):
    az_class_name = data_type_cls.get_xml_type()
    new_class_name = name_prefix + az_class_name

    # Is the class already defined in the global namespace? If so - we
    # avoid defining it again.
    if globals().has_key(new_class_name):
        classobj = globals()[new_class_name]
    else:
        base_classes = (common_cls, data_type_cls)
        classobj = new.classobj(new_class_name, base_classes, {})
        del base_classes
        globals()[new_class_name] = classobj

    if __epydoc_mode:
        classobj.__plugin_class__ = True

    if class_map_dict is not None:
        class_map_dict[az_class_name] = classobj
    return classobj

# The two class maps we provide by default.
STANDARD_CLASS_MAP = {}
DOPAL_CLASS_MAP = {}

# These methods are common on all DOPAL variants of classes we create.
class DopalObjectMixin:

    # Used by repr.
    def short_description(self):
        try:
            result = self._short_description()
        except MissingRemoteAttributeError:
            result = None

        if result is None:
            return ''
        return result

    # Used by str.
    def full_description(self):
        try:
            result = self._full_description()
        except MissingRemoteAttributeError:
            result = None

        if result is None:
            return ''
        return result

    def _short_description(self):
        return None

    def _full_description(self):
        return self.short_description()

    def __str__(self): # DopalObjectMixin
        '''
        Generates a string representation of this object - the value of this
        string will be the result returned by the L{__unicode__} method.

        Note - this method should return a string which is appropriate for
        the system's encoding (so C{UnicodeEncodeError}s should not occur), but
        it makes no guarantee I{how} it will do this.

        As of DOPAL 0.60, it encodes the string using the default system
        encoding, using 'replace' as the default way to handle encoding
        problems.
        '''
        # What should be the default behaviour?
        #
        # http://aspn.activestate.com/ASPN/Cookbook/Python/Recipe/466341
        #
        #   1) Use encoding - "raw_unicode_escape".
        #   2) Use error handler - "replace" (current).
        #   3) Use error handler - "ignore".
        return unicode(self).encode(sys.getdefaultencoding(), 'replace')

    def __unicode__(self):
        '''
        Generates a text representation of this object. If the
        L{full_description} returns a useful representation, then the string
        will have this format::
           RemoteTypeName: FullDescriptionString

        Otherwise, it will resort to the superclass string representation.

        Example::
           Download: The Subways - Staring at the Sun.mp3 [Stopped, 100.0%]
        '''

        nice_name = self.full_description()

        if nice_name:
            result = "%s: %s" % (self.get_remote_type(), nice_name)
        else:
            result = AzureusObject.__str__(self)

        try:
            return unicode(result)

        # Python 2.2 doesn't define UnicodeDecodeError, we have to use
        # UnicodeError.
        except UnicodeError, error:
            # string_escape only defined in Python 2.3.
            if sys.version_info >= (2, 3):
                return unicode(result.encode('string_escape'))
            else:
                return unicode(AzureusObject.__str__(self))
        #

    def __repr__(self):
        nice_name = self.short_description()

        repr_string = AzureusObject.__repr__(self)
        if nice_name:
            if repr_string[-1:] == ">":
                repr_string = repr_string[:-1] + \
                    ', for "%s">' % nice_name
        return repr_string

class DopalObjectStatsMixin(DopalObjectMixin):

    def _short_description(self):
        return "S:%s P:%s" % (self.seed_count, self.non_seed_count)

    def _full_description(self):
        return "Seeds: %s, Peers: %s" % (self.seed_count, self.non_seed_count)

# Some classes which are basically stat counts share these methods.

# Now we create the classes - the standard variants first, then the DOPAL
# enhanced variants afterwards.
#
# The DOPAL variants are only automatically generated if we haven't defined
# them manually. We only define them manually if we have methods we want
# to define on them.
for az_class in _cdefs._class_map.values():
    _make_class(AzureusObject, az_class, 'Azureus', STANDARD_CLASS_MAP)
del az_class

#
#
# We've now created all the classes we wanted. We now define extra methods
# on particular classes we care about.
#
#

# Now we declare DOPAL variants of these classes - these classes will end up
# providing a richer API than just the standard plugin classes.
class DopalPluginConfig(DopalObjectMixin, AzureusPluginConfig):

    def get_upload_speed_limit(self):
        return self.getIntParameter(self.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC, 0)

    def get_download_speed_limit(self):
        return self.getIntParameter(self.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC, 0)

    def set_upload_speed_limit(self, limit):
        if limit is None:
            limit = 0
        self.setIntParameter(self.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC, limit)

    def set_download_speed_limit(self, limit):
        if limit is None:
            limit = 0
        self.setIntParameter(self.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC, limit)

class DopalDownload(DopalObjectMixin, AzureusDownload):

    def _short_description(self):
        return self.torrent.short_description()

    def _full_description(self):
        result = self.short_description()
        if not result:
            return result

        result += " " + self.stats.full_description()
        return result

class DopalDownloadStats(DopalObjectMixin, AzureusDownloadStats):

    def _full_description(self):
        return "[%s, %.1f%%]" % (self.status, float(self.completed) / 10)

class DopalDiskManagerFileInfo(DopalObjectMixin, AzureusDiskManagerFileInfo):

    def _full_description(self):
        filename = self.short_description()
        if not filename:
            return None

        if self.is_skipped:
            return filename + " [skipped]"
        elif self.is_priority:
            return filename + " [high]"
        else:
            return filename + " [normal]"

    def _short_description(self):
        import os.path
        return os.path.basename(self.file)

class DopalLoggerChannel(DopalObjectMixin, AzureusLoggerChannel):

    def _full_description(self):
        result = self.name
        if not self.enabled:
            result += " [disabled]"
        return result

    def _short_description(self):
        return self.name

class DopalPeer(DopalObjectMixin, AzureusPeer):

    def _full_description(self):
        return "%s:%s" % (self.ip, self.port)

    def _short_description(self):
        return self.ip

class DopalPluginInterface(DopalObjectMixin, AzureusPluginInterface):

    def _full_description(self):
        return self.plugin_name

    def _short_description(self):
        return self.plugin_id

class DopalTorrent(DopalObjectMixin, AzureusTorrent):

    def _short_description(self):
        return self.name

# Let's define the rest of the DOPAL classes.
for az_class in [AzureusDownloadAnnounceResult, AzureusDownloadScrapeResult]:
    _make_class(DopalObjectStatsMixin, az_class, 'Dopal', DOPAL_CLASS_MAP)
for az_class in STANDARD_CLASS_MAP.values():
    _make_class(DopalObjectMixin, az_class, 'Dopal', DOPAL_CLASS_MAP)
del az_class


# Bugfix for tf-b4rt: don't try to use/change __doc__ if it's
# empty, which is the case if Python was invoked with -OO
# (except for early Python 2.5 releases where -OO is broken:
# http://mail.python.org/pipermail/python-bugs-list/2007-June/038590.html).
if __doc__ is not None:

    # Amend the docstring to contain all the object types defined.
    doc_string_sub_dict = {}
    for class_map_dict, dict_entry in [
        (STANDARD_CLASS_MAP, 'standard_classes'),
        (DOPAL_CLASS_MAP, 'dopal_classes'),
    ]:
        cls = None
        classes_in_map = [cls.__name__ for cls in class_map_dict.values()]
        classes_in_map.sort()
        doc_string_sub_dict[dict_entry] = ', '.join(classes_in_map)
        del classes_in_map, cls

    __doc__ = __doc__ % doc_string_sub_dict
    del doc_string_sub_dict

del __epydoc_mode

STANDARD_CLASS_MAP[None] = DOPAL_CLASS_MAP[None] = TypelessRemoteObject
