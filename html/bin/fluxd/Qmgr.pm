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
package Qmgr;
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

# data-dir
my $dataDir;
my $path_dataDir = "qmgr/";

# queue-file
my $fileQueue;
my $path_fileQueue = "qmgr.queue";

# transfers-dir
my $transfersDir;

# time-vars
my ($time, $localtime);

# globals
my %globals;

# jobs
my %jobs;

# queue
my @queue;
my $queueIdx = 0;

# start-tries-hash
my %startTries;

# some defaults
my $default_limitStartTries = 3;

# sf-instance-field to reuse object
my $sf = StatFile->new();

################################################################################
# constructor + destructor                                                     #
################################################################################

#------------------------------------------------------------------------------#
# Sub: new (Constructor Method)                                                #
# Arguments: Null                                                              #
# Returns: Object                                                              #
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
	Fluxd::printMessage("Qmgr", "shutdown\n");
	# save queue
	queueSave();
	# strings
	undef $dataDir;
	undef $fileQueue;
	undef $transfersDir;
	# undef
	undef %globals;
	undef %jobs;
	undef @queue;
	undef %startTries;
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
	$dataDir = $ddir . $path_dataDir;
	# check if our main-dir exists. try to create if it doesnt
	if (! -d $dataDir) {
		Fluxd::printMessage("Qmgr", "creating data-dir : ".$dataDir."\n");
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
	# queue-file
	$fileQueue = $dataDir . $path_fileQueue;

	# transfers-dir
	$transfersDir = Fluxd::getPathTransferDir();
	if (!(defined $transfersDir)) {
		# message
		$message = "transfers-dir not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	if (! -d $transfersDir) {
		# message
		$message = "transfers-dir does not exist";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# interval
	$interval = FluxDB->getFluxConfig("fluxd_Qmgr_interval");
	if (!(defined $interval)) {
		# message
		$message = "interval not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# global-limit
	my $limitGlobal = FluxDB->getFluxConfig("fluxd_Qmgr_maxTotalTransfers");
	if (!(defined $limitGlobal)) {
		# message
		$message = "global-limit not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# user-limit
	my $limitUser = FluxDB->getFluxConfig("fluxd_Qmgr_maxUserTransfers");
	if (!(defined $limitUser)) {
		# message
		$message = "user-limit not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	Fluxd::printMessage("Qmgr", "initializing (loglevel: ".$loglevel." ; data-dir: ".$dataDir." ; interval: ".$interval." ; global-limit: ".$limitGlobal." ; user-limit: ".$limitUser.")\n");

	# Create some time vars
	$time = time();
	$localtime = localtime();

	# initialize our globals hash
	$globals{'main'} = 0;
	$globals{'started'} = 0;
	$globals{'limitGlobal'} = $limitGlobal;
	$globals{'limitUser'} = $limitUser;
	$globals{'limitStartTries'} = $default_limitStartTries;

	#initialize the queue
	if (-f $fileQueue) {
		# actually load the queue
		queueLoad();
	} else {
		if ($loglevel > 0) {
			Fluxd::printMessage("Qmgr", "creating empty queue\n");
		}
		@queue = qw();
	}

	# start-tries hash
	%startTries = ();

	# update running transfers
	runningUpdate();

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
	my $key = shift;
	$globals{$key} = shift;
}

#------------------------------------------------------------------------------#
# Sub: main                                                                    #
# Arguments: Null                                                              #
# Returns:                                                                     #
#------------------------------------------------------------------------------#
sub main {

	if ((time() - $time_last_run) >= $interval) {

		# log
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "process queue...\n");
		}

		# process queue
		queueProcess();

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
	$_= shift;
	SWITCH: {
		/^count-jobs/ && do {
			return jobsCount();
		};
		/^count-queue/ && do {
			return queueCount();
		};
		/^list-queue/ && do {
			return queueList();
		};
		/^enqueue;(.*);(.*)/ && do {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "enqueue-request : ".$1." (".$2.")\n");
			}
			return queueAdd($1, $2);
		};
		/^dequeue;(.*);(.*)/ && do {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "dequeue-request : ".$1." (".$2.")\n");
			}
			return queueRemove($1, $2);
		};
		/^set;(.*);(.*)/ && do {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "set : \"".$1."\"->\"".$2."\")\n");
			}
			return set($1, $2);
		};
	}
	return "Unknown command";
}

