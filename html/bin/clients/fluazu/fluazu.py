#!/usr/bin/env python
################################################################################
# $Id$
# $Date$
# $Revision$
################################################################################
#                                                                              #
# LICENSE                                                                      #
#                                                                              #
# This program is free software; you can redistribute it and/or                #
# modify it under the terms of the GNU General Public License (GPL)            #
# as published by the Free Software Foundation; either version 2               #
# of the License, or (at your option) any later version.                       #
#                                                                              #
# This program is distributed in the hope that it will be useful,              #
# but WITHOUT ANY WARRANTY; without even the implied warranty of               #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                 #
# GNU General Public License for more details.                                 #
#                                                                              #
# To read the license please visit http://www.gnu.org/copyleft/gpl.html        #
#                                                                              #
#                                                                              #
################################################################################
#                                                                              #
#  Requirements :                                                              #
#   * DOPAL                                                                    #
#     http://dopal.sourceforge.net                                             #
#   * Azureus with XML/HTTP Plugin                                             #
#     http://azureus.sourceforge.net                                           #
#     http://azureus.sourceforge.net/plugin_details.php?plugin=xml_http_if)    #
#                                                                              #
################################################################################
# standard-imports
import sys
# fluazu
from fluazu.FluAzuD import FluAzuD
################################################################################

""" ------------------------------------------------------------------------ """
""" main                                                                     """
""" ------------------------------------------------------------------------ """
if __name__ == '__main__':

    # version
    if sys.argv[1:] == ['--version']:
        from fluazu import __version_str__
        print __version_str__
        sys.exit(0)

    # check argv-length
    if len(sys.argv) < 7:
        from fluazu import __version_str__
        print "fluazu %s" % __version_str__
        print "\nError: missing arguments.\n"
        print "Usage:"
        print "fluazu.py path host port secure username password\n"
        print " path     : flux-path"
        print " host     : host of azureus-server"
        print " port     : port of azureus-server (xml/http, default: 6884)"
        print " secure   : use secure connection to azureus (0/1)"
        print " username : username to use when connecting to azureus-server"
        print " password : password to use when connecting to azureus-server\n"
        sys.exit(0)

    # run daemon
    daemon = FluAzuD()
    exitVal = 0
    try:
        exitVal = daemon.run(sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6])
    except KeyboardInterrupt:
        daemon.shutdown()
        exitVal = 0
    except Exception, e:
        print e

    # exit
    sys.exit(exitVal)
