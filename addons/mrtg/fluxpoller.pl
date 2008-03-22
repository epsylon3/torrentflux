#!/usr/bin/perl
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
use strict;
################################################################################

# load-average multiplier
# CHANGEME
my $AVGmultiplier = "100";

# stat-file-dir (".transfers" in tf-b4rt and ".torrents" in TF 2.1 / 2.1-b4rt)
my $STATFILEDIR=".transfers";

# webserver-user
# (only used on bsd)
my $WEBUSER = "www";

# define socket-bins. default : qw( python transmissionc wget )
# (only used on bsd)
my @BINS_SOCKET = qw( python transmissionc wget java );

# should we try to find needed binaries ? (using "whereis" + "awk")
# use 1 to activate, else "constants" are used (the faster + safer way)
my $autoFindBinaries = 0;

# Internal Vars
my ($REVISION, $DIR, $PROG, $EXTENSION, $USAGE, $OSTYPE);

# bin Vars
my ($BIN_CAT, $BIN_HEAD, $BIN_TAIL, $BIN_NETSTAT, $BIN_SOCKSTAT, $BIN_GREP, $BIN_AWK);

# check env
checkEnv();

# define common binaries
$BIN_CAT = "/bin/cat";
$BIN_HEAD = "/usr/bin/head";
$BIN_TAIL = "/usr/bin/tail";
$BIN_AWK = "/usr/bin/awk";
if ($OSTYPE == 1) { # linux
	$BIN_GREP = "/bin/grep";
	$BIN_NETSTAT = "/bin/netstat";
} elsif ($OSTYPE == 2) { # bsd
	$BIN_GREP = "/usr/bin/grep";
	$BIN_SOCKSTAT = "/usr/bin/sockstat";
}

#-------------------------------------------------------------------------------
# Main
#-------------------------------------------------------------------------------

# find binaries
if ($autoFindBinaries != 0) { findBinaries() };

# init some vars
$REVISION =
	do { my @r = (q$Revision$ =~ /\d+/g); sprintf "%d"."%02d" x $#r, @r };
($DIR=$0) =~ s/([^\/\\]*)$//;
($PROG=$1) =~ s/\.([^\.]*)$//;
$EXTENSION=$1;

# main-"switch"
SWITCH: {
	$_ = shift @ARGV;
	/^traffic/ && do { # --- traffic ---
		printTraffic(shift @ARGV, shift @ARGV);
		exit;
	};
	/^connections/ && do { # --- connections ---
		printConnections(shift @ARGV);
		exit;
	};
	/^loadavg/ && do { # --- LOAD AVG ---
		printLoadAVG(shift @ARGV);
		exit;
	};
	/.*(version|-v).*/ && do { # --- version ---
		printVersion();
		exit;
	};
	/.*(help|-h).*/ && do { # --- help ---
		printUsage();
		exit;
	};
	printUsage();
	exit;
}

#===============================================================================
# Subs
#===============================================================================

#------------------------------------------------------------------------------#
# Sub: printTraffic                                                            #
# Parameters: string with path of flux-dir                                     #
#             string with wanted output-format (mrtg|cacti)                    #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printTraffic {
	my $fluxDir = shift;
	if (!(defined $fluxDir)) {
		printUsage();
		exit;
	}
	$fluxDir .= "/".$STATFILEDIR;
	my $outputFormat = shift;
	if ($outputFormat eq "mrtg") {
		mrtgPrintTraffic($fluxDir);
	} elsif ($outputFormat eq "cacti") {
		cactiPrintTraffic($fluxDir);
	} else {
		# get traffic-vals
		my @traffic = fluxTraffic($fluxDir);
		# print traffic-vals
		print $traffic[0]." ".$traffic[1]."\n";
	}
}

#------------------------------------------------------------------------------#
# Sub: mrtgPrintTraffic                                                        #
# Parameters: string with path of flux-".stat-files"-dir                       #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub mrtgPrintTraffic {
	my $fluxDir = shift;
	# get traffic-vals
	my @traffic = fluxTraffic($fluxDir);
	# print down-speed for mrtg
	print $traffic[0];
	print "\n";
	# print up-speed for mrtg
	print $traffic[1];
	print "\n";
	# print uptime for mrtg
	mrtgPrintUptime();
	# print target-name for mrtg
	mrtgPrintTargetname();
}