#------------------------------------------------------------------------------#
# Sub: queueProcess                                                            #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub queueProcess {
	# update running transfers
	runningUpdate();
	if (queueCount() > 0) {
		# queue-loop
		$queueIdx = 0;
		QUEUE: while ($queueIdx < queueCount()) {
			# next job
			my $nextTransfer = $queue[$queueIdx];
			my $nextUser = $jobs{"queued"}{$nextTransfer};
			# check if this queue-entry exists in running-jobs.
			# dont try to start what is already running.
			# this may be after a restart or transfer was started outside.
			if (exists $jobs{"running"}{$nextTransfer}) {                       # already running
				# remove job from queue
				if (queueRemove($nextTransfer, $nextUser) == 0) { $queueIdx++; }
				# check if more entries
				if (queueCountEntriesLeft() == 0) { last QUEUE; }
			} else {                                                            # transfer not already running
				my @jobAry = (keys %{$jobs{"running"}});
				my $jobcountr = scalar(@jobAry);
				# lets see if max limit applies
				if ($jobcountr < $globals{'limitGlobal'}) {                     # max limit does not apply
					# lets see if per user limit applies
					$jobcountr = 0;
					foreach my $anJob (@jobAry) {
						if ($jobs{"running"}{$anJob} eq $nextUser) {
							$jobcountr++;
						}
					}
					if ($jobcountr < $globals{'limitUser'}) {                   # user limit does not apply
						# startup the thing
						if ($loglevel > 0) {
							Fluxd::printMessage("Qmgr", "starting transfer : ".$nextTransfer." (".$nextUser.")\n");
						}
						# set start-counter-var
						if (exists $startTries{$nextTransfer}) {
							$startTries{$nextTransfer} += 1;
						} else {
							$startTries{$nextTransfer} = 1;
						}
						if (transferStart($nextTransfer) == 1) {                # start transfer succeeded
							# reset start-counter-var
							delete($startTries{$nextTransfer});
							# remove job from queue
							if (queueRemove($nextTransfer, $nextUser) == 0) { $queueIdx++; }
							# add job to jobs running
							if ($loglevel > 0) {
								Fluxd::printMessage("Qmgr", "adding job to jobs running : ".$nextTransfer." (".$nextUser.")\n");
							}
							$jobs{"running"}{$nextTransfer} = $nextUser;
							# check if more entries
							if (queueCountEntriesLeft() == 0) { last QUEUE; }
						} else {                                                # start transfer failed
							Fluxd::printError("Qmgr", "start transfer failed : ".$nextTransfer." (".$nextUser.")\n");
							# already tried max-times to start this thing ?
							if ($startTries{$nextTransfer} > $globals{'limitStartTries'}) {
								# reset start-counter-var
								delete($startTries{$nextTransfer});
								Fluxd::printError("Qmgr", $globals{'limitStartTries'}." errors when starting, cancel job : ".$nextTransfer." (".$nextUser.")\n");
								# remove job from queue
								if (queueRemove($nextTransfer, $nextUser) == 0) { $queueIdx++; }
								# check if more entries
								if (queueCountEntriesLeft() == 0) { last QUEUE; }
							} else {
								Fluxd::printError("Qmgr", $startTries{$nextTransfer}." errors when starting, skip job : ".$nextTransfer." (".$nextUser.")\n");
								# next entry
								$queueIdx++;
								# check if more entries
								if (queueCountEntriesLeft() == 0) { last QUEUE; }
							}
						} # end start transfer failed
					} else {                                                    # user-limit for this user applies
						# check next queue-entry if one exists
						if ($queueIdx < (queueCount() - 1)) { # there is a next entry
							if ($loglevel > 0) {
								Fluxd::printMessage("Qmgr", "user limit reached, skipping job : ".$nextTransfer." (".$nextUser.") (next queue-entry)\n");
							}
							$queueIdx++;
						} else { # no more in queue
							if ($loglevel > 0) {
								Fluxd::printMessage("Qmgr", "user limit reached, skipping job : ".$nextTransfer." (".$nextUser.") (last queue-entry)\n");
							}
							last QUEUE;
						}
					}
				} else {                                                        # max limit does apply
					if ($loglevel > 0) {
						Fluxd::printMessage("Qmgr", "max limit reached, skipping job : ".$nextTransfer." (".$nextUser.")\n");
					}
					last QUEUE;
				}
			} # end already runnin
		} # queue-while-loop
	} else {
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "queue empty...\n");
		}
	}
	# increment main-count
	$globals{"main"} += 1;
}

