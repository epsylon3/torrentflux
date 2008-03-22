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
package Watch;
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

# jobs-hash: dir => {  # anonymous hash
#                     U => user,
#                     P => profile, # optional
#                                   # optional other attributes, currently ignored
#                     D => dir
#                   }
my %jobs;


################################################################################
# constants                                                                    #
################################################################################

my $delimJobs      = ';';
my $delimJob       = ':';
my $delimComponent = '=';


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
	# log
	Fluxd::printMessage("Watch", "shutdown\n");
	# undef
	undef %jobs;
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
	$interval = FluxDB->getFluxConfig("fluxd_Watch_interval");
	if (!(defined $interval)) {
		# message
		$message = "interval not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# jobs
	my $jobs = FluxDB->getFluxConfig("fluxd_Watch_jobs");
	if (!(defined $jobs)) {
		# message
		$message = "jobs not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	Fluxd::printMessage("Watch", "initializing (loglevel: ".$loglevel." ; interval: ".$interval." ; jobs: ".$jobs.")\n");

	# parse jobs
	my (@jobsAry) = split(/$delimJobs/,$jobs);
	foreach my $jobEntry (@jobsAry) {
		# U=user:[P=profile:]D=dir

		$_ = Fluxd::lrtrim $jobEntry;
		next if !defined $_ || length $_ == 0;

		my %jobEntry;
		while (1) {
			if (/^D$delimComponent/) {	# Dir: final component.
				$jobEntry{D} = substr $_, 2;
				last;
			} else {					# Other component.
				my (@jobAry) = split /$delimJob/, $_, 2;
				if ($#jobAry != 1 || $jobAry[0] !~ /^\s*[A-Z]$delimComponent/) {
					# message
					$message = "invalid job entry";
					# set state
					$state = Fluxd::MOD_STATE_ERROR;
					# return
					return 0;
				}
				$_ = Fluxd::lrtrim shift @jobAry;
				$jobEntry{substr $_, 0, 1} = substr $_, 2;
				$_ = Fluxd::lrtrim shift @jobAry;
			}
		}

		if (
			defined $jobEntry{U} && length $jobEntry{U} > 0 &&
			defined $jobEntry{D} && -d $jobEntry{D}
		) {
			if ($loglevel > 1) {
				Fluxd::printMessage("Watch",
					"job : user=".$jobEntry{U}.
					((defined $jobEntry{P} && length $jobEntry{P} > 0) ? ", profile=".$jobEntry{P} : '').
					", dir=".$jobEntry{D}.
					"\n"
				);
			}
			$jobs{$jobEntry{D}} = { %jobEntry };
		}
	}

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
# Returns:                                                                     #
#------------------------------------------------------------------------------#
sub main {

	if ((time() - $time_last_run) >= $interval) {

		# watch in dirs for dropped meta-files
		foreach my $key (sort keys %jobs) {
			my $job = $jobs{$key};
			my $user    = defined $$job{U} ? $$job{U} : '';
			my $profile = defined $$job{P} ? $$job{P} : '';
			my $dir     = defined $$job{D} ? $$job{D} : '';
			if (length $user > 0 && -d $dir) {
				if ($loglevel > 1) {
					my $msg = "executing job :\n";
					$msg .= " user: ".$user."\n";
					$msg .= " profile: ".$profile."\n" if length $profile > 0;
					$msg .= " dir: ".$dir."\n";
					Fluxd::printMessage("Watch", $msg);
				}
				# exec
				tfwatch($dir, $user, $profile);
			}
		}

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
	$return .= "\n-= Watch Revision ".$VERSION." =-\n";
	$return .= "interval : ".$interval." s \n";
	$return .= "jobs :\n";
	foreach my $key (sort keys %jobs) {
		my $job = $jobs{$key};
		my $user    = defined $$job{U} ? $$job{U} : '';
		my $profile = defined $$job{P} ? $$job{P} : '';
		my $dir     = defined $$job{D} ? $$job{D} : '';
		if (length $user > 0 && -d $dir) {
			$return .= "  * user: ".$user."\n";
			$return .= "    profile: ".$profile."\n" if length $profile > 0;
			$return .= "    dir: ".$dir."\n";
		}
	}
	return $return;
}

#------------------------------------------------------------------------------#
# Sub: tfwatch                                                                 #
# Arguments: dir, user[, profile]                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub tfwatch {
	my $dir = shift;
	my $user = shift;
	my $profile = shift;
	my $options = 'ds';
	my @extra;
	if (defined($profile) && length($profile) > 0) {
		$options .= 'p';
		push @extra, $profile;
	}
	# fluxcli-call
	return Fluxd::fluxcli("watch", $dir, $user, $options, @extra);
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