#------------------------------------------------------------------------------#
# Sub: cactiPrintTraffic                                                       #
# Parameters: string with path of flux-".stat-files"-dir                       #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub cactiPrintTraffic {
	my $fluxDir = shift;
	# get traffic-vals
	my @traffic = fluxTraffic($fluxDir);
	# print traffic for cacti
	my $trafficLine = "";
	$trafficLine .= "bandwidth_in:";
	$trafficLine .= $traffic[0];
	$trafficLine .= " ";
	$trafficLine .= "bandwidth_out:";
	$trafficLine .= $traffic[1];
	print $trafficLine;
}

#------------------------------------------------------------------------------#
# Sub: printConnections                                                        #
# Parameters: string with wanted output-format (mrtg|cacti)                    #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printConnections {
	my $outputFormat = shift;
	if ($outputFormat eq "mrtg") {
		mrtgPrintConnections();
	} elsif ($outputFormat eq "cacti") {
		cactiPrintConnections();
	} else {
		print fluxConnections();
		print "\n";
	}
}

#------------------------------------------------------------------------------#
# Sub: mrtgPrintConnections                                                    #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub mrtgPrintConnections {
	# print down-"speed" for mrtg
	print fluxConnections();
	print "\n";
	# print up-"speed" for mrtg
	print "0";
	print "\n";
	# print uptime for mrtg
	mrtgPrintUptime();
	# print target-name for mrtg
	mrtgPrintTargetname();
}

#------------------------------------------------------------------------------#
# Sub: cactiPrintConnections                                                   #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub cactiPrintConnections {
	# print connections for cacti
	print fluxConnections();
}

#------------------------------------------------------------------------------#
# Sub: printLoadAVG                                                            #
# Parameters: string with wanted output-format (mrtg|cacti)                    #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printLoadAVG {
	my $outputFormat = shift;
	if ($outputFormat eq "mrtg") {
		mrtgPrintLoadAVG();
	} elsif ($outputFormat eq "cacti") {
		cactiPrintLoadAVG();
	} else {
		print LoadAVG();
		#print "\n";
	}
}

#------------------------------------------------------------------------------#
# Sub: mrtgPrintLoadAVG                                                        #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub mrtgPrintLoadAVG {
	# print Load AVG. for mrtg
	LoadAVG();
	# print uptime for mrtg
	mrtgPrintUptime();
	# print target-name for mrtg
	mrtgPrintTargetname();
}

#------------------------------------------------------------------------------#
# Sub: cactiPrintLoadAVG                                                       #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub cactiPrintLoadAVG {
	# print Load AVG. for cacti
	LoadAVG();
}

#------------------------------------------------------------------------------#
# Sub: LoadAVG                                                                 #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub LoadAVG {
	# vars
	my ($AVG1min, $AVG5min, $AVG15min);
	#generate LOAD AVG.
	if ($OSTYPE == 1) { # linux
		my $loadAVG = `cat /proc/loadavg`;
		($AVG1min, $AVG5min, $AVG15min) = split /\s/, $loadAVG, 3;
	} elsif ($OSTYPE == 2) { # bsd
		my $loadAVG = `uptime`;
		($AVG1min, $AVG5min, $AVG15min) = $loadAVG=~/.*load averages: (\S+), (\S+), (\S+)/;
	}
	#1m AVG.
	print ($AVG1min * $AVGmultiplier);
	print "\n";
	#5m AVG.
	print ($AVG5min * $AVGmultiplier);
	print "\n";
}

#------------------------------------------------------------------------------#
# Sub: mrtgPrintUptime                                                         #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub mrtgPrintUptime {
	# uptime data for mrtg
	my $uptime = `uptime`;
    $uptime =~ /up (.*?), (.*?), /;
    print "$1, $2\n";
}

#------------------------------------------------------------------------------#
# Sub: mrtgPrintTargetname                                                     #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub mrtgPrintTargetname {
	# target-name for mrtg
	my $targetname = `hostname`;
	print $targetname;
}

#------------------------------------------------------------------------------#
# Sub: fluxTraffic                                                             #
# Parameters:	string with path of flux-".stat-files"-dir                     #
# Return: array with current down-traffic ([0]) and up-traffic ([1])           #
#------------------------------------------------------------------------------#
sub fluxTraffic {
	my $fluxDir = shift;
	# init speed-sum-vars
	my $downspeed = 0.0;
	my $upspeed = 0.0;
	# process stat-files
	opendir(DIR, $fluxDir);
	my @files = map { $_->[1] } # extract pathnames
	map { [ $_, "$fluxDir/$_" ] } # full paths
	grep { !/^\./ } # no dot-files
	grep { /.*\.stat$/ } # only .stat-files
	readdir(DIR);
	closedir(DIR);
	foreach my $statFile (@files) {
		if (-f $statFile) {
			my ($down, $up) = split(/\n/, `$BIN_CAT $statFile | $BIN_HEAD -n 5 | $BIN_TAIL -n 2`, 2);
			if ($down != "") {
				$down =~ s/(.*\d)(\s.*)/$1/;
				chomp $down;
				$downspeed += $down;
			}
			if ($up != "") {
				$up =~ s/(.*\d)(\s.*)/$1/;
				chomp $up;
				$upspeed += $up;
			}
		}
	}
	my @retVal;
	$retVal[0] = ($downspeed<<10);
	$retVal[1] = ($upspeed<<10);
	return @retVal;
}

