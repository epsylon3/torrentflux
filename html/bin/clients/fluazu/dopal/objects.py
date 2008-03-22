# File: objects.py
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
Defines the object layer framework.
'''

from dopal.core import ExtendedAzureusConnection
from dopal.errors import AzMethodError, InvalidObjectIDError, \
    RemoteMethodError, StaleObjectReferenceError, ConnectionlessObjectError, \
    NonRefreshableConnectionlessObjectError, MissingRemoteAttributeError, \
    NonRefreshableIncompleteObjectError, NonRefreshableObjectError
import dopal.utils

class AzureusObjectConnection(ExtendedAzureusConnection):
    '''
    This connection class generates remote representations of each object available in Azureus.

    @ivar is_persistent_connection: Boolean indicating whether the connection should be persistent. Default is C{False}.

    @ivar converter: Callable object which will be used to convert response data into objects.

    This will usually be a L{RemoteObjectConverter<dopal.convert.RemoteObjectConverter>} instance which will convert the results of method invocations into objects. A value must be assigned for this object to work - no suitable default is provided automatically.
    '''

    def __init__(self): # AzureusObjectConnection
        ExtendedAzureusConnection.__init__(self)
        self.is_persistent_connection = False
        self.converter = None
        self.cached_plugin_interface_object = None

    def _on_reconnect(self): # AzureusObjectConnection
        if not self.is_persistent_connection:
            self.cached_plugin_interface_object = None

    def get_plugin_interface(self): # AzureusObjectConnection
        # XXX: Add docstring.

        obj = self.cached_plugin_interface_object
        if self.cached_plugin_interface_object is not None:

            # Try to verify that it exists.
            #
            # Why do we verify the object? Well, we just want to ensure that
            # the object we return here is valid. It would be valid if we
            # returned getPluginInterface. If the object needs repairing,
            # then it is better to do it immediately.
            #
            # Why do we not rely on the object just sorting itself out?
            # Well, the default definitions for extracting the object from the
            # root will use the plugin interface object as the root.
            #
            try:
                self.verify_objects([self.cached_plugin_interface_object])
            except NonRefreshableObjectError:
                # Subclasses of this error will occur if there's a problem
                # refreshing the object (for whatever reason). Refreshing
                # it will only occur if the object is not valid.
                #
                # If, for whatever reason, our cached plugin interface hasn't
                # repaired itself, we'll just lose the cached version and get
                # a new object.
                #
                # Why do we not just get a plugin interface object and update
                # the cached plugin interface object? If the object is
                # 'broken', or object persistency is not enabled, there's no
                # reason to repair it - we wouldn't normally do that for any
                # other object.
                #
                # But it is important we return a valid object.
                self.cached_plugin_interface_object = None

        if self.cached_plugin_interface_object is None:
            self.cached_plugin_interface_object = self.getPluginInterface()

        return self.cached_plugin_interface_object

    def getPluginInterface(self): # AzureusObjectConnection
        return self.invoke_object_method(None, 'getSingleton', (), 'PluginInterface')

    # Invoke the remote method - nothing regarding object persistency is
    # handled here.
    def _invoke_object_method(self, az_object, method_name, method_args, result_type=None): # AzureusObjectConnection
        if az_object is None:
            az_object_id = None
        else:
            az_object_id = az_object.get_object_id()

        # We don't need to extract the object ID's for objects which are in
        # method_args - they will be an instance of one of the wrapper type
        # classes, which will be appropriate enough to pull out the correct
        # type to use.
        response = self.invoke_remote_method(az_object_id, method_name, method_args)

        result = self.converter(response.response_data, result_type=result_type)
        return result

    def invoke_object_method(self, az_object, method_name, method_args, result_type=None): # AzureusObjectConnection

        objects = [obj for obj in method_args if isinstance(obj, RemoteObject)]
        if az_object is not None:
            objects.insert(0, az_object)

        self.verify_objects(objects)

        try:
            return self._invoke_object_method(az_object, method_name, method_args, result_type)
        except InvalidObjectIDError, error:
            # XXX: TODO, this exception is likely to be one of two subclasses
            # You don't need to call is_connection_valid, since you'll know
            # from the subclasses error. It's an unnecessary method call - fix
            # it!
            if not self.is_persistent_connection:
                raise
            if self.is_connection_valid():
                raise
            self.establish_connection()
            self.verify_objects(objects)

            # Two very quick failures of this type is unlikely to happen -
            # it is more likely to be a logic error in this case, so we
            # don't retry if it fails again.
            return self._invoke_object_method(az_object, method_name, method_args, result_type)

    def verify_objects(self, objects): # AzureusObjectConnection
        # I did write this as a list expression initially, but I guess we
        # should keep it readable.
        has_refreshed_objects = False
        for obj in objects:
            if obj.__connection_id__ != self.connection_id:
                if self.is_persistent_connection:
                    obj._refresh_object(self)
                    has_refreshed_objects = True
                else:
                    raise StaleObjectReferenceError, obj
        return has_refreshed_objects

class RemoteObject(object):

    def __init__(self, connection, object_id, attributes=None): # RemoteObject

        if connection is None:
            self.__connection_id__ = None
        elif isinstance(connection, AzureusObjectConnection):
            self.__connection_id__ = connection.connection_id
        else:
            err = "connection must be instance of AzureusObjectConnection: %s"
            raise ValueError, err % connection

        self.__connection__ = connection
        self.__object_id__ = object_id

        if attributes is not None:
            self.update_remote_data(attributes)

    def __repr__(self): # RemoteObject
        txt = "<%s object at 0x%08X" % (self.__class__.__name__, id(self))

        # "sid" stands for short ID.
        sid = self.get_short_object_id()
        if sid is not None:
            txt += ", sid=%s" % sid
        return txt + ">"

    def __str__(self): # RemoteObject
        sid = self.get_short_object_id()
        if sid is None:
            return RemoteObject.__repr__(self)
        else:
            return "<%s, sid=%s>" % (self.__class__.__name__, sid)

    def get_short_object_id(self):
        if self.__object_id__ is None:
            return None

        return dopal.utils.make_short_object_id(self.__object_id__)

    def get_object_id(self): # RemoteObject
        return self.__object_id__

    def get_remote_type(self): # RemoteObject
        if not hasattr(self, 'get_xml_type'):
            return None
        return self.get_xml_type()

    def get_remote_attributes(self): # RemoteObject
        result = {}
        result['__connection__'] = self.__connection__
        result['__connection_id__'] = self.__connection_id__
        result['__object_id__'] = self.__object_id__
        return result

    # set_remote_attribute and update_remote_data are very closely
    # linked - the former sets one attribute at a time while the
    # other sets multiple attributes together. It is recommended
    # that set_remote_attribute is not overridden, but
    # update_remote_data is instead. If you choose to override
    # set_remote_attribute, you should override update_remote_data
    # to use set_remote_attribute.
    def set_remote_attribute(self, name, value): # RemoteObject
        return self.update_remote_data({name: value})

    def update_remote_data(self, attribute_data): # RemoteObject
        for key, value in attribute_data.items():
            setattr(self, key, value)

    def get_connection(self): # RemoteObject
        if self.__connection__ is None:
            raise ConnectionlessObjectError, self
        return self.__connection__

    # Exits quietly if the current connection is valid.
    #
    # If it is invalid, then this object's _refresh_object method will be
    # called instead to retrieve a new object ID (if applicable), but only
    # if this object's connection is a persistent one. If not, it will raise
    # a StaleObjectReferenceError.
    def verify_connection(self): # RemoteObject
        return self.get_connection().verify_objects([self])

    def refresh_object(self): # RemoteObject
        '''
        Updates the remote attributes on this object.

        @raise NonRefreshableConnectionlessObjectError: If the object is not
               attached to a connection.
        @return: None
        '''
        try:
            if not self.verify_connection():
                self._refresh_object(self.__connection__)
        except ConnectionlessObjectError:
            raise NonRefreshableConnectionlessObjectError, self

    def _refresh_object(self, connection_to_use): # RemoteObject
        '''
        Internal method which refreshes the attributes on the object.

        This method actually performs two different functionalities.

        If the connection to use is the same as the one already attached,
        with the same connection ID, then a refresh will take place.

        If the connection is either a different connection, or the connection
        ID is different, then an attempt will be made to retrieve the
        equivalent object to update the attributes.

        @param connection_to_use: The connection object to update with.
        @type connection_to_use: L{AzureusObjectConnection}
        @raise NonRefreshableObjectTypeError: Raised when the object type is
        not one which can be refreshed on broken connections.
        @raise NonRefreshableIncompleteObjectError: Raised when the object is
        missing certain attributes which prevents it being refreshed on broken
        connections.
        @return: None
        '''

        # If the object is still valid, let's use the refresh method.
        if (self.__connection__ == connection_to_use) and \
            self.__connection_id__ == connection_to_use.connection_id:
            new_object = connection_to_use.invoke_object_method(
                self, '_refresh', (), result_type=self.get_xml_type())

        # The object is no longer valid. Let's grab the equivalent object.
        else:

            # Special case - if the object is the cached plugin interface
            # object, then we need to avoid calling get_plugin_interface.
            #
            # Why? Because that'll pick up that the object is invalid, and
            # then attempt to refresh it. Recursive infinite loop.
            #
            # So in that case, we just get a plugin interface object
            # directly.
            if connection_to_use.cached_plugin_interface_object is self:
                new_object = connection_to_use.getPluginInterface()
            else:
                root = connection_to_use.get_plugin_interface()
                new_object = self._get_self_from_root_object(root)
                del root

        # Get the attributes...
        new_data = new_object.get_remote_attributes()

        # (Make sure that the important attributes are there...)
        if __debug__:
            attrs = ['__connection__', '__connection_id__', '__object_id__']
            for key in attrs:
                if key not in new_data:
                    err = "%r.get_remote_attributes() is missing values!"
                    raise AssertionError, err % self
            del attrs, key

        # Update the values.
        self.update_remote_data(new_data)

    # This method is used to locate the remote equivalent object from the
    # plugin interface object. If the object cannot be retrieved from the
    # PluginInterface, you should raise a NonRefreshableObjectTypeError
    # instead (this is the default behaviour).
    def _get_self_from_root_object(self, plugin_interface): # RemoteObject
        raise NonRefreshableObjectError, self

    def invoke_object_method(self, method, method_args, result_type=None): # RemoteObject
        try:
            return self.get_connection().invoke_object_method(self, method, method_args, result_type=result_type)
        except RemoteMethodError, error:

            # There's three different ways an error can be generated here:
            #   1) _handle_invocation_error raises an error - this will have
            #      the traceback of where it was raised.
            #   2) _handle_invocation_error returns an error object - this
            #      will have the traceback of the original exception.
            #   3) _handle_invocation_error returns None - this will just
            #      reraise the original error.

            error = self._handle_invocation_error(error, method, method_args)
            if error is not None:
                import sys
                raise error, None, sys.exc_info()[2]
            raise

    def _handle_invocation_error(self, error, method_name, method_args): # RemoteObject
        # Default behaviour - just reraise the old error.
        return None

    # Called by the converter classes to determine the type of a remote
    # attribute.
    def _get_type_for_attribute(self, attrib_name, mapping_key=None): # RemoteObject
        return None

class RemoteConstantsMetaclass(type):

    def __init__(cls, name, bases, cls_dict):
        super(RemoteConstantsMetaclass, cls).__init__(name, bases, cls_dict)
        if hasattr(cls, '__az_constants__'):
            for key, value in cls.__az_constants__.items():
                setattr(cls, key, value)

# This just used for interrogation purposes - the guts of this function will
# be used to build other functions (see below).
#
# Poor little function - ends up being consumed and tossed aside, like a
# Hollow devouring a human soul.
def _invoke_remote_method(self, *args, **kwargs):
    from dopal.utils import handle_kwargs
    kwargs = handle_kwargs(kwargs, result_type=None)
    return self.invoke_object_method(__funcname__, args, **kwargs)

from dopal.utils import MethodFactory
_methodobj = MethodFactory(_invoke_remote_method)
make_instance_remote_method = _methodobj.make_instance_method
make_class_remote_method = _methodobj.make_class_method
del _methodobj, MethodFactory

from dopal.aztypes import AzureusMethods

class RemoteMethodMetaclass(type):

    def __init__(cls, name, bases, cls_dict):
        super(RemoteMethodMetaclass, cls).__init__(name, bases, cls_dict)

        az_key = '__az_methods__'
        if az_key not in cls_dict:
            methodsobj = AzureusMethods()
            for base in bases:
                if hasattr(base, az_key):
                    methodsobj.update(getattr(base, az_key))
            setattr(cls, az_key, methodsobj)
        else:
            methodsobj = getattr(cls, az_key)

        # Create the real methods based on those in __az_methods__.
        for method_name in methodsobj.get_method_names():
            if not hasattr(cls, method_name):
                _mobj = make_class_remote_method(method_name, cls)
                setattr(cls, method_name, _mobj)

class RemoteMethodMixin(object):
    __use_dynamic_methods__ = False
    __use_type_checking__ = True

    def __getattr__(self, name):
        # Anything which starts with an underscore is unlikely to be a public
        # method.
        if (not name.startswith('_')) and self.__use_dynamic_methods__:
            return self._get_remote_method_on_demand(name)
        _superclass = super(RemoteMethodMixin, self)

        # Influenced by code here:
        #   http://aspn.activestate.com/ASPN/Mail/Message/python-list/1620146
        #
        # The problem is that we can't use the super object to get a
        # __getattr__ method for the appropriate class.
        self_mro = list(self.__class__.__mro__)
        for cls in self_mro[self_mro.index(RemoteMethodMixin)+1:]:
            if hasattr(cls, '__getattr__'):
                return cls.__getattr__(self, name)
        else:
            # Isn't there something I can call to fall back on default
            # behaviour?
            text = "'%s' object has no attribute '%s'"
            raise AttributeError, text % (type(self).__name__, name)

    # Used to create a remote method object on demand.
    def _get_remote_method_on_demand(self, name):
        return make_instance_remote_method(name, self)

    def invoke_object_method(self, method, method_args, result_type=None):
        if self.__use_type_checking__:
            try:
                az_methods = self.__az_methods__
            except AttributeError:
                if not self.__use_dynamic_methods__:
                    raise RuntimeError, "%s uses type checking, but has no methods to check against" % type(self).__name__
            else:
                try:
                    method_args, result_type = \
                        az_methods.wrap_args(method, method_args)
                except AzMethodError:
                    if not self.__use_dynamic_methods__:
                        raise

        return super(RemoteMethodMixin, self).invoke_object_method(method, method_args, result_type=result_type)


class RemoteAttributeMetaclass(type):

    # XXX: What the hell is this meant to do!?
    def __init__(cls, name, bases, cls_dict):
        deft_names = '__default_remote_attribute_names__'
        az_attrs = '__az_attributes__'

        attr_dict = cls_dict.setdefault(deft_names, {})
        attr_dict.update(cls_dict.get(az_attrs, {}))

        for base in bases:
            attr_dict.update(getattr(base, deft_names, {}))
            attr_dict.update(getattr(base, az_attrs, {}))

        setattr(cls, deft_names, attr_dict)
        super(RemoteAttributeMetaclass, cls).__init__(name, bases, cls_dict)

class RemoteAttributesMixin(object):
    __default_remote_attribute_names__ = {}
    __reset_attributes_on_refresh__ = False
    __protect_remote_attributes__ = True

    def __init__(self, *args, **kwargs):
        # Class attribute becomes instance attribute.
        super(RemoteAttributesMixin, self).__init__(*args, **kwargs)
        self.__remote_attribute_names__ = self.__default_remote_attribute_names__.copy()

    def __getattr__(self, name):
        if name in self.__remote_attribute_names__:
            raise MissingRemoteAttributeError, name

        # Influenced by code here:
        #   http://aspn.activestate.com/ASPN/Mail/Message/python-list/1620146
        self_mro = list(self.__class__.__mro__)
        for cls in self_mro[self_mro.index(RemoteAttributesMixin)+1:]:
            if hasattr(cls, '__getattr__'):
                return cls.__getattr__(self, name)
        else:
            # Isn't there something I can call to fall back on default
            # behaviour?
            text = "'%s' object has no attribute '%s'"
            raise AttributeError, text % (type(self).__name__, name)


    def __setattr__(self, name, value):
        if self.__protect_remote_attributes__ and not name.startswith('__'):
            if name in self.__remote_attribute_names__:
                err = "cannot set remote attribute directly: %s"
                raise AttributeError, err % name
        return super(RemoteAttributesMixin, self).__setattr__(name, value)

    def set_remote_attribute(self, name, value):
        if name not in self.__remote_attribute_names__:
            self.__remote_attribute_names__[name] = None
        return super(RemoteAttributesMixin, self).__setattr__(name, value)

    def get_remote_attributes(self):
        result = super(RemoteAttributesMixin, self).get_remote_attributes()
        for attribute in self.__remote_attribute_names__:
            if hasattr(self, attribute):
                result[attribute] = getattr(self, attribute)
        return result

    def is_remote_attribute(self, name):
        return name in self.__remote_attribute_names__

    def update_remote_data(self, remote_attribute_dict):
        if self.__reset_attributes_on_refresh__:
            for attrib in self.__remote_attribute_names__:
                try:
                    delattr(self, attrib)
                except AttributeError:
                    pass

        _super = super(RemoteAttributesMixin, self)

        # XXX: Do a better fix than this!
        pra_value = self.__protect_remote_attributes__
        self.__protect_remote_attributes__ = False
        try:
            return _super.update_remote_data(remote_attribute_dict)
        finally:
            self.__protect_remote_attributes__ = pra_value

    def _get_type_for_attribute(self, name, mapping_key=None):
        if mapping_key is not None:
            key_to_use = name + ',' + mapping_key
        else:
            key_to_use = name
        result = self.__remote_attribute_names__.get(key_to_use)
        if result is not None:
            return result
        else:
            import dopal
            if dopal.__dopal_mode__ == 1:
                raise RuntimeError, (self, key_to_use)
        _superfunc = super(RemoteAttributesMixin, self)._get_type_for_attribute
        return _superfunc(name, mapping_key)

class AzureusObjectMetaclass(RemoteConstantsMetaclass, RemoteMethodMetaclass, RemoteAttributeMetaclass):
    pass

class AzureusObject(RemoteAttributesMixin, RemoteMethodMixin, RemoteObject):
    __metaclass__ = AzureusObjectMetaclass

    def _get_self_from_root_object(self, plugin_interface):
        # XXX: Err, this is a bit incorrect - it should be get_remote_type.
        # But it will do for now. Need to think more carefully about
        # the responsibilities of the two methods.
        if hasattr(self, 'get_xml_type'):
            from dopal.persistency import get_equivalent_object_from_root
            return get_equivalent_object_from_root(self, plugin_interface)
        return super(AzureusObject, self)._get_self_from_root_object(plugin_interface)

class TypelessRemoteObject(RemoteAttributesMixin, RemoteMethodMixin, RemoteObject):
    __use_dynamic_methods__ = True

TYPELESS_CLASS_MAP = {None: TypelessRemoteObject}

# XXX: Define converter here?
# Add type checking code (though this proably should be core)
# Add some code to read data from statistics file (what level should this be at?)
## Allow some code to make link_error_handler assignable
# Converter - needs to have some default behaviours (easily changeable):
#   a) Atoms - what to do if no type is suggested. (not so important this one)
#   b) Objects - what to do if no class is given (if no id is given?)