#------------------------------------------------------------------------------#
# Sub: queueCountEntriesLeft                                                   #
# Arguments: Null                                                              #
# Returns: 0|num of entries                                                    #
#------------------------------------------------------------------------------#
sub queueCountEntriesLeft {
	# check if more entries
	my $jobcount = queueCount();
	if (($jobcount > 0) && ($queueIdx < ($jobcount))) {
		# more jobs in queue
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "next queue-entry\n");
		}
		return ($jobcount - $queueIdx);
	} else {
		# nothing more in queue
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "last queue-entry\n");
		}
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: runningUpdate                                                           #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub runningUpdate {
	# get running transfers
	opendir(DIR, $transfersDir);
	my @pids = map { $_->[1] } # extract pathnames
	map { [ $_, "$_" ] } # no full paths
	grep { /.*\.pid$/ } # only .pid-files
	readdir(DIR);
	closedir(DIR);
	# flush running-jobs-hash
	$jobs{"running"} = ();
	# refill hash
	if (scalar(@pids) > 0) {
		foreach my $pidFile (@pids) {
			my $transfer = (substr ($pidFile, 0, (length($pidFile)) - 4));
			$sf->initialize($transfersDir.$transfer.".stat");
			my $running = $sf->get("running");
			my $user = $sf->get("transferowner");
			# user
			if ((!(defined $user)) || ($user eq "")) {
				if ($loglevel > 1) {
					Fluxd::printMessage("Qmgr", "cannot get owner of running transfer, using n/a : ".$transfer."\n");
				}
				$user = "n/a";
			}
			# running
			if ((!(defined $running)) || ($running ne "1")) {
				if ($loglevel > 1) {
					Fluxd::printMessage("Qmgr", "transfer not in running state, checking via ps-list if transfer is up : ".$transfer."\n");
				}
				if (FluxCommon::transferIsRunning($transfer) == 1) {
					if ($loglevel > 1) {
						Fluxd::printMessage("Qmgr", "transfer is running, adding to running transfers : ".$transfer." (".$user.")\n");
					}
				} else {
					if ($loglevel > 1) {
						Fluxd::printMessage("Qmgr", "transfer not running, skipping add to running transfers : ".$transfer." (".$user.")\n");
					}
					# skip
					next;
				}
			}
			# add it
			if (! exists $jobs{"running"}{$transfer}) {
				if ($loglevel > 1) {
					Fluxd::printMessage("Qmgr", "adding to running transfers : ".$transfer." (".$user.")\n");
				}
				$jobs{"running"}{$transfer} = $user;
			} else {
				if ($loglevel > 1) {
					Fluxd::printMessage("Qmgr", "transfer already exists in running transfers, skipping : ".$transfer." (".$user.")\n");
				}
			}
		}
	}
}

#------------------------------------------------------------------------------#
# Sub: queueLoad                                                               #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub queueLoad {
	if ($loglevel > 0) {
		Fluxd::printMessage("Qmgr", "loading queue-file : ".$fileQueue."\n");
	}
	# read from file into queue-array
	my $lineSep = $/;
	$/ = "\n";
	my @tempo = qw();
	open(QUEUEFILE,"< $fileQueue");
	while (<QUEUEFILE>) {
		chomp;
		push(@tempo, $_);
	}
	close QUEUEFILE;
	$/ = $lineSep;
	# fill queue
	@queue = qw();
	foreach my $transfer (@tempo) {
		$sf->initialize($transfersDir.$transfer.".stat");
		my $running = $sf->get("running");
		my $user = $sf->get("transferowner");
		if ((!(defined $running)) || ($running ne "3")) {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "transfer not in queued state, skipping : ".$fileQueue."\n");
			}
		} elsif ((!(defined $user)) || ($user eq "")) {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "cannot get owner, skipping : ".$fileQueue."\n");
			}
		} else {
			if (! exists $jobs{"queued"}{$transfer}) {
				if ($loglevel > 1) {
					Fluxd::printMessage("Qmgr", "adding job to queue : ".$transfer." (".$user.")\n");
				}
				$jobs{"queued"}{$transfer} = $user;
				push(@queue, $transfer);
			}
		}
	}
	# done loading
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: queueSave                                                               #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub queueSave {
	my $jobcount = queueCount();
	if ($jobcount > 0) {
		if ($loglevel > 0) {
			Fluxd::printMessage("Qmgr", $jobcount." job(s) queued, writing queue-file...\n");
		}
		# open queue-file
		open(QUEUEFILE,">$fileQueue");
		# queued transfers
		foreach my $queueEntry (@queue) {
			if ($loglevel > 1) {
				Fluxd::printMessage("Qmgr", "saving job : ".$queueEntry."\n");
			}
			print QUEUEFILE $queueEntry."\n";
		}
		# close queue-file
		close(QUEUEFILE);
	} else {
		if (-f $fileQueue) {
			if ($loglevel > 0) {
				Fluxd::printMessage("Qmgr", "no jobs queued, deleting queue-file...\n");
			}
			return unlink($fileQueue);
		}
	}
}