#------------------------------------------------------------------------------#
# Sub: fluxConnections                                                         #
# Parameters: null                                                             #
# Return: int with current flux-tcp-connections (python + transmission)        #
#------------------------------------------------------------------------------#
sub fluxConnections {
	my $cons = 0;
	my $cons_temp = 0;
	if ($OSTYPE == 1) { # linux
		$cons_temp = `$BIN_NETSTAT -e -p --tcp -n 2> /dev/null | $BIN_GREP -v root | $BIN_GREP -v 127.0.0.1 | $BIN_GREP -cE '.*(python|transmissionc|wget).*'`;
		chomp $cons_temp;
		$cons = int $cons_temp;
	} elsif ($OSTYPE == 2) { # bsd
		foreach my $bin_socket (@BINS_SOCKET) {
			$cons_temp = `$BIN_SOCKSTAT | $BIN_GREP -cE $WEBUSER.+$bin_socket.+tcp`;
			chomp $cons_temp;
			$cons += $cons_temp;
		}
	}
	return $cons;
}

#------------------------------------------------------------------------------#
# Sub: findBinaries                                                            #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub findBinaries {
	$BIN_CAT = `whereis cat | awk '{print \$2}'`; chomp $BIN_CAT;
	$BIN_HEAD = `whereis head | awk '{print \$2}'`; chomp $BIN_HEAD;
	$BIN_TAIL = `whereis tail | awk '{print \$2}'`; chomp $BIN_TAIL;
	$BIN_NETSTAT = `whereis netstat | awk '{print \$2}'`; chomp $BIN_NETSTAT;
	$BIN_SOCKSTAT = `whereis sockstat | awk '{print \$2}'`; chomp $BIN_SOCKSTAT;
	$BIN_GREP = `whereis grep | awk '{print \$2}'`; chomp $BIN_GREP;
	$BIN_AWK = `whereis awk | awk '{print \$2}'`; chomp $BIN_AWK;
}

#------------------------------------------------------------------------------#
# Sub: checkEnv                                                                #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub checkEnv {
	## win32 not supported ;)
	if ("$^O" =~ /win32/i) {
		print "\r\nWin32 not supported.\r\n";
		exit;
	} elsif ("$^O" =~ /linux/i) {
		$OSTYPE = 1;
		return;
	} elsif ("$^O" =~ /bsd$/i) {
		$OSTYPE = 2;
		return;
	}
}

#------------------------------------------------------------------------------#
# Sub: printVersion                                                            #
# Arguments: Null                                                              #
# Returns: Version Information                                                 #
#------------------------------------------------------------------------------#
sub printVersion {
	print $PROG.".".$EXTENSION." Version ".$REVISION."\n";
}

#------------------------------------------------------------------------------#
# Sub: printUsage                                                              #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printUsage {
	print <<"USAGE";
$PROG.$EXTENSION (Revision $REVISION)

Usage: $PROG.$EXTENSION type [extra-args]

types:
<traffic>     : print current flux-traffic.
                extra-args : 1. flux-dir (aka "Path" inside flux-admin)
                             2. (optional) output-format (mrtg|cacti)

<connections> : print current flux-tcp-connections.
                extra-args : 1. (optional) output-format (mrtg|cacti)

<loadavg>     : print current load-average.
                extra-args : 1. (optional) output-format (mrtg|cacti)

Examples:

$PROG.$EXTENSION traffic /usr/local/torrentflux
$PROG.$EXTENSION traffic /usr/local/torrentflux mrtg
$PROG.$EXTENSION traffic /usr/local/torrentflux cacti

$PROG.$EXTENSION connections
$PROG.$EXTENSION connections mrtg
$PROG.$EXTENSION connections cacti

$PROG.$EXTENSION loadavg
$PROG.$EXTENSION loadavg mrtg
$PROG.$EXTENSION loadavg cacti

USAGE

}

# EOF
