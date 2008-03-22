# File: scripting.py
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
This module is designed to provide an 'environment' that allows small scripts
to be written without having to deal with the setting up and exception handling
that you would normally have to deal with.

It also tries to make it straight-forward and distribute scripts without
requiring any modification by another user to get it working on their system
(most common change would be to personalise the script to work with a user's
particular connection setup).

This module provides simple functionality for scripts - data persistency, error
handling and logging - it even provides a mechanism for sending alerts to the
user to be displayed in Azureus (via "Mr Slidey").

There are two main functions provided here:
  - C{L{ext_run}} - which provides all the main functionality; and
  - C{L{run}} - which calls ext_run with the default settings, but allows these
          arguments to be modified through command line arguments.

The following features are provided by this module:

  - B{Automatic connection setup} - Default connection settings can be set by
        running this module (or any script which uses the run method) with the
        C{--setup-connection} command line argument. This will provide the user
        with an input prompt to enter connection values, and store it an
        appropriate directory (see L{determine_configuration_directory}). That
        data is then used for all scripts using that module.

  - B{Data Persistency} - You are provided with access to methods to save and
        load a pickleable object - the module keeps the data stored in a unique
        data directory based on the script's name.

  - B{Logging (local)} - A logging system is initialised for the script to log
        any messages to - by default, logging to a file in the data directory.

  - B{Logging (remote)} - A LoggerChannel is set up to provide the ability to
        send log messages to Azureus through it's own logging mechanism. It
        also provides the ability to send alerts to the user (via Mr Slidey).

  - B{Pause on exit} - The module provides behaviour to pause whenever a script
        has finished execution, either in all cases, or only if an error has
        occurred. This makes it quite useful if you have the script setup to
        run in a window, which closes as soon as the script has terminated.

When writing a script, it should look like this::

    def script_function(env):
       ... # Do something here.

    if __name__ == '__main__':
        import dopal.scripting
        dopal.scripting.run("functionname", script_function)

