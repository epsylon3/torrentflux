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
package Rssad;
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

# jobs
my @jobs;

# data-dir
my $dataDir = "rssad/";

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
	Fluxd::printMessage("Rssad", "shutdown\n");
	# undef
	undef @jobs;
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

	# data-dir
	my $ddir = Fluxd::getPathDataDir();
	if (!(defined $ddir)) {
		# message
		$message = "data-dir not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	$dataDir = $ddir . $dataDir;
	# check if our main-dir exists. try to create if it doesnt
	if (! -d $dataDir) {
		Fluxd::printMessage("Rssad", "creating data-dir : ".$dataDir."\n");
		mkdir($dataDir, 0700);
		if (! -d $dataDir) {
			# message
			$message = "data-dir does not exist and cannot be created";
			# set state
			$state = Fluxd::MOD_STATE_ERROR;
			# return
			return 0;
		}
	}

	# interval
	$interval = FluxDB->getFluxConfig("fluxd_Rssad_interval");
	if (!(defined $interval)) {
		# message
		$message = "interval not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# jobs
	my $jobs = FluxDB->getFluxConfig("fluxd_Rssad_jobs");
	if (!(defined $jobs)) {
		# message
		$message = "jobs not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	Fluxd::printMessage("Rssad", "initializing (loglevel: ".$loglevel." ; data-dir: ".$dataDir." ; interval: ".$interval." ; jobs: ".$jobs.")\n");

	# parse jobs
	# job1|job2|job3
	my (@jobsAry) = split(/\|/, $jobs);
	foreach my $jobEntry (@jobsAry) {
		# savedir#url#filtername
		chomp $jobEntry;
		my (@jobAry) = split(/#/,$jobEntry);
		my $savedir = shift @jobAry;
		chomp $savedir;
		my $url = shift @jobAry;
		chomp $url;
		my $filter = shift @jobAry;
		chomp $filter;
		# job-entry
		if ($loglevel > 1) {
			Fluxd::printMessage("Rssad", "job : savedir=".$savedir.", url=".$url.", filter=".$filter."\n");
		}
		# add to jobs-array
		if ((!($savedir eq "")) && (!($url eq "")) && (!($filter eq ""))) {
			my $index = scalar(@jobs);
			$jobs[$index] = {
				'savedir' => $savedir,
				'filter' => $filter,
				'url' => $url
			};
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

		# exec tfrss-jobs
		my $jobCount = scalar(@jobs);
		for (my $i = 0; $i < $jobCount; $i++) {
			if ($loglevel > 1) {
				my $msg = "executing job :\n";
				$msg .= " savedir: ".$jobs[$i]{"savedir"}."\n";
				$msg .= " filter: ".$dataDir.$jobs[$i]{"filter"}."\n";
				$msg .= " url: ".$jobs[$i]{"url"}."\n";
				Fluxd::printMessage("Rssad", $msg);
			}
			# exec
			tfrss($jobs[$i]{"savedir"}, $dataDir.$jobs[$i]{"filter"}, $jobs[$i]{"url"});
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
	$return .= "\n-= Rssad Revision ".$VERSION." =-\n";
	$return .= "interval : ".$interval." s \n";
	$return .= "jobs :\n";
	my $jobCount = scalar(@jobs);
	for (my $i = 0; $i < $jobCount; $i++) {
		$return .= "  * savedir: ".$jobs[$i]{"savedir"}."\n";
		$return .= "    filter: ".$jobs[$i]{"filter"}."\n";
		$return .= "    url: ".$jobs[$i]{"url"}."\n";
	}
	return $return;
}

#------------------------------------------------------------------------------#
# Sub: tfrss                                                                   #
# Arguments: save-location, filter, rss-feed-url                               #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub tfrss {
	my $save = shift;
	my $filter = shift;
	my $url = shift;
	my $logfile = $filter.".log";
	# log the invocation
	open(FILTERLOG,">>$logfile");
	print FILTERLOG localtime()." - ".$url."\n";
	close(FILTERLOG);
	# fluxcli-call
	return Fluxd::fluxcli("rss", $save, $filter.".dat", $filter.".hist", $url);
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
