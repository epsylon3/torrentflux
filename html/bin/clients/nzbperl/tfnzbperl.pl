#!/usr/bin/perl -w
################################################################################
# $Id$
# $Date$
# $Revision$
################################################################################
#                                                                              #
# Copyright (C) 2004 jason plumb                                               #
#                                                                              #
# This program is free software; you can redistribute it and/or                #
# modify it under the terms of the GNU General Public License                  #
# as published by the Free Software Foundation; either version 2               #
# of the License, or (at your option) any later version.                       #
#                                                                              #
# This program is distributed in the hope that it will be useful,              #
# but WITHOUT ANY WARRANTY; without even the implied warranty of               #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                #
# GNU General Public License for more details.                                 #
#                                                                              #
# You should have received a copy of the GNU General Public License            #
# along with this program; if not, write to the Free Software                  #
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.  #
#                                                                              #
################################################################################
#                                                                              #
# nzbperl.pl -- version 0.6.8                                                  #
#                                                                              #
# for more information:                                                        #
# http://noisybox.net/computers/nzbperl/                                       #
#                                                                              #
# this version is modified and extended for torrentflux-b4rt                   #
# http://tf-b4rt.berlios.de/                                                   #
#                                                                              #
################################################################################
#                                                                              #
#  Required :                                                                  #
#   * IO::File                                                                 #
#   * IO::Select                                                               #
#   * IO::Socket::INET                                                         #
#   * File::Basename                                                           #
#   * Getopt::Long                                                             #
#   * Cwd                                                                      #
#   * XML::Simple                                                              #
#   * XML::DOM                                                                 #
#                                                                              #
#  Optional :                                                                  #
#   * threads                                                                  #
#   * Thread::Queue                                                            #
#                                                                              #
################################################################################
use strict;
use File::Basename;
use IO::File;
use IO::Select;
use XML::DOM;
use Getopt::Long;
use Time::HiRes qw(gettimeofday tv_interval);	# timer stuff
use Cwd;
use FluxCommon;
use StatFile;
################################################################################

################################################################################
# fields                                                                       #
################################################################################

my $version = '0.6.8';
#my $ospeed = 9600;
my $recv_chunksize = 5*1024;	# How big of chunks we read at once from a connection (this is pulled from ass)
my $UPDATE_URL = 'http://noisybox.net/computers/nzbperl/nzbperl_version.txt';
my $dispchunkct = 250;			# Number of data lines to read between screen updates.
my $targkBps = 0;
my ($medbw, $lowbw) = (95, 35);	# Defaults for low and medium speed settings.
my $sleepdur = 0;				# Used when throttling

# Make stdout not buffered.
my $old_fh = select(STDOUT);
$| = 1;
select($old_fh);

my $quitnow = 0;
my $showinghelpscreen = 0;
my $skipthisfile = 0;
my $usecolor = 1;

# These are getting hefty, so they're now 5 per line
my (	$server, $port, $user, $pw, $keepparts,
		$keepbroken, $keepbrokenbin, $help, $nosort, $overwritefiles,
		$connct, $nocolor, $insane, $dropbad, $skipfilect,
		$reconndur, $filterregex, $configfile, $uudeview, $daemon,
		$dlrelative, $dlpath, $noupdate, $ssl, $socks_server,
		$socks_port, $proxy_user, $proxy_passwd, $http_proxy_server, $http_proxy_port,
		$dlcreate, $dlcreategrp, $noansi, $queuedir, $rcport,
		$postDecProg, $postNzbProg, $ipv6, $forever, $DECODE_DBG_FILE,
		$ifilterregex, $dthreadct, $diskfree, $tfuser
		) =
	(	'', -1, '', '', 0,
		0, 0, 0, 0, 0,
		2, 0, 0, 0, 0,
		300, undef, "$ENV{HOME}/.nzbperlrc", undef, 0,
		undef, undef, 0, undef, undef,
		-1, undef, undef, undef, -1,
		undef, undef, 0, undef, undef,
		undef, undef, undef, undef, undef,
		undef, 1, undef, ''
	);

# How commandline args are mapped to vars.  This map is also used by config file processor
my %optionsmap = ('server=s' => \$server, 'user=s' => \$user, 'pw=s' => \$pw,
				'help' => \$help, 'med=s' => \$medbw, 'low=s' => \$lowbw,
				'speed=s' => \$targkBps, 'keepparts' => \$keepparts,
				'keepbroken' => \$keepbroken, 'keepbrokenbin' => \$keepbrokenbin,
				'nosort' => \$nosort, 'redo' => \$overwritefiles, 'conn=i' => \$connct,
				'nocolor' => \$nocolor, 'insane' => \$insane, 'dropbad' => \$dropbad,
				'skip=i' => \$skipfilect, 'retrywait=i' => \$reconndur, 'filter=s' => \$filterregex,
				'config=s' => \$configfile, 'uudeview=s' => \$uudeview, 'dlrelative' => \$dlrelative,
				'dlpath=s' => \$dlpath, 'noupdate' => \$noupdate, 'ssl' => \$ssl,
				'socks_server=s' => \$socks_server, 'socks_port=i' => \$socks_port,
				'socks_user=s' => \$proxy_user, 'socks_passwd=s' => \$proxy_passwd,
				'http_proxy=s' => \$http_proxy_server, 'dlcreate'=>\$dlcreate,
				'dlcreategrp' => \$dlcreategrp, 'noansi' => \$noansi, 'rcport=i' => \$rcport,
				'postdec=s' => \$postDecProg, 'postnzb=s' => \$postNzbProg, 'ipv6' => \$ipv6,
				'chunksize=s' => \$recv_chunksize, 'decodelog=s' => \$DECODE_DBG_FILE,
				'ifilter=s' => \$ifilterregex, 'dthreadct=s' => \$dthreadct,
				'diskfree=s' => \$diskfree, 'tfuser=s' => \$tfuser);

################################################################################
# main                                                                         #
################################################################################

# parse args
if (defined(my $errmsg = handleCommandLineOptions())) {
	showUsage($errmsg);
	exit 1;
}

# message
printMessage("nzbperl starting up...\n");

# ipv6
if (not $ipv6){
	use IO::Socket::INET;
}

