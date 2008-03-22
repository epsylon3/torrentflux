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
# standard-imports
import sys
import time
################################################################################

""" ------------------------------------------------------------------------ """
""" getPrefix                                                                """
""" ------------------------------------------------------------------------ """
def getPrefix():
    return time.strftime('[%Y/%m/%d - %H:%M:%S]') + " "

""" ------------------------------------------------------------------------ """
""" getOutput                                                                """
""" ------------------------------------------------------------------------ """
def getOutput(message):
    return getPrefix() + message + "\n"

""" ------------------------------------------------------------------------ """
""" printMessage                                                             """
""" ------------------------------------------------------------------------ """
def printMessage(message):
    sys.stdout.write(getOutput(message))
    sys.stdout.flush()

""" ------------------------------------------------------------------------ """
""" printError                                                               """
""" ------------------------------------------------------------------------ """
def printError(message):
    sys.stderr.write(getOutput(message))
    sys.stderr.flush()

""" ------------------------------------------------------------------------ """
""" printException                                                           """
""" ------------------------------------------------------------------------ """
def printException():
    print getPrefix(), sys.exc_info()
    sys.stdout.flush()
