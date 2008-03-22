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
package StatFile;
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
# -1 error
#  0 null
#  1 initialized (stat-file loaded)
my $state = 0;

# message, error etc. keep it in one string for simplicity atm.
my $message = "";

# stat-file
my $statFile = "";

# stat-file-data-hash, keys 1 : 1 StatFile-class of TF
my %data;
# running
# percent_done
# time_left
# down_speed
# up_speed
# transferowner
# seeds
# peers
# sharing
# seedlimit
# uptotal
# downtotal
# size

################################################################################
# constructor + destructor                                                     #
################################################################################

#------------------------------------------------------------------------------#
# Sub: new                                                                     #
# Arguments: null or path to stat-file                                         #
# Returns: object reference                                                    #
#------------------------------------------------------------------------------#
sub new {
	my $class = shift;
	my $self = bless ({}, ref ($class) || $class);
	# initialize file now if name supplied in ctor
	$statFile = shift;
	if (defined($statFile)) {
		$self->initialize($statFile);
	}
	return $self;
}

#------------------------------------------------------------------------------#
# Sub: destroy                                                                 #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub destroy {

	# set state
	$state = 0;

	# strings
	$message = "";
	$statFile = "";

	# undef
	undef %data;
}

################################################################################
# public methods                                                               #
################################################################################

#------------------------------------------------------------------------------#
# Sub: initialize. this is separated from constructor to call it independent   #
#      from object-creation.                                                   #
# Arguments: path to stat-file                                                 #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub initialize {

	shift; # class

	# path-to-stat-file
	$statFile = shift;
	if (!(defined $statFile)) {
		# message
		$message = "path-to-stat-file not defined";
		# set state
		$state = -1;
		# return
		return 0;
	}

	# read in stat-file + set fields
	if (-f $statFile) {
		# sep + open file
		my $lineSep = $/;
		undef $/;
		open(STATFILE,"<$statFile");
		# read data
		my $content = <STATFILE>;
		# close file + sep
		close STATFILE;
		$/ = $lineSep;
		# process data
		my @contentary = split(/\n/, $content);
		%data = ();
		$data{"running"} = shift @contentary;
		$data{"percent_done"} = shift @contentary;
		$data{"time_left"} = shift @contentary;
		$data{"down_speed"} = shift @contentary;
		$data{"up_speed"} = shift @contentary;
		$data{"transferowner"} = shift @contentary;
		$data{"seeds"} = shift @contentary;
		$data{"peers"} = shift @contentary;
		$data{"sharing"} = shift @contentary;
		$data{"seedlimit"} = shift @contentary;
		$data{"uptotal"} = shift @contentary;
		$data{"downtotal"} = shift @contentary;
		$data{"size"} = shift @contentary;
		# set state
		$state = 1;
		# return
		return 1;
	} else {
		# message
		$message = "stat-file no file";
		# set state
		$state = -1;
		# return
		return 0;
	}
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
# Sub: get                                                                     #
# Arguments: key                                                               #
# Returns: value                                                               #
#------------------------------------------------------------------------------#
sub get {
	shift; # class
	my $key = shift;
	return $data{$key};
}

#------------------------------------------------------------------------------#
# Sub: set                                                                     #
# Arguments: key,value                                                         #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub set {
	shift; # class
	my $key = shift;
	$data{$key} = shift;
}

#------------------------------------------------------------------------------#
# Sub: getFilename                                                             #
# Arguments: null                                                              #
# Returns: string                                                              #
#------------------------------------------------------------------------------#
sub getFilename {
	return $statFile;
}

#------------------------------------------------------------------------------#
# Sub: getData                                                                 #
# Arguments: null                                                              #
# Returns: hash                                                                #
#------------------------------------------------------------------------------#
sub getData {
	return %data;
}

#------------------------------------------------------------------------------#
# Sub: write                                                                   #
# Arguments: null                                                              #
# Returns: 1|0                                                                 #
#------------------------------------------------------------------------------#
sub write {
	# open file
	open(STATFILE,">$statFile") or return 0;
	my $retVal = 1;
	# content
	my $content = "";
	$content .= $data{"running"}."\n";
	$content .= $data{"percent_done"}."\n";
	$content .= $data{"time_left"}."\n";
	$content .= $data{"down_speed"}."\n";
	$content .= $data{"up_speed"}."\n";
	$content .= $data{"transferowner"}."\n";
	$content .= $data{"seeds"}."\n";
	$content .= $data{"peers"}."\n";
	$content .= $data{"sharing"}."\n";
	$content .= $data{"seedlimit"}."\n";
	$content .= $data{"uptotal"}."\n";
	$content .= $data{"downtotal"}."\n";
	$content .= $data{"size"};
	# print
	print STATFILE $content or $retVal = 0;
	# close file
	close(STATFILE);
	# return
	return $retVal;
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
