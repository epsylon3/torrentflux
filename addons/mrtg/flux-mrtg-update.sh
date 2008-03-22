#!/bin/sh
################################################################################
# $Id$
# $Revision$
# $Date$
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

# defaults
FLUXPATH="/usr/local/torrentflux"
CONFFILE="/etc/mrtg/mrtg.flux.cfg"
BIN_MRTG="/usr/bin/mrtg"
DEFAULT_CONFFILE="/etc/mrtg/flux-mrtg.conf"

# load conf-file
if [ "$1X" != "X" ] ; then
  if [ -e "$1" ] ; then
    . $1
  fi
else
  if [ -e "$DEFAULT_CONFFILE" ] ; then
    . $DEFAULT_CONFFILE
  fi
fi

# check for mrtg-bin
if [ ! -x "$BIN_MRTG" ] ; then
  BIN_MRTG=`whereis mrtg | awk '{print $2}'`
  if [ ! -x "$BIN_MRTG" ] ; then
    echo "error: cant find mrtg"
    exit
  fi
fi

# check for mrtg-directory, create if missing.
if [ ! -d "$FLUXPATH/.mrtg" ] ; then
  mkdir -p $FLUXPATH/.mrtg
fi

# invoke mrtg for flux
$BIN_MRTG $CONFFILE | tee -a $FLUXPATH/.mrtg/mrtg.log

