# File: interact.py
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
Interactive Python application which initialises DOPAL to connect with a chosen
Azureus server.
'''


def main():
    '''Function to invoke this application.'''
    # Get host and port.
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
        connection_details['password'] = getpass.getpass('Enter password: ')

    my_locals = {}
    from dopal.main import make_connection
    connection = make_connection(**connection_details)
    connection.is_persistent_connection = True

    from dopal.errors import LinkError
    try:
        interface = connection.get_plugin_interface()
    except LinkError, error:
        interface = None
        connection_error = error
    else:
        connection_error = None

    from dopal import __version_str__
    banner = "DOPAL %s - interact module\n\n" % __version_str__
    banner += "Connection object stored in 'connection' variable.\n"

    if connection_error is None:
        banner += "Plugin interface stored in 'interface' variable.\n"
    else:
        banner += "\nError getting plugin interface object - could not connect to Azureus, error:\n  %s" % connection_error.to_error_string()

    import dopal
    if dopal.__dopal_mode__ == 1:
        banner += "\nRunning in DEBUG mode.\n"
    elif dopal.__dopal_mode__ == 2:
        banner += '\nWARNING: Running in "epydoc" mode.\n'

    my_locals['connection'] = connection
    if interface is not None:
        my_locals['interface'] = interface
    my_locals['__import__'] = __import__

    print
    print '------------------------'
    print

    import code
    code.interact(banner, local=my_locals)

if __name__ == '__main__':
    def _main(env):
       return main()

    import dopal.scripting
    dopal.scripting.ext_run(
        'dopal.interact', _main,
        make_connection=False,
        setup_logging=False,
        timeout=8,
        pause_on_exit=2,
    )
