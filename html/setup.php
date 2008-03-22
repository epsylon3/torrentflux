<?php

/* $Id$ */

/*******************************************************************************

 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html

*******************************************************************************/

// version
if (is_file('version.php'))
    require_once('version.php');
else
    die("Fatal Error. version.php is missing.");

// install-functions
if ((@is_file('inc/install/functions.install.php')) === true)
	require_once('inc/install/functions.install.php');
else
	die("Fatal Error. inc/install/functions.install.php is missing.");

// defines
define('_NAME', 'torrentflux-b4rt');
define('_TITLE', _NAME.' '._VERSION.' - Setup');
define('_DIR', dirname($_SERVER["SCRIPT_FILENAME"])."/");
define('_FILE_DBCONF', 'inc/config/config.db.php');
define('_FILE_THIS', $_SERVER['SCRIPT_NAME']);
define('_FORUM_URL', "http://tf-b4rt.berlios.de/forum/");

// Database-Types
$databaseTypes = array();
$databaseTypes['MySQL'] = 'mysql_connect';
$databaseTypes['SQLite'] = 'sqlite_open';
$databaseTypes['Postgres'] = 'pg_connect';

// generic msg about db config missing:
$msgDbConfigMissing = 'Database configuration file <em>'._DIR._FILE_DBCONF.'</em> missing. ';
$msgDbConfigMissing .= 'Setup cannot continue.  Please check the file exists and is readable by the webserver before continuing.';

// init queries
initQueries("install");

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

// ob-start
if (@ob_get_level() == 0)
	@ob_start();

