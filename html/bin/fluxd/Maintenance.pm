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
package Maintenance;
use strict;
use warnings;
################################################################################

################################################################################
# fields                                                                       #
################################################################################

# version in a var
my $VERSION = do {
	my @r = (q$Revision$ =~ /\d+/g); sprintf "%d"."%02d" x $#r, @r };

# state
my $state = Fluxd::MOD_STATE_NULL;

# message, error etc. keep it in one string for simplicity atm.
my $message = "";

# loglevel
my $loglevel = 0;

# run-interval
my $interval;

# time of last run
my $time_last_run = 0;

# transfer-restart
my $trestart = 0;

################################################################################
# constructor + destructor                                                     #
################################################################################

#------------------------------------------------------------------------------#
# Sub: new                                                                     #
# Arguments: Null                                                              #
# Returns: object reference                                                    #
#------------------------------------------------------------------------------#
sub new {
	my $class = shift;
	my $self = bless ({}, ref ($class) || $class);
	return $self;
}

#------------------------------------------------------------------------------#
# Sub: destroy                                                                 #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub destroy {
	# set state
	$state = Fluxd::MOD_STATE_NULL;
	# print
	Fluxd::printMessage("Maintenance", "shutdown\n");
}

################################################################################
# public methods                                                               #
################################################################################

#------------------------------------------------------------------------------#
# Sub: initialize. this is separated from constructor to call it independent   #
#      from object-creation.                                                   #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub initialize {

	shift; # class

	# loglevel
	$loglevel = Fluxd::getLoglevel();
	if (!(defined $loglevel)) {
		# message
		$message = "loglevel not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# interval
	$interval = FluxDB->getFluxConfig("fluxd_Maintenance_interval");
	if (!(defined $interval)) {
		# message
		$message = "interval not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# transfer-restart
	$trestart = FluxDB->getFluxConfig("fluxd_Maintenance_trestart");
	if (!(defined $trestart)) {
		# message
		$message = "transfer-restart not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# print
	Fluxd::printMessage("Maintenance", "initializing (loglevel: ".$loglevel." ; interval: ".$interval." ; trestart: ".$trestart.")\n");

	# reset last run time
	$time_last_run = time();

	# set state
	$state = Fluxd::MOD_STATE_OK;

	# return
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: getVersion                                                              #
# Arguments: null                                                              #
# Returns: VERSION                                                             #
#------------------------------------------------------------------------------#
sub getVersion {
	return $VERSION;
}

#------------------------------------------------------------------------------#
# Sub: getState                                                                #
# Arguments: null                                                              #
# Returns: state                                                               #
#------------------------------------------------------------------------------#
sub getState {
	return $state;
}

#------------------------------------------------------------------------------#
# Sub: getMessage                                                              #
# Arguments: null                                                              #
# Returns: message                                                             #
#------------------------------------------------------------------------------#
sub getMessage {
	return $message;
}

#------------------------------------------------------------------------------#
# Sub: set                                                                     #
# Arguments: Variable [value]                                                  #
# Returns:                                                                     #
#------------------------------------------------------------------------------#
sub set {
}

#------------------------------------------------------------------------------#
# Sub: main                                                                    #
# Arguments: Null                                                              #
# Returns: Info String                                                         #
#------------------------------------------------------------------------------#
sub main {

	if ((time() - $time_last_run) >= $interval) {

		# print
		if ($loglevel > 1) {
			Fluxd::printMessage("Maintenance", "executing maintenance (trestart: ".$trestart."):\n");
		}

		# exec
		tfmaintenance();

		# set last run time
		$time_last_run = time();
	}
}

#------------------------------------------------------------------------------#
# Sub: command                                                                 #
# Arguments: command-string                                                    #
# Returns: result-string                                                       #
#------------------------------------------------------------------------------#
sub command {
	shift; # class
	my $command = shift;
	# TODO
	return "";
}

#------------------------------------------------------------------------------#
# Sub: status                                                                  #
# Arguments: Null                                                              #
# Returns: Status information                                                  #
#------------------------------------------------------------------------------#
sub status {
	my $return = "";
	$return .= "\n-= Maintenance Revision ".$VERSION." =-\n";
	$return .= "interval : ".$interval." s \n";
	$return .= "trestart : ".$trestart."\n";
	return $return;
}

#------------------------------------------------------------------------------#
# Sub: tfmaintenance                                                           #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub tfmaintenance {
	# fluxcli-call
	return Fluxd::fluxcli("maintenance", ($trestart == 1) ? "true" : "false");
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
