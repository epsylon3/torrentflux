# File: logutils.py
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
Module containing various logging-related utilities and classes.
'''
# From:
#   http://news.hping.org/comp.lang.python.archive/19937.html
def noConfig():
    '''
    This function should be called to indicate that you are explicitly
    notifying the logging module that you do not intend to add any
    handlers to the root logger.

    This suppresses the warning C{No handlers could be found for logger
    "root"} from being emitted. This function performs the following
    call to disable the warning::
        logging.root.manager.emittedNoHandlerWarning = True
    '''
    import logging
    logging.root.manager.emittedNoHandlerWarning = True