if (isset($_REQUEST["1"])) {                                                    // 1 - Database
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database</h2>");
	send("<p>In this section you will choose the type of database you wish to use with "._NAME.".  You will then be prompted to provide the hostname, database name, username and password that "._NAME." will use to store information.</p>");
	send("<p>Finally "._NAME." will run some tests to check everything works OK and write the database and server configuration.</p>");
	send("<p>For more information and support with this installation, please feel free to visit <a href='"._FORUM_URL."'>the "._NAME." forum</a>.</p><br/>");
	send("<br/>");
	sendButton(11);
} elseif (isset($_REQUEST["11"])) {                                             // 11 - Database - type
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Select Type of Database</h2>");
	send("<p>Please select the type of database you wish to use with your "._NAME." installation below:</p>");
	send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
	send('<select name="db_type">');
	foreach ($databaseTypes as $databaseTypeName => $databaseTypeFunction) {
		$option = '<option value="'.$databaseTypeName.'"';
		if ((isset($_REQUEST["db_type"])) && ($_REQUEST["db_type"] == $databaseTypeName))
			$option .= ' selected';
		$option .= '>'.$databaseTypeName.'</option>';
		$option .= '</option>';
		send($option);
	}
	send('</select>');
	send('<p><strong>Note:</strong> if you do not see the type of database you wish to use, please visit <a href="'._FORUM_URL.'">the '._NAME.' forum</a> to find out about getting your database type added to '._NAME.'.</p>');
	send('<input type="Hidden" name="12" value="">');
	send('<input type="submit" value="Continue">');
	send('</form></p>');
} elseif (isset($_REQUEST["12"])) {                                             // 12 - Database - type check
	if ((isset($_REQUEST["db_type"])) && ($databaseTypes[$_REQUEST["db_type"]] != "")) {
		$type = $_REQUEST["db_type"];
		sendHead(" - Database");
		send("<h1>"._TITLE."</h1>");
		send("<h2>Database - Type Check</h2>");

		if (function_exists($databaseTypes[$type])) {
			$msg = "Your PHP installation supports ".$type;
			displaySetupMessage($msg, true);

			send("<br/>");
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="db_type" value="'.$type.'">');
			send('<input type="Hidden" name="13" value="">');
			send('<input type="submit" value="Continue">');
			send('</form>');
		} else {
			$err='Your PHP installation does not have support for '.$type.' built into it. Please reinstall PHP and ensure support for your database is built in.</p>';
			displaySetupMessage($err, false);

			send("<br/>");
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="11" value="">');
			send('<input type="submit" value="Back">');
			send('</form>');
		}
	} else {
		@header("location: setup.php?11");
		exit();
	}
} elseif (isset($_REQUEST["13"])) {                                             // 13 - Database - config
	$type = $_REQUEST["db_type"];
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Config - ".$type."</h2>");
	send("<p>The installation will now configure and test your database settings.</p><br/>");
	send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
	send('<table border="0">');

	// settings
	send('<tr><td colspan="2"><strong>Database Settings</strong></td></tr>');
	send('<tr><td colspan="2">Please enter your '.$type.' database settings below:</td></tr>');

	switch (strtolower($type)) {
		case "sqlite":
			// file
			$line = '<tr><td>Database-File : </td>';
			$line .= '<td><input name="db_host" type="Text" maxlength="254" size="40" value="';
			if (isset($_REQUEST["db_host"]))
				$line .= $_REQUEST["db_host"];
			$line .= '"></td></tr>';
			send($line);
			break;

		// MySQL and PostgreSQL have same data reqs, make it default case:
		case "mysql":
		case "postgres":
		default:
			// host
			$line = '<tr><td>Host : </td>';
			$line .= '<td><input name="db_host" type="Text" maxlength="254" size="40" value="';
			if (isset($_REQUEST["db_host"]))
				$line .= $_REQUEST["db_host"];
			else
				$line .= 'localhost';
			$line .= '"></td></tr>';
			send($line);
			// name
			$line = '<tr><td>Name : </td>';
			$line .= '<td><input name="db_name" type="Text" maxlength="254" size="40" value="';
			if (isset($_REQUEST["db_name"]))
				$line .= $_REQUEST["db_name"];
			else
				$line .= "torrentfluxb4rt";
			$line .= '"></td></tr>';
			send($line);
			// user
			$line = '<tr><td>Username : </td>';
			$line .= '<td><input name="db_user" type="Text" maxlength="254" size="40" value="';
			if (isset($_REQUEST["db_user"]))
				$line .= $_REQUEST["db_user"];
			else
				$line .= "torrentfluxb4rt";
			$line .= '"></td></tr>';
			send($line);
			// pass
			$line = '<tr><td>Password : </td>';
			$line .= '<td><input name="db_pass" type="Password" maxlength="254" size="40"';
			if (isset($_REQUEST["db_pass"]))
				$line .= ' value="'.$_REQUEST["db_pass"].'">';
			else
				$line .= '>';
			$line .= '</td></tr>';
			send($line);
			//
			break;
	}

	// create
	$line = '<tr><td>Create Database:</td>';
	$line .= '<td><input name="db_create" type="Checkbox" value="true" checked> <strong>Note:</strong> the next step will fail if the database already exists.';
	$line .= '</td></tr>';
	send($line);

	// pcon
	$line = '<tr><td>Use Persistent Connection:';
	$line .= '<td><input name="db_pcon" type="Checkbox" value="true"';
	if (isset($_REQUEST["db_pcon"]))
		$line .= ' checked">';
	else
		$line .= '>';
	$line .= ' <strong>Note:</strong> enabling persistent connections may help reduce the load on your database.</td></tr>';
	send($line);
	send('</table>');
	send("<br/>");
	send('<input type="Hidden" name="db_type" value="'.$type.'">');
	send('<input type="Hidden" name="14" value="">');
	send('<input type="submit" value="Continue">');
	send('</form>');
} elseif (isset($_REQUEST["14"])) {                                             // 14 - Database - creation + test
	$type = $_REQUEST["db_type"];
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Creation + Test - ".$type."</h2>");

	$paramsOk = true;
	if (isset($_REQUEST["db_host"]))
		$host = $_REQUEST["db_host"];
	else
		$paramsOk = false;
	if (isset($_REQUEST["db_create"]))
		$create = true;
	else
		$create = false;
	if (isset($_REQUEST["db_pcon"]))
		$pcon = "true";
	else
		$pcon = "false";

	switch (strtolower($type)) {
		case "sqlite":
			$name = "";
			$user = "";
			$pass = "";
			break;
		case "mysql":
		case "postgres":
		default:
			if (!empty($_REQUEST["db_name"]))
				$name = stripslashes($_REQUEST["db_name"]);
			else
				$paramsOk = false;

			if (!empty($_REQUEST["db_user"]))
				$user = $_REQUEST["db_user"];
			else
				$paramsOk = false;

			if (!empty($_REQUEST["db_pass"]))
				$pass = $_REQUEST["db_pass"];
			else
				$paramsOk = false;

	}

	$databaseTestOk = false;
	$databaseError = "";

	// create + test
	if ($paramsOk) {
		send("<p>The installation will now try to connect to the database server, create a new database if applicable and run some tests to check we can create tables in the database.</p>");

		$databaseExists = true;
		if (($create) && (strtolower($type) != "sqlite")) {
			$dbCon = getAdoConnection($type, $host, $user, $pass);
			if (!$dbCon) {
				$databaseExists = false;
				$databaseTestOk = false;
				$databaseError = "Cannot connect to database.  Check username, hostname and password?";
			} else {
				$sqlState = "CREATE DATABASE ";
				if ($type == "mysql")
					$sqlState .= "`".$name."`";
				else
					$sqlState .= $name;
                $dbCon->Execute($sqlState);

				send('<ul>');
				if ($dbCon->ErrorNo() == 0) {
					send('<li/><font color="green">Ok:</font> Created database <em>'.$name.'</em>');
					$databaseExists = true;
				} else { // damn there was an error
					send('<li/><font color="red">Error:</font> Could not create database <em>'.$name.'</em>');
					$databaseExists = false;
					$databaseTestOk = false;
					$databaseError = "Check the database <strong>$name</strong> does not exist already to perform this step.";
				}
				send('</ul>');

				// close ado-connection
				$dbCon->Close();
			}
			unset($dbCon);
		}

		if ($databaseExists) {
			$dbCon = getAdoConnection($type, $host, $user, $pass, $name);
			if (!$dbCon) {
				$databaseTestOk = false;
				$databaseError = "Cannot connect to database to perform query tests.";
			} else {
				$databaseTestCount = 0;

				send('<ul>');
				foreach ($queries['test'][strtolower($type)] as $databaseTypeName => $databaseQuery) {
					send('<li/>');
					$dbCon->Execute($databaseQuery);
					if ($dbCon->ErrorNo() == 0) {
						send('<font color="green">Query Ok:</font> '.$databaseQuery);
						$databaseTestCount++;
					} else { // damn there was an error
						send('<font color="red">Query Error:</font> '.$databaseQuery);
						// close ado-connection
						$dbCon->Close();
						break;
					}
				}
				if ($databaseTestCount == count($queries['test'][strtolower($type)])) {
					// close ado-connection
					$dbCon->Close();
					$databaseTestOk = true;
				} else {
					$databaseTestOk = false;
				}
				send('</ul>');
			}
		}
	} else {
		$databaseTestOk = false;
		$databaseError = "Problem found in configuration details supplied - please supply hostname, database name, username and password to continue.";
	}

	// output
	if ($databaseTestOk) {
		$msg = "Database creation and tests succeeded";
		displaySetupMessage($msg, true);

		send("<br/>");
		send("<h2>Next: Write Database Configuration File</h2>");
		send("Please ensure this script can write to the directory <em>"._DIR."inc/config/</em> before continuing.<p>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="db_type" value="'.$type.'">');
		send('<input type="Hidden" name="db_host" value="'.$host.'">');
		send('<input type="Hidden" name="db_name" value="'.$name.'">');
		send('<input type="Hidden" name="db_user" value="'.$user.'">');
		send('<input type="Hidden" name="db_pass" value="'.$pass.'">');
		send('<input type="Hidden" name="db_pcon" value="'.$pcon.'">');
		send('<input type="Hidden" name="15" value="">');
		send('<input type="submit" value="Continue">');
	} else {
		displaySetupMessage($databaseError, false);

		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="db_type" value="'.$type.'">');
		send('<input type="Hidden" name="13" value="">');
		if (isset($_REQUEST["db_name"]))
			send('<input type="Hidden" name="db_host" value="'.$_REQUEST["db_host"].'">');
		if (isset($_REQUEST["db_name"]))
			send('<input type="Hidden" name="db_name" value="'.$_REQUEST["db_name"].'">');
		if (isset($_REQUEST["db_user"]))
			send('<input type="Hidden" name="db_user" value="'.$_REQUEST["db_user"].'">');
		if (isset($_REQUEST["db_pass"]))
			send('<input type="Hidden" name="db_pass" value="'.$_REQUEST["db_pass"].'">');
		if (isset($_REQUEST["db_pcon"]))
			send('<input type="Hidden" name="db_pcon" value="'.$_REQUEST["db_pcon"].'">');
		if (isset($_REQUEST["db_create"]))
			send('<input type="Hidden" name="db_create" value="'.$_REQUEST["db_create"].'">');
		send('<input type="submit" value="Back">');
	}
	send('</form>');
} elseif (isset($_REQUEST["15"])) {                                             // 15 - Database - config-file
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Config-File</h2>");
	send("<p>The installation will now attempt to write the database configuration file to "._DIR._FILE_DBCONF.".</p>");
	$type = $_REQUEST["db_type"];
	$host = $_REQUEST["db_host"];
	$name = $_REQUEST["db_name"];
	$user = $_REQUEST["db_user"];
	$pass = $_REQUEST["db_pass"];
	$pcon = $_REQUEST["db_pcon"];

	// write file
	$databaseConfWriteOk = false;
	$databaseConfWriteError = "";
	$databaseConfContent = "";
	writeDatabaseConfig($type, $host, $user, $pass, $name, $pcon);

	// output
	if ($databaseConfWriteOk) {
		$msg = 'Database configuration file <em>'._DIR._FILE_DBCONF.'</em> written.';
		displaySetupMessage($msg, true);
	} else {
		displaySetupMessage($databaseConfWriteError, false);
		send("<br/>");
		send('<p>To perform this step manually please paste the following content to the database configuration file <em>'._DIR._FILE_DBCONF.'</em> and ensure the file is readable by the user the webserver runs as:</p>');
		send('<textarea cols="81" rows="33">'.$databaseConfContent.'</textarea>');
		send("<p><strong>Note:</strong> You must write this file before you can continue!</p>");
	}

	send("<br/>");
	send("<h2>Next : Create Tables</h2>");
	sendButton(16);
} elseif (isset($_REQUEST["16"])) {                                             // 16 - Database - table-creation
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Create Tables</h2>");
	send("<p>The installation will now attempt to create the database tables required for running "._NAME.".</p>");
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$databaseTableCreationCount = 0;
		$databaseTableCreation = false;
		$databaseError = "";
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);

		if (!$dbCon) {
			$databaseTableCreation = false;
			$databaseError = "Cannot connect to database.";
		} else {
			send('<ul>');
			foreach ($queries['create'][$cfg["db_type"]] as $databaseTypeName => $databaseQuery) {
				send('<li/>');
				$dbCon->Execute($databaseQuery);
				if ($dbCon->ErrorNo() == 0) {
					send('<font color="green">Query Ok:</font> <em>'.$databaseQuery.'</em>');
					$databaseTableCreationCount++;
				} else { // damn there was an error
					send('<font color="red">Query Error:</font> <em>'.$databaseQuery.'</em>');
					$databaseError = "Could not create tables.  Note that the database must be empty to perform this step.";
					// close ado-connection
					$dbCon->Close();
					break;
				}
			}
			if ($databaseTableCreationCount == count($queries['create'][$cfg["db_type"]])) {
				// close ado-connection
				$dbCon->Close();
				$databaseTableCreation = true;
			} else {
				$databaseTableCreation = false;
			}
			send('</ul>');
		}
		if ($databaseTableCreation) {
			$msg = $databaseTableCreationCount.' tables created';
			displaySetupMessage($msg, true);
			send("<br/>");
			send("<h2>Next: Insert Data Into Database</h2>");
			sendButton(17);
		} else {
			displaySetupMessage($databaseError, false);
		}
	} else {
		displaySetupMessage($msgDbConfigMissing, false);
	}
} elseif (isset($_REQUEST["17"])) {                                             // 17 - Database - data
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Insert Data Into Database</h2>");
	send("<p>The installation will now attempt to insert all the data required for the system into the database.</p>");
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$databaseDataCount = 0;
		$databaseData = false;
		$databaseError = "";
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			$databaseData = false;
			$databaseError = "cannot connect to database.";
		} else {
			send('<ul>');
			foreach ($queries['data'][$cfg["db_type"]] as $databaseTypeName => $databaseQuery) {
				send('<li/>');
				$dbCon->Execute($databaseQuery);
				if ($dbCon->ErrorNo() == 0) {
					send('<font color="green">Query Ok:</font> '.$databaseQuery);
					$databaseDataCount++;
				} else { // damn there was an error
					send('<font color="red">Query Error:</font> '.$databaseQuery);
					$databaseError = "Could not import data into database.  Database tables must be empty before performing this step.";
					// close ado-connection
					$dbCon->Close();
					break;
				}
			}
			if ($databaseDataCount == count($queries['data'][$cfg["db_type"]])) {
				// close ado-connection
				$dbCon->Close();
				$databaseData = true;
			} else {
				$databaseData = false;
			}
			send('</ul>');
		}
		if ($databaseData) {
			$msg = $databaseDataCount.' queries executed.';
			displaySetupMessage($msg, true);
			send("<br/>");
			send("<h2>Next: Server Configuration</h2>");
			sendButton(2);
		} else {
			displaySetupMessage($databaseError, false);
		}
	} else {
		displaySetupMessage($err, false);
	}
} elseif (isset($_REQUEST["2"])) {                                              // 2 - Configuration
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Server Configuration</h2>");
	send("<p>The installation will now continue to prompt you for some basic settings required to get "._NAME." running.</p>");
	send("<br/>");
	send("<h2>Next : Server Settings</h2>");
	sendButton(21);
} elseif (isset($_REQUEST["21"])) {                                             // 21 - Configuration - Server Settings input
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings</h2>");

	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			$err = "cannot connect to database.";
		} else {
			$tf_settings = loadSettings("tf_settings");

			// close ado-connection
			$dbCon->Close();
			if ($tf_settings !== false) {
				send("<p>Please enter the path to the directory you want "._NAME." to save your user downloads into below.</p>");
				send("<p><strong>Important:</strong> this path <b>must</b> be writable by the webserver user</p>");
				send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
				send('<table border="0">');

				// path
				$line = '<tr><td width="200"><strong>User Download Path:</strong></td>';
				$line .= '<td><input name="path" type="Text" maxlength="254" size="40" value="';
				if (!empty($_REQUEST["path"]))
					$line .= $_REQUEST["path"];
				else
					$line .= $tf_settings["path"];
				$line .= '"></td></tr>';
				$line .= '<tr><td>&nbsp;</td><td width="400"><strong>Note:</strong> this is what you may know as "path" (or "downloads") ';
				$line .= 'from TF 2.1 and TF 2.1-b4rt - the parent directory where home directories will ';
				$line .= 'be created and transfers will be downloaded to.</td></tr>';
				send($line);
				send('</table>');

				// docroot
				if (isset($_REQUEST["docroot"]))
					send('<input type="Hidden" name="docroot" value="'.$_REQUEST["docroot"].'">');
				else
					send('<input type="Hidden" name="docroot" value="'.getcwd().'">');
				send('<input type="Hidden" name="22" value="">');
				send('<input type="submit" value="Continue">');
				send('</form>');
			} else {
				$err = "error loading settings.";
				displaySetupMessage($err, false);
			}
		}
	} else {
		displaySetupMessage($msgDbConfigMissing, false);
	}
} elseif (isset($_REQUEST["22"])) {                                             // 22 - Configuration - Server Settings validate
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings Validation</h2>");

	$serverSettingsTestCtr = 0;
	$serverSettingsTestError = "";
	$pathExists = false;

	$path = $_REQUEST["path"];
	if(empty($path)){
		$serverSettingsTestError = "user download path cannot be empty, please supply the full path to the directory.";
	} elseif (((strlen($path) > 0)) && (substr($path, -1 ) != "/")){
		$path .= "/";
	}

	$docroot = $_REQUEST["docroot"];
	if (((strlen($docroot) > 0)) && (substr($docroot, -1 ) != "/"))
		$docroot .= "/";

	// Only go here if no error already:
	if(empty($serverSettingsTestEror)){
		// path
		if (!(@is_dir($path) === true)) {
			// dir doesn't exist, try to create
			if (!((@mkdir($path, 0777)) === true))
				$serverSettingsTestError .= "path <em>".$path."</em> does not exist and cannot be created.  Check that the path is writable by the webserver user.";
			else
				$pathExists = true;
		} else {
			$pathExists = true;
		}

		if ($pathExists) {
			if (!(@is_writable($path) === true))
				$serverSettingsTestError .= "path <em>".$path."</em> is not writable. Check that the path is writable by the webserver user.";
			else
				$serverSettingsTestCtr++;
		}

		// docroot
		if (is_file($docroot."version.php"))
			$serverSettingsTestCtr++;
		else
			$serverSettingsTestError .= "docroot <em>".$docroot."</em> is not valid.";
	}

	// output
	if ($serverSettingsTestCtr == 2) {
		$msg = "User download directory set to: <em>".$path."</em>";
		displaySetupMessage($msg, true);

		$msg = "Document root directory set to: <em>".$docroot."</em>";
		displaySetupMessage($msg, true);

		send("<br/>");
		send("<h2>Next: Check For Third Party Utilities</h2>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="path" value="'.$path.'">');
		send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
		send('<input type="Hidden" name="221" value="">');
		send('<input type="submit" value="Continue">');
	} else {
		displaySetupMessage($serverSettingsTestError, false);

		send("<br/>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="path" value="'.$path.'">');
		send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
		send('<input type="Hidden" name="21" value="">');
		send('<input type="submit" value="Back">');
	}
	send('</form>');
} elseif (isset($_REQUEST["221"])) {
	$OS = strtolower(exec("uname"));

	// Check for system tools like grep, awk, netstat, rar, etc:
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Check System Tools</h2>");
	send("<p>The installation will now check to locate the system tools required for operating "._NAME." smoothly.</p><br/>");
	$line  = '<table border="1" cellspacing="0">';
	$line .= '<tr style="font-weight:bold"><td>Tool Name</td><td>Path</td><td width="400">Info</td></td></tr>';

	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$databaseError = "";

		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			$databaseError = "cannot connect to database.";
			// stop:
			displaySetupMessage($databaseError, false);
		} else {
			// extra non-standard binary paths to check installed binaries:
			$binPaths = array(
				// FreeBSD:
				'/usr/local/bin',
				'/usr/local/sbin',

				// NetBSD:
				'/usr/pkgsrc/bin',
				'/usr/pkgsrc/sbin',

				// OpenBSD (same as fbsd?):
				// Solaris (unsure):
				// AN other:
			);

			// Array of binaries => default binary paths:
			$bins = array(
				'grep'		=> '/bin/grep',
				'netstat'	=> '/bin/netstat',
				'php'		=> '/usr/bin/php',
				'awk'		=> '/usr/bin/awk',
				'du'		=> '/usr/bin/du',
				'wget'		=> '/usr/bin/wget',
				'unrar'		=> '/usr/bin/unrar',
				'unzip'		=> '/usr/bin/unzip',
				'cksfv'		=> '/usr/bin/cksfv',
				'sockstat'	=> '/usr/bin/sockstat',
				'vlc'		=> '/usr/local/bin/vlc',
				'uudeview'	=> '/usr/local/bin/uudeview'
			);

			$pathErrCount = 0;
			foreach ($bins as $bin => $path){
				if($OS == "linux" && $bin == "sockstat"){
					continue;
				}

				$foundPath = "";
				$isExe = false;
				$line .= '<tr valign="top"><td>'.$bin.'</td><td>';

				// see if which finds this binary:
				$foundPath = trim(exec(escapeshellcmd("which $bin")));

				if(empty($foundPath)){
					// OK no bin found, let's check the non-standard paths:
					foreach ($binPaths as $extraPath){
						$thisBin = $extraPath."/".$bin;
						if( is_file($thisBin) ){
							// Yay, found the file:
							$foundPath = $thisBin;

							// Check executable bit:
							if ( is_executable($foundPath) ){
								$isExe = true;

								// Done with this exe, move onto next:
								break;
							}
						}
					}
				} else {
					// Check is exe:
					if(is_executable($foundPath)){
						$isExe = true;
					}
				}

				if(!empty($foundPath)){
					$line .= $foundPath.'</td><td>Path found Ok. ';
					if(!$isExe){
						$line .= '<font color="red">Error: binary '.$foundPath.' is NOT executable.  Ensure webserver user can execute this binary before continuing.</font>';
					} else {
						$line .= $foundPath.' is executable.';
					}

					// Update path for this binary:
					$databaseQuery = "UPDATE tf_settings SET tf_value='$foundPath' WHERE tf_key='bin_$bin'";
					$dbCon->Execute($databaseQuery);

					if ($dbCon->ErrorNo() != 0) {
						// Problem with query:
						$line .= "<br/><br/>Error executing query:<br/><strong>$databaseQuery</strong>";
					}
				} else {
					// Didn't find this binary, let the user know:
					$line .= '<font color="red">NOT FOUND</font></td>';
					$line .= '<td><font color="orange">Warning: could not find <strong>'.$bin.'</strong> on your system.  Default path <strong>'.$path.'</strong> used.</font>';
					$pathErrCount++;
				}

				$line .= "</td></tr>";
			}
			$line .="</table>";

			if($pathErrCount > 0){
				$line .= "<br/><p><strong>Important:</strong><br/>There were problems locating the paths to some tools on your server.  ";
				$line .= "Depending on which tools they were, some features may not work as expected.</p><p>After installation, check that the tools reported as <font color=\"red\">NOT FOUND</font> above are installed correctly and modify your installation settings to reflect the path of the problematic tools.  You can do this by clicking on the 'Admin' link at the top right of the "._NAME." page and then selecting the 'Server Settings' tab.</p>";
			}
			send($line);

			send("<br/>");
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="path" value="'.$_REQUEST["path"].'">');
			send('<input type="Hidden" name="docroot" value="'.$_REQUEST["docroot"].'">');
			send('<input type="Hidden" name="23" value="">');
			send("<br/>");
			send("<h2>Next: Server Settings Save</h2>");
			send('<input type="submit" value="Continue">');
			send('</form>');
		}
	} else {
		// stop:
		displaySetupMessage($msgDbConfigMissing, false);
	}
} elseif (isset($_REQUEST["23"])) {                                             // 23 - Configuration - Server Settings	save
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings Save</h2>");

	$path = $_REQUEST["path"];
	$docroot = $_REQUEST["docroot"];

	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);

		if (!$dbCon) {
			$err = "cannot connect to database.  Check database settings in "._FILE_DBCONF;
			displaySetupMessage($err);
		} else {
			$settingsSaveCtr = 0;

			if (updateSetting("tf_settings", "path", $path) === true)
				$settingsSaveCtr++;

			if (updateSetting("tf_settings", "docroot", $docroot) === true)
				$settingsSaveCtr++;

			if ($settingsSaveCtr == 2) {
				$msg = 'Server settings saved to database.';
				displaySetupMessage($msg, true);

				send("<br/>");
				send("<h2>Next: Installation End</h2>");
				sendButton(3);
			} else {
				$err = 'could not save path and docroot server settings to database.';
				displaySetupMessage($err, false);

				send("<br/>");
				send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
				send('<input type="Hidden" name="path" value="'.$path.'">');
				send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
				send('<input type="Hidden" name="21" value="">');
				send('<input type="submit" value="Back">');
				send('</form>');
			}
			// close ado-connection
			$dbCon->Close();
		}
	} else {
		displaySetupMessage($msgDbConfigMissing, false);
	}
} elseif (isset($_REQUEST["3"])) {                                              // 3 - End
	sendHead(" - End");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Setup Completed</h2>");
	send("<p>Congratulations!  "._NAME." has successfully been installed.</p>");

	if ((substr(_VERSION, 0, 3)) != "svn") {
		$result = @unlink(__FILE__);
		if ($result !== true) {
			$err = 'Could not delete '.__FILE__.'. Please delete the file manually.';
			$err .= '<strong>Important:</strong> '._NAME.' will not run until this file is deleted for security reasons!';
			displaySetupMessage($err, false);
		} else {
			$msg = 'Deleted '.__FILE__.' successfully.';
			displaySetupMessage($msg, true);

			send("<br/>");
			send("<h2>Next: Login</h2>");
			send("<p>To continue on to the "._NAME." login screen, click the button below:</p>");
			send('<form name="setup" action="login.php" method="post">');
			send('<input type="submit" value="Continue">');
			send('</form>');
		}
	} else {
		$msg = '<font color="blue">This is an svn-version. '.__FILE__.' is untouched. Please remove the file manually to login to your '._NAME.' installation.</font>';
		displaySetupMessage($msg, true);
	}
		send("<p><strong>Important:</strong><br/>When logging in for the first time <strong>the login username and password you supply there will create the default superadmin user for your "._NAME." installation.</strong>  For this reason it is important you do this immediately and remember the username and password!!!</p>");
} else {                                                                        // default
	sendHead();
	if (is_file(_FILE_DBCONF))
		send('<p><br><font color="red"><h1>db-config already exists ('._FILE_DBCONF.')</h1></font>Delete setup.php if you came here after finishing setup to proceed to login.</p><hr>');
	send("<h1>"._TITLE."</h1>");
	send("<p>Welcome to the installation script for ". _NAME.". In the following pages you will be guided through the steps necessary to get your installation of "._NAME." up and running, including database configuration and initial "._NAME." system configuration file creation.</p>");
	send("<br/>");
	send("<h2>Next: Database</h2>");
	sendButton(1);
}

// foot
sendFoot();

// ob-end + exit
@ob_end_flush();
exit();

?>