#------------------------------------------------------------------------------#
# Sub: jobsDump                                                                #
# Parameters: -                                                                #
# Return: -                                                                    #
#------------------------------------------------------------------------------#
sub jobsDump {
	if ($loglevel > 0) {
		Fluxd::printMessage("Qmgr", "dumping jobs to queue-file : ".$fileQueue."\n");
	}
	# open queue-file
	open(QUEUEFILE,">$fileQueue");
	# running transfers
	foreach my $jobName (keys %{$jobs{"running"}}) {
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "dumping running job : ".$jobName."\n");
		}
		print QUEUEFILE $jobName."\n";
	}
	# queued transfers
	foreach my $queueEntry (@queue) {
		if ($loglevel > 1) {
			Fluxd::printMessage("Qmgr", "dumping queued job : ".$queueEntry."\n");
		}
		print QUEUEFILE $queueEntry."\n";
	}
	# close queue-file
	close(QUEUEFILE);
}

#------------------------------------------------------------------------------#
# Sub: queueList                                                               #
# Arguments: Null                                                              #
# Returns: List of queued transfers                                            #
#------------------------------------------------------------------------------#
sub queueList {
	my $return = "";
	foreach my $queueEntry (@queue) {
		$return .= $queueEntry."\n";
	}
	return $return;
}