# Verify that uudeview is installed
if (not haveUUDeview()){
	printError("Please install and configure uudeview and try again.\n");
	exit;
}
if (!($uudeview =~ m#^([\w\s\.\_\-\/\\]+)$#)) {
	printError("Invalid characters in uudeview path.\n");
	exit;
}

# some vars
my $lastDirCheckTime = 0;
my $lastDiskFullTime = undef;
my $lastDiskFreePerc = 0;
my %nzbfiles;	# the hash/queue of nzb files we're handling
# $nzbfiles{'files'}->{<filename>}->{'read'}     : 1 if we've parsed/loaded it
# $nzbfiles{'files'}->{<filename>}->{'finished'} : 1 if all files have been downloaded

# ui-vars
my ($oldwchar, $wchar, $oldhchar, $hchar, $wpixels, $hpixels) = (0); # holds screen size info
my @lastdrawtime = Time::HiRes::gettimeofday();

# statusmessages
my @statusmsgs;

# file-vars
my ($file_nzb, $file_stat, $file_cmd, $file_pid);

# fileset
my @fileset;
if (scalar(@ARGV) > 0){
	$file_nzb = shift @ARGV; #$ARGV[0];
	my @fsparts = parseNZB($file_nzb, 1);
	if (!defined($fsparts[0])) {
		printError("No Fileset-Parts found. exit.");
		exit;
	}
	@fsparts = regexAndSkipping(@fsparts);	# It checks options inside too
	push @fileset, @fsparts;
	#
	$file_stat = $file_nzb.".stat";
	$file_cmd = $file_nzb.".cmd";
	$file_pid = $file_nzb.".pid";
}
my @queuefileset = @fileset;

# message
printMessage('Looks like we have got ' . scalar @fileset . ' possible files ahead of us.'."\n");

# suspectFileInd
my @suspectFileInd;
if ($insane) {
} else{
	&doNZBSanityChecks();
	if($dropbad){
		&dropSuspectFiles();
	}
}

# totals
my %totals = (
	'total size' => computeTotalNZBSize(@fileset),
	'finished files' => 0,
	'total bytes' => 0,
	'total file ct' => scalar @fileset
);
my %totalsCopy = (
	'total size' => $totals{'total size'},
	'finished files' => $totals{'finished files'},
	'total bytes' => $totals{'total bytes'},
	'total file ct' => $totals{'total file ct'}
);

# startup-message
printMessage("nzbperl starting up :\n");
printMessage(" - files : ".$totals{'total file ct'}."\n");
printMessage(" - size : ".$totals{'total size'}."\n");
printMessage(" - tfuser : ".$tfuser."\n");
printMessage(" - nzbfile : ".$file_nzb."\n");
printMessage(" - statfile : ".$file_stat."\n");
printMessage(" - pidfile : ".$file_pid."\n");
printMessage(" - cmdfile : ".$file_cmd."\n");
printMessage(" - dlpath : ".$dlpath."\n");
printMessage(" - server : ".$server."\n");
printMessage(" - speed : ".$targkBps."\n");
printMessage(" - conn : ".$connct."\n");
printMessage(" - dthreadct : ".$dthreadct."\n");

# sf-instance-field (reuse object)
my $sf = StatFile->new($file_stat);

# write af
writeStatStartup();

# write pid
pidFileWrite();

# Check for and delete stale .cmd files
if (-e $file_cmd ) {
	printMessage("removing command-file ".$file_cmd."...\n");
	unlink($file_cmd);
}

# set up our signal handlers
printMessage("setting up signal handlers...\n");
$SIG{HUP} = \&gotSigHup;
$SIG{INT} = \&gotSigInt;
$SIG{TERM} = \&gotSigTerm;
$SIG{QUIT} = \&gotSigQuit;

# start remote control
my $rc_sock = undef;
my @rc_clients;
#startRemoteControl();

my @conn;
createNNTPConnections();
if ($user){
	unless (doLogins()) {
		printError("Error authenticating to server.\nPlease check the user/pass info and try again.\n");
		# shutdown
		shutdownClient();
	}
}

# Start up the decoding thread(s)...
my ($decMsgQ, $decQ, @decThreads);
if (usingThreadedDecoding()){
	$decMsgQ = Thread::Queue->new;	# For status msgs
	$decQ = Thread::Queue->new;
	foreach my $i (1..$dthreadct){
		push @decThreads, threads->new(\&file_decoder_thread, $i);
	}
}

# message
printMessage("nzbperl up and running.\n");

# main loop
my @dlstarttime = Time::HiRes::gettimeofday();
my %lasttime = (
	'stat' => [gettimeofday],
	'cmd' => [gettimeofday]
);
my $noMoreWorkTodo = 0;
my $elapsed = 0;
while (1) {

	# nzb-action
	doFileAssignments();
	doBodyRequests();
	doReceiverPart();

	# if 5 secs passed, write stat-file
	$elapsed = tv_interval($lasttime{'stat'});
	if ($elapsed >= 5) {

		# stat-file
		writeStatRunning();

		# set time
		$lasttime{'stat'} = [gettimeofday];
	}

	# if 1 sec passed, process command-stack
	$elapsed = tv_interval($lasttime{'cmd'});
	if ($elapsed >= 1) {

		# process command stack
		processCommandStack();

		# set time
		$lasttime{'cmd'} = [gettimeofday];
	}

	# queueNewNZBFilesFromDir();	# queue up new nzb files from dir (guards inside)
	# See if queuefileset is empty AND all sockets don't have files
	# when that happens, that's when we're done.
	# if(not scalar @queuefileset){		# no more files in queue
	# 	doBodyRequests();		# total hack, but that's where decoding happens...
	# }
	# dequeueNextNZBFileIfNecessary();

	# remote controls
	#doRemoteControls();

	# exit on quit
	$quitnow and last;

	# check if done
	$noMoreWorkTodo = no_more_work_to_do();
	if ($noMoreWorkTodo) {
		# done
		printMessage("all downloads complete.\n");
		last;
	}
}

# message
printMessage("nzbperl shutting down...\n");

# Do some cleanups
if ($quitnow) {
	foreach my $c (@conn){
		next unless $c->{'file'};
		if($c->{'tmpfile'}){
			printMessage("Closing and deleting " . $c->{'tmpfilename'} . "...\n");
			undef $c->{'tmpfile'};	# causes a close
			unlink $c->{'tmpfilename'};
		}
	}
}

# Clean up server socket for remote control schtuff
#disconnectAll();

# decoder-threads
printMessage("Waiting for file decoding thread(s) to terminate...\n");
eval {
	local $SIG{ALRM} = sub {die "alarm\n"};
	alarm 30;
	#
	foreach my $i (1..$dthreadct){
		# Send a quit now message for each decoder thread.
		usingThreadedDecoding() and $decQ->enqueue('quit now');
	}
	foreach my $i (0..$dthreadct-1){
		# Now join on every decoder thread, waiting for all to finish
		usingThreadedDecoding() and $decThreads[$i]->join;
	}
	#
	alarm 0;
};
# Check for alarm (timeout) condition
if ($@) {
	printMessage("possible hung thread(s), waited 30 secs for file decoding thread(s) :\n ".$@."\n");
}

# shutdown
shutdownClient();

################################################################################
# subs                                                                         #
################################################################################

# Descriptions of what's in the connection hash (for sanity sake)
#
# $conn->{'sock'}       : the socket for comms
# $conn->{'msg'}        : message that describes what's going on
# $conn->{'file'}       : the file it's working on
# $conn->{'segnum'}     : the segment number of the file it's working on
# $conn->{'segbytes'}   : number of bytes read in the current segment
# $conn->{'filebytes'}  : number of bytes read in the current file
# $conn->{'bstatus'}    : status about how we're handling a body (starting/finishing)
# $conn->{'buff'}       : where body data is buffered
# $conn->{'tmpfilename'}: temporary file name
# $conn->{'tmpfile'}    : temporary file handle
# $conn->{'bwstarttime'}: time when the bandwdith applied
# $conn->{'bwstartbytes'}: bytes read on file when bandwidth applied
# $conn->{'truefname'}  : true filename on disk (assumed after decoding)
# $conn->{'skipping'}   : indicates we're in the middle of a skipping operation
# $conn->{'last data'}  : time when data was last seen on this channel
# $conn->{'sleep start'}: time that we started sleeping (for retries)
# $conn->{'isbroken'}   : set when one or more parts fails to download
# $conn->{'tfseq'}      : temporary file sequence id


#########################################################################################
# no_more_work_to_do - returns 1 if there is more work to do, 0 otherwise.  Used to
# detect when the main loop body should terminate
#########################################################################################
sub no_more_work_to_do {
	foreach my $i (1..$connct){
		if($conn[$i-1]->{'file'}){
			return 0;
		}
	}
	return scalar(@queuefileset) == 0;	# no more files in queue, all done
}
#########################################################################################
# This is the thread that does file decoding
# It uses two queues for communication -- decQ for files to decode, decMsgQ for status
# messages back to the main thread.
#########################################################################################
sub file_decoder_thread {
	my $threadNum = shift;

	my ($nzbpath, $nzbfile, $isbroken, $islastonnzb, $tmpfilename, $truefilename, $decodedir);
	my $prefixMsg = ($dthreadct > 1) ? "Decoder #$threadNum:" : '';

	while(1){
		# We get 6 things on the q per file...
		# nzbpath, $nzbfile, isbroken, tmpfilename, truefilename, decodedir
		$nzbpath = $decQ->dequeue;

		last unless defined($nzbpath);
		($nzbpath =~ /^quit now$/) and last;	# Time to shut down

		$nzbfile = $decQ->dequeue;
		$isbroken= $decQ->dequeue;
		$islastonnzb = $decQ->dequeue;
		$tmpfilename = $decQ->dequeue;
		$decodedir = $decQ->dequeue;
		$truefilename = $decQ->dequeue;

		doUUDeViewFile($nzbpath, $nzbfile, $isbroken, $islastonnzb,
						$tmpfilename, $decodedir, $truefilename, $prefixMsg);
	}
}
#########################################################################################
# Does multiplexed comms receiving
#########################################################################################
sub doReceiverPart {
	my $select = IO::Select->new();

	foreach my $i (1..$connct){
	    next unless (defined($conn[$i-1]->{'sock'}));
	    #next unless ($conn[$i-1]->{'file'});
	    $select->add($conn[$i-1]->{'sock'});
	}

	# If there are no active connections, we need to do a little sleep to prevent maxing out cpu.
	# the select->can_read call passes right through if it has no handles.
	(not $select->count()) and select undef, undef, undef, 0.1;

	my @ready = $select->can_read(0.25);

	foreach my $i (1..$connct){
		my $conn = $conn[$i-1];

		#next unless $conn->{'file'};	# This connection must be working on a file...otherwise next

		# TODO: Create a way to disable reconnection (when we don't want to do it)
		# Reconnect if we don't have a socket but we do have a file.
		if((not defined($conn->{'sock'})) and $conn->{'file'}){
			doReconnectLogicPart($i-1);
			next;
		}

		my $canread = 0;
		foreach my $fh (@ready) {
		    if (defined($conn->{'sock'}) and $fh == $conn->{'sock'}) {
				$canread = 1;
				last;
		    }
		}

		if ($canread) {
			my ($recvret, $buff);
			if (ref($conn->{'sock'}) eq "IO::Socket::SSL") {
			    $recvret = $conn->{'sock'}->sysread($buff, $recv_chunksize);
			}
			else {
			    $recvret = recv($conn->{'sock'}, $buff, $recv_chunksize, 0);
				if(defined($recvret)){
					$recvret = length $buff;
				}
				else{
					$recvret = -1;
				}
			}

			if(($recvret < 0) or !length($buff)){
				# TODO: Determine how to gracefully handle the crap we've already downloaded
				if (ref($conn->{'sock'}) eq "IO::Socket::SSL") {
					$conn->{'sock'}->shutdown( 2 );
					$conn->{'sock'}->close( SSL_no_shutdown => 1 );
				}
				else {
					$conn->{'sock'}->close;
				}
				$conn->{'sock'} = undef;
				$conn->{'sleep start'} = time;
				statMsg(sprintf("* Remote disconnect on connection #%d", $i));
				drawStatusMsgs();
				$conn->{'buff'} = '';
				next;
			}

			$conn->{'buff'} .= $buff;

			if(not connIsStartingSeg($conn)){ # only bump these up if we're not starting...
				$conn->{'segbytes'} += length($buff);
				$conn->{'filebytes'} += length($buff);
				$totals{'total bytes'} += length($buff);
			}

			$conn->{'last data'} = time;

			# Spool all lines from the buffer into the output file.
			spoolOutConnBuffData($i, $conn);
		}
		#drawScreenAndHandleKeys();
		doThrottling();
	}

	if ($#ready < 0) {
		#drawScreenAndHandleKeys();
		doThrottling();
	}
}

#########################################################################################
# spoolOutConnBuffData - spool the given connection's data to the output file.
# There's other stuff here too....it should be made simpler.
#########################################################################################
sub spoolOutConnBuffData {
	my ($i, $conn) = @_;

	return unless defined($conn->{'buff'}) and
		length($conn->{'buff'}) and
		defined($conn->{'tmpfile'});

	while(1){
		my $ind1 = index $conn->{'buff'}, "\r\n";
		last unless $ind1 >= 0;
		my $line = substr $conn->{'buff'}, 0, $ind1+2, '';

		if(connIsStartingSeg($conn)){
			startSegmentOnConnection($i, $conn, $line);
			next;
		}
		# Try and detect the "real" filename
		if(not $conn->{'truefname'}){
			my $tfn = getTrueFilename($line);
			if($tfn){
				$conn->{'truefname'} = $tfn;
				statMsg("Conn. $i: Found true filename: $tfn");

				my $targdir = getDestDirForFile($conn->{'file'}); # where the file is going on disk
				makeTargetDirIfNecessary($targdir);

				my $tfndisk = $targdir . '/' . $tfn;

				if(-e $tfndisk){
					if(!$overwritefiles){
						# We can't just close and delete, because there will likely still be
						# data waiting in the receive buffer.  As such, we have to set a flag
						# to indicate that the file already exists and should be skipped...
						# This is perhaps a bit silly -- we have to finish slurping in the
						# BODY part before we can start working on the next file...
						statMsg("Conn. $i: File already exists on disk (skipping after segment completes)");
						$conn->{'skipping'} = 1;
					}
				}
			}
		}

		if($line =~ /^\.\r\n/){		# detect end of BODY..
			$conn->{'bstatus'} = 'finished';
			if($conn->{'skipping'}){

				$totals{'total file ct'}--;
				$totals{'total bytes'} -= $conn->{'filebytes'}; # Remove bytes downloaded
				$totals{'total size'} -= $conn->{'file'}->{'totalsize'}; # Remove file bytes from job total

				undef $conn->{'tmpfile'}; # causes a close
				unlink $conn->{'tmpfilename'};
				$conn->{'file'} = undef;
				$conn->{'skipping'} = undef;	# no longer skipping (for next time)
			}
			last;
		}
		else{
			$line =~ s/^\.\././o;
			print {$conn->{'tmpfile'}} $line;
		}
	}
}

#########################################################################################
# Figures out where a file will be going on disk.  Returns the directory.
#########################################################################################
sub getDestDirForFile {
	my $file = shift;
	if(defined($dlpath)){
		if (defined($dlcreate)) {	# if we like to create nicely organized subdirs
			return $dlpath . $file->{'nzb path'};
		}
		elsif (defined($dlcreategrp)){
			return $dlpath . $file->{'groups'}->[0];
		}
		return $dlpath;
	}
	elsif(defined($dlrelative)){
		return $file->{'nzb path'};
	}
	return undef;	#this should not happen...either dlpath or dlrelative should be set
}

#########################################################################################
# makes the given dowload dir if necessary
#########################################################################################
sub makeTargetDirIfNecessary {
	my $targdir = shift;
	if( not -d ($targdir) and defined($dlpath) and
		(defined($dlcreate) or defined($dlcreategrp))){
		if(!mkdir($targdir)){
			statMsg("ERROR: Could not create $targdir: $!");
		}
	}
}

#########################################################################################
# connIsStartingSeg - Returns 1 if the segment BODY is being started on a connection,
# or returns 0 otherwise.
#########################################################################################
sub connIsStartingSeg {
	my $conn = shift;
	return (defined($conn) and
		defined($conn->{'bstatus'}) and
		($conn->{'bstatus'} =~ /starting/));
}

#########################################################################################
# startSegmentOnConnection - Handles an input line when a segment is just starting
# on a connection.  This looks into detecting missing segments and handles server
# responses that mean various things.
#########################################################################################
sub startSegmentOnConnection {
	my ($i, $conn, $line) = @_;
	my ($mcode, $msize, $mbody, $mid) = split /\s+/, $line;

	# We're just starting, need to slurp up 222 (or other) response
	if($line =~ /^2\d\d\s.*\r\n/s){
		# Bad case where server sends a 5xx message after a 2xx (222)
		if(!$msize and ($conn->{'buff'} =~ /^5\d\d /)){
			# Handle this error condition (display message to user)
			my $errline = $conn->{'buff'};
			$errline =~ s/\r\n.*//s;
			statMsg(sprintf("Conn. %d: Server returned error: %s", $i, $errline));
		}
		else{
			$conn->{'segbytes'} = length($conn->{'buff'});
		}
		$conn->{'bstatus'} = 'running';
	}
	else{ # This is an error condition -- often when the server can't find a segment
		$line =~ s/\r\n$//;
		statMsg( sprintf("Conn. %d: FAILED to fetch part #%d (%s)", $i,
						$conn->{'segnum'}+1, $line));
		drawStatusMsgs();
		$conn->{'bstatus'} = 'finished';  # Flag BODY segment as finished
		$conn->{'isbroken'} = 1;


		# Ok, so now that a segment fetch FAILED, we need to determine how to continue...
		# We will look at the keep variables to determine how to continue...
		# If keepbroken or keepbrokenbin are set, we will keep downloading parts...otherwise we will bump
		# up the segnum so that we skip all remaining segments (if any)

		if($keepbroken or $keepbrokenbin){		# If we shound continue downloading this broken file
			# Subtract the size of the current segment from the totals
			# (for this file and for the grand totals)
			my $failedsegsize = @{$conn->{'file'}->{'segments'}}[$conn->{'segnum'}]->{'size'};
			$totals{'total size'} -= $failedsegsize ;
			$conn->{'file'}->{'totalsize'} -= $failedsegsize;
		}
		else{
			statMsg(sprintf("Conn. %d: Aborting file (failed to fetch segment #%d)",
					$i, $conn->{'segnum'}+1));

			# Adjust totals due to skipping failed file
			$totals{'total file ct'}--;
			$totals{'total bytes'} -= $conn->{'filebytes'}; # Remove bytes downloaded
			$totals{'total size'} -= $conn->{'file'}->{'totalsize'}; # Remove file bytes from job total


			$conn->{'segnum'} = scalar @{$conn->{'file'}->{'segments'}} - 1;
			undef $conn->{'tmpfile'};	# causes a close
			unlink $conn->{'tmpfilename'};
			$conn->{'file'} = undef;
		}
	}
}

#########################################################################################
# Handles reconnection logic
#########################################################################################
sub doReconnectLogicPart {
	my $i = shift;
	my $forceNow = shift; # can be specified to force a reconnect right now
	my $conn = $conn[$i];

	if(not $forceNow){
		my $remain = $reconndur - (time - $conn->{'sleep start'});
		if($remain > 0){	# still sleeping
			return;
		}
	}
	#my $iaddr = inet_aton($server) || die "Error resolving host: $server";

	statMsg(sprintf("Connection #%d attempting reconnect to %s:%d...", $i+1, $server, $port));
	($conn->{'sock'}, my $line) = createSingleConnection($i, "$server:$port", 1);

	if(!$conn->{'sock'}){		# couldn't reconnect
		statMsg($line);
		$conn->{'sleep start'} = time;	# reset reconnect timer
		return;
	}

	my $msg = sprintf("Connection #%d reestablished.", $i+1);
	$user and $msg .= "..performing login";
	statMsg($msg);
	drawStatusMsgs();

	if($user){	#need to authenticate...
		doSingleLogin($i, 1);
		statMsg(sprintf("Login on connection #%d complete.", $i+1));
	}

	$conn->{'sleep start'} = undef;
	# These two lines reset our state so that we restart the segment we were on
	# prior to the disconnect.  Sure, a bit convoluted, but it's used elsewhere.
	$conn->{'bstatus'} = 'finished';
	defined($conn->{'segnum'}) and $conn->{'segnum'}--
		unless $conn->{'segnum'} < 0;;
}

#########################################################################################
# Heuristically determines the "true" filename.  Returns filename or undef
#########################################################################################
sub getTrueFilename {
	my $line = shift;
	$line =~ s/\s+$//;
	if($line =~ /^=ybegin/){			# Yenc candidate
		# I'm assuming that the last tag on the line is "name=...", which I honestly have no idea
		# if that's always true.  :)
		$line =~ s/.* name=//;
		return $line;
	}
	elsif($line =~ /^begin \d+ /){		# UUencoded candidate
		$line =~ s/^begin \d+ //;
		return $line;
	}
	else{
		return undef;
	}
}

#########################################################################################
# Handles segments and detects when we're done with a file
#########################################################################################
sub doBodyRequests {
	foreach my $i (1..$connct){
		my $conn = $conn[$i-1];
		my $file = $conn->{'file'};
		next unless $file;			# Bail if we don't have a file

		if($conn->{'segnum'} < 0){
			next unless $conn->{'sock'}; # no socket, perhaps waiting for reconnect
			$conn->{'segnum'} = 0;
			my $seg = @{$file->{'segments'}}[0];
			#$conn->{'seg'} = $seg;
			my $msgid = $seg->{'msgid'};

			sockSend($conn->{'sock'}, 'BODY <' . $msgid . ">\r\n");

			$conn->{'bstatus'} = 'starting';
			$conn->{'segbytes'} = 0;
		}
		elsif($conn->{'bstatus'} =~ /finished/){ # finished a segment
			$conn->{'segnum'}++;

			if($conn->{'segnum'} >= scalar @{$file->{'segments'}}){ # All segments for this file exhausted.

				cursorPos(5, 6+(3*($i-1)));
				my $len = pc("File finished! Sending details to decoder queue...\n", 'bold white');
				#print ' ' x ($wchar-$len-6);
				statMsg("Conn. $i: Finished downloading " . $conn->{'file'}->{'name'});

				doDecodeOrQueueCompletedFile($conn);
				drawStatusMsgs();

				$totals{'finished files'}++;
				$conn->{'file'} = undef;
				#$conn->{'seg'} = undef;

			}
			else {
				next unless $conn->{'sock'}; # no socket, perhaps waiting for reconnect
				my $segnum = $conn->{'segnum'};
				my $seg = @{$file->{'segments'}}[$segnum];
				#$conn->{'seg'} = $seg;
				my $msgid = $seg->{'msgid'};

				sockSend($conn->{'sock'}, 'BODY <' . $msgid . ">\r\n");

				$conn->{'bstatus'} = 'starting';
				$conn->{'segbytes'} = 0;
			}
		}
	}
}

#########################################################################################
# doStartFileDecoding - initiates or performs a decode for a completed file.
# If dthreadct == 0, this will decode in place, otherwise it just queues the request
# to decode to the decoder thread.
#########################################################################################
sub doDecodeOrQueueCompletedFile {
	my $conn = shift;
	my $file = $conn->{'file'};
	undef $conn->{'tmpfile'};	# causes a close
	my $outdir = getDestDirForFile($file);
	$outdir = cwd unless defined($outdir); # default to current dir

	my $tmpfilename = $conn->{'tmpfilename'};
	my $truefilename = $conn->{'truefname'};
	my $isbroken = $conn->{'isbroken'};
	$isbroken = 0 unless (defined($isbroken)); # ensure a definite value
	my $islastonnzb = $file->{'lastonnzb'};

	if(usingThreadedDecoding()){
		# Queue the items to the decoding thread
		$decQ->enqueue($file->{'nzb path'}, $file->{'nzb file'},
			$isbroken, $islastonnzb, $tmpfilename, $outdir, $truefilename);
	}
	else{
		doUUDeViewFile($file->{'nzb path'}, $file->{'nzb file'},
			$isbroken, $islastonnzb, $tmpfilename, $outdir, $truefilename);
	}
}

#########################################################################################
# Decodes a file to disk and handles cleanup (deleting/keeping parts)
#########################################################################################
sub doUUDeViewFile {
	my ($nzbpath, $nzbfile, $isbroken, $islastonnzb, $tmpfilename,
		$decodedir, $truefilename, $prefixMsg) = @_;

	$prefixMsg = '' unless defined($prefixMsg);
	$prefixMsg =~ s/\s+$//;
	length($prefixMsg) and $prefixMsg .= ' ';

	statOrQ($prefixMsg . "Starting decode of $truefilename");

	# Do the decode and confirm that it worked...
	if(!$isbroken or $keepbrokenbin){
		my $kb = '';
		$keepbrokenbin and $kb = '-d';	# If keeping broken, pass -d (desparate mode) to uudeview
		my $decodelogpart = '';
		my $qopts = '-q';
		if(defined($DECODE_DBG_FILE)){
			$decodelogpart = " >> $DECODE_DBG_FILE";
			$qopts = '-n';
		}
		else{
			$decodelogpart = " > /dev/null";
		}

		my $rc = system("$uudeview -i -a $kb $qopts \"$tmpfilename\" -p \"$decodedir\"$decodelogpart 2>&1");
		$rc and $isbroken = 1;	# If decode failed, file is broken

		if($rc){		# Problem with the decode
			if(defined($DECODE_DBG_FILE)){
				statOrQ($prefixMsg . "FAILED decode of $tmpfilename (see $DECODE_DBG_FILE for details)");
			}
			else{
				statOrQ($prefixMsg . "FAILED decode of $tmpfilename");
				statOrQ("Consider using --decodelog <file> to troubleshoot.");
			}
		}
		else{
			statOrQ($prefixMsg . "Completed decode of " . $truefilename);
		}
	}

	# Decide if we need to keep or delete the temp .parts file
	if($keepparts or ($isbroken and $keepbroken)){
		my $brokemsg = $isbroken ? ' broken' : '';
		statOrQ("Keeping$brokemsg file segments in $tmpfilename (--keepparts given)");
		# TODO: rename to .broken
	}
	else {
		unlink($tmpfilename) or statOrQ("Error removing $tmpfilename from disk: $!");
	}

	runPostDecodeProgram($tmpfilename, $decodedir, $truefilename, $isbroken);
	$islastonnzb and (runPostNzbDecodeProgram($nzbpath, $nzbfile, $decodedir, $truefilename));
}

#########################################################################################
# runPostDecodeProgram -- Possibly runs an external program after a file has been
# decoded (regardless of success).
#########################################################################################
sub runPostDecodeProgram {
	my ($tmpfilename, $decodedir, $truefilename, $isbroken) = @_;
	return unless defined($postDecProg);

	$truefilename = $decodedir .
		(($decodedir =~ /\/$/) ? '' : '/') . $truefilename;

	runProgWithEnvParams($postDecProg, 'post-decoding',
		NZBP_FILE => $truefilename, NZBP_TEMPFILE => $tmpfilename,
		 NZBP_ISBROKEN => $isbroken);
}

#########################################################################################
# runPostNzbDecodeProgram -- Possibly runs external prog when nzb is completed.
#########################################################################################
sub runPostNzbDecodeProgram {
	my ($nzbpath, $nzbfile, $decodedir, $truefilename) = @_;
	return unless defined($postNzbProg);	#option not specified
	runProgWithEnvParams($postNzbProg, 'post-nzb',
		NZBP_NZBDIR => $nzbpath, NZBP_NZBFILE => $nzbfile,
		NZBP_DECODEDIR => $decodedir, NZBP_LASTFILE => $truefilename);
}

#########################################################################################
# Runs a program with environment vars prepended to the commandline as parameters.
# This is used by the post decoder program runner and the post nzb program runner.
#########################################################################################
sub runProgWithEnvParams {
	my ($prog, $desc, %env) = @_;
	my $cmd = '';
	# This is a little strange...but showing the env vars onto the command is
	# the only way I could find to pass environments from a perl thread to
	# an external prog.  I wish there was a better way (like using $ENV, but
	# that fails)
	foreach my $k (keys %env){
		my $envitem = $env{$k};
		$envitem =~ s/"/\\"/g;		# escape double quotes
		$envitem =~ s/`/\\`/g;		# escape backticks (evil)
		$cmd .= sprintf("export %s=\"%s\"; ", $k, $envitem);
	}
	$cmd .= $prog;
	statMsg("Running $desc program : $prog");
	system($cmd);
	statMsg("Finished running $desc program.");
	drawStatusMsgs();
}

#########################################################################################
# Shifts from the file queue and assigns the files to a connection.  When a file is
# assigned, the first segment is not assigned.
#########################################################################################
sub doFileAssignments {
	foreach my $i (1..$connct){
		my $conn = $conn[$i-1];
		next if $conn->{'file'};	# already working on a file

		if(hitDiskSpaceLimit($queuefileset[0])){	# Do free space checking if option set
			next;
		}

		my $file = shift @queuefileset;
		last unless $file;

		statMsg(sprintf("Conn. %d: Starting file: %s", $i, $file->{'name'}));
		$conn->{'file'} = $file;
		$conn->{'segnum'} = -1;
		$conn->{'filebytes'} = 0;
		$conn->{'truefname'} = undef;
		$conn->{'bwstartbytes'} = 0;
		$conn->{'isbroken'} = 0; # Assume unbroken until we know it is
		@{$conn->{'bwstarttime'}} = Time::HiRes::gettimeofday();
		$conn->{'tfseq'}++;

		# Create temp filename and open
		my $tmpfile = 'nzbperl.tmp' . time . '.' . $i . '.' . $conn->{'tfseq'} . '.parts';
		if(defined($dlpath)){		# stick in dlpath if given
			$tmpfile = $dlpath . $tmpfile;
		}
		elsif(defined($dlrelative)){ # otherwise stick in relative dir to nzbfile
			$tmpfile = $file->{'nzb path'} . $tmpfile;
			if(not -w $file->{'nzb path'}){
				statMsg(sprintf("*** ERROR: nzb path %s is not writable!  There will be failures!", $file->{'nzb path'}));
				statMsg("*** Please change the permissions or use --dlpath instead of --dlrelative.");
			}
		}

		($tmpfile =~ m#^([\w\d\s\.\_\-\/\\]+)$#) and $tmpfile = $1;	# untaint tmpfile
		$conn->{'tmpfilename'} = $tmpfile;
		$conn->{'tmpfile'} = undef;	# just to be absolutely sure

		open $conn->{'tmpfile'}, ">$tmpfile" or
			(statMsg("*** ERROR opening $tmpfile (critical!)") and next);
		statMsg("Conn. $i: Opened temp file $tmpfile");
		binmode $conn->{'tmpfile'};
	}
}

#########################################################################################
# Returns 1 if the param to prevent disk filling was set and we're within the threshhold
#########################################################################################
sub hitDiskSpaceLimit {
	return 0 unless defined $diskfree;
	my $file = shift;

	# Only check freespace every 15 seconds
	if(defined($lastDiskFullTime)){
		return 1 unless (time - $lastDiskFullTime) > 15;
	}

	my $freeperc = getFreeDiskPercentage(getDestDirForFile($file));
	if($freeperc <= $diskfree){
		if(not defined($lastDiskFullTime)){ # the first time we detect free space is out
			statMsg("Warning: Download disk has less than $diskfree% free.");
			statMsg("Waiting for free space before continuing downloading.");
		}
		$lastDiskFullTime = time;
		return 1;
	}
	$lastDiskFullTime = undef;
	return 0;
}

#########################################################################################
# Tries to get the free disk percentage on the provided path
#########################################################################################
sub getFreeDiskPercentage {
	my $path = shift;
	my @reslines = `df '$path'`;
	my $line = pop @reslines;
	chomp $line;
	# Are all dfs created equal???  If not, we could use col headers?
	my ($fs, $size, $used, $avail, $dfperc, $mount) = split /\s+/, $line;
	$dfperc =~ s/%//;
	$lastDiskFreePerc = 100-$dfperc;
	return $lastDiskFreePerc;
}

#########################################################################################
# Decides if its time to do the next nzb file...which is when the @queuefileset array
# is empty and there is at least 1 idle connection.
#########################################################################################
sub dequeueNextNZBFileIfNecessary {

	return if scalar(@queuefileset);	# still have queued files

	foreach my $i (1..$connct){
		if(not $conn[$i-1]->{'file'}){	# the connection is idle
			my ($newQueuedCt, $dequeuedNewFile, $reconnAttempts) = (0,0,0);

			$newQueuedCt = queueNewNZBFilesFromDir(1);	# force a dircheck first
			$dequeuedNewFile = dequeueNextNZBFile();
			if($dequeuedNewFile){
				$reconnAttempts = reconnectAllDisconnectedNow();
			}

			if($newQueuedCt or $dequeuedNewFile or $reconnAttempts){
				drawStatusMsgs();
			}
			return;
		}
	}
}

#########################################################################################
# Forces an immediate reconnect on all not connected connections.
# Returns number of connections that had reconnect *attempts* (not necessarily the
# number that were actually reconnected)
#########################################################################################
sub reconnectAllDisconnectedNow {
	my $retCt = 0;
	foreach my $i (1..$connct){
		if(not defined($conn[$i-1]->{'sock'})){
			doReconnectLogicPart($i-1, 1);
			$retCt++;
		}
	}
	return $retCt;
}

#########################################################################################
# Pulls out the next nzb file in queue, parses it, and then add its files/parts to
# @queuefileset.
#########################################################################################
sub dequeueNextNZBFile {
	my @keys = keys %{$nzbfiles{'files'}};
	foreach my $key (@keys){
		if(not $nzbfiles{'files'}->{$key}->{'read'}){
			statMsg("Moving to next nzb file in queue: $key");
			my @newset = parseNZB($queuedir . '/' . $key, 1);
			if(!defined($newset[0])){
				statMsg("Warning: no new files loaded from queued nzb file");
				return 0;
			}
			push @queuefileset, @newset;
			statMsg("Loaded " . scalar(@newset) . " new files to download from nzb file: $key");
			$totals{'total file ct'} += scalar @newset;
			$totals{'total size'} += computeTotalNZBSize(@newset);
			$nzbfiles{'files'}->{$key}->{'read'} = 1;
			return 1;
		}
	}
	return 0;
}

#########################################################################################
# Looks at the nzbfile hash and counts the number that haven't been read (are queued)
#########################################################################################
sub countQueuedNZBFiles {
	my @keys = keys %{$nzbfiles{'files'}};
	my $ct = 0;
	foreach my $key (@keys){
		if($nzbfiles{'files'}->{$key}->{'read'} == 0){
			$ct++;
		}
	}
	return $ct;
}

#########################################################################################
# queues new nzb files from the queue dir if they exist and adds them to the hash/queue
# of all nzb files we're processing.  Returns the number of files dequeued.
#########################################################################################
sub queueNewNZBFilesFromDir {
	my $forcecheck = shift;
	return 0 unless $queuedir and not scalar @queuefileset;
	return 0 unless $forcecheck or (time - $lastDirCheckTime > 15);	# don't check more than once every 15 seconds
	$lastDirCheckTime = time;

	my $retCt = 0;
	opendir(QDIR, $queuedir);
	my @candidates = grep(/\.nzb$/, readdir(QDIR));
	foreach my $file (@candidates){
		if( !defined($nzbfiles{'files'}->{$file})){	# not queued yet
			statMsg("Queueing new nzb file found on disk: $file");
			$nzbfiles{'files'}->{$file}->{'read'} = 0;
			$retCt++;
		}
	}
	closedir(QDIR);
	return $retCt;
}

#########################################################################################
# Start up the remote control(s)
#########################################################################################
sub startRemoteControl {
	return unless defined($rcport);	# nuthin to do

	eval "use XML::Simple;";
	($@) and die "ERROR: XML::Simple required if using remote control...Please install it.";

	$rc_sock = createRCMasterSocket();

	printMessage("Remote control server socket created.\n");
}

#########################################################################################
# creates the remote control master port, using either ipv4 or ipv6
#########################################################################################
sub createRCMasterSocket {
	my $ret;

	my %opts = (Listen => 5, LocalAddr => 'localhost',
				LocalPort => $rcport,
				Proto=>'tcp', Type => SOCK_STREAM, Reuse => 1);
	if($ipv6){
		$ret = IO::Socket::INET6->new( %opts ) or die "Error creating remote control socket: $!";
	}
	else{
		$ret = IO::Socket::INET->new( %opts ) or die "Error creating remote control socket: $!";
	}
	return $ret;
}

#########################################################################################
# Main loop entry point for handling remote control stuff
#########################################################################################
sub doRemoteControls {
	return unless defined($rcport);
	getNewRcClients();
	handleRcClients();
	my @tmprcc;
	foreach my $client (@rc_clients){				#clean up dropped clients
		if(defined($client->{'closenow'})){
			statMsg(sprintf("Remote control client from %s:%s disconnected.", $client->{'ip'}, $client->{'port'}));
			close $client->{'sock'};
		}
		else{
			push @tmprcc, $client;
		}
	}
	@rc_clients = @tmprcc;
}

#########################################################################################
# handleRcClients -- read and handle all remote commands from all clients
#########################################################################################
sub handleRcClients{
	for (my $i=0; $i < scalar @rc_clients; $i++){
		my $client = $rc_clients[$i];
		my $cmd = readRcClientCommand($client);
		defined($cmd) and handleRcClientCmd($client, $cmd);
	}
}

#########################################################################################
# handleRcClientCmd - Handle's an rc client command
#########################################################################################
sub handleRcClientCmd {
	my ($client, $cmdstr) = @_;
	my ($cmd, $params, $responsemsg) = ($cmdstr, $cmdstr);
	$cmd =~ s/\s+.*//;
	$params =~ s/^\w+\s+//;
	$params = '' unless $params ne $cmd;
	if($cmd =~ /ping/i){
		$responsemsg = sprintf("PONG! %s", $params);
	}
	elsif($cmd =~ /^quit/i){
		sendRemoteResponse($client, "Nice having ya.");
		$client->{'closenow'} = 1;
		return;
	}
	elsif($cmd =~ /^keys/i){
		my @keys = split //, $params;
		foreach my $key (@keys){
			handleKey($key);
		}
		$responsemsg = sprintf("Ok, processed %d keystrokes", scalar @keys);
	}
	elsif($cmd =~ /^summary/i){
		$responsemsg = generateRcSummary();
	}
	elsif($cmd =~/^speed/i){
		if($params =~ /\d+/){
			$targkBps = $params;
			$responsemsg = sprintf("Ok, set download speed to %dkBps", $params);
		}
		else{
			$responsemsg = "Error: please specify speed in kBps";
		}
	}
	elsif($cmd =~ /^diskfree/i){
		$params =~ s/%//;
		$diskfree = $params;
		$responsemsg = "Ok, set max disk free percentage to $diskfree%";
	}
	elsif($cmd =~ /^enqueue/i){
		if(defined($nzbfiles{'files'}->{$params})){	# not queued yet
			$responsemsg = "Error: Refusing to queue file already queued ($params).";
		}
		elsif(not -e $params){
			$responsemsg = "Error: File does not exist ($params)";
		}
		else{
			$responsemsg = "Queueing new nzb file found on disk: $params";
			statMsg($responsemsg);
			$nzbfiles{'files'}->{$params}->{'read'} = 0;
		}
	}
	else{
		$responsemsg = "Sorry, command not understood.";
	}
	sendRemoteResponse($client, $responsemsg);
}

#########################################################################################
# sendRemoteResponse -- send a remote command response to the remote client.
#########################################################################################
sub sendRemoteResponse {
	my ($client, $msg) = @_;
	my $sock = $client->{'sock'};
	# simple protocol, eh?
	sockSend($sock, sprintf("%d\r\n%s\r\n", length($msg)+2, $msg));
}

#########################################################################################
# readRcClientCommand -- Attempts to read a command from the client socket
# returns the command or undef
#########################################################################################
sub readRcClientCommand {
	my $client = shift;
	my $buff = readNewRcClientSockData($client);
	if(defined($buff)){
		my $nlindex = index $client->{'data'}, "\r\n";
		if($nlindex >= 0){
			#get cmd and replace in client{data} with nothing
			my $cmd = substr $client->{'data'}, 0, $nlindex+2, '';
			$cmd = trimWS($cmd);
			return $cmd;
		}
	}
	return undef;
}

#########################################################################################
# readNewRcClientSockData -- Pulls client data off the socket if there is any.
#########################################################################################
sub readNewRcClientSockData {
	my $client = shift;
	my $sock = $client->{'sock'};
	my $sockfn = fileno($sock);
	my ($rin, $win, $ein) = ('', '', '');
	my ($rout, $wout, $eout);
	vec($rin, $sockfn, 1) = 1;
	vec($win, $sockfn, 1) = 1;
	vec($ein, $sockfn, 1) = 1;
	my $nfound = select($rout=$rin, $wout=$win, $eout=$ein, 0);
	return undef unless $nfound > 0;
	if(vec($rout, $sockfn,1) == 1){
		my $buff;
		recv($sock, $buff, $recv_chunksize, 0);
		if(not length($buff)){
			$client->{'closenow'} = 1;
			return undef;
		}
		$client->{'data'} .= $buff;
		return $buff;
	}
	if((vec($eout, $sockfn, 1) == 1) || (vec($wout, $sockfn, 1) != 1)){
		$client->{'closenow'} = 1;
	}
	return undef;
}

#########################################################################################
# Accepts new connections from clients and adds them to the list.
#########################################################################################
sub getNewRcClients {
	while(1){
		my ($rin,$rout) = ('','');
		vec($rin, fileno($rc_sock), 1)  = 1;
		my $nfound = select($rout=$rin, undef, undef, 0);
		last unless ($nfound > 0);
		my $nclient;
		my $client_addr = accept($nclient, $rc_sock);
		#my $old = select($nclient);
		#$| = 1;	# make nonbuffered
		#select($old);
		my ($clientport, $clientippart) = sockaddr_in($client_addr);
		my $clientip = inet_ntoa($clientippart);
		statMsg("New remote control connection from " . $clientip . ":" . $clientport);
		sockSend($nclient, "nzbperl version $version\r\n");
		push @rc_clients, {'sock' => $nclient, 'ip' => $clientip, 'port' => $clientport};
	}
}

#########################################################################################
# generateRcSummary - generates a summary of information for a remote request for it.
#########################################################################################
sub generateRcSummary {
	my %s;
	$s{'connections'} = $connct;
	my $tspeed = $targkBps ? hrsv($targkBps*1024) . "Bps" : "unlimited";
	$s{'speeds'} = {'current' => getCurrentSpeed(), 'target' => $tspeed, 'session' => getTotalSpeed()};
	$s{'completed'} = {'files' => $totals{'finished files'}, 'size' => hrsv($totals{'total bytes'})};
	$s{'completed'}->{'files'} = 0 unless $s{'completed'}->{'files'};
	$s{'remaining'} = {'files' => $totals{'total file ct'}-$totals{'finished files'},
						'time' => getETA(), 'size' => hrsv($totals{'total size'} - $totals{'total bytes'}),
						'queued_nzb_files' => countQueuedNZBFiles()};
	my $summary = XML::Simple->new()->XMLout(\%s, rootname => 'summary', noattr => 1);
	$summary =~ s/\s+$//;
	return $summary ;
}

#########################################################################################
# Creates all connections and adds them to the @conn global
#########################################################################################
sub createNNTPConnections {
	foreach my $i (1..$connct){
		#my $iaddr = inet_aton($server) || die "Error resolving host: $server";
		my $paddr = "$server:$port";
		($conn[$i-1]->{'sock'}, my $line) = createSingleConnection($i-1, $paddr);
	}
	return 1;
}

#########################################################################################
# Connects to an NNTP server and attempts to read the greet string line.
# Returns the socket and the greet line.
#########################################################################################
sub createSingleConnection {
	my ($i, $paddr, $silent) = @_;
	my ($osock, $sock) = (undef, undef);

	if (defined($socks_server)) {
	    porlp(sprintf("Attempting SOCKS connection #%d %s:%d ->%s:%d...",
				   $i+1, $socks_server, $socks_port, $server, $port), $silent);

		my %sockparam = (ProxyAddr => $socks_server, ProxyPort => $socks_port,
			ConnectAddr => $server, ConnectPort => $port );

		if(defined($proxy_user)){		# Add authentication info
			$sockparam{'AuthType'} = 'userpass';
			$sockparam{'Username'} = $proxy_user;
			$sockparam{'Password'} = $proxy_passwd;
		}

	    $osock = IO::Socket::Socks->new( %sockparam );
	}
	elsif (defined($http_proxy_server)) {
	    porlp(sprintf('Attempting HTTP Proxy connection #%d %s:%d -> %s:%d...'."\n",
				   $i+1, $http_proxy_server, $http_proxy_port, $server, $port), $silent);
	    $osock = Net::HTTPTunnel->new( 'proxy-host' => $http_proxy_server,
					   'proxy-port' => $http_proxy_port,
					   'remote-host' => $server,
					   'remote-port' => $port );
	}
	else {
	    porlp(sprintf('Attempting connection #%d to %s:%d...'."\n", $i+1, $server, $port), $silent);
	    $osock = createNNTPClientSocket($paddr);
	}
	if(!$osock){
		porlp("Connection FAILED!\n", $silent);
		return (undef, "Error connecting to server: '$!'");
	}
	porlp("success!\n", $silent);

	if (defined($ssl)) {
	    porlp(sprintf("Establishing SSL connection #%d to %s:%d...\n", $i+1, $server, $port), $silent);
	    $sock = IO::Socket::SSL->start_SSL($osock);
	    die "SSL error: " . IO::Socket::SSL::errstr() . $!  unless (defined($sock));
	}
	else {
	    $sock = $osock;
	}

	my $line = blockReadLine($sock);	# read server connection/response string
	not $line =~ /^(200|201)/ and die "Unexpected server response: $line" . "Expected 200 or 201.\n";
	if (ref($sock) eq "IO::Socket::SSL") {
	    my ($subj, $iss, $cipher) = ($sock->peer_certificate("subject"),
					 $sock->peer_certificate("issuer"),
					 $sock->get_cipher());
	    pc("cipher: $cipher: Subject $subj: Issuer: $iss\n", "bold white");
	}
	return ($sock, $line);
}

#########################################################################################
# Encapsulates creating a socket for use with NNTP.  Pulled to a sub because it can
# handle IPv6 sockets if the option is set.
#########################################################################################
sub createNNTPClientSocket {
	my $paddr = shift;
	my %opts = (PeerAddr => $paddr, Proto => 'tcp', Type => SOCK_STREAM);
	$ipv6 and return IO::Socket::INET6->new(%opts);
	return IO::Socket::INET->new(%opts);
}

#########################################################################################
# Attempts to perform a login on each connection
#########################################################################################
sub doLogins {
	foreach my $i (1..$connct){
		doSingleLogin($i-1);
	}
	return 1;
}

#########################################################################################
# Logs in a single connection.  Pass in the connection index.
#########################################################################################
sub doSingleLogin {
	my ($i, $silent) = @_;
	my $conn = $conn[$i];
	my $sock = $conn[$i]->{'sock'};
	return unless $sock;
	not $silent and printMessage(sprintf("Attempting login on connection #%d...\n", $i+1));

	sockSend($sock, "AUTHINFO USER $user\r\n");

	my $line = blockReadLine($sock);
	if($line =~ /^381/){

		sockSend($sock, "AUTHINFO PASS $pw\r\n");

		$line = blockReadLine($sock);
		$line =~ s/\r\n//;
		(not $line =~ /^281/) and not $silent and printError(">FAILED<\n* Authentication to server failed: ($line)\n") and shutdownClient();
		not $silent and printMessage("success!\n");
	}
	elsif($line =~ /^281/){ # not sure if this happens, but this means no pw needed I guess
		not $silent and printMessage("no password needed, success!\n");
	}
	else {
		not $silent and printError("server returned: $line\n");
		printError(">LOGIN FAILED<\n");
		# shutdown
		shutdownClient();
	}
}

#########################################################################################
# Computes and returns the total speed for this session.
#########################################################################################
sub getTotalSpeed {
	my $runtime = Time::HiRes::tv_interval(\@dlstarttime);
	return uc(hrsv($totals{'total bytes'}/$runtime)) . 'Bps';
}

#########################################################################################
# Looks at all the current connections and calculates a "current" speed
#########################################################################################
sub getCurrentSpeed {
	my $sumbps = 0;
	my $suppresshsrv = shift;
	foreach my $i (1..$connct){
		my $c = $conn[$i-1];
		next unless $c->{'file'};	# skip inactive connections
		$sumbps += ($c->{'filebytes'} - $c->{'bwstartbytes'})/Time::HiRes::tv_interval($c->{'bwstarttime'});
	}
	$suppresshsrv and return $sumbps;
	return uc(hrsv($sumbps)) . 'Bps';
}
#########################################################################################
# gets the estimated ETA in hrs:mins:secs
#########################################################################################
{
  my @old_speeds;
  sub getETA {

	  my ($h, $m, $s);
	  my $curspeed = getCurrentSpeed(1) || 0; # in bytes/sec

	  if (push(@old_speeds, $curspeed) > 20) { # keep the last 20 measurements
		 shift(@old_speeds);
	  }

	  my $avgspeed = 0;
	  foreach my $i (@old_speeds) {
		 $avgspeed += $i;
	  }
	  $avgspeed /= scalar(@old_speeds);
	  if ($avgspeed == 0) {
		 return "-";
	  }

	  my $remainbytes = $totals{'total size'} - $totals{'total bytes'};
	  my $etasec = $remainbytes / $avgspeed;
	  $h = int($etasec/(60*60));
	  $m = int(($etasec-(60*60*$h))/60);
	  $s = $etasec-(60*60*$h)-(60*$m);
	  if($h > 240){	# likely bogus...just punt
		 return "-";
	  }
	  return sprintf("%.2d:%.2d:%.2d", $h, $m, $s);
	}
}

#########################################################################################
# Checks the last paint time and updates the screen if necessary.  Also checks for
# keyboard keys.
#########################################################################################
sub drawScreenAndHandleKeys {
	$daemon and return;	# don't draw screen when in daemon mode...RC keys handled elsewhere
	if($showinghelpscreen){
		cursorPos(40, 14);
		pc("ETA: " . getETA(), 'bold green');
		pc(")", 'bold white');
		cursorPos(0, $hchar);
	}
	elsif((Time::HiRes::tv_interval(\@lastdrawtime) > 0.5) or # Refresh screen every 0.5sec max
		(usingThreadedDecoding() and $decMsgQ->pending > 0)){  # or we got status messages from decoder thread

		($wchar, $hchar, $wpixels, $hpixels) = GetTerminalSize();
		if($oldwchar != $wchar){
			$oldwchar and statMsg("Terminal was resized (new width = $wchar), redrawing");
			clearScreen();
			drawBorder();
		}
		$oldwchar = $wchar;
		@lastdrawtime = Time::HiRes::gettimeofday();
		drawHeader();
		drawConnInfos();
		drawStatusMsgs();

		cursorPos(0, $hchar);
		pc("'?' for help> ", 'bold white');
	}
	my $char;
	while (defined ($char = getch()) ) {	# have a key
		$char =~ s/[\r\n]//;
		handleKey($char);
	}
}

#########################################################################################
# Simple helper to determine if we're using threaded or nonthreaded decoding.
# It looks at the dthreadct variable and returns 1 if dthreadct > 0.
#########################################################################################
sub usingThreadedDecoding {
	return ($dthreadct > 0);
}

#########################################################################################
# getch -- gets a key in nonblocking mode
#########################################################################################
sub getch {
#	$daemon and return;
#	ReadMode ('cbreak');
#	my $char;
#	$char = ReadKey(-1);
#	ReadMode ('normal');                  # restore normal tty settings
#	return $char;
}

#########################################################################################
# Does bandwidth throttling
#########################################################################################
sub doThrottling {
	not $targkBps and return;		# Max setting, don't throttle.
	$quitnow and return;			# Don't bother if quitting
	my $curbps = getCurrentSpeed(1)/1024; # in kBps
	# TODO: Using percentages could likely make this way better.
	# (ie. inc/dec sleep duration by error percentage %)
	if($curbps > $targkBps){		# We're going too fast...
		if($sleepdur == 0){
			$sleepdur = 0.001;		# arbitrary 1ms add
		}
		else{
			$sleepdur *= 1.5;
		}
		if($sleepdur > 1.0){		# cap at 1 second sleep time, which is rediculously long anyway
			$sleepdur = 1.0;
		}
	}
	elsif($curbps < $targkBps){
		if($sleepdur > 0){
			if($sleepdur < 0.00001){	# lowest thresshold at 10us
				$sleepdur = 0;
			}
			else{
				$sleepdur -= ($sleepdur * 0.5);
			}
		}
	}
	if($sleepdur > 0){ 				# throttle if appropriate
		select undef, undef, undef, $sleepdur;
	}
}

#########################################################################################
# Trim the middle out of a string to shorten it to a target length
#########################################################################################
sub trimString {
	my $string = shift;
	my $target_len = shift;

	my $len = length($string);

	if($target_len >= $len || $target_len < 5) {
		return $string;
	}
	my $chop = $len - $target_len + 3; # 3 for the ...
	substr($string, ($len - $chop) / 2, $chop) = "...";
	return $string;
}

#########################################################################################
# Handles a keypress
#########################################################################################
sub handleKey {

	if($showinghelpscreen){
		$showinghelpscreen = 0;
		clearScreen();
		$oldwchar = 0;		# Hack to force border(s) to be redrawn
		return;	# cancel help screen display
	}

	my $key = shift;
	if($key =~ /q/){
		$quitnow = 1;
		statMsg("User forced quit...exiting...");
		# TODO: Close open files and delete parts files.
		drawStatusMsgs();
		updateBWStartPts();
	}
	elsif($key =~ /1/){
		$targkBps = $lowbw;
		statMsg("Setting bandwidth to low value ($lowbw" . "kBps)");
		drawStatusMsgs();
		updateBWStartPts();
	}
	elsif($key =~ /2/){
		$targkBps = $medbw;
		statMsg("Setting bandwidth to medium value ($medbw" . "kBps)");
		drawStatusMsgs();
		updateBWStartPts();
	}
	elsif($key =~ /3/){
		$targkBps = 0;	# set to high
		statMsg("Setting bandwidth to maximum (unlimited)");
		drawStatusMsgs();
		updateBWStartPts();
	}
	elsif($key =~ /h/ or $key =~ /\?/){
		statMsg("Displaying help screen at user's request");
		showHelpScreen();
	}
	elsif($key =~ /c/){
		$usecolor = $usecolor ^ 0x01; #invert value
	}
	elsif($key =~ /\+/){
		if($targkBps){
			$targkBps++;
			statMsg("Nudging bandwidth setting up to " . $targkBps . "kBps");
			drawStatusMsgs();
			updateBWStartPts();
		}
	}
	elsif($key =~ /-/){
		if(!$targkBps){ # Set to unlimited
			$targkBps = int(getCurrentSpeed(1)/1024)-1;
			statMsg("Nudging bandwidth setting down to " . $targkBps . "kBps");

		}
		elsif($targkBps > 1){ # Bottom out at 1
			$targkBps--;
			statMsg("Nudging bandwidth setting down to " . $targkBps . "kBps");
		}
		drawStatusMsgs();
		updateBWStartPts();
	}
	else {
		statMsg("Unknown key: $key (try 'h' for help)");
	}
}

#########################################################################################
# When the bandwidth changes, update all bw baselines for all connections
#########################################################################################
sub updateBWStartPts {
	foreach my $i (1..$connct){
		my $c = $conn[$i-1];
		$c->{'bwstartbytes'} = $c->{'filebytes'};
		@{$c->{'bwstarttime'}} = Time::HiRes::gettimeofday();
	}
}

#########################################################################################
# Draws the header that contains summary info etc.
#########################################################################################
sub drawHeader(){
	cursorPos(2, 1);
	my $len = 0;
	$len += pc("nzbperl v.$version", 'bold red');
	$len += pc(" :: ", 'bold white');
	$len += pc("noisybox.net", 'bold red');
	my $queuedCount = countQueuedNZBFiles();
	if($queuedCount > 0){
		$len += pc("  [", 'bold blue');
		$len += pc(sprintf("+%d nzb files queued", $queuedCount), 'bold cyan');
		$len += pc("]", 'bold blue');
	}
	if(scalar @rc_clients > 0){
		$len += pc("  [", 'bold blue');
		$len += pc(sprintf("%d remotes", scalar @rc_clients), 'bold cyan');
		$len += pc("]", 'bold blue');
	}
	pc((' ' x ($wchar-$len-4)), 'white');

	cursorPos(2, 3);
	$len += pc("Files remaining: ", 'bold white');
	$len += pc($totals{'total file ct'} - $totals{'finished files'}, 'bold green');
	$len += pc(" of ", 'white');
	$len += pc($totals{'total file ct'}, 'bold green');
	my $dlperc = $totals{'total size'} == 0 ? 0 : int(100.0*$totals{'total bytes'} / $totals{'total size'});
	$len += pc(' [', 'bold blue');
	$len += pc(hrsv($totals{'total bytes'}) . 'B', 'bold green');
	$len += pc('/', 'bold white');
	$len += pc(hrsv($totals{'total size'}) . 'B', 'bold green');
	$len += pc(']', 'bold blue');
	$len += pc(" ", 'white');
	$len += pc($dlperc. '%', 'bold yellow');
	$len += pc("  ETA: ", 'bold white');
	$len += pc(getETA(), 'bold yellow');
	pc((' ' x ($wchar-$len-4)), 'white');

	cursorPos(2, 2);
	$len = pc("Current speed: ", 'bold white');
	$len += pc(getCurrentSpeed(), 'bold green');
	$len += pc(" (", 'bold blue');
	$len += pc("target", 'white');
	$len += pc(' = ', 'white');
	if($targkBps){
		$len += pc(hrsv($targkBps*1024) . "Bps", 'bold green');
	}
	else{
		$len += pc("unlimited!", 'bold red');
	}
	$len += pc(")", 'bold blue');
	$len += pc("  Session speed: ", 'bold white');
	$len += pc(getTotalSpeed(), 'bold green');
	pc((' ' x ($wchar-$len-4)), 'white');
}

#########################################################################################
# Draws statuses for all individual connections
#########################################################################################
sub drawConnInfos(){
	my $startrow = 6;
	my $len;
	foreach my $i(1..$connct){
		my $conn = $conn[$i-1];

		cursorPos(2, $startrow+(3*($i-1)));

		if(not defined($conn->{'file'})){
			if(scalar(@queuefileset) == 0){
				# This connection has no more work to do...
				$len = pc(sprintf("%d: Nothing left to do...", $i), 'bold cyan');
				if(!defined($conn->{'sock'})){	# connection closed
					$len += pc(" [", 'bold white');
					$len += pc("closed", 'bold red');
					$len += pc("]", 'bold white');
				}
				pc((' ' x ($wchar-$len-4)), 'white');
				cursorPos(2, $startrow+(3*($i-1))+1);
				$len = pc(defined($forever) ? "   <waiting for more files to come in>"  :
											"   <waiting for others to finish>", 'bold cyan');
				pc((' ' x ($wchar-$len-4)), 'white');
			}
			elsif(defined($lastDiskFullTime)) {		# connection waiting on free disk space
				$len = pc(sprintf("%d: Waiting for free space on disk...[%d%% free, limit %d%%]", $i, $lastDiskFreePerc, $diskfree), 'bold yellow');
				pc((' ' x ($wchar-$len-4)), 'white');
				cursorPos(2, $startrow+(3*($i-1))+1);
				$len = pc(sprintf("   <last check was %d%% free, limit is %d%%>", $lastDiskFreePerc, $diskfree), 'bold white');
				pc((' ' x ($wchar-$len-4)), 'white');
			}

			next;
		}

		if(!defined($conn->{'sock'})){	# connection closed
			$len = pc(sprintf("%d: ", $i), 'bold white');
			$len += pc("Connection is closed", 'bold red');
			if($conn->{'sleep start'}){	# will be a reconnect
				my $remain = $reconndur - (time - $conn->{'sleep start'});
				$len += pc(sprintf(" (reconnect in %s)", hrtv($remain)), 'bold yellow');
			}
			pc((' ' x ($wchar-$len-4)), 'white');

			cursorPos(2, $startrow+(3*($i-1))+1);
			pc((' ' x ($wchar-4)), 'white');
			next;
		}

		my $file = $conn->{'file'};
		my $filesize = $file->{'totalsize'};
		my $filebytesread = $conn->{'filebytes'};
		my $segnum = $conn->{'segnum'}+1;
		my $segct = scalar @{$conn->{'file'}->{'segments'}};
		my $segbytesread = $conn->{'segbytes'};
		my $cursegsize = @{$file->{'segments'}}[$segnum-1]->{'size'};

		$len = pc(sprintf("%d: Downloading: ", $i), 'bold white');
		my $fn = $file->{'name'};
		if( length($fn) + $len > $wchar-4){
			$fn = substr($fn, 0, $wchar-4-$len);
		}
		$len += pc($fn, 'white');
		if($len < $wchar-4){
			pc(' ' x ($wchar-$len-4), 'white');
		}

		cursorPos(2, $startrow+(3*($i-1))+1);
		my $perc = 0;
		$filesize and $perc = int(($filebytesread/$filesize)*25);
		if ($noansi) {
			($perc > 25) and $perc = 25;	# cap progress bar length
			$len = pc("   |", 'bold white');
			if($perc){
				$len += pc('#' x ($perc-1), 'bold white');
				$len += pc('#', 'bold red');
			}
			$len += pc('-' x (25-$perc), 'white');
			$len += pc("| ", 'bold white');
		}
		else {
			$len = pc("\x1B(0" . "   [", 'bold white');
			if($perc){
				$len += pc('a' x ($perc-1), 'bold white');
				$len += pc('a', 'bold red');
			}
			$len += pc('q' x (25-$perc), 'white');
			$len += pc("] " . "\x1B(B", 'bold white');
		}
		if($filesize){
			$len += pc( sprintf("%2d", ($filebytesread/$filesize)*100) . "%", 'bold yellow');
		}
		else{
			$len += pc("??%", 'bold yellow');
		}
		$len += pc(' ' x (7-length(hrsv($filebytesread))) . "[", 'bold white');
		#$len += pc(sprintf("%5s", hrsv($filebytesread)), 'bold green');
		$len += pc(hrsv($filebytesread), 'bold green');
		$len += pc("/", 'bold white');
		$len += pc(hrsv($filesize), 'bold green');
		$len += pc("]", 'bold white');
		$len += pc("  [part ", 'bold white');
		$len += pc($segnum, 'bold cyan');
		$len += pc("/", 'bold white');
		$len += pc($segct, 'bold cyan');
		$len += pc(" ", 'bold white');
		$len += pc(sprintf("%4s", hrsv($segbytesread)), 'bold cyan');
		$len += pc("/", 'bold white');
		$len += pc(hrsv($cursegsize), 'bold cyan');
		$len += pc("]", 'bold white');

		pc((' ' x ($wchar - $len - 4)), 'white');

	}

}

#########################################################################################
sub drawStatusMsgs {
	# TODO:  Consider saving state about status messages -- could save cycles by not
	#        automatically drawing every time.
	$showinghelpscreen and return;
	return unless defined($wchar);	# to prevent decoder thread from trying to draw...

	my $row = 3*$connct + 6 + 1;
	my $statuslimit = $hchar - 9 - (3*$connct);	# number of lines to show.

	# Pull any decode messages from the queue and append them
	# This might not be the *best* place for this...
	while(usingThreadedDecoding() and $decMsgQ->pending > 0){
		statMsg($decMsgQ->dequeue);
	}

	# Trim status messages to size
	while( scalar(@statusmsgs) > $statuslimit){
		shift @statusmsgs;
	}
	foreach my $line (@statusmsgs){
		cursorPos(2, $row);
		if(length($line) > ($wchar-4)){
			$line = substr($line, 0, $wchar-4);	# Clip line
		}
		else{
			$line .= (' ' x ($wchar-4-length($line)));
		}
		pc($line, 'white');
		$row++;
	}
	cursorPos(0, $hchar);
	pc("'?' for help> ", 'bold white');
}

#########################################################################################
# Draws a border around the screen.
#########################################################################################
sub drawBorder {
	drawVLine(0);
	drawVLine($wchar);
	drawHLine(0, "top");
	drawHLine(4, "middle");
	drawHLine(1+5+(3*$connct), "middle");
	drawHLine($hchar-2, "bottom");
}

sub drawHLine {
	my $ypos = shift;
	my $hpos = shift;
	cursorPos(0, $ypos);
	if ($noansi) {
		pc('+' . ('-' x ($wchar-2)) . '+', 'bold white');
	}
	else {
		if ($hpos eq "top") {
			pc("\x1B(0" . 'l' . ('q' x ($wchar-2)) . 'k' . "\x1B(B", 'bold white');
		}
		elsif ($hpos eq "middle") {
			pc("\x1B(0" . 't' . ('q' x ($wchar-2)) . 'u' . "\x1B(B", 'bold white');
		}
		elsif ($hpos eq "bottom") {
			pc("\x1B(0" . 'm' . ('q' x ($wchar-2)) . 'j' . "\x1B(B", 'bold white');
		}
	}
}
sub drawVLine {
	my $xpos = shift;
	my $height = shift;
	not $height and $height = ($hchar-2);
	foreach(0..$height){
		cursorPos($xpos, $_);
		if ($noansi) {
			pc('|', 'bold white');
		}
		else {
			pc("\x1B(0" . "x" . "\x1B(B", 'bold white');
		}
	}
}

#########################################################################################
# helper for printing in color (or not)
#########################################################################################
sub pc {
	my $str = shift;
	printMessage($str);
#	my ($string, $colstr) = @_;
#	not defined($colstr) and $colstr = "white";	# default to plain white
#	$daemon and return length($string);
#	if($usecolor){
#		print colored ($string, $colstr);
#	}
#	else{
#		print $string;
#	}
#	return length($string);
}

sub clearScreen {
#	!$daemon and
#		$terminal->Tputs('cl', 1, *STDOUT);			# clears screen
}

#########################################################################################
# Positions the cursor at x,y.  Looks at $daemon first.
#########################################################################################
sub cursorPos {
#	my ($x, $y) = @_;
#	!$daemon and
#		$terminal->Tgoto('cm', $x, $y, *STDOUT);
}

#########################################################################################
# Print or log, depending on $silent
#########################################################################################
sub porl {
	porlp(shift, $daemon);
}
#########################################################################################
# porlp :: "Print or log [with] param" -
#########################################################################################
sub porlp {
	my $msg = shift;
	my $lognotprint = shift;
	if ($lognotprint){
		chomp $msg;
		statMsg($msg);
	} else{
		printMessage($msg);
	}
}
#########################################################################################
# statOrQ - calls statMsg or enqueues the message, based on the value of $dthreadct,
# which governs if we're using a threaded approach or not.
#########################################################################################
sub statOrQ {
	my $msg = shift;
	if(usingThreadedDecoding()){
		$decMsgQ->enqueue($msg);
	}
	else{
		statMsg($msg);
	}
}
#########################################################################################
# Adds a status message with timestamp
#########################################################################################
sub statMsg {
	my $str = shift;
	my @t = localtime;
	my $msg = sprintf("%0.2d:%0.2d - %s", $t[2], $t[1], $str);
	push @statusmsgs, $msg;
	printMessage($str."\n");
	return 1;
}

#########################################################################################
# Socket send that can handle both SSL and regular socket...
#########################################################################################
sub sockSend {
	my ($sock, $msg) = @_;
	if (ref($sock) eq "IO::Socket::SSL") {
	    $sock->syswrite($msg, undef);
	}
	else {
		send $sock, $msg, 0;
	}
}

#########################################################################################
# Reads a line from the socket in a blocking manner.
#########################################################################################
sub blockReadLine {
	my $sock = shift;
	my ($line, $buff) = ('', '');
	while(1){
		if (ref($sock) eq "IO::Socket::SSL") {
		    $sock->sysread($buff, 1024);
		}
		else{
			defined(recv($sock, $buff, 1024, 0)) or last;
		}
	    $line .= $buff;
	    last if $line =~ /\r\n$/;
	}
	return $line;
}

#########################################################################################
# Gracefully close down all server connections.
#########################################################################################
sub disconnectAll {
	foreach my $i (1..$connct){
		my $sock = $conn[$i-1]->{'sock'},
		printMessage("Closing down connection #$i...\n");
		not $sock and printMessage("(already closed)\n") and next;

		sockSend($sock, "QUIT\r\n");

		my $line = blockReadLine($sock);
		$line =~ /^205/ and printMessage("closed gracefully!");
		print "\n";
		if (ref($sock) eq "IO::Socket::SSL") {
		    $sock->shutdown( 2 );
		    $sock->close( SSL_no_shutdown => 1);
		}
		else {
		    close($sock);
		}
		$conn[$i-1]->{'sock'} = undef;
	}
}

#########################################################################################
# human readable time value (from seconds)
#########################################################################################
sub hrtv {
	my $sec = shift;
	if($sec < 60){
		return $sec . "s";
	}
	my $h = int($sec/(60*60));
	my $m = int(($sec - ($h*60*60))/60.0);
	my $s = $sec - ($h*60*60) - ($m*60);
	if($h){
		return sprintf("%02d:%02d:%02d", $h, $m, $s);
	}
	else{
		return sprintf("%02d:%02d", $m, $s);
	}
}

#########################################################################################
# human readable size value
#########################################################################################
sub hrsv {
	my $size = shift;  # presumed bytes
	$size = 0 unless defined($size);
	my $k = 1.0*$size/1024;
	my $m = 1.0*$size/(1024*1024);
	my $g = 1.0*$size/(1024*1024*1024);
	if($g > 1){
		return sprintf("%0.2fG", $g);
	}
	if($m > 1){
		return sprintf("%0.2fM", $m);
	}
	if($k > 1){
		return sprintf("%dk", $k);
	}
	return sprintf("%0.2f", $size);
}

#########################################################################################
# read password without echoing it
#########################################################################################
sub readPassword {
#	ReadMode 2;	# no echo
#	my $pw = <STDIN>;
#	chomp $pw;
#	ReadMode 0; # default
#	print "\n";
#	return $pw;
}

#########################################################################################
# Determines the total file size for all segments in the NZB file
#########################################################################################
sub computeTotalNZBSize {
	my @fileset = @_;
	my $tot = 0;
	foreach my $file (@fileset){
		foreach my $seg (@{$file->{'segments'}}){
			$tot += $seg->{'size'};
		}
	}
	return $tot;
}

#########################################################################################
# Parse NZB file and return array of files
# TODO: The structure returned from this function should really be documented....but
# for now, if you need it, use Dumper to view the format.  Should be self explanatory.
#########################################################################################
sub parseNZB {
	my $nzbfilename = shift;
	my $lognoprint = shift;
	$nzbfiles{'files'}->{basename($nzbfilename)}->{'read'} = 1;	# set flag indicating we've processed it
	my $nzbdir = derivePath($nzbfilename);
	if ((defined($dlpath)) and (defined($dlcreate))) {
	    my $nzbbase = basename($nzbfilename);
	    if ($nzbbase =~ /msgid_[0-9]*_(.*).nzb/) {
			# Filter name from NewzBin style names
			$nzbdir = $1 . "/";
	    }
		elsif ($nzbbase =~ /(.*).nzb/) {
			# Strip the .nzb extension and
			$nzbdir = $1 . "/";
	    }
		else {
			# Just use the nzb file as a directory itself
			$nzbdir = $nzbbase . "/";
	    }
	}
	$nzbdir .= '/' unless $nzbdir =~ /\/$/;
	my $parser = new XML::DOM::Parser;
	my @fileset;
	porlp("Loading and parsing nzb file: " . $nzbfilename . "\n", $lognoprint);
	my $nzbdoc;
	eval {
		$nzbdoc = $parser->parsefile($nzbfilename);
	};
	if($@){
		my $errmsg = trimWS($@);
		if($lognoprint){
			statMsg("The nzb file is BROKEN and the XML could not be parsed.");
		}
		else{
			pc("\n");
			pc(" Sorry, but nzb file is broken!  The xml could not be parsed:\n", 'bold yellow');
			pc("\n");
			pc(" $errmsg\n\n", 'bold yellow');
			pc(" *** nzbperl requires valid, well-formed XML documents.\n\n", 'bold red');
		}
		return undef;
	}

	my $files = $nzbdoc->getElementsByTagName("file");
	my $totalsegct = 0;
	foreach my $i (0..$files->getLength()-1){
		my $fileNode = $files->item($i);
		my $subj = $fileNode->getAttributes()->getNamedItem('subject');
		my $postdate = $fileNode->getAttributes()->getNamedItem('date');

		my %file;
		$file{'nzb path'} = $nzbdir;
		$file{'nzb file'} = basename($nzbfilename);
		$file{'name'} = $subj->getValue();
		$file{'date'} = $postdate->getValue();

		my @groupnames;
		for my $group ($fileNode->getElementsByTagName('group')) {
			push @groupnames, $group->getFirstChild()->getNodeValue();
		}
		$file{'groups'} = \@groupnames;

		my @segments;
		for my $seg ($fileNode->getElementsByTagName('segment')) {
			my %seghash;

			my $size = $seg->getAttributes()->getNamedItem('bytes')->getValue();
			$file{'totalsize'} += $size;

			my $segNumber = $seg->getAttributes()->getNamedItem('number')->getValue();

			$seghash{'msgid'} = $seg->getFirstChild()->getNodeValue();
			$seghash{'size'} = $size;
			$seghash{'number'} = $segNumber;

			push @segments, \%seghash;
		}

		# If segment numbers are present, use them to sort.
		if (defined($segments[0]) && defined($segments[0]->{'number'})){
			@segments = sort {
				$a->{'number'} <=> $b->{'number'} } @segments;
		}

		$totalsegct += scalar @segments;
		$file{'segments'} = \@segments;

		push @fileset, \%file;
	}
	$nzbdoc->dispose;

	porlp("Loaded $totalsegct total segments for " . $files->getLength() . " file(s).\n", $lognoprint);

	@fileset = sortFilesBySubject($lognoprint, @fileset);	# It checks $sort inside
	@fileset = resetLastOnNzbFlag(@fileset);

	return @fileset;
}

#########################################################################################
# Filters out files if there is a filter regex, and skips over files from --skip <n>
#########################################################################################
sub regexAndSkipping {
	my @fileset = @_;

	if(defined($filterregex)){	# the inclusive filter
		@fileset = filterFilesOnSubject(1, $filterregex, @fileset);
	}
	if(defined($ifilterregex)){	# the exclusive (inverse) filter
		@fileset = filterFilesOnSubject(0, $ifilterregex, @fileset);
	}

	if($skipfilect){
		if($skipfilect >= scalar @fileset){
			pc("\nWhoops:  --skip $skipfilect would skip ALL " . scalar @fileset .
					" files...aborting!\n\n", 'bold yellow') and shutdownClient();
		}
		printMessage("Removing $skipfilect files from nzb set (--skip $skipfilect)\n");
		while($skipfilect > 0){
			shift @fileset;
			$skipfilect--;
		}
	}

	@fileset = resetLastOnNzbFlag(@fileset);
	return @fileset;
}

#########################################################################################
# Takes in a list of files and filters them based on subject.
#########################################################################################
sub filterFilesOnSubject {
	my $inclusiveRegex = shift;
	my $regex = shift;
	my @fileset = @_;
	printMessage("Filtering files on " . ($inclusiveRegex ? '' : 'inverse ') . "regular expression...\n");
	my $orgsize = scalar @fileset;
	my @nset;
	while(scalar(@fileset) > 0){
		my $f = shift @fileset;
		if( ($inclusiveRegex and ($f->{'name'} =~ /$regex/)) or
			( not $inclusiveRegex and (not $f->{'name'} =~ /$regex/))){
			push @nset, $f;
		}
	}
	if(scalar @nset < 1){
		pc("\nWhoops:  Filter removed all files (nothing left)...aborting!\n\n", 'bold yellow') and shutdownClient();
	}
	printMessage(sprintf("Kept %d of %d files (filtered %d)\n", scalar(@nset), $orgsize, $orgsize-scalar(@nset)));
	return @nset;
}

#########################################################################################
# Sorts files in a fileset based on the name
#########################################################################################
sub sortFilesBySubject {
	my $quiet = shift;
	my @fileset = @_;
	if(!$nosort){
		porlp("Sorting files by filename (subject)...", $quiet);
		@fileset =
			sort {
				$a->{'name'} cmp $b->{'name'};
			} @fileset;
		porlp("finished.\n", $quiet);
	}
	return @fileset;
}

#########################################################################################
# Traverses a fileset and resets the islastonnzb flag.
#########################################################################################
sub resetLastOnNzbFlag {
	my @fileset = @_;
	foreach my $i (0..scalar(@fileset)-1){
		if($i == scalar(@fileset)-1){
			$fileset[$i]->{'lastonnzb'} = 1;
		}
		else{
			$fileset[$i]->{'lastonnzb'} = 0;
		}
	}
	return @fileset;
}

#########################################################################################
# Derives a path from a filename (passed on commandline).
# The result isn't necessarily absolute, can be relative
#########################################################################################
sub derivePath {
	my $filename = shift;
	if($filename =~ /\//){		# then it has path information, likely not windows compat
		$filename =~ s/(^.*\/).*/$1/;
		return $filename;
	}
	return cwd;
}

#########################################################################################
# Main entry point for NZB file sanity checking
#########################################################################################
sub doNZBSanityChecks(){
	printMessage("Analyzing sanity of NZB file segment completeness...\n");
	@suspectFileInd = getSuspectFileIndexes();
	my $badfilect = scalar @suspectFileInd;
	not $badfilect and pc("All files pass segment size sanity checks!  Swell.\n", 'bold green') and return;

	SMENUDONE:
	while(1){
		pc(sprintf("There are %d of %d files that may have missing or broken segments.\n", $badfilect, scalar @fileset), 'bold yellow');
		pc("It is likely that these files will be unusable if downloaded.\n", 'bold yellow');
		($dropbad or $insane) and return;	# User selection not needed.
		print "\n How do you want to proceed?\n\n";
		print " k)eep everything and try all files anyway (--insane)\n";
		print " d)rop files suspected broken (--dropbad)\n";
		print " v)iew gory details about broken segments\n";
		print " q)uit now\n";
		print "\n -> ";
		while(1){
			my $char;
			if(defined ($char = getch()) ) {	# have a key
				print "\n";
				if($char =~ /q/){
					# shutdown
					shutdownClient();
				}
				elsif($char =~ /k/){
					print "Setting --insane option...\n";
					$insane = 1;
					last SMENUDONE;
				}
				elsif($char =~ /d/){
					print "Setting --dropbad option...\n";
					$dropbad = 1;
					last SMENUDONE;
				}
				elsif($char =~ /v/){
					showSuspectDetails(@suspectFileInd);
				}
				last;
			}
			else{
				select undef, undef, undef, 0.1;
			}
		}
	}
}

#########################################################################################
# Shows details about suspect files...
#########################################################################################
sub showSuspectDetails {
	my @susFileInd = @_;
	foreach my $fileind (1..scalar @susFileInd){
		my $file = @fileset[$susFileInd[$fileind-1]];
		my $avgsize = avgFilesize($file);
		print "------------------------------------------------------\n";
		printf(" * File: %s\n", $file->{'name'});
		printf("   Posted on: %s (%d days ago)\n",
				scalar localtime $file->{'date'},
				(time - $file->{'date'})/(60*60*24) );
		printf("   Adjusted average part size = %d bytes\n", $avgsize);
		my @sids = getSuspectSegmentIndexes($file, $avgsize);
		foreach my $si (@sids){
			my $seg = @{$file->{'segments'}}[$si];
			my $percdiff = 100;
			$avgsize and $percdiff = 100*(abs($seg->{'size'} - $avgsize)/$avgsize);
			printf("      Part %d : %d bytes (%.2f%% error from average)\n",
					$si+1, $seg->{'size'}, $percdiff);
		}
	}
	print "------------------------------------------------------\n";
}

#########################################################################################
# Looks at the fileset and returns an array of file indexes that are suspect
#########################################################################################
sub getSuspectFileIndexes {
	my @ret;
	foreach my $fileind (1..scalar @fileset){
		my $file = $fileset[$fileind-1];
		my $avg = avgFilesize($file);
		#printf("File has average size = %d\n", $avg);
		my $segoffct = 0;

		my @suspectSegInd = getSuspectSegmentIndexes($file, $avg);
		if(scalar @suspectSegInd){
			push @ret, $fileind-1;
		}
	}
	return @ret;
}

#########################################################################################
sub getSuspectSegmentIndexes {
	my $MAX_OFF_PERC = 25;		# Percentage of segment size error/diff to trigger invalid
	my ($file, $avg) = @_;
	my @ret;
	foreach my $i (1..(scalar @{$file->{'segments'}}-1)){  # Last segment is allowed to slide...
		my $seg = @{$file->{'segments'}}[$i-1];
		my $percdiff = 100;
		$avg and $percdiff = 100*(abs($seg->{'size'} - $avg)/$avg);
		#printf("   seg $i of %d is %0.2f off avg [%d versus %d (avg)]\n", scalar @{$file->{'segments'}}, $percdiff, $seg->{'size'}, $avg);
		if($percdiff > $MAX_OFF_PERC){
			push @ret, $i-1;
		}
	}
	return @ret;
}

#########################################################################################
sub dropSuspectFiles(){ my @newset; my $dropct = 0; foreach my $i (0..scalar @fileset-1){
		if ((defined($suspectFileInd[0])) && ($i == $suspectFileInd[0])) {
			my $ind = shift @suspectFileInd;
			my $file = $fileset[$ind];
			printMessage(sprintf("Dropping [%s] from filset (suspect)\n", $file->{'name'}));
			$dropct++;
			next;
		}
		push @newset, shift @fileset;
	}
	@fileset = @newset;
	pc(sprintf("Dropped %d suspect files from NZB (%d files remain)\n", $dropct, scalar @fileset), 'bold yellow');
	printMessage(" -> short delay (for user review)\n");
	foreach(5,4,3,2,1){
		sleep 1;
	}
	printMessage("...let's go!\n");
}

#########################################################################################
# Not a true average, but an average of all segments except the last one...
# ...unless there's only one segment, in which case it's the segment size.
#########################################################################################
sub avgFilesize {
	my $file = shift;
	my @segs = @{$file->{'segments'}};
	return $segs[0]->{'size'} unless scalar @segs > 1;
	my ($sum, $ct) = (0, 0);
	foreach my $i (1..scalar(@segs)){
		my $seg = $segs[$i-1];
		last unless $i < scalar(@segs);
		$ct++;
		$sum += $seg->{'size'};
	}
	return $sum*1.0/($ct*1.0);
}

#########################################################################################
# Parse command line options and assign sane globals etc.
#########################################################################################
sub handleCommandLineOptions {
	my @saveargs = @ARGV;

	# This extra call is required to set up the --config option, expected below
	GetOptions(%optionsmap);

	my $errmsg;
	# This is the facility for trapping stderr from GetOptions, so that we
	# can pretty print it at the bottom of the help screen.
	local $SIG{'__WARN__'} = sub {
		$errmsg = $_[0];
		chomp $errmsg;
	};

	# First see if the config file is there, if so, slurp options from it.
	my $optionsAreOk;
	if(-e $configfile){
		readConfigFileOptions();
		$optionsAreOk = eval 'GetOptions(%optionsmap)';
		return $errmsg unless $optionsAreOk;
	} else {
		printMessage("Config file $configfile does not exist.  Skipping.\n");
	}

	# Now restore the commandline args and parse those (overriding config file options)
	@ARGV = @saveargs;	# restore
	$optionsAreOk = eval 'GetOptions(%optionsmap)';
	return $errmsg unless $optionsAreOk;
	if($help){
		return "";
	}
	$nocolor and $usecolor = 0;

	not $optionsAreOk and return "";

	if(usingThreadedDecoding()){
		eval "
		use threads;
		use Thread::Queue;";
		($@) and return "ERROR: Could not use Perl thread modules.\r\n" .
		" Try setting --dthreadct 0 to run with a single threaded Perl.";
	}

	if($recv_chunksize =~ /kb?$/i){
		$recv_chunksize =~ s/kb?$//i;
		$recv_chunksize = $recv_chunksize*1024;
	}

	if(defined($queuedir) and (not $queuedir =~ /^\//)){
		return "--queuedir must specify an ABSOLUTE (not relative) path.";
	}
	if(not $ARGV[0] and (not defined $queuedir)){		# No NZB file given?
		return "Missing nzb file or directory queue.";
	}

	if($server =~ /:\d+$/){
		$port = $server;
		$port =~ s/.*://;
		$server =~ s/:.*//;
	}
	if(not length($server)){
		$server = $ENV{'NNTPSERVER'};
		not $server and return "Must provide --server or set \$NNTPSERVER environment";
	}
	$server = trimWS($server);

	$dlpath = cwd unless (defined($dlpath) or defined($dlrelative));
	if($dlpath and not $dlpath =~ /^\//){
		return "--dlpath must specify an ABSOLUTE (not relative) path.";
	}

	# Make sure that dlpath ends with a slash
	if($dlpath and (not ($dlpath =~ /\/$/))){
		$dlpath .= '/';
		($dlpath =~ m#^([\w\d\s\.\_\-\/\\]+)$#) and $dlpath = $1;	# untaint dlpath
	}

	if($dropbad and $insane){	# conflicting
		return "Error: --dropbad and --insane are conflicting (choose one)";
	}

	if($forever and not (defined($rcport) or defined($queuedir))){
		return "Error: --forever requires either --queuedir or --rcport.\n" .
				" Please choose one and try again.";
	}

	if(defined($queuedir) and !$dropbad and !$insane){
		return "Use of --queuedir requires either --dropbad or --insane.\n" .
				" Please choose one and try again.";
	}

	if(defined($postDecProg) and not -e $postDecProg){
		return "--postdec program \"$postDecProg\" does not exist.\n" .
				" Please confirm the program and try again.";
	}

	if($dlpath and $dlrelative){ # conflicting options
		return "Error: --dlrelative and --dlpath <dir> are conflicting (choose one)";
	}

	# Verify that output dir is writable...
	if(defined($dlpath) and not -w $dlpath) {
		return "Error: dlpath '$dlpath' is not writable!\n" .
				" Please change the permissions or use a different directory.";
	}

	if(defined($DECODE_DBG_FILE)){
		if(open(DBGTMP,">$DECODE_DBG_FILE")){
			close DBGTMP;	#all good
		}
		else{
			return "The decode log file '$DECODE_DBG_FILE' is unwritable!";
		}
	}

	if($port == -1) {
	    if (defined($ssl)) {
			(undef, undef, $port, undef) = getservbyname("nntps", "tcp");
	    }
		else {
			(undef, undef, $port, undef) = getservbyname("nntp", "tcp");
	    }
	}

	if(defined($socks_server) and defined($http_proxy_server)){
		return "Error: --socks_server and --http_proxy are conflicting (choose one)";
	}
	if(defined($dlcreate) and defined($dlcreategrp)){
		return "Error: --dlcreate and --dlcreategrp are conflicting (choose one)";
	}

	if (defined($ssl)) {
	    eval "use IO::Socket::SSL;";		# use module only if option is enabled.
		($@) and return "ERROR: --ssl was specified, but IO::Socket::SSL isn't available.\r\n" .
						" Please install IO::Socket::SSL to use --ssl and try again.";
	}

	if (defined($socks_server)) {
	    eval "use IO::Socket::Socks;"; 		# use module only if option enabled
		($@) and return "ERROR: --socks_server was specified, but IO::Socket::Socks isn't available.\r\n" .
						" Please install IO::Socket::Socks to use a SOCKS server and try again.";

	    if ($socks_port == -1) {
			if($socks_server =~ /:\d+$/){
				$socks_port = $socks_server;
				$socks_port =~ s/.*://;
				$socks_server =~ s/:.*//;
			}
			else {
				(undef, undef, $socks_port, undef) = getservbyname("socks", "tcp");
			}
	    }
		$socks_server = trimWS($socks_server);
	}

	if (defined($http_proxy_server)) {
	    eval "use Net::HTTPTunnel;";		# use module only if option enabled
		($@) and return "ERROR: --http_proxy was specified, but Net::HTTPTunnel isn't available.\r\n" .
						" Please install Net::HTTPTunnel to use an HTTP proxy and try again.";

		if($http_proxy_server =~ /:\d+$/){
			$http_proxy_port = $http_proxy_server;
			$http_proxy_port =~ s/.*://;
			$http_proxy_server =~ s/:.*//;
		}
		else {
			(undef, undef, $http_proxy_port, undef) = getservbyname("webcache", "tcp");
		}
		$http_proxy_server = trimWS($http_proxy_server);
	}

	if(defined($ipv6)){
	    eval "use IO::Socket::INET6;";		# use ipv6 module if option given
		($@) and return "ERROR: --ipv6 was given and the IO::Socket::INET6 module could not be found.\r\n" .
						" You must install the IO::Socket::INET6 module to use IPv6";
	}

	# check tf-arg
	if (!$tfuser) {
		return "no tfuser given\n";
	}

	return undef;	# success
}

#########################################################################################
# Helper to detect that uudeview is installed.  Always a good idea, ya'know, since we're
# dependant on it!
#########################################################################################
sub haveUUDeview {
	if(defined($uudeview)){	# Given on commandline or config file
		if (-e $uudeview){
			return 1;
		}
		printError("Warning: uudeview not found at location $uudeview\n");
	}
	my @paths = split /:/, $ENV{'PATH'};	# path sep different on winderz?
	foreach my $p (@paths){
		$p =~ s/\/$//;
		$p = $p . "/uudeview";
		if(-e $p){
			printMessage("uudeview found: $p\n");
			$uudeview = $p;
			return 1;
		}
	}
	printError("Error: uudeview not found in path...aborting!\n");
	return 0;
}

#########################################################################################
# Reads options from the config file and tucks them into @ARGV, so that they all
# look like they were passd on the commandline.  So, when this returns (successfully),
# ARGV contains the config file contents.  ARGV must be preserved externally.
#########################################################################################
sub readConfigFileOptions(){
	printMessage("Reading config options from $configfile...\n");
	open CFG, "<$configfile" or die "Error opening $configfile for config options";
	my $line;
	my @opts;
	while($line = <CFG>){
		chomp $line;
		$line =~ s/^\s+//;
		$line =~ s/^-+//;				# In case dashes in config file
		$line =~ s/(\s+)?=(\s+)?/=/;	# Remove whitespace around equals sign
		next if $line =~ /^#/;
		next unless length($line);
		push @opts, "--$line";
	}
	close CFG;
	@ARGV = @opts;
}

#########################################################################################
# Trim ws on both sides of string.  Undef is ok.
#########################################################################################
sub trimWS {
	my $s = shift;
	return $s unless defined $s;
	$s =~ s/^\s+//;
	$s =~ s/\s+$//;
	return $s;
}

#########################################################################################
# Checks for a newer version, disabled with --noupdate
#########################################################################################
sub checkForNewVersion {
	$noupdate and return;	# they don't want update checking
	printMessage("Checking for availability of newer version...\n");
	eval "use LWP::Simple;";
	if($@){
		printMessage("LWP::Simple is not installed, skipping up-to-date check.\n");
		return;
	}
	my $remote_ver = eval "get \"$UPDATE_URL\"";
	if(!defined($remote_ver)){
		pc("Error fetching current version during update check: $!\n", 'bold red');
		pc("Skipping up-to-date check.\n", 'bold yellow');
		return;
	}

	chomp $remote_ver;

	if($remote_ver eq $version){
		printMessage("Look like you're running the most current version.  Good.\n");
	}
	else{
		pc("A newer version of nzbperl is available: ", 'bold red');
		pc('version ' . $remote_ver . "\n", 'bold white');
		pc("You should consider downloading it from ", 'bold white');
		pc("http://noisybox.net/computers/nzbperl/\n", 'bold yellow');
		pc("This delay is intentional: ");
		foreach(1..8){
			print "..." . (9-$_);
			sleep 1;
		}
		pc("\n");
	}

}

#########################################################################################
sub displayShortGPL {
	print <<EOL

  nzbperl version $version, Copyright (C) 2004 Jason Plumb
  nzbperl comes with ABSOLUTELY NO WARRANTY; This is free software, and
  you are welcome to redistribute it under certain conditions;  Please
  see the source for additional details.

EOL
;
}

#########################################################################################
# Shows a help screen for interactive keys
#########################################################################################
sub showHelpScreen {
	clearScreen();
	print <<EOL

  Hi.  This is the nzbperl help screen.
  You can use the following keys while we're running:

  '1'   : Switch to low bandwidth mode ($lowbw kBps)
  '2'   : Switch to med bandwidth mode ($medbw kBps)
  '3'   : Switch to high bandwidth mode (unlimited)
  '+'   : Nudge target bandwidth setting up 1 kBps
  '-'   : Nudge target bandwidth setting down 1 kBps
  'c'   : Toggle color on or off
  'q'   : Quit the program (aborts all downloads)
  '?'   : Show this help screen

  Connected to $server:$port
  (Your download is still in progress:

  [ Press any key to return to the main screen ]

EOL
;
	drawVLine(0, 17);
	drawVLine($wchar, 17);
	drawHLine(0, 'top');
	drawHLine(17, 'bottom');

	cursorPos(40, 14);
	pc("ETA: " . getETA(), 'bold green');
	pc(")", 'bold white');
	$showinghelpscreen = 1;
}

#########################################################################################
# Show program usage
#########################################################################################
sub showUsage {
my $errmsg = shift;
print <<EOL

  nzbperl version $version -- usage:

  nzbperl <options> <file1.nzb> ... <file.nzb>

  where <options> are:

 --config <file>   : Use <file> for config options (default is ~/.nzbperlrc)
 --server <server> : Usenet server to use (defaults to NNTPSERVER env var)
                   : Port can also be specified with --server <server:port>
 --user <user>     : Username for server (blank of not needed)
 --pw <pass>       : Password for server (blank to prompt if --user given)
 --conn <n>        : Use <n> server connections (default = 2)
 --ssl             : Connect to server using SSL (secure sockets layer).
                   : May be combined with --http_proxy or --socks_server to
                   : use a proxy server with SSL.
 --socks_server <s>: Connect using <s> as a socks proxy server. Defaults to
                   : port 1080, but can use --socks_server <server:port> to
                   : use an alternative port.
 --http_proxy <s>  : Use <s> as an http proxy server to use.  Defaults
                   : to port 8080, but can use --http_proxy <server:port> to
                   : use an alternative port.
 --proxy_user <u>  : Authenticate to the proxy using <u> as the username
 --proxy_passwd <p>: Use <p> as the proxy user password (otherwise prompted)
 --ipv6            : Use IPv6 sockets for communication
 --keepparts       : Keep all encoded parts files on disk after decoding
 --keepbroken      : Continue downloading files with broken/missing segments
                   : and leave the parts files on disk still encoded.
 --keepbrokenbin   : Decode and keep broken decoded files (binaries) on disk.
 --dlpath <dir>    : Download and decode all files to <dir>
                   : (default downloads to current dirctory)
 --dlrelative      : Download and decode to the dir that the nzbfiles are in
                   : (default downloads to current directory)
 --dlcreate        : Create download directories per nzb file
 --dlcreategrp     : Create download dirctories with usenet group names
 --queuedir <dir>  : Monitor <dir> for nzb files and queue new ones
 --forever         : Run forever, waiting for new nzbs (requires --queuedir)
 --postdec <prog>  : Run <prog> after each file is decoded, env var params.
 --postnzb <prog>  : Run <prog> after each NZB file is completed.
 --diskfree <perc> : Stop downloading when dir free space above <perc>
 --redo            : Don't skip over existing downloads, do them again
 --insane          : Bypass NZB sanity checks completely
 --dropbad         : Auto-skip files in the NZBs with suspected broken parts
 --skip <n>        : Skip the first <n> files in the nzb (don't process)
 --med <kBps>      : Set "med" bandwidth to kBps (default is 95kBps)
 --low <kBps>      : Set "low" bandwidth to kBps (default is 35kBps)
 --speed <speed>   : Explicitly specify transfer bandwidth in kBps
 --decodelog <file>: Append uudeview output into <file> (default = none)
 --dthreadct <ct>  : Use <ct> number of decoder threads.  Set ct = 0 for single
                     threaded perl operation.  (Note: When ct = 0, downloads
                     will be paused during file decoding)
 --rcport <port>   : Enable remote control functionality on port <port>
 --retrywait <n>   : Wait <n> seconds between reconnect tries (default = 300)
 --nosort          : Don't sort files by name before processing
 --chunksize       : Amount to read on each recv() call (for tweakers only)
                   : Default = 5k, Can specify in bytes or kb (ie. 5120 or 5k)
 --filter <regex>  : Filter NZB contents on <regex> in subject line
 --ifilter <regex> : Inverse filter NZB contents on <regex> in subject line
 --uudeview <app>  : Specify full path to uudeview (default found in \$PATH)
 --tfuser          : TF username to run as (required)
 --help            : Show this screen

  nzbperl version $version, Copyright (C) 2004 Jason Plumb
  nzbperl comes with ABSOLUTELY NO WARRANTY; This is free software, and
  you are welcome to redistribute it under certain conditions;  Please
  see the source for additional details.

  this version is rewritten to be used with torrentflux-b4rt and cannot work
  in standalone mode.

EOL
;
	if($errmsg and (length($errmsg))){
		print " *****************************************************************\n";
		print " ERROR:\n";
		print " $errmsg\n";
		print " *****************************************************************\n";
	}

}

#------------------------------------------------------------------------------#
# Sub: getStatSpeed                                                            #
# Arguments: null                                                              #
# Returns: down-speed formatted for stat-file                                  #
#------------------------------------------------------------------------------#
sub getStatSpeed {
	my $sumbps = 0;
	foreach my $i (1..$connct){
		my $c = $conn[$i-1];
		next unless $c->{'file'};	# skip inactive connections
		$sumbps += ($c->{'filebytes'} - $c->{'bwstartbytes'})/Time::HiRes::tv_interval($c->{'bwstarttime'});
	}
	return sprintf("%0.2f %s", ($sumbps / 1024), "kB/s");
}

#------------------------------------------------------------------------------#
# Sub: writeStatStartup                                                        #
# Arguments: null                                                              #
# Returns: return-value of write                                               #
#------------------------------------------------------------------------------#
sub writeStatStartup {
	# set some values
	$sf->set("running", 1);
	$sf->set("percent_done", 0);
	$sf->set("time_left", "Starting...");
	$sf->set("down_speed", "0.00 kB/s");
	$sf->set("up_speed", "0.00 kB/s");
	$sf->set("transferowner", $tfuser);
	$sf->set("seeds", 1);
	$sf->set("peers", 1);
	$sf->set("sharing", "");
	$sf->set("seedlimit", "");
	$sf->set("uptotal", 0);
	$sf->set("downtotal", 0);
	$sf->set("size", $totalsCopy{'total size'});
	# write
	return $sf->write();
}

#------------------------------------------------------------------------------#
# Sub: writeStatRunning                                                        #
# Arguments: null                                                              #
# Returns: return-value of write                                               #
#------------------------------------------------------------------------------#
sub writeStatRunning {
	# set some values
	$sf->set("percent_done", $totals{'total size'} == 0 ? 0 : int(100.0 * $totals{'total bytes'} / $totals{'total size'}));
	$sf->set("time_left", getETA());
	$sf->set("down_speed", getStatSpeed());
	$sf->set("downtotal", $totals{'total bytes'});
	# write
	return $sf->write();
}

#------------------------------------------------------------------------------#
# Sub: writeStatShutdown                                                       #
# Arguments: null                                                              #
# Returns: return-value of write                                               #
#------------------------------------------------------------------------------#
sub writeStatShutdown {
	# set some values
	$sf->set("running", 0);
	if ($noMoreWorkTodo) {
		# done
		$sf->set("percent_done", 100);
		$sf->set("time_left", "Download Succeeded!");
	} else {
		# stopped
		$sf->set("percent_done", $totals{'total size'} == 0 ? "-100" : ((int(100.0 * $totals{'total bytes'} / $totals{'total size'})) + 100) * (-1));
		$sf->set("time_left", "Transfer Stopped");
	}
	$sf->set("down_speed", "");
	$sf->set("up_speed", "");
	$sf->set("transferowner", $tfuser);
	$sf->set("seeds", "");
	$sf->set("peers", "");
	$sf->set("sharing", "");
	$sf->set("seedlimit", "");
	$sf->set("uptotal", 0);
	$sf->set("downtotal", $totals{'total bytes'});
	$sf->set("size", $totalsCopy{'total size'});
	# write
	return $sf->write();
}

#------------------------------------------------------------------------------#
# Sub: pidFileWrite                                                            #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub pidFileWrite {
	printMessage("writing pid-file ".$file_pid." (pid: ".$$.")\n");
	open(PIDFILE,">$file_pid");
	print PIDFILE $$."\n";
	close(PIDFILE);
}

#------------------------------------------------------------------------------#
# Sub: pidFileDelete                                                           #
# Arguments: null                                                              #
# Returns: return-val of delete                                                #
#------------------------------------------------------------------------------#
sub pidFileDelete {
	printMessage("deleting pid-file ".$file_pid."\n");
	return unlink($file_pid);
}

#------------------------------------------------------------------------------#
# Sub: printMessage                                                            #
# Arguments: message                                                           #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printMessage {
	my $message = shift;
	print STDOUT FluxCommon::getTimeStamp()." ".$message;
}

#------------------------------------------------------------------------------#
# Sub: printError                                                              #
# Arguments: message                                                           #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printError {
	my $message = shift;
	print STDERR FluxCommon::getTimeStamp()." ".$message;
}

#------------------------------------------------------------------------------#
# Sub: processCommandStack                                                     #
# Arguments: Null                                                              #
# Return: 1|0                                                                  #
#------------------------------------------------------------------------------#
sub processCommandStack {
	# check for command-file
	if (!(-e $file_cmd)) {
		return 0;
	}
	# process the command file
	printMessage("Processing command-file ".$file_cmd."...\n");
	# sep + open file
	my $lineSep = $/;
	undef $/;
	open(CMDFILE,"<$file_cmd");
	# read data
	my $content = <CMDFILE>;
	# close file + sep
	close(CMDFILE);
	$/ = $lineSep;
	# delete file
	unlink($file_cmd);
	# process data
	my @contentary = split(/\n/, $content);
	my $commandCount = 0;
	foreach my $command (@contentary) {
		# exec command
		my $result = execCommand($command);
		if ($result == 1) {
			return 1;
		} elsif ($result == 0) {
			$commandCount++;
		}
	}
	if ($commandCount == 0) {
		printMessage("No commands found.\n");
	}
	return 0;
}

#------------------------------------------------------------------------------#
# Sub: execCommand                                                             #
# Arguments: command                                                           #
# Return: -1|0|1                                                               #
#------------------------------------------------------------------------------#
sub execCommand {
	my $command = shift;
	chomp $command;
	$_ = $command;
	SWITCH: {
		/^q$/ && do {
			# quit
			printMessage("command: stop-request, setting shutdown-flag...\n");
			$quitnow = 1;
			return 1;
		};
		/^d(\d+)/ && do {
			# set download speed
			printMessage("command: setting Download-Rate to ".$1."\n");
			$targkBps = $1;
			return 0;
		};
		# default
		printMessage("command unknown or invalid. op-code : ".substr($command, 0 , 1)."\n");
	} # SWITCH
	return -1;
}

#------------------------------------------------------------------------------#
# Sub: shutdownClient                                                          #
# Arguments: Null                                                              #
# Return: Null                                                                 #
#------------------------------------------------------------------------------#
sub shutdownClient {
	# write stat-file
	writeStatShutdown();
	# delete pid-file
	pidFileDelete();
	# exit message
	printMessage("nzbperl exit.\n");
	# exit
	exit;
}

#------------------------------------------------------------------------------#
# Sub: gotSigHup                                                               #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub gotSigHup {
	printMessage("got SIGHUP, ignoring...\n");
}

#------------------------------------------------------------------------------#
# Sub: gotSigInt                                                               #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub gotSigInt {
	printMessage("got SIGINT, setting shutdown-flag...\n");
	$quitnow = 1;
}

#------------------------------------------------------------------------------#
# Sub: gotSigTerm                                                              #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub gotSigTerm {
	printMessage("got SIGTERM, setting shutdown-flag...\n");
	$quitnow = 1;
}

#------------------------------------------------------------------------------#
# Sub: gotSigQuit                                                              #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub gotSigQuit {
	printMessage("got SIGQUIT, setting shutdown-flag...\n");
	$quitnow = 1;
}
