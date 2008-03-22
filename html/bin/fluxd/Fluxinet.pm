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
#   * IO::Select       ( perl -MCPAN -e "install IO::Select" )                 #
#   * IO::Socket::INET ( perl -MCPAN -e "install IO::Socket::INET" )           #
#                                                                              #
################################################################################
package Fluxinet;
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

# port
my $port = 3150;

# server-socket
my ($server, $select);

# modules loaded
my $modsLoaded = 0;

################################################################################
# constructor + destructor                                                     #
################################################################################

#------------------------------------------------------------------------------#
# Sub: new                                                                     #
# Arguments: Null                                                              #
# Returns: Info String                                                         #
#------------------------------------------------------------------------------#
sub new {
	my $class = shift;
	my $self = bless ({}, ref ($class) || $class);
	return $self;
}

#------------------------------------------------------------------------------#
# Sub: destroy                                                                 #
# Arguments: Null                                                              #
# Returns: Info String                                                         #
#------------------------------------------------------------------------------#
sub destroy {
	# set state
	$state = Fluxd::MOD_STATE_NULL;
	# log
	Fluxd::printMessage("Fluxinet", "shutdown\n");
	# remove
	foreach my $handle ($select->handles) {
		$select->remove($handle);
		$handle->close;
	}
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

	# $port
	$port = FluxDB->getFluxConfig("fluxd_Fluxinet_port");
	if (!(defined $port)) {
		# message
		$message = "port not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	Fluxd::printMessage("Fluxinet", "initializing (loglevel: ".$loglevel." ; port: ".$port.")\n");

	# load modules
	if ($modsLoaded == 0) {
		if (loadModules() != 1) {
			return 0;
		}
	}

	# Create the read set
	$select = new IO::Select();

	# Create the server socket
	$server = IO::Socket::INET->new(
		LocalPort       => $port,
		Proto           => 'tcp',
		Listen          => 16,
		Reuse           => 1);
	if (!(defined $server)) {
		# message
		$message = "could not create server socket";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	$select->add($server);

	# log
	if ($loglevel > 1) {
		Fluxd::printMessage("Fluxinet", "tcp-server-socket setup on port ".$port."\n");
	}

	# set state
	$state = Fluxd::MOD_STATE_OK;

	# return
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: loadModules                                                             #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadModules {

	# load IO::Select
	if ($loglevel > 2) {
		Fluxd::printMessage("Fluxinet", "loading Perl-module IO::Select\n");
	}
	if (eval "require IO::Select")  {
		IO::Select->import();
	} else {
		# message
		$message = "cant load perl-module IO::Socket::INET : ".$@;;
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# load IO::Socket::INET
	if ($loglevel > 2) {
		Fluxd::printMessage("Fluxinet", "loading Perl-module IO::Socket\n");
	}
	if (eval "require IO::Socket::INET")  {
		IO::Socket::INET->import();
	} else {
		# message
		$message = "cant load perl-module IO::Socket::INET : ".$@;;
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# set flag
	$modsLoaded = 1;

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
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub main {
	# Get the readable handles. timeout is 0, only process stuff that can be
	# read NOW.
	my $return = "";
	my @ready = $select->can_read(0);
	foreach my $socket (@ready) {
		if ($socket == $server) {
			my $new = $socket->accept();
			$select->add($new);
		} else {
			my $buf = "";
			my $char = getc($socket);
			while (defined($char) && $char ne "\n") {
					$buf .= $char;
					$char = getc($socket);
			}
			$return = Fluxd::processRequest($buf);
			$socket->send($return);
			$select->remove($socket);
			close($socket);
		}
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
	$return .= "\n-= Fluxinet Revision ".$VERSION." =-\n";
	$return .= "port : ".$port."\n";
	return $return;
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