#------------------------------------------------------------------------------#
# Sub: queueAdd                                                                #
# Arguments: transfer, user                                                    #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub queueAdd {
	# Verify that the arguments look good
	my $temp = shift;
	if (!(defined $temp)) {
		Fluxd::printError("Qmgr", "invalid argument for transfer on add\n");
		return 0;
	}
	my $transfer = $temp;
	$temp = shift;
	if (!(defined $temp)) {
		Fluxd::printError("Qmgr", "invalid argument for username on add\n");
		return 0;
	}
	my $username = $temp;
	# add it
	if ((! exists $jobs{"queued"}{$transfer}) && (! exists $jobs{"running"}{$transfer})) {
		if ($loglevel > 0) {
			Fluxd::printMessage("Qmgr", "adding job to jobs queued : ".$transfer." (".$username.")\n");
		}
		$jobs{"queued"}{$transfer} = $username;
		# add
		push(@queue,$transfer);
		# save queue
		queueSave();
		# return
		return 1;
	} else {
		if ($loglevel > 0) {
			Fluxd::printMessage("Qmgr", "job already present in jobs : ".$transfer." (".$username.")\n");
		}
		# return
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: queueRemove                                                             #
# Arguments: transfer, user                                                    #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub queueRemove {
	# Verify that the arguments look good
	my $temp = shift;
	if (!(defined $temp)) {
		Fluxd::printError("Qmgr", "invalid argument for transfer on remove\n");
		return 0;
	}
	my $transfer = $temp;
	$temp = shift;
	if (!(defined $temp)) {
		Fluxd::printError("Qmgr", "invalid argument for username on remove\n");
		return 0;
	}
	my $username = $temp;
	# log
	if ($loglevel > 0) {
		Fluxd::printMessage("Qmgr", "remove job from jobs queued : ".$transfer." (".$username.")\n");
	}
	# remove from job-hash
	my $retValJobs = 0;
	if (exists $jobs{"queued"}{$transfer}) {
		delete($jobs{"queued"}{$transfer});
		$retValJobs = 1;
	}
	# remove from queue-stack
	my $retValQueue = 0;
	my $Idx = 0;
	LOOP: foreach my $queueEntry (@queue) {
		if ($queueEntry eq $transfer) {
			$retValQueue = 1;
			last LOOP;
		}
		$Idx++;
	}
	if ($retValQueue > 0) {
		if ($Idx > 0) { # not first entry, stack-action
			my @stack;
			for (my $i = 0; $i < $Idx; $i++) {
				push(@stack, (shift @queue));
			}
			shift @queue;
			for (my $i = 0; $i < $Idx; $i++) {
				push(@queue, (shift @stack));
			}
			$Idx--;
		} else { # first entry, just shift
			shift @queue;
		}
	}
	# return / save
	if (($retValJobs > 0) && ($retValQueue > 0)) {
		# save queue
		queueSave();
		return 1;
	} else {
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: jobsCount                                                               #
# Parameters: -                                                                #
# Return: number of  Jobs                                                      #
#------------------------------------------------------------------------------#
sub jobsCount {
	my $jobcount = 0;
	$jobcount += queueCount();
	$jobcount += runningCount();
	return $jobcount;
}

#------------------------------------------------------------------------------#
# Sub: queueCount                                                              #
# Parameters: -                                                                #
# Return: number of queued jobs                                                #
#------------------------------------------------------------------------------#
sub queueCount {
	return scalar(@queue);
}

#------------------------------------------------------------------------------#
# Sub: runningCount                                                            #
# Parameters: -                                                                #
# Return: number of queued jobs                                                #
#------------------------------------------------------------------------------#
sub runningCount {
	return scalar((keys %{$jobs{"running"}}));
}

#------------------------------------------------------------------------------#
# Sub: transferStart                                                           #
# Parameters: transfer-name                                                    #
# Return: int with return of start-call (0|1)                                  #
#------------------------------------------------------------------------------#
sub transferStart {
	my $transfer = shift;
	if (!(defined $transfer)) {
		return 0;
	}
	# fluxcli-call
	my $result = Fluxd::fluxcli("start", $transfer);
	if ($result == 1) {
		$globals{"started"} += 1;
		return 1;
	}
	return 0;
}

#------------------------------------------------------------------------------#
# Sub: stack                                                                   #
# Arguments: integer, array ref                                                #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub stack {
	my $index = shift;
	my $array = shift;
	if ($index) {
		my @stack;
		for (my $i = 0; $i < $index; $i++) {
			push(@stack, (shift @$array));
		}
		shift @$array;
		for (my $i = 0; $i < $index; $i++) {
			push(@$array, (shift @stack));
		}
		$index--;
	} else {
		shift @$array;
	}
}

#------------------------------------------------------------------------------#
# Sub: status                                                                  #
# Arguments: Null                                                              #
# Returns: status string                                                       #
#------------------------------------------------------------------------------#
sub status {
	my $return = "";
	$return .= "\n-= Qmgr Revision ".$VERSION." =-\n";
	$return .= "interval : ".$interval." s \n";
	# get count-vars
	my $countQueue = queueCount();
	my $countRunning = runningCount();
	my $countJobs = $countQueue + $countRunning;
	# some vars
	$return .= "max transfers global : ".$globals{'limitGlobal'}."\n";
	$return .= "max transfers per user : ".$globals{'limitUser'}."\n";
	$return .= "max start-tries : ".$globals{'limitStartTries'}."\n";
	# jobs total
	$return .= "jobs total : ".$countJobs."\n";
	# jobs queued
	$return .= "jobs queued : ".$countQueue."\n";
	foreach my $jobName (sort keys %{$jobs{"queued"}}) {
		my $jobUser = $jobs{"queued"}{$jobName};
		$return .= "  * ".$jobName." (".$jobUser.")\n";
	}
	# jobs running
	$return .= "jobs running : ".$countRunning."\n";
	foreach my $jobName (sort keys %{$jobs{"running"}}) {
		my $jobUser = $jobs{"running"}{$jobName};
		$return .= "  * ".$jobName." (".$jobUser.")\n";
	}
	# misc stats
	$return .= "running since : ".$localtime." (";
	$return .= FluxCommon::niceTimeString($time).") ";
	$return .= "(".$globals{'main'}." cycles) \n";
	$return .= "started transfers : ".$globals{'started'}."\n";
	# return
	return $return;
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
