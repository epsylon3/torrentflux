#!/usr/bin/perl
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
# check.pl is a simple script to check Perl-Module-Requirements.               #
#                                                                              #
################################################################################
use strict;
################################################################################

# Internal Vars
my ($VERSION, $DIR, $PROG, $EXTENSION, $USAGE);

#-------------------------------------------------------------------------------
# Main
#-------------------------------------------------------------------------------

# init some vars
$VERSION =
	do { my @r = (q$Revision$ =~ /\d+/g); sprintf "%d"."%02d" x $#r, @r };
($DIR=$0) =~ s/([^\/\\]*)$//;
($PROG=$1) =~ s/\.([^\.]*)$//;
$EXTENSION=$1;

# check args
my $argCount = scalar(@ARGV);
if ($argCount != 1) {
	printUsage();
	exit;
}

# ops
if ($argCount == 1) {
	SWITCH: {
		$_ = shift @ARGV;
		/all/ && do { # --- all ---
			checkAll();
			exit;
		};
		/fluxd/ && do { # --- fluxd ---
			checkFluxd();
			exit;
		};
		/nzbperl/ && do { # --- nzbperl ---
			checkNzbperl();
			exit;
		};
		/ttools/ && do { # --- ttools ---
			checkTtools();
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
}

# exit
exit;

#===============================================================================
# Subs
#===============================================================================

#------------------------------------------------------------------------------#
# Sub: checkAll                                                                #
# Arguments: Null                                                              #
# Returns: info on system requirements                                         #
#------------------------------------------------------------------------------#
sub checkAll {
	# print
	print "checking all requirements...\n";
	# 1. fluxd
	checkFluxd();
	# 2. nzbperl
	checkNzbperl();
	# 3. ttools
	checkTtools();
	# done
	print "done checking all requirements.\n";
}

#------------------------------------------------------------------------------#
# Sub: checkFluxd                                                              #
# Arguments: Null                                                              #
# Returns: info on system requirements                                         #
#------------------------------------------------------------------------------#
sub checkFluxd {
	# print
	print "checking fluxd requirements...\n";

	my $errors = 0;
	my $warnings = 0;
	my @errorMessages = ();
	my @warningMessages = ();

	# 1. CORE-Perl-modules
	print "1. CORE-Perl-modules\n";
	my @mods = (
		'IO::Select',
		'IO::Socket::UNIX',
		'IO::Socket::INET',
		'POSIX'
	);
	foreach my $mod (@mods) {
		if (eval "require $mod")  {
			print "   - OK : ".$mod."\n";
			next;
		} else {
			$errors++;
			push(@errorMessages, "Loading of CORE-Perl-module ".$mod." failed.\n");
			print "   - FAILED : ".$mod."\n";
		}
	}

	# 2. FluxDB-Perl-modules
	print "2. Database-Perl-modules\n";
	if (eval "require DBI")  {
		print "   - OK : DBI\n";
	} else {
		$warnings++;
		push(@warningMessages, "Loading of FluxDB-Perl-module DBI failed. fluxd cannot work in DBI/DBD-mode but only in PHP-mode.\n");
		print "   - FAILED : DBI\n";
	}
	my $dbdwarnings = 0;
	@mods = (
		'DBD::mysql',
		'DBD::SQLite',
		'DBD::Pg'
	);
	foreach my $mod (@mods) {
		if (eval "require $mod")  {
			print "   - OK : ".$mod."\n";
			next;
		} else {
			$dbdwarnings++;
			print "   - FAILED : ".$mod."\n";
		}
	}
	if ($dbdwarnings == 3) {
		$warnings++;
		push(@warningMessages, "No DBD-Module could be loaded. fluxd cannot work in DBI/DBD-mode but only in PHP-mode.\n");
	}

	# 3. Result
	print "3. Result : ".(($errors == 0) ? "PASSED" : "FAILED")."\n";
	# failures
	if ($errors > 0) {
		print "Errors:\n";
		foreach my $msg (@errorMessages) {
			print $msg;
		}
	}
	# warnings
	if ($warnings > 0) {
		print "Warnings:\n";
		foreach my $msg (@warningMessages) {
			print $msg;
		}
	}

	# done
	print "done checking fluxd requirements.\n";
}

#------------------------------------------------------------------------------#
# Sub: checkNzbperl                                                            #
# Arguments: Null                                                              #
# Returns: info on system requirements                                         #
#------------------------------------------------------------------------------#
sub checkNzbperl {
	# print
	print "checking nzbperl requirements...\n";

	my $errors = 0;
	my $warnings = 0;
	my @errorMessages = ();
	my @warningMessages = ();

	# 1. CORE-Perl-modules
	print "1. CORE-Perl-modules\n";
	my @mods = (
		'IO::File',
		'IO::Select',
		'IO::Socket::INET',
		'File::Basename',
		'Getopt::Long',
		'Time::HiRes',
		'Cwd',
		'XML::Simple',
		'XML::DOM'
	);
	foreach my $mod (@mods) {
		if (eval "require $mod")  {
			print "   - OK : ".$mod."\n";
			next;
		} else {
			$errors++;
			push(@errorMessages, "Loading of CORE-Perl-module ".$mod." failed.\n");
			print "   - FAILED : ".$mod."\n";
		}
	}

	# 2. Perl-Threads
	my $threadproblems = 0;
	print "2. Perl-Threads\n";
	eval "use threads;";
	if ($@) {
		$warnings++;
		print "   - FAILED : threads\n";
		$threadproblems++;
	} else {
		print "   - OK : threads\n";
	}
	@mods = ('Thread::Queue');
	foreach my $mod (@mods) {
		if (eval "require $mod")  {
			print "   - OK : ".$mod."\n";
			next;
		} else {
			$warnings++;
			$threadproblems++;
			print "   - FAILED : ".$mod."\n";
		}
	}
	if ($threadproblems != 0) {
		$warnings++;
		push(@warningMessages, "Could not use Perl thread modules.\n");
	}

	# 3. Result
	print "3. Result : ".(($errors == 0) ? "PASSED" : "FAILED")."\n";
	# failures
	if ($errors > 0) {
		print "Errors:\n";
		foreach my $msg (@errorMessages) {
			print $msg;
		}
	}
	# warnings
	if ($warnings > 0) {
		print "Warnings:\n";
		foreach my $msg (@warningMessages) {
			print $msg;
		}
	}

	# done
	print "done checking nzbperl requirements.\n";
}

#------------------------------------------------------------------------------#
# Sub: checkTtools                                                             #
# Arguments: Null                                                              #
# Returns: info on system requirements                                         #
#------------------------------------------------------------------------------#
sub checkTtools {
	# print
	print "checking ttools requirements...\n";

	my $errors = 0;
	my $warnings = 0;
	my @errorMessages = ();
	my @warningMessages = ();

	# 1. CORE-Perl-modules
	print "1. CORE-Perl-modules\n";
	my @mods = ('Digest::SHA1', 'LWP::UserAgent');
	foreach my $mod (@mods) {
		if (eval "require $mod")  {
			print "   - OK : ".$mod."\n";
			next;
		} else {
			$errors++;
			push(@errorMessages, "Loading of CORE-Perl-module ".$mod." failed.\n");
			print "   - FAILED : ".$mod."\n";
		}
	}

	# 2. Result
	print "2. Result : ".(($errors == 0) ? "PASSED" : "FAILED")."\n";
	# failures
	if ($errors > 0) {
		print "Errors:\n";
		foreach my $msg (@errorMessages) {
			print $msg;
		}
	}
	# warnings
	if ($warnings > 0) {
		print "Warnings:\n";
		foreach my $msg (@warningMessages) {
			print $msg;
		}
	}

	# done
	print "done checking ttools requirements.\n";
}

#------------------------------------------------------------------------------#
# Sub: printVersion                                                            #
# Arguments: Null                                                              #
# Returns: Null                                                                #
#------------------------------------------------------------------------------#
sub printVersion {
	print $PROG.".".$EXTENSION." Version ".$VERSION."\n";
}

#------------------------------------------------------------------------------#
# Sub: printUsage                                                              #
# Parameters: null                                                             #
# Return: null                                                                 #
#------------------------------------------------------------------------------#
sub printUsage {
	print <<"USAGE";
$PROG.$EXTENSION (Revision $VERSION)

Usage: $PROG.$EXTENSION type
       type may be : all/fluxd/nzbperl/ttools

Examples:
$PROG.$EXTENSION fluxd
$PROG.$EXTENSION all

USAGE

}

# EOF
