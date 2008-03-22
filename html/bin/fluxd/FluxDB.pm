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
#  Requirements for dbi-Mode :                                                 #
#   * DBI                          ( perl -MCPAN -e "install Bundle::DBI" )    #
#   * DBD::mysql  for MySQL        ( perl -MCPAN -e "install DBD::mysql"  )    #
#   * DBD::SQLite for SQLite       ( perl -MCPAN -e "install DBD::SQLite" )    #
#   * DBD::Pg     for PostgreSQL   ( perl -MCPAN -e "install DBD::Pg"     )    #
#                                                                              #
################################################################################
package FluxDB;
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

# operation-mode : dbi / php
my $mode = "dbi";

# docroot
my $docroot = "/var/www";

# load conf : 0|1
my $loadConf = 1;

# flux-config-hash
my %fluxConf;

# flux-config-test-keys
my @fluxConfTestKeys = ('fluxd_dbmode', 'fluxd_loglevel');

# load users : 0|1
my $loadUsers = 0;

# usernames
my %names;

# users
my @users;

# database-conf-file
my $dbConfig = "config.db.php";

# database-handle
my $dbHandle = undef;

# database-settings
my $dbType = "";
my $dbName = "";
my $dbHost = "";
my $dbPort = 0;
my $dbUser = "";
my $dbPass = "";
my $dbDSN = "";

# php
my $php = "/usr/bin/php";

# fluxcli
my $fluxcli = "bin/fluxcli.php";

################################################################################
# constructor + destructor                                                     #
################################################################################

#------------------------------------------------------------------------------#
# Sub: new                                                                     #
# Arguments: null                                                              #
# Returns: object reference                                                    #
#------------------------------------------------------------------------------#
sub new {
	my $class = shift;
	my $self = bless ({}, ref ($class) || $class);
	return $self;
}

#------------------------------------------------------------------------------#
# Sub: destroy                                                                 #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub destroy {

	# log
	Fluxd::printMessage("FluxDB", "shutdown\n");

	# set state
	$state = Fluxd::MOD_STATE_NULL;

	# close connection
	dbDisconnect();

	# undef
	undef $dbHandle;
	undef %fluxConf;
	undef @users;
	undef %names;
}

################################################################################
# public methods                                                               #
################################################################################