where "script_function" is the main body of the script (which takes one
argument, a C{L{ScriptEnvironment}} instance) and "functionname" which is used
to define the script (in terms of where persistent data is sent), and what the
script is called when sending alerts to Azureus.
'''

# Python 2.2 compatibility.
from __future__ import generators

import os, os.path
_default_config_dir = None

def determine_configuration_directory(mainname='DOPAL Scripts', subname=None,
    create_dir=True, preserve_case=False):

    '''
    Determines an appropriate directory to store application data into.

    This function will look at environmental settings and registry settings to
    determine an appropriate directory.

    The locations considered are in order:
      - The user's home, as defined by the C{home} environment setting.
      - The user's application directory, as determined by the C{win32com} library.
      - The user's application directory, as determine by the C{_winreg} library.
      - The user's application directory, as defined by the C{appdata} environment setting.
      - The user's home, as defined by the C{homepath} environment setting (and if it exists, the C{homedrive} environment setting.
      - The user's home, as defined by the C{os.path.expanduser} function.
      - The current working directory.

    (Note: this order may change between releases.)

    If an existing directory can be found, that will be returned. If no
    existing directory is found, then this function will try to create the
    directory in the most preferred location (based on the order of
    preference). If that fails - no existing directory was found and no
    directory could be created, then an OSError will be raised. If create_dir
    is False and no existing directory can be found, then the most preferred
    candidate directory will be returned.

    The main argument taken by this function is mainname. This should be a
    directory name which is suitable for a Windows application directory
    (e.g. "DOPAL Scripts"), as opposed to something which
    resembles more Unix-based conventions (e.g. ".dopal_scripts"). This
    function will convert the C{mainname} argument into a Unix-style filename
    automatically in some cases (read below). You can set the C{preserve_case}
    argument to C{True} if you want to prevent automatic name conversation of
    this argument to take place.

    The C{subname} argument is the subdirectory which gets created in the
    main directory. This name will be used literally - no translation of the
    directory name will occur.

    When this function is considering creating or locating a directory inside
    a 'home' location, it will use a Unix-style directory name (e.g.
    ".dopal_scripts"). If it is considering an 'application' directory, it will
    use a Windows-style directory name (e.g. "DOPAL Scripts"). If it considers
    a directory it is unable to categorise (like the current working
    directory), it will use a Windows-style name on Windows systems, or a
    Unix-style name on all other systems.

    @param mainname: The main directory name to store data in - the default is
      C{"DOPAL Scripts"}. This value cannot be None.
    @param subname: The subdirectory to create in the main directory - this may
      be C{None}.
    @param create_dir: Boolean value indicating whether we should create the
      directory if it doesn't already exist (default is C{True}).
    @param preserve_case: Indicates whether the value given in C{mainname}
      should be taken literally, or whether name translation can be performed.
      Default is C{False}.
    @return: A directory which matches the specification given. This directory
      is guaranteed to exist, unless this function was called with
      C{create_dir} being False.
    @raise OSError: If C{create_dir} is C{True}, and no appropriate directory
      could be created.
    '''

    # If we have an application data directory, then we will prefer to use
    # that. We will actually iterate over all directories that we consider, and
    # return the first directory we find. If we don't manage that, we'll create
    # one in the most appropriate directory. We'll also try to stick to some
    # naming conventions - using a dot-prefix for home directories, using
    # normal looking names in application data directories.
    #
    # Code is based on a mixture of user.py and homedirectory.py from the
    # pyopengl library.

    # Our preferred behaviour - existance of a home directory, and creating a
    # .dopal_scripts directory there.
    if not preserve_case:
        app_data_name = mainname
        home_data_name = '.' + mainname.lower().replace(' ', '_')

        import sys
        if sys.platform == 'win32':
            unknown_loc_name = app_data_name
        else:
            unknown_loc_name = home_data_name
    else:
        app_data_name = home_data_name = unknown_loc_name = mainname

    if subname:
        app_data_name = os.path.join(app_data_name, subname)
        home_data_name = os.path.join(home_data_name, subname)
        unknown_loc_name = os.path.join(unknown_loc_name, subname)

    def suggested_location():

        # 1) Test for the home directory.
        if os.environ.has_key('home'):
            yield os.environ['home'], home_data_name

        # 2) Test for application data - using win32com library.
        try:
            from win32com.shell import shell, shellcon
            yield shell.SHGetFolderPath(0, shellcon.CSIDL_APPDATA, 0, 0), app_data_name
        except Exception, e:
            pass

        # 3) Test for application data - using _winreg.
        try:
            import _winreg
            key = _winreg.OpenKey(_winreg.HKEY_CURRENT_USER, r"Software\Microsoft\Windows\CurrentVersion\Explorer\Shell Folders")
            path = _winreg.QueryValueEx(key, 'AppData')[0]
            _winreg.CloseKey(key)
            yield path, app_data_name
        except Exception, e:
            pass

        # 4) Test for application data - using environment settings.
        if os.environ.has_key('appdata'):
            yield os.environ['appdata'], app_data_name

        # 5) Test for home directory, using other environment settings.
        if os.environ.has_key('homepath'):
            if os.environ.has_key('homedrive'):
                yield os.path.join(os.environ['homedrive'], os.environ['homepath']), home_data_name
            else:
                yield os.environ['homepath'], home_data_name

        # 6) Test for home directory, using expanduser.
        expanded_path = os.path.expanduser('~')
        if expanded_path != '~':
            yield expanded_path, home_data_name

        # 7) Try the current directory then.
        yield os.getcwd(), unknown_loc_name

    # This will go through each option and choose what directory to choose.
    # It will keep yielding suggestions until we've decided what we want to
    # use.
    suggested_unmade_paths = []
    for suggested_path, suggested_name in suggested_location():
        full_suggested_path = os.path.join(suggested_path, suggested_name)
        if os.path.isdir(full_suggested_path):
            return full_suggested_path
        suggested_unmade_paths.append(full_suggested_path)

    # Return the first path we're able to create.
    for path in suggested_unmade_paths:

        # If we don't want to create a directory, just return the first path
        # we have dealt with.
        try:
            os.makedirs(path)
        except OSError, e:
            pass
        else:
            # Success!
            if os.path.isdir(path):
                return path

    # If we get here, then there's nothing we can do. We gave it our best shot.
    raise OSError, "unable to create an appropriate directory"

# Lazily-generated attribute stuff for ScriptEnvironment, taken from here:
#   http://aspn.activestate.com/ASPN/Cookbook/Python/Recipe/363602
class _lazyattr(object):
    def __init__(self, calculate_function):
        self._calculate = calculate_function

    def __get__(self, obj, typeobj=None):
        if obj is None:
            return self
        value = self._calculate(obj)
        setattr(obj, self._calculate.func_name, value)
        return value

#
# Methods used for saving and loading data.
#

class ScriptEnvironment(object):

    '''
    The ScriptEnvironment class contains values and methods useful for a script
    to work with.

    @ivar name: The name of the script.

    @ivar filename: The filename (no directory information included) of where
        persistent data for this object should be stored - default is
        C{data.dpl}. If you want to set a different filename, this value should
        be set before any saving or loading of persistent data takes place in
        the script.

    @ivar connection: The AzureusObjectConnection to work with. The connection
        should already be in an established state. connection may be None if
        ext_run is configured that way.

    @ivar logger: The logger instance to log data to. May be C{None}.

    @ivar log_channel: The logger channel object to send messages to. May be
        C{None}. For convenience the L{alert} method is available on
        ScriptEnvironment messages.

    @ivar default_repeatable_alerts: Indicates whether alerts are repeatable
        or not by default. This can be set explicitly on the object, but it
        can also be overridden when calling the L{alert} method. In most cases,
        this value will be C{None} when instantiated, and will be automatically
        determined the first time the L{alert} method is called.
    '''

    def __init__(self, name, data_file='data.dpl'):

        '''
        Note - this is a module-B{private} constructor. The method signature
        for this class may change without notice.
        '''
        self.name = name
        self.filename = data_file
        self.connection = None
        self.logger = None
        self.default_repeatable_alerts = None

    def get_data_dir(self, create_dir=True):
        try:
            return self.config_dir
        except AttributeError:
            config_dir = determine_configuration_directory(subname=self.name, create_dir=create_dir)
            if create_dir:
                self.config_dir = config_dir
            return config_dir

    def get_data_file_path(self, create_dir=True):
        return os.path.join(self.get_data_dir(create_dir), self.filename)

    def load_data(self):
        data_file_path = self.get_data_file_path()
        if not os.path.exists(data_file_path):
            return None
        data_file = file(data_file_path, 'rb')
        data = data_file.read()
        data_file.close()
        return _zunpickle(data)

    def save_data(self, data):
        data_file_path = self.get_data_file_path()
        data_file = file(data_file_path, 'wb')
        data_file.write(_zpickle(data))
        data_file.close()

    def get_log_file_path(self, create_dir=True):
        return os.path.join(self.get_data_dir(create_dir), 'log.txt')

    def get_log_config_path(self, create_dir=True):
        return os.path.join(self.get_data_dir(create_dir), 'logconfig.ini')

    def alert(self, message, alert_type='info', repeatable=None):
        if self.log_channel is None:
            return

        # Azureus 2.4.0.0 and onwards have a Hide All button, therefore we
        # don't mind having the same message popping up.
        if repeatable is None:
            if self.default_repeatable_alerts is None:
                if self.connection is None:
                    self.default_repeatable_alerts = False
                else:
                    self.default_repeatable_alerts = \
                        self.connection.get_azureus_version() >= (2, 4, 0, 0)

            repeatable = self.default_repeatable_alerts

        alert_code = {
            'warn': self.log_channel.LT_WARNING,
            'error': self.log_channel.LT_ERROR,
        }.get(alert_type, self.log_channel.LT_INFORMATION)

        if repeatable:
            _log = self.log_channel.logAlertRepeatable
        else:
            _log = self.log_channel.logAlert

        import dopal.errors
        try:
            _log(alert_code, message)
        except dopal.errors.DopalError:
            pass

    def log_channel(self):
        if hasattr(self, '_log_channel_factory'):
             return self._log_channel_factory()
        return None

    log_channel = _lazyattr(log_channel)

def _zunpickle(byte_data):
    import pickle, zlib
    return pickle.loads(zlib.decompress(byte_data))

def _zpickle(data_object):
    import pickle, zlib
    return zlib.compress(pickle.dumps(data_object))

#
# Methods for manipulating the default connection data.
#

def input_connection_data():
    print
    print 'Enter the default connection data to be used for scripts.'
    print
    save_file = save_connection_data(ask_for_connection_data())
    print
    print 'Data saved to', save_file

def ask_for_connection_data():
    connection_details = {}
    connection_details['host'] = raw_input('Enter host: ')
    port_text = raw_input('Enter port (default is 6884): ')
    if port_text:
        connection_details['port'] = int(port_text)

    # Username and password.
    username = raw_input('Enter user name (leave blank if not applicable): ')
    password = None
    if username:
        import getpass
        connection_details['user'] = username
        password1 = getpass.getpass('Enter password: ')
        password2 = getpass.getpass('Confirm password: ')
        if password1 != password2:
            raise ValueError, "Password mismatch!"
        connection_details['password'] = password1

    # Additional information related to the connection.
    print
    print 'The following settings are for advanced connection configuration.'
    print 'Just leave these values blank if you are unsure what to set them to.'
    print
    additional_details = {}
    additional_details['persistent'] = raw_input(
        "Enable connection persistency [type 'no' to disable]: ") != 'no'

    timeout_value = raw_input('Set socket timeout (0 to disable, blank to use script default): ')
    if timeout_value.strip():
        additional_details['timeout'] = int(timeout_value.strip())

    return connection_details, additional_details

def save_connection_data(data_dict):
    ss = ScriptEnvironment(None, 'connection.dpl')
    ss.save_data(data_dict)
    return ss.get_data_file_path()

def load_connection_data(error=True):
    ss = ScriptEnvironment(None, 'connection.dpl')
    data = ss.load_data()
    if data is None and error:
        from dopal.errors import NoDefaultScriptConnectionError
        raise NoDefaultScriptConnectionError, "No default connection data found - you must run dopal.scripting.input_connection_data(), or if you are running as a script, use the --setup-connection parameter."
    return data

def get_stored_connection():
    return _get_connection_from_config(None, None, None, False, False)

def _sys_exit(exitcode, message=''):
    import sys
    if message:
        print >>sys.stderr, message
    sys.exit(exitcode)

def _press_any_key_to_exit():
    # We use getpass to swallow input, because we don't want to echo
    # any nonsense that the user types in.
    print
    import getpass
    getpass.getpass("Press any key to exit...")

def _configure_logging(script_env, setup_logging):
    try:
        import logging
    except ImportError:
        return False

    if setup_logging is False:
        import dopal.logutils
        dopal.logutils.noConfig()
    elif setup_logging is True:
        logging.basicConfig()
    else:
        log_ini = script_env.get_log_config_path(create_dir=False)
        if not os.path.exists(log_ini):
            log_ini = ScriptEnvironment(None).get_log_config_path(create_dir=False)
        if os.path.exists(log_ini):
            import logging.config
            logging.config.fileConfig(log_ini)
        else:
            import dopal.logutils
            dopal.logutils.noConfig()

    return True

def _create_handlers(script_env, log_to_file, log_file, log_to_azureus):
    try:
        import logging.handlers
    except ImportError:
        return []

    created_handlers = []

    if log_to_file:
        if log_file is None:
            log_file = script_env.get_log_path()
        handler = logging.handlers.RotatingFileHandler(log_file, maxBytes=2000000)
        created_handlers.append(handler)

    return created_handlers

def _get_remote_logger(script_env, use_own_log_channel):
    import dopal.errors, types

    try:
        logger = script_env.connection.getPluginInterface().getLogger()
        channel_by_name = dict([(channel.getName(), channel) for channel in logger.getChannels()])

        if isinstance(use_own_log_channel, types.StringTypes):
            log_channel_name = use_own_log_channel
        elif use_own_log_channel:
            log_channel_name = name
        else:
            log_channel_name = 'DOPAL Scripts'

        # Reuse an existing channel, or create a new one.
        if log_channel_name in channel_by_name:
            return channel_by_name[log_channel_name]
        else:
            return logger.getChannel(log_channel_name)
    except dopal.errors.DopalError, e:

        # Not too sure about this at the moment. It's probably better to
        # provide some way to let errors escape.
        import dopal
        if dopal.__dopal_mode__ == 1:
            raise
        return None

def _get_connection_from_config(script_env, connection, timeout, establish_connection, silent_on_connection_error):

    import dopal.errors

    if script_env is None:
        logger = None
    else:
        logger = script_env.logger

    extended_settings = {}
    if connection is None:
        if logger:
            logger.debug("No connection explicitly defined, attempting to load DOPAL scripting default settings.")
        connection_details, extended_settings = load_connection_data()
        if logger:
            logger.debug("Connection settings loaded, about to create connection.")

        import dopal.main
        connection = dopal.main.make_connection(**connection_details)
        if logger:
            logger.debug("Connection created. Processing advanced settings...")

    if timeout is not None:
        timeout_to_use = timeout
    elif extended_settings.has_key('timeout'):
        timeout_to_use = extended_settings['timeout']
    else:
        timeout_to_use = None

    if timeout_to_use is not None:

        # This is how we distinguish between not giving a value, and turning
        # timeouts off - 0 means don't use timeouts, and None means "don't do
        # anything".
        if timeout_to_use == 0:
            timeout_to_use = None

        if logger:
            logger.debug("Setting timeout to %s." % timeout_to_use)
        import socket
        try:
            socket.setdefaulttimeout(timeout_to_use)
        except AttributeError: # Not Python 2.2
            pass

        connection.is_persistent_connection = extended_settings.get('persistent', True)

    if not establish_connection:
        return connection

    if logger:
        logger.debug("About to establish connection to %s." % connection.get_cgi_path(auth_details=True))

    try:
        connection.establish_connection()
    except dopal.errors.LinkError:
        if silent_on_connection_error:
            if logger:
                logger.info("Failed to establish connection.", exc_info=1)
            return None
        else:
            if logger:
                logger.exception("Failed to establish connection.")
            raise
    else:
        if logger:
            logger.debug("Connection established.")
        return connection


def ext_run(name, function,

    # Connection related.
    connection=None, make_connection=True,

    # Connection setup.
    timeout=15,

    # Remote logging related.
    use_repeatable_remote_notification=None, use_own_log_channel=False,
    remote_notify_on_run=False, remote_notify_on_error=True,

    # Local logging related.
    logger=None, setup_logging=None, log_to_file=False, log_level=None,
    log_file=None,

    # Exit behaviour.
    silent_on_connection_error=False, pause_on_exit=0, print_error_on_pause=1):

    '''
    Prepares a L{ScriptEnvironment} object based on the settings here, and
    executes the passed function.

    You may alternatively want to use the L{run} function if you don't wish
    to determine the environment settings to run in, and would prefer the
    settings to be controlled through arguments on the command line.

    @note: If passing additional arguments, you must use named arguments,
    and not rely on the position of the arguments, as these arguments may
    be moved or even completely removed in later releases.

    @param name: The I{name} of the script - used for storing data, log files
       and so on.

    @param function: The callable object to invoke. Must take one argument,
       which will be the L{ScriptEnvironment} instance.

    @param connection: The
       L{AzureusObjectConnection<dopal.objects.AzureusObjectConnection>} object
       to use - if C{None} is provided, one will be automatically determined
       for you.

    @param make_connection: Determines whether the C{scripting} module
       should attempt to create a connection based on the default connection
       details or not. Only has an effect if the C{connection} parameter is
       C{None}.

    @param timeout: Defines how long socket operations should wait before
       timing out for (in seconds). Specify C{0} to disable timeouts, the
       default is C{15}. Specifying C{None} will resort to using the default
       timeout value specified in the connection details.

    @param use_repeatable_remote_notification: Determines whether the
       L{alert<ScriptEnvironment.alert>} method should use repeatable
       notification by default or not (see L{ScriptEnvironment.alert}).

    @param use_own_log_channel: Determines what log channel to use. The default
       behaviour is to use a log channel called "C{DOPAL Scripts}". Passing a
       string value will result in logging output being sent to a channel with
       the given name. Passing C{True} will result in a channel being used
       which has the same name as the script.

    @param remote_notify_on_run: Determines whether to send
       L{alert<ScriptEnvironment.alert>} calls when the script starts and ends.
       Normally, this is only desired when testing that the script is working.

    @param remote_notify_on_error: Determines whether to send an alert to the
      Azureus connection if an error has occurred during the script's
      execution.

    @param logger: The C{logging.Logger} instance to log to - the root logger
      will be used by default. Will be C{None} if the C{logging} module is not
      available on the system.

    @param setup_logging: Determines whether automatically set up logging with
      the C{logging.Logger} module. If C{True}, C{logging.basicConfig} will be
      called. If C{False}, L{dopal.logutils.noConfig} will be called. If
      C{None} (default), then this module will look for file named C{log.ini},
      firstly in the script's data directory and then in the global DOPAL
      scripts directory. If such a file can be found, then
      C{logging.fileConfig} will be invoked, otherwise
      L{dopal.logutils.noConfig} will be called instead.

    @param log_to_file: If C{True}, then a C{RotatingFileHandler} will log to a
      file in the script's data directory.

    @param log_level: The logging level assigned to any logger or handlers
      I{created} by this function.

    @param log_file: If C{log_to_file} is C{True}, this parameter
      specifies determines which file to log to (default is that the script
      will determine a path automatically).

    @param silent_on_connection_error: If C{True}, this function will silently
      exit if a connection cannot be established with the stored connection
      object. Otherwise, the original error will be raised.

    @param pause_on_exit: If set to C{0} (default), then after execution of the
      script has occurred, the function will immediately return. If C{1}, the
      script will wait for keyboard input before terminating. If C{2}, the
      script will wait for keyboard input only if an error has occurred.

    @param print_error_on_pause: If C{pause_on_exit} is enabled, this flag
      determines whether any traceback should be printed. If C{0}, no
      traceback will be printed. If C{1} (default), any error which occurs
      inside this function will be printed. If C{2}, only tracebacks which have
      occurred in the script will be printed. If C{3}, only tracebacks which
      have occurred outside of the script's invocation will be printed.

    @raises ScriptFunctionError: Any exception which occurs in the
      function passed in will be wrapped in this exception.
    '''

    from dopal.errors import raise_as, ScriptFunctionError

    try:

        # This will be eventually become a parameter on this method in a later
        # version of DOPAL, so I'll declare the variable here and program the
        # code with it in mind.
        log_to_azureus = False

        # All data for the script will be stored here.
        script_env = ScriptEnvironment(name)

        # First step, initialise the logging environment.
        #
        # We do this if we have not been passed a logger object.
        if logger is None:

            # We don't call this method if we have been specifically
            # asked to construct handlers from these function arguments.
            #
            # (Currently, that's just "log_to_file" that we want to check.)
            if log_to_file:
                logging_configured_by_us = False

            # We want to log to Azureus, but we can't set that up yet, because
            # we don't have a connection set up (probably). Adding a logging
            # handler is the last thing we do before invoking the script, because
            # we don't want to log any scripting initialisation messages here
            # remotely (we only want to log what the script wants to log).
            elif log_to_azureus:
                logging_configured_by_us = _configure_logging(script_env, False)

            # Configure using the setup_logging flag.
            else:
                logging_configured_by_us = _configure_logging(script_env, setup_logging)

            if logging_configured_by_us:
                import logging
                logger = logging.getLogger()

            if log_level is not None:
                logger.setLevel(log_level)

        else:
            logging_configured_by_us = False

        script_env.logger = logger

        set_levels_on_handlers = \
            (log_level is not None) and (not logging_configured_by_us)

        del logging_configured_by_us

        # Setup all handlers, apart from any remote handlers...
        for handler in _create_handlers(script_env, log_to_file, log_file, None):
            if set_levels_on_handlers:
                handler.setLevel(log_level)

        # Next step, sort out a connection (if we need to).
        if connection is None and make_connection:
            connection = _get_connection_from_config(script_env, None, timeout, True, silent_on_connection_error)

            # If connection is None, that means that we failed to establish a
            # connection, but we don't mind, so just return silently.
            if connection is None:
                return

        # Assign connection if we've got one.
        if connection is not None:
            script_env.connection = connection

        # Next step, setup a remote channel for us to communicate with Azureus.
        if connection is not None:

            def make_log_channel():
                return _get_remote_logger(script_env, use_own_log_channel)

            script_env._log_channel_factory = make_log_channel

        script_env.default_repeatable_alerts = use_repeatable_remote_notification

        # Configure remote handlers at this point.
        for handler in _create_handlers(script_env, False, None, log_to_azureus):
            if set_levels_on_handlers:
                handler.setLevel(log_level)

        if remote_notify_on_run:
            script_env.alert('About to start script "%s"...' % name, repeatable=True)

        try:
            function(script_env)
        except Exception, e:
            if logger:
                logger.exception("Error occurred inside script.")

            # Do we want to notify Azureus?
            if remote_notify_on_error:
                script_env.alert('An error has occurred while running the script "%s".\nPlease check any related logs - the script\'s data directory is located at:\n  %s'  % (script_env.name, script_env.get_data_dir(create_dir=False)), alert_type='error')

            raise_as(e, ScriptFunctionError)

        if remote_notify_on_run:
            script_env.alert('Finished running script "%s".' % name, repeatable=True)

    # Error during execution.
    except:

        if pause_on_exit:

            # Do we want to log the exception?
            import sys
            _exc_type, _exc_value, _exc_tb = sys.exc_info()
            if isinstance(_exc_value, ScriptFunctionError):
                _print_tb = print_error_on_pause in [1, 2]

                # If we are printing the traceback, we do need to print the
                # underlying traceback if we have a ScriptFunctionError.
                _exc_value = _exc_value.error
                _exc_type  = _exc_value.__class__
            else:
                _print_tb = print_error_on_pause in [1, 3]

            if _print_tb:
                import traceback
                traceback.print_exception(_exc_type, _exc_value, _exc_tb)
            _press_any_key_to_exit()

        # Reraise the original error.
        raise

    # Script finished cleanly, just exit normally.
    else:
        if pause_on_exit == 1:
            _press_any_key_to_exit()

def run(name, function):

    '''
    Main entry point for script functions to be executed in a preconfigured
    environment.

    This function wraps up the majority of the functionality offered by
    L{ext_run}, except it allows it to be configured through command line
    arguments.

    This function requires the C{logging} and C{optparse} (or C{optik}) modules
    to be present - if they are not (which is the case for a standard Python
    2.2 distribution), then a lot of the configurability which is normally
    provided will not be available.

    You can find all the configuration options that are available by running
    this function and passing the C{--help} command line option.

    There are several options available which will affect how the script is
    executed, as well as other options which will do something different other
    than executing the script (such as configuring the default connection).

    This script can be passed C{None} as the function value - this will force
    all the command line handling and so on to take place, without requiring
    a script to be executed. This is useful if you want to know whether
    calling this function will actually result in your script being executed -
    for example, you might want to print the text C{"Running script..."}, but
    only if your script is actually going to executed.

    This function does not return a value - if this method returns cleanly,
    then it means the script has been executed (without any problems). This
    function will raise C{SystemExit} instances if it thinks it is appropriate
    to do so - this is always done if the script actually fails to be executed.

    The exit codes are::
        0 - Exit generated by optparse (normally when running with C{--help}).
        2 - Required module is missing.
        3 - No default connection stored.
        4 - Error parsing command line arguments.
        5 - Connection not established.
       16 - Script not executed (command line options resulted in some other behaviour to occur).

    If an exception occurs inside the script, it will be passed back to the
    caller of this function, but it will be wrapped in a
    L{ScriptFunctionError<dopal.errors.ScriptFunctionError>} instance.

    If any exception occurs inside the script, in this function, or in
    L{ext_run}, it will be passed back to the caller of this function (rather
    than being suppressed).

    @note: C{sys.excepthook} may be modified by this function to ensure that
      an exception is only printed once to the user with the most appopriate
      information.
    '''

    EXIT_TRACEBACK = 1
    EXIT_MISSING_MODULE = 2
    EXIT_NO_CONNECTION_STORED = 3
    EXIT_OPTION_PARSING = 4
    EXIT_COULDNT_ESTABLISH_CONNECTION = 5
    EXIT_SCRIPT_NOT_EXECUTED = 16

    def abort_if_no_connection():
        if load_connection_data(error=False) is None:
            _sys_exit(EXIT_NO_CONNECTION_STORED,
                "No connection data stored, please re-run with --setup-connection.")

    try:
        from optik import OptionGroup, OptionParser, OptionValueError, TitledHelpFormatter
    except ImportError:
        try:
            from optparse import OptionGroup, OptionParser, OptionValueError, TitledHelpFormatter
        except ImportError:
            import sys
            if len(sys.argv) == 1:
                abort_if_no_connection()
                if function is not None:
                    ext_run(name, function)
                return

            _module_msg = "Cannot run - you either need to:\n" + \
            "  - Install Python 2.3 or greater\n" + \
            "  - the 'optik' module from http://optik.sf.net\n" + \
            "  - Run with no command line arguments."
            _sys_exit(EXIT_MISSING_MODULE, _module_msg)

    # Customised help formatter.
    #
    # Why do we need one? We don't.
    # Why do *I* want one? Here's why:
    #
    class DOPALCustomHelpFormatter(TitledHelpFormatter):

        #
        # 1) Choice options which I create will have a metavar containing
        #    a long string of all the options that can be used. If it's
        #    bunched together with other options, it doesn't read well, so
        #    I want an extra space.
        #
        def format_option(self, option):
            if option.choices is not None:
                prefix = '\n'
            else:
                prefix = ''

            return prefix + TitledHelpFormatter.format_option(self, option)

        #
        # 2) I don't like the all-lower-case "options" header, so we
        #    capitalise it.
        #
        def format_heading(self, heading):
            if heading == 'options':
                heading = 'Options'
            return TitledHelpFormatter.format_heading(self, heading)

        #
        # 3) I don't like descriptions not being separated out from option
        #    strings, hence the extra space.
        #
        def format_description (self, description):
            result = TitledHelpFormatter.format_description(self, description)
            if description[-1] == '\n':
                result += '\n'
            return result

    parser = OptionParser(formatter=DOPALCustomHelpFormatter(), usage='%prog [options] [--help]')

    def parser_error(msg):
        import sys
        parser.print_usage(sys.stderr)
        _sys_exit(EXIT_OPTION_PARSING, msg)

    parser.error = parser_error

    # We want to raise a different error code on exit.

    def add_option(optname, options, help_text, group=None):

        options_processing = [opt.lower() for opt in options]

        # This is the rest of the help text we will generate.
        help_text_additional = ': one of ' + \
            ', '.join(['"%s"' % option for option in options]) + '.'

        if group is not None:
            parent = group
        else:
            parent = parser

        parent.add_option(
            '--' + optname,
            type="choice",
            metavar='[' + ', '.join(options) + ']',
            choices=options_processing,
            dest=optname.replace('-', '_'),
            help=help_text,# + help_text_additional,
        )

    logging_group = OptionGroup(parser, "Logging setup options",
        "These options will configure how logging is setup for the script.")
    parser.add_option_group(logging_group)

    add_option(
        'run-mode',
        ['background', 'command', 'app'],
        'profile to run script in'
    )

    add_option(
        'logging',
        ['none', 'LOCAL'], # , 'remote', 'FULL'],
        'details where the script can send log messages to',
        logging_group,
    )

    add_option(
        'loglevel',
        ['debug', 'info', 'WARN', 'error', 'fatal'],
        'set the threshold level for logging',
        logging_group,
    )

    add_option(
        'logdest',
        ['FILE', 'stderr'],
        'set the destination for local logging output',
        logging_group,
    )

    logging_group.add_option('--logfile', type='string', help='log file to write out to')

    add_option(
        'needs-connection',
        ['YES', 'no'],
        'indicates whether the ability to connect is required, if not, then it causes the script to terminate cleanly',
    )

    add_option(
        'announce',
        ['yes', 'ERROR', 'no'],
        'indicates whether the user should be alerted via Azureus when the script starts and stops (or just when errors occur)'
    )

    add_option(
        'pause-on-exit',
        ['yes', 'error', 'NO'],
        'indicates whether the script should pause and wait for keyboard input before terminating'
    )

    connection_group = OptionGroup(parser, "Connection setup options",
        "These options are used to set up and test your own personal "
        "connection settings. Running with any of these options will cause "
        "the script not to be executed.\n")

    connection_group.add_option('--setup-connection', action="store_true",
        help="Setup up the default connection data for scripts.")

    connection_group.add_option('--test-connection', action="store_true",
        help="Test that DOPAL can connect to the connection configured.")

    connection_group.add_option('--delete-connection', action="store_true",
        help="Removes the stored connection details.")

    script_env_group = OptionGroup(parser, "Script setup options",
        "These options are used to extract and set information related to "
        "the environment set up for the script. Running with any of these "
        "options will cause the script not to be executed.\n")

    script_env_group.add_option('--data-dir-info', action="store_true",
        help="Prints out where the data directory is for this script.")

    parser.add_option_group(connection_group)
    parser.add_option_group(script_env_group)

    options, args = parser.parse_args()

    # We don't permit an explicit filename AND a conflicting log destination.
    if options.logdest not in [None, 'file'] and options.logfile:
        parser.error("cannot set conflicting --logdest and --logfile values")

    # We don't allow any command line argument which will make us log to file
    # if local logging isn't enabled.
    if options.logging not in [None, 'local', 'full'] and \
        (options.logdest or options.logfile or options.loglevel):
        parser.error("--logging setting conflicts with other parameters")

    # Want to know where data is kept?
    if options.data_dir_info:
        def _process_senv(senv):
            def _process_senv_file(fpath_func, descr):
                fpath = fpath_func(create_dir=False)
                print descr + ':',
                if not os.path.exists(fpath):
                   print '(does not exist)',
                print
                print '  "%s"' % fpath
                print

            if senv.name is None:
                names = [
                    'Global data directory',
                    'Global default connection details',
                    'Global logging configuration file',
                ]
            else:
                names = [
                    'Script data directory',
                    'Script data file',
                    'Script logging configuration file',
                ]

            _process_senv_file(senv.get_data_dir, names[0])
            _process_senv_file(senv.get_data_file_path, names[1])
            _process_senv_file(senv.get_log_config_path, names[2])

        _process_senv(ScriptEnvironment(None, 'connection.dpl'))
        _process_senv(ScriptEnvironment(name))
        _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)

    # Delete connection details?
    if options.delete_connection:
        conn_path = ScriptEnvironment(None, 'connection.dpl').get_data_file_path(create_dir=False)
        if not os.path.exists(conn_path):
            print 'No stored connection data file found.'
        else:
            try:
                os.remove(conn_path)
            except OSError, error:
                print 'Unable to delete "%s"...' % conn_path
                print ' ', error
            else:
                print 'Deleted "%s"...' % conn_path
        _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)

    # Do we need to setup a connection.
    if options.setup_connection:
        input_connection_data()
        _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)

    # Want to test the connection?
    if options.test_connection:
        abort_if_no_connection()
        connection = get_stored_connection()

        print 'Testing connection to', connection.link_data['host'], '...'
        import dopal.errors
        try:
            connection.establish_connection(force=False)
        except dopal.errors.LinkError, error:
            print "Unable to establish a connection..."
            print "   Destination:", connection.get_cgi_path(auth_details=True)
            print "   Error:", error.to_error_string()
            _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)
        else:
            print "Connection established, examining XML/HTTP plugin settings..."

            # While we're at it, let the user know whether their settings are
            # too restrictive.
            #
            # XXX: We need a subclass of RemoteMethodError representing
            # Access Denied messages.
            from dopal.errors import NoSuchMethodError, RemoteMethodError

            # Read-only methods?
            try:
                connection.get_plugin_interface().getTorrentManager()
            except RemoteMethodError:
                read_only = True
            else:
                read_only = False

            # XXX: Some sort of plugin utility module?
            if read_only:
                print
                print 'NOTE: The XML/HTTP plugin appears to be set to read-only - this may restrict'
                print '      scripts from working properly.'
                _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)

            # Generic classes became the default immediately after 2.4.0.2.
            if connection.get_azureus_version() > (2, 4, 0, 2):
                generic_classes = True
                generic_classes_capable = True
            elif connection.get_azureus_version() < (2, 4, 0, 0):
                generic_classes = False
                generic_classes_capable = False
            else:
                generic_classes_capable = True
                try:
                    connection.get_plugin_interface().getLogger()
                except NoSuchMethodError:
                    generic_classes = False
                else:
                    generic_classes = True

            if not generic_classes:
                print
                if generic_classes_capable:
                    print 'NOTE: The XML/HTTP plugin appears to have the "Use generic classes"'
                    print '      setting disabled. This may prevent some scripts from running'
                    print '      properly - please consider enabling this setting.'
                else:
                    print 'NOTE: This version of Azureus appears to be older than 2.4.0.0.'
                    print '      This may prevent some scripts from running properly.'
                    print '      Please consider upgrading an updated version of Azureus.'
            else:
                print 'No problems found with XML/HTTP plugin settings.'

            _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)

    # Is the logging module available?
    try:
        import logging
    except ImportError:
        logging_available = False
    else:
        logging_available = True

    # Now we need to figure out what settings have been defined.
    #
    # In level of importance:
    #   - Option on command line.
    #   - Default options for chosen profile.
    #   - Default global settings.

    # Global default settings.
    settings = {
        'logging': 'none',
        'needs_connection': 'yes',
        'announce': 'error',
        'pause_on_exit': 'no',
    }

    # Profile default settings.
    #
    # I'll only define those settings which differ from the global defaults.
    settings.update({
        'background': {
            'needs_connection': 'no'
        },
        'command': {
            'logging': 'none',
            'announce': 'no',
        },
        'app': {
            'logging': 'none',
            'pause_on_exit': 'error',
            'announce': 'no',
        },
        None: {},
    }[options.run_mode])

    # Explicitly given settings.
    for setting_name in settings.keys():
        if getattr(options, setting_name) is not None:
            settings[setting_name] = getattr(options, setting_name)

    # Ensure that the user doesn't request logging settings which we can't
    # support.
    #
    # logdest = file or stderr
    # logfile = blah
    # logging -> if local, then log to (default) file.
    if not logging_available and \
        (options.loglevel is not None or \
         settings['logging'] != 'none' or \
         options.logfile or  options.logdest):

        _module_msg = "Cannot run - you either need to:\n" + \
            "  - Install Python 2.3 or greater\n" + \
            "  - the 'logging' module from http://www.red-dove.com/python_logging.html\n" + \
            "  - Run the command again without --loglevel or --logging parameters"
        _sys_exit(EXIT_MISSING_MODULE, _module_msg)

    # What log level to use?
    loglevel = None
    if options.loglevel is not None:
        loglevel = getattr(logging, options.loglevel.upper())

    # Now we interpret the arguments given and execute ext_run.
    kwargs = {}
    kwargs['silent_on_connection_error'] = settings['needs_connection'] == 'no'
    kwargs['pause_on_exit'] = {'yes': 1, 'no': 0, 'error': 2}[settings['pause_on_exit']]

    kwargs['remote_notify_on_run'] = settings['announce'] == 'yes'
    kwargs['remote_notify_on_error'] = settings['announce'] in ['yes', 'error']

    # Logging settings.
    if options.logdest == 'stderr':
        setup_logging = True
        logging_to_stderr = True
    else:
        setup_logging = None
        logging_to_stderr = False

    kwargs['setup_logging'] = setup_logging
    kwargs['log_level'] = loglevel
    kwargs['log_to_file'] = options.logdest == 'file' or \
        options.logfile is not None
    kwargs['log_file'] = options.logfile

    # print_error_on_pause:
    #   Do we want to print the error? That's a bit tough...
    #
    # If we know that we are logging to stderr, then any internal script
    # error will already be printed, so we won't want to do it in that case.
    #
    # If an error has occurred while setting up, we will let it be printed
    # if we pause on errors, but then we have to suppress it from being
    # reprinted (through sys.excepthook). Otherwise, we can let sys.excepthook
    # handle it.
    #
    # If we aren't logging to stderr, and an internal script error occurs,
    # we can do the same thing as we currently do for setting up errors.
    #
    # However, if we are logging to stderr, we need to remember that setting
    # up errors aren't fed through to the logger, so we should print setting
    # up errors.
    if logging_to_stderr:
        # Print only initialisation errors.
        kwargs['print_error_on_pause'] = 3
    else:
        # Print all errors.
        kwargs['print_error_on_pause'] = 1

    print_traceback_in_ext_run = kwargs['pause_on_exit'] and kwargs['print_error_on_pause']
    abort_if_no_connection()

    from dopal.errors import LinkError, ScriptFunctionError

    # Execute script.
    if function is not None:
        try:
            ext_run(name, function, **kwargs)
        except LinkError, error:
            print "Unable to establish a connection..."
            print "   Connection:", error.obj
            print "   Error:", error.to_error_string()
            _sys_exit(EXIT_SCRIPT_NOT_EXECUTED)
        except:
            # Override sys.excepthook here.
            #
            # It does two things - firstly, if we know that the traceback
            # has already been printed to stderr, then we suppress it
            # being printed again. Secondly, if the exception is a
            # ScriptFunctionError, it will print the original exception
            # instead.
            import sys

            previous_except_hook = sys.excepthook
            def scripting_except_hook(exc_type, exc_value, exc_tb):

                is_script_function_error = False
                if isinstance(exc_value, ScriptFunctionError):
                    exc_value = exc_value.error
                    exc_type = exc_value.__class__
                    is_script_function_error = True

                if logging_to_stderr and is_script_function_error:
                    # Only script function errors will be logged to the
                    # logger, so we'll only suppress the printing of this
                    # exception if the exception is a scripting function
                    # error.
                    return
                if print_traceback_in_ext_run:
                    return
                previous_except_hook(exc_type, exc_value, exc_tb)

            sys.excepthook = scripting_except_hook
            raise

    return

if __name__ == '__main__':

    SCRIPT_NAME = 'scripting_main'

    # Verify that the command line arguments are accepted.
    run(SCRIPT_NAME, None)

    # Set up two scripts, one which should work, and the other which will fail.
    # We add in some delays, just so things don't happen too quickly.
    print 'The following code will do 2 things - it will run a script which'
    print 'will work, and then run a script which will fail. This is for'
    print 'testing purposes.'
    print

    def do_something_good(script_env):
        print "DownloadManager:", script_env.connection.get_plugin_interface().getDownloadManager()

    def do_something_bad(script_env):
        print "UploadManager:", script_env.connection.get_plugin_interface().getUploadManager()

    print 'Running good script...'
    run(SCRIPT_NAME, do_something_good)
    print

    print 'Finished running good script, waiting for 4 seconds...'
    import time
    time.sleep(4)
    print

    print 'Running bad script...'
    run(SCRIPT_NAME, do_something_bad)
    print
