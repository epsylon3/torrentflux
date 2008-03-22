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
define('_UPGRADE_FROM', '2.1');
define('_UPGRADE_TO', '1.0');
define('_DEFAULT_PATH', '/usr/local/torrent/');
define('_TITLE', _NAME.' '._VERSION.' - Upgrade '._UPGRADE_FROM.' to '._UPGRADE_TO);
define('_DIR', dirname($_SERVER["SCRIPT_FILENAME"])."/");
define('_FILE_DBCONF', 'inc/config/config.db.php');
define('_FILE_THIS', $_SERVER['SCRIPT_NAME']);

// Database-Types
$databaseTypes = array();
$databaseTypes['mysql'] = 'mysql_connect';
$databaseTypes['postgres'] = 'pg_connect';

// init queries
initQueries("upgrade", _UPGRADE_FROM);

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
	sendButton(11);
} elseif (isset($_REQUEST["11"])) {                                             // 11 - Database - type
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Type</h2>");
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
	send('<input type="Hidden" name="12" value="">');
	send('<input type="submit" value="Continue">');
	send('</form>');
} elseif (isset($_REQUEST["12"])) {                                             // 12 - Database - type check
	if ((isset($_REQUEST["db_type"])) && ($databaseTypes[$_REQUEST["db_type"]] != "")) {
		$type = $_REQUEST["db_type"];
		sendHead(" - Database");
		send("<h1>"._TITLE."</h1>");
		send("<h2>Database - Type Check</h2>");
		if (function_exists($databaseTypes[$type])) {
			send('<font color="green"><strong>Ok</strong></font><br>');
			send('This PHP does support <em>'.$type.'</em>.<p>');
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="db_type" value="'.$type.'">');
			send('<input type="Hidden" name="13" value="">');
			send('<input type="submit" value="Continue">');
			send('</form>');
		} else {
			send('<font color="red"><strong>Error</strong></font><br>');
			send('This PHP does not support <em>'.$type.'</em>.<p>');
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="11" value="">');
			send('<input type="submit" value="Back">');
			send('</form>');
		}
	} else {
		header("location: setup.php?11");
		exit();
	}
} elseif (isset($_REQUEST["13"])) {                                             // 13 - Database - config
	$type = $_REQUEST["db_type"];
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Config - ".$type."</h2>");
	send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
	send('<table border="0">');
	// settings
	send('<tr><td colspan="2"><strong>Database Settings : </strong></td></tr>');
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
		$line .= 'torrentflux';
	$line .= '"></td></tr>';
	send($line);
	// user
	$line = '<tr><td>Username : </td>';
	$line .= '<td><input name="db_user" type="Text" maxlength="254" size="40" value="';
	if (isset($_REQUEST["db_user"]))
		$line .= $_REQUEST["db_user"];
	else
		$line .= 'root';
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
	// pcon
	$line = '<tr><td colspan="2">Persistent Connection :';
	$line .= '<input name="db_pcon" type="Checkbox" value="true"';
	if (isset($_REQUEST["db_pcon"]))
		$line .= ' checked">';
	else
		$line .= '>';
	$line .= '</td></tr>';
	send($line);
	send('</table>');
	send('<input type="Hidden" name="db_type" value="'.$type.'">');
	send('<input type="Hidden" name="14" value="">');
	send('<input type="submit" value="Continue">');
	send('</form>');
} elseif (isset($_REQUEST["14"])) {                                             // 14 - Database - test
	$type = $_REQUEST["db_type"];
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Test - ".$type."</h2>");
	$paramsOk = true;
	if (isset($_REQUEST["db_host"]))
		$host = $_REQUEST["db_host"];
	else
		$paramsOk = false;
	if (isset($_REQUEST["db_pcon"]))
		$pcon = "true";
	else
		$pcon = "false";
	if (isset($_REQUEST["db_name"]))
		$name = $_REQUEST["db_name"];
	else
		$paramsOk = false;
	if (isset($_REQUEST["db_user"]))
		$user = $_REQUEST["db_user"];
	else
		$paramsOk = false;
	if (isset($_REQUEST["db_pass"]))
		$pass = $_REQUEST["db_pass"];
	else
		$paramsOk = false;
	$databaseTestOk = false;
	$databaseError = "";
	// test
	if ($paramsOk) {
		$dbCon = getAdoConnection($type, $host, $user, $pass, $name);
		if (!$dbCon) {
			$databaseTestOk = false;
			$databaseError = "cannot connect to database.";
		} else {
			send('<ul>');
			$databaseTestCount = 0;
			foreach ($queries['test'][$type] as $databaseTypeName => $databaseQuery) {
				send('<li><em>'.$databaseQuery.'</em> : ');
				$dbCon->Execute($databaseQuery);
				if ($dbCon->ErrorNo() == 0) {
					send('<font color="green">Ok</font></li>');
					$databaseTestCount++;
				} else { // damn there was an error
					send('<font color="red">Error</font></li>');
					// close ado-connection
					$dbCon->Close();
					break;
				}
			}
			if ($databaseTestCount == count($queries['test'][$type])) {
				$databaseTestOk = true;
			} else {
				$databaseTestOk = false;
			}
			send('</ul>');
		}
	} else {
		$databaseTestOk = false;
		$databaseError = "config error.";
	}
	// output
	if ($databaseTestOk) {
		// load path
		$tf_settings = loadSettings("tf_settings");
		if ($tf_settings !== false) {
			$oldpath = $tf_settings["path"];
			if (((strlen($oldpath) > 0)) && (substr($oldpath, -1 ) != "/"))
				$oldpath .= "/";
		} else {
			$oldpath = _DEFAULT_PATH;
		}
		// close ado-connection
		$dbCon->Close();
		send('<font color="green"><strong>Ok</strong></font><br>');
		send("<h2>Next : Write Config File</h2>");
		send("Please ensure this script can write to the dir <em>"._DIR."inc/config/</em><p>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="oldpath" value="'.$oldpath.'">');
		send('<input type="Hidden" name="db_type" value="'.$type.'">');
		send('<input type="Hidden" name="db_host" value="'.$host.'">');
		send('<input type="Hidden" name="db_name" value="'.$name.'">');
		send('<input type="Hidden" name="db_user" value="'.$user.'">');
		send('<input type="Hidden" name="db_pass" value="'.$pass.'">');
		send('<input type="Hidden" name="db_pcon" value="'.$pcon.'">');
		send('<input type="Hidden" name="15" value="">');
		send('<input type="submit" value="Continue">');
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send($databaseError."<p>");
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
		send('<input type="submit" value="Back">');
	}
	send('</form>');
} elseif (isset($_REQUEST["15"])) {                                             // 15 - Database - config-file
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Config-File</h2>");
	$oldpath = $_REQUEST["oldpath"];
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
		send('<font color="green"><strong>Ok</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> written.');
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send($databaseConfWriteError."<p>");
		send('to perform this step manual paste the following content to the database-config-file <em>'._DIR._FILE_DBCONF.'</em> : <p>');
		send('<textarea cols="81" rows="33">'.$databaseConfContent.'</textarea>');
		send("<p>Note : You must write this file before you can continue !");
	}
	send("<h2>Next : Create/Alter Tables</h2>");
	send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
	send('<input type="Hidden" name="oldpath" value="'.$oldpath.'">');
	send('<input type="Hidden" name="16" value="">');
	send('<input type="submit" value="Continue">');
	send('</form>');
} elseif (isset($_REQUEST["16"])) {                                             // 16 - Database - table-creation
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Create/Alter Tables</h2>");
	$oldpath = $_REQUEST["oldpath"];
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$databaseTableCreationCount = 0;
		$databaseTableCreation = false;
		$databaseError = "";
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			$databaseTableCreation = false;
			$databaseError = "cannot connect to database.";
		} else {
			send('<ul>');
			foreach ($queries['create'][$cfg["db_type"]] as $databaseTypeName => $databaseQuery) {
				send('<li><em>'.$databaseQuery.'</em> : ');
				$dbCon->Execute($databaseQuery);
				if ($dbCon->ErrorNo() == 0) {
					send('<font color="green">Ok</font></li>');
					$databaseTableCreationCount++;
				} else { // damn there was an error
					send('<font color="red">Error</font></li>');
					$databaseError = "error creating tables.";
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
			send('<font color="green"><strong>Ok</strong></font><br>');
			send($databaseTableCreationCount.' queries executed.');
			send("<h2>Next : Data</h2>");
			send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
			send('<input type="Hidden" name="oldpath" value="'.$oldpath.'">');
			send('<input type="Hidden" name="17" value="">');
			send('<input type="submit" value="Continue">');
			send('</form>');
		} else {
			send('<font color="red"><strong>Error</strong></font><br>');
			send($databaseError."<p>");
		}
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> missing. setup cannot continue.');
	}
} elseif (isset($_REQUEST["17"])) {                                             // 17 - Database - data
	sendHead(" - Database");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Database - Data</h2>");
	$oldpath = $_REQUEST["oldpath"];
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
			// add path
			array_unshift($queries['data'][$cfg["db_type"]], "INSERT INTO tf_settings VALUES ('path','".$oldpath."')");
			// add delete-state
			array_unshift($queries['data'][$cfg["db_type"]], "DELETE FROM tf_settings");
			// exec
			foreach ($queries['data'][$cfg["db_type"]] as $databaseTypeName => $databaseQuery) {
				send('<li><em>'.$databaseQuery.'</em> : ');
				$dbCon->Execute($databaseQuery);
				if ($dbCon->ErrorNo() == 0) {
					send('<font color="green">Ok</font></li>');
					$databaseDataCount++;
				} else { // damn there was an error
					send('<font color="red">Error</font></li>');
					$databaseError = "error importing data.";
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
			send('<font color="green"><strong>Ok</strong></font><br>');
			send($databaseDataCount.' queries executed.');
			send("<h2>Next : Configuration</h2>");
			sendButton(2);
		} else {
			send('<font color="red"><strong>Error</strong></font><br>');
			send($databaseError."<p>");
		}
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> missing. setup cannot continue.');
	}
} elseif (isset($_REQUEST["2"])) {                                              // 2 - Configuration
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration</h2>");
	send("<h2>Next : Server Settings</h2>");
	sendButton(21);
	send('</form>');
} elseif (isset($_REQUEST["21"])) {                                             // 21 - Configuration - Server Settings input
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings</h2>");
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			send('<font color="red"><strong>Error</strong></font><br>');
			send("cannot connect to database.<p>");
		} else {
			$tf_settings = loadSettings("tf_settings");
			// close ado-connection
			$dbCon->Close();
			if ($tf_settings !== false) {
				send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
				send('<table border="0">');
				// docroot
				/*
				$line = '<tr><td>docroot : </td>';
				$line .= '<td><input name="docroot" type="Text" maxlength="254" size="40" value="';
				if (isset($_REQUEST["docroot"]))
					$line .= $_REQUEST["docroot"];
				else
					$line .= _DIR;
				$line .= '"></td></tr>';
				send($line);
				*/
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
				send('<font color="red"><strong>Error</strong></font><br>');
				send("error loading settings.<p>");
			}
		}
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> missing. setup cannot continue.');
	}
} elseif (isset($_REQUEST["22"])) {                                             // 22 - Configuration - Server Settings validate
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings Validation</h2>");
	$docroot = $_REQUEST["docroot"];
	if (((strlen($docroot) > 0)) && (substr($docroot, -1 ) != "/"))
		$docroot .= "/";
	$serverSettingsTestCtr = 0;
	$serverSettingsTestError = "";
	// docroot
	if (is_file($docroot."version.php"))
		$serverSettingsTestCtr++;
	else
		$serverSettingsTestError .= "docroot <em>".$docroot."</em> is not valid.";
	// output
	if ($serverSettingsTestCtr == 1) {
		send('<font color="green"><strong>Ok</strong></font><br>');
		send("docroot : <em>".$docroot."</em><br>");
		send("<h2>Next : Save Server Settings</h2>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
		send('<input type="Hidden" name="23" value="">');
		send('<input type="submit" value="Continue">');
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send($serverSettingsTestError."<p>");
		send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
		send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
		send('<input type="Hidden" name="21" value="">');
		send('<input type="submit" value="Back">');
	}
	send('</form>');
} elseif (isset($_REQUEST["23"])) {                                             // 23 - Configuration - Server Settings	save
	sendHead(" - Configuration");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Configuration - Server Settings Save</h2>");
	$docroot = $_REQUEST["docroot"];
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			send('<font color="red"><strong>Error</strong></font><br>');
			send("cannot connect to database.<p>");
		} else {
			$settingsSaveCtr = 0;
			if (updateSetting("tf_settings", "docroot", $docroot) === true)
				$settingsSaveCtr++;
			if ($settingsSaveCtr == 1) {
				send('<font color="green"><strong>Ok</strong></font><br>');
				send('Server Settings saved.');
				send("<h2>Next : Rename Files and Dirs</h2>");
				sendButton(3);
			} else {
				send('<font color="red"><strong>Error</strong></font><br>');
				send('could not save Server Settings.');
				send('<form name="setup" action="' . _FILE_THIS . '" method="post">');
				send('<input type="Hidden" name="docroot" value="'.$docroot.'">');
				send('<input type="Hidden" name="21" value="">');
				send('<input type="submit" value="Back">');
				send('</form>');
			}
			// close ado-connection
			$dbCon->Close();
		}
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> missing. setup cannot continue.');
	}
} elseif (isset($_REQUEST["3"])) {                                             // 3 - rename files and dirs
	sendHead(" - Rename Files and Dirs");
	send("<h1>"._TITLE."</h1>");
	send("<h2>Rename Files and Dirs</h2>");
	if (is_file(_FILE_DBCONF)) {
		require_once(_FILE_DBCONF);
		$dbCon = getAdoConnection($cfg["db_type"], $cfg["db_host"], $cfg["db_user"], $cfg["db_pass"], $cfg["db_name"]);
		if (!$dbCon) {
			send('<font color="red"><strong>Error</strong></font><br>');
			send("cannot connect to database.<p>");
		} else {
			$tf_settings = loadSettings("tf_settings");
			// close ado-connection
			$dbCon->Close();
			if ($tf_settings !== false) {
				$path = $tf_settings["path"];
				$pathExists = false;
				$renameOk = false;
				$allDone = true;
				if ((@is_dir($path) === true) && (@is_dir($path.".torrents") === true)) {
					$pathExists = true;
					send('<ul>');

					// transfers-dir
					send('<li><em>'.$path.".torrents -> ".$path.".transfers".'</em> : ');
					$renameOk = rename($path.".torrents", $path.".transfers");
					if ($renameOk === true) {
						send('<font color="green">Ok</font></li>');
					} else {
						$allDone = false;
						send('<font color="red">Error</font></li>');
					}

					// old queue-dir
					if ($renameOk) {
						if (@is_dir($path.".transfers/queue")) {
							$files = array();
							if ($dirHandle = opendir($path.".transfers/queue")) {
								while (false !== ($file = readdir($dirHandle))) {
									if ((strlen($file) > 4) && ((substr($file, -4)) == "stat"))
										array_push($files, $file);
								}
								closedir($dirHandle);
							}
							$filesCount = count($files);
							$filesCtr = 0;
							if ($filesCount > 0) {
								foreach ($files as $file) {
									$fileSource = $path.".transfers/queue/".$file;
									send('<li>delete : <em>'.$fileSource.'</em> : ');
									$fileUnlinkOk = @unlink($fileSource);
									if ($fileUnlinkOk === true) {
										$filesCtr++;
										send('<font color="green">Ok</font></li>');
									} else {
										send('<font color="red">Error</font></li>');
									}
								}
								if ($filesCount != $filesCtr)
									$allDone = false;
							}
							send('<li>delete : <em>'.$path.".transfers/queue".'</em> : ');
							$rmdirOk = @rmdir($path.".transfers/queue");
							if ($rmdirOk === true) {
								send('<font color="green">Ok</font></li>');
							} else {
								$allDone = false;
								send('<font color="red">Error</font></li>');
							}
						}
					}

					// stat-files
					if ($renameOk) {
						$files = array();
						if ($dirHandle = opendir($path.".transfers")) {
							while (false !== ($file = readdir($dirHandle))) {
								if ((strlen($file) > 7) && ((substr($file, -7)) == "torrent"))
									array_push($files, $file);
							}
							closedir($dirHandle);
						}
						$filesCount = count($files);
						$filesCtr = 0;
						if ($filesCount > 0) {
							foreach ($files as $file) {
								$fileNameSource = (strtolower(substr($file, 0, -7)))."stat";
								$fileSource = $path.".transfers/".$fileNameSource;
								$fileNameTarget = $file.".stat";
								$fileTarget = $path.".transfers/".$fileNameTarget;
								send('<li><em>'.$fileSource.' -> '.$fileTarget.'</em> : ');
								$fileRenameOk = rename($fileSource, $fileTarget);
								if ($fileRenameOk === true) {
									$filesCtr++;
									send('<font color="green">Ok</font></li>');
								} else {
									send('<font color="red">Error</font></li>');
								}
							}
							if ($filesCount != $filesCtr)
								$allDone = false;
						}
					}

					// prio-files
					if ($renameOk) {
						$files = array();
						if ($dirHandle = opendir($path.".transfers")) {
							while (false !== ($file = readdir($dirHandle))) {
								if ((strlen($file) > 4) && ((substr($file, -4)) == "prio"))
									array_push($files, $file);
							}
							closedir($dirHandle);
						}
						$filesCount = count($files);
						$filesCtr = 0;
						if ($filesCount > 0) {
							foreach ($files as $file) {
								$fileNameSource = $file;
								$fileSource = $path.".transfers/".$fileNameSource;
								$fileNameTarget = substr($file, 0, -4)."torrent.prio";
								$fileTarget = $path.".transfers/".$fileNameTarget;
								send('<li><em>'.$fileSource.' -> '.$fileTarget.'</em> : ');
								$fileRenameOk = rename($fileSource, $fileTarget);
								if ($fileRenameOk === true) {
									$filesCtr++;
									send('<font color="green">Ok</font></li>');
								} else {
									send('<font color="red">Error</font></li>');
								}
							}
							if ($filesCount != $filesCtr)
								$allDone = false;
						}
					}

					send('</ul>');
					if ($allDone) {
						send('<font color="green"><strong>Ok</strong></font><br>');
						send('Files and Dirs renamed.');
						send("<h2>Next : End</h2>");
						sendButton(4);
					} else { // damn there was an error
						send('<font color="red">Error</font></li>');
						send("error renaming Files and Dirs. you have to re-inject all torrents.<p>");
					}
				} else {
					send('<font color="red">Error</font></li>');
					send("path <em>".$path.".torrents</em> does not exist. you have to re-inject all torrents.<p>");
				}
			} else {
				send('<font color="red"><strong>Error</strong></font><br>');
				send("error loading settings.<p>");
			}
		}
	} else {
		send('<font color="red"><strong>Error</strong></font><br>');
		send('database-config-file <em>'._DIR._FILE_DBCONF.'</em> missing. setup cannot continue.');
	}
} elseif (isset($_REQUEST["4"])) {                                              // 4 - End
	sendHead(" - End");
	send("<h1>"._TITLE."</h1>");
	send("<h2>End</h2>");
	send("<p>Upgrade completed.</p>");
	if ((substr(_VERSION, 0, 3)) != "svn") {
		$result = @unlink(__FILE__);
		if ($result !== true)
			send('<p><font color="red">Could not delete '.__FILE__.'</font><br>Please delete the file manual.</p>');
		else
			send('<p><font color="green">Deleted '.__FILE__.'</font></p>');
	} else {
		send('<p><font color="blue">This is a svn-version. '.__FILE__.' is untouched.</font></p>');
	}
	send("<h2>Next : Login</h2>");
	send('<form name="setup" action="login.php" method="post">');
	send('<input type="submit" value="Continue">');
	send('</form>');
} else {                                                                        // default
	sendHead();
	if (is_file(_FILE_DBCONF))
		send('<p><br><font color="red"><h1>db-config already exists ('._FILE_DBCONF.')</h1></font>Delete upgrade.php if you came here after finishing upgrade to proceed to login.</p><hr>');
	send("<h1>"._TITLE."</h1>");
	send("<p>This script will upgrade from TorrentFlux  "._UPGRADE_FROM." to "._NAME." "._UPGRADE_TO."</p>");
	send("<h2>Next : Database</h2>");
	sendButton(1);
}

// foot
sendFoot();

// ob-end + exit
@ob_end_flush();
exit();

?>