#------------------------------------------------------------------------------#
# Sub: initialize. this is separated from constructor to call it independent   #
#      from object-creation.                                                   #
# Arguments: path-to-docroot, path-to-php, mode                                #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub initialize {

	shift; # class

	# path-docroot
	$docroot = shift;
	if (!(defined $docroot)) {
		# message
		$message = "path-to-docroot not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# path-php
	$php = shift;
	if (!(defined $php)) {
		# message
		$message = "path-to-php not defined";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	if (!(-x $php)) {
		# message
		$message = "cant execute php (".$php.")";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# db-mode
	my $_dbmode = shift;

	# print
	Fluxd::printMessage("FluxDB", "initializing (mode: ".$_dbmode." ; docroot: ".$docroot." ; php: ".$php.")\n");

	# db-mode
	SWITCH: {
		$_ = $_dbmode;
		# dbi
		/^dbi/ && do {
			$mode = $_;

			# load DBI
			if (eval "require DBI")  {
				DBI->import();
			} else {
				# message
				$message = "cant load DBI-module : ".$@;
				# set state
				$state = Fluxd::MOD_STATE_ERROR;
				# return
				return 0;
			}

			# db-config
			$dbConfig = $docroot."inc/config/".$dbConfig;
			if (!(-f $dbConfig)) {
				# message
				$message = "db-config no file (".$dbConfig.")";
				# set state
				$state = Fluxd::MOD_STATE_ERROR;
				# return
				return 0;
			}

			# load Database-Config
			if (loadDatabaseConfig($dbConfig) == 0) {
				# return
				return 0;
			}

			# connect
			if (dbConnect() == 0) {
				# close connection
				dbDisconnect();
				# return
				return 0;
			}

			# load config
			if ($loadConf == 1) {
				if (loadFluxConfigDBI() == 0) {
					# close connection
					dbDisconnect();
					# return
					return 0;
				}
			}

			# load users
			if ($loadUsers == 1) {
				if (loadFluxUsersDBI() == 0) {
					# close connection
					dbDisconnect();
					# return
					return 0;
				}
			}

			# close connection
			dbDisconnect();

			# done
			last SWITCH;
		};
		# php
		/^php/ && do {
			$mode = $_;

			# fluxcli
			if (!(-f $docroot.$fluxcli)) {
				# message
				$message = "fluxcli missing (".$docroot.$fluxcli.")";
				# set state
				$state = Fluxd::MOD_STATE_ERROR;
				# return
				return 0;
			}

			# load config
			if ($loadConf == 1) {
				if (loadFluxConfigPHP() == 0) {
					# return
					return 0;
				}
			}

			# load users
			if ($loadUsers == 1) {
				if (loadFluxUsersPHP() == 0) {
					# return
					return 0;
				}
			}

			# done
			last SWITCH;
		};
		# no valid mode. bail out
		# message
		$message = "no valid mode";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}

	# loglevel
	$loglevel = $fluxConf{"fluxd_loglevel"};

	# print
	Fluxd::printMessage("FluxDB", "data loaded and cached, FluxDB ready.\n");

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
# Sub: getMode                                                                 #
# Arguments: null                                                              #
# Returns: mode                                                                #
#------------------------------------------------------------------------------#
sub getMode {
	return $mode;
}

#------------------------------------------------------------------------------#
# Sub: getDatabaseType                                                         #
# Arguments: null                                                              #
# Returns: Database-Type                                                       #
#------------------------------------------------------------------------------#
sub getDatabaseType {
	return $dbType;
}

#------------------------------------------------------------------------------#
# Sub: getDatabaseName                                                         #
# Arguments: null                                                              #
# Returns: Database-Name                                                       #
#------------------------------------------------------------------------------#
sub getDatabaseName {
	return $dbName;
}

#------------------------------------------------------------------------------#
# Sub: getDatabaseHost                                                         #
# Arguments: null                                                              #
# Returns: Database-Host                                                       #
#------------------------------------------------------------------------------#
sub getDatabaseHost {
	return $dbHost;
}

#------------------------------------------------------------------------------#
# Sub: getDatabasePort                                                         #
# Arguments: null                                                              #
# Returns: Database-Port                                                       #
#------------------------------------------------------------------------------#
sub getDatabasePort {
	return $dbPort;
}

#------------------------------------------------------------------------------#
# Sub: getDatabaseUser                                                         #
# Arguments: null                                                              #
# Returns: Database-User                                                       #
#------------------------------------------------------------------------------#
sub getDatabaseUser {
	return $dbUser;
}

#------------------------------------------------------------------------------#
# Sub: getDatabasePassword                                                     #
# Arguments: null                                                              #
# Returns: Database-Password                                                   #
#------------------------------------------------------------------------------#
sub getDatabasePassword {
	return $dbPass;
}

#------------------------------------------------------------------------------#
# Sub: getDatabaseDSN                                                          #
# Arguments: null                                                              #
# Returns: Database-DSN                                                        #
#------------------------------------------------------------------------------#
sub getDatabaseDSN {
	return $dbDSN;
}

#------------------------------------------------------------------------------#
# Sub: getFluxConfig                                                           #
# Arguments: key                                                               #
# Returns: conf-value                                                          #
#------------------------------------------------------------------------------#
sub getFluxConfig {
	shift; # class
	my $key = shift;
	return (exists $fluxConf{$key}) ? $fluxConf{$key} : "";
}

#------------------------------------------------------------------------------#
# Sub: setFluxConfig                                                           #
# Arguments: key,value                                                         #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub setFluxConfig {
	shift; # class
	my $key = shift;
	$fluxConf{$key} = shift;
}

#------------------------------------------------------------------------------#
# Sub: getFluxUsernames                                                        #
# Arguments: null                                                              #
# Returns: hash                                                                #
#------------------------------------------------------------------------------#
sub getFluxUsernames {
	return %names;
}

#------------------------------------------------------------------------------#
# Sub: getFluxUsers                                                            #
# Arguments: null                                                              #
# Returns: array                                                               #
#------------------------------------------------------------------------------#
sub getFluxUsers {
	return @users;
}

#------------------------------------------------------------------------------#
# Sub: reload                                                                  #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub reload {

	# print
	if ($loglevel > 0) {
		Fluxd::printMessage("FluxDB", "reloading DB-Cache...\n");
	}

	SWITCH: {
		$_ = $mode;
		# dbi
		/^dbi/ && do {

			# connect
			if (dbConnect() == 0) {
				# close connection
				dbDisconnect();
				# return
				return 0;
			}

			# load config
			if ($loadConf == 1) {
				if (loadFluxConfigDBI() == 0) {
					# close connection
					dbDisconnect();
					# return
					return 0;
				}
			}

			# load users
			if ($loadUsers == 1) {
				if (loadFluxUsersDBI() == 0) {
					# close connection
					dbDisconnect();
					# return
					return 0;
				}
			}

			# close connection
			dbDisconnect();

		};
		# php
		/^php/ && do {

			# load config
			if ($loadConf == 1) {
				if (loadFluxConfigPHP() == 0) {
					# return
					return 0;
				}
			}

			# load users
			if ($loadUsers == 1) {
				if (loadFluxUsersPHP() == 0) {
					# return
					return 0;
				}
			}

		};
	}

	# print
	if ($loglevel > 0) {
		Fluxd::printMessage("FluxDB", "done reloading DB-Cache.\n");
	}

	# done
	return 1;
}

################################################################################
# private methods                                                              #
################################################################################

#------------------------------------------------------------------------------#
# Sub: loadDatabaseConfig                                                      #
# Arguments: db-config-file (config.db.php)                                    #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadDatabaseConfig {
	my $file = shift;
	open(CONFIG, $file) || return 0;
	my $lineSep = $/;
	undef $/;
	while (<CONFIG>) {
		if (/db_type.*[^\[]\"(.*)\"[^\]]/) {
			$dbType = $1;
		}
		if (/db_host.*[^\[]\"(.*)\"[^\]]/) {
			$dbHost = $1;
		}
		if (/db_name.*[^\[]\"(.*)\"[^\]]/) {
			$dbName = $1;
		}
		if (/db_user.*[^\[]\"(.*)\"[^\]]/) {
			$dbUser = $1;
		}
		if (/db_pass.*[^\[]\"(.*)\"[^\]]/) {
			$dbPass = $1;
		}
	}
	$/ = $lineSep;
	close(CONFIG);

	# build dsn
	$dbDSN = "DBI:";
	SWITCH: {
		$_ = $dbType;

		# MySQL
		/^mysql/i && do {
			$dbDSN .= "mysql:".$dbName.":".$dbHost;
			if ($dbPort > 0) {
				$dbDSN .= $dbPort;
			}
			last SWITCH;
		};

		# SQLite
		/^sqlite/i && do {
			$dbDSN .= "SQLite:dbname=".$dbHost;
			$dbUser = "";
			$dbPass = "";
			last SWITCH;
		};

		# PostgreSQL
		/^postgres/i && do {
			$dbDSN .= "Pg:dbname=".$dbName;
			if ($dbPort > 0) {
				$dbDSN .= ";port=".$dbPort;
			}
			last SWITCH;
		};

		# no valid db-type. bail out
		# message
		$message = "no valid db-type : ".$dbType;
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: dbConnect                                                               #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub dbConnect {

	# connect
	$dbHandle = DBI->connect(
		$dbDSN, $dbUser, $dbPass, { PrintError => 0, AutoCommit => 1 }
	);

	# check
	if (!(defined $dbHandle)) {
		# message
		$message = "error connecting to database :\n".$DBI::errstr;
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: dbDisconnect                                                            #
# Arguments: null                                                              #
# Returns: null                                                                #
#------------------------------------------------------------------------------#
sub dbDisconnect {
	# disconnect
	if (defined $dbHandle) {
		$dbHandle->disconnect();
		undef $dbHandle;
	}
}

#------------------------------------------------------------------------------#
# Sub: checkFluxConfig                                                         #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub checkFluxConfig {
	foreach my $fluxConfTestKey (@fluxConfTestKeys) {
		if (!(exists $fluxConf{$fluxConfTestKey})) {
			# message
			$message = "checkFluxConfig failed. config does not exist : ".$fluxConfTestKey;
			# set state
			$state = Fluxd::MOD_STATE_ERROR;
			# return
			return 0;
		}
		if (!(defined $fluxConf{$fluxConfTestKey})) {
			# message
			$message = "checkFluxConfig failed. config not defined : ".$fluxConfTestKey;
			# set state
			$state = Fluxd::MOD_STATE_ERROR;
			# return
			return 0;
		}
	}
	# return
	return 1;
}

#------------------------------------------------------------------------------#
# Sub: checkFluxUsers                                                          #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub checkFluxUsers{
	if (scalar(@users) > 0) {
		# return
		return 1;
	} else {
		# message
		$message = "checkFluxUsers failed.";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: loadFluxConfigDBI                                                       #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadFluxConfigDBI {
	if (defined $dbHandle) {

		# flush first
		%fluxConf = ();

		# load from db
		my $sth = $dbHandle->prepare(q{ SELECT tf_key, tf_value FROM tf_settings });
		$sth->execute();
		my ($tfKey, $tfValue);
		my $rv = $sth->bind_columns(undef, \$tfKey, \$tfValue);
		while ($sth->fetch()) {
			$fluxConf{$tfKey} = $tfValue;
		}
		$sth->finish();

		# check
		return checkFluxConfig();
	} else {
		# message
		$message = "cannot load flux-config from database (dbi)";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: loadFluxUsersDBI                                                        #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadFluxUsersDBI {
	if (defined $dbHandle) {

		# flush first
		@users = ();
		%names = ();

		# load from db
		my $sth = $dbHandle->prepare(q{ SELECT uid, user_id FROM tf_users });
		$sth->execute();
		my ($uid, $userid);
		my $rv = $sth->bind_columns(undef, \$uid, \$userid);
		my $index = 0;
		while ($sth->fetch()) {
			$users[$index] = {
				uid => $uid,
				username => $userid
			};
			$names{$userid} = $index;
			$index++;
		}
		$sth->finish();

		# check
		return checkFluxUsers();
	} else {
		# message
		$message = "cannot load flux-users from database (dbi)";
		# set state
		$state = Fluxd::MOD_STATE_ERROR;
		# return
		return 0;
	}
}

#------------------------------------------------------------------------------#
# Sub: loadFluxConfigPHP                                                       #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadFluxConfigPHP {

	# flush first
	%fluxConf = ();

	# dump and init
	my $shellCmd = $php." ".$fluxcli." dump settings";
	my ($tfKey, $tfValue);
	open(CALL, $shellCmd." |");
	while(<CALL>) {
		chomp;
		($tfKey, $tfValue) = split(/\*/, $_);
		$fluxConf{$tfKey} = $tfValue;
	}
	close(CALL);

	# check
	return checkFluxConfig();
}

#------------------------------------------------------------------------------#
# Sub: loadFluxUsersPHP                                                        #
# Arguments: null                                                              #
# Returns: 0|1                                                                 #
#------------------------------------------------------------------------------#
sub loadFluxUsersPHP {

	# flush first
	@users = ();
	%names = ();

	# dump and init
	my $shellCmd = $php." ".$fluxcli." dump users";
	my ($uid, $userid);
	my $index = 0;
	open(CALL, $shellCmd." |");
	while(<CALL>) {
		chomp;
		($uid, $userid) = split(/\*/, $_);
		$users[$index] = {
			uid => $uid,
			username => $userid
		};
		$names{$userid} = $index;
		$index++;
	}
	close(CALL);

	# check
	return checkFluxUsers();
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
