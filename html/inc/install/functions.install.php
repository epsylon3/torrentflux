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

/**
 * load Settings
 *
 * @param $dbTable
 * @return array
 */
function loadSettings($dbTable) {
    global $dbCon;
    // pull the config params out of the db
    $sql = "SELECT tf_key, tf_value FROM ".$dbTable;
    $recordset = $dbCon->Execute($sql);
	if ($dbCon->ErrorNo() != 0)
		return false;
    $retVal = array();
    while (list($key, $value) = $recordset->FetchRow()) {
        $tmpValue = '';
		if (strpos($key,"Filter") > 0) {
		  $tmpValue = unserialize($value);
		} elseif ($key == 'searchEngineLinks') {
            $tmpValue = unserialize($value);
    	}
    	if(is_array($tmpValue))
            $value = $tmpValue;
        $retVal[$key] = $value;
    }
    return $retVal;
}

/**
 * update Setting
 *
 * @param $dbTable
 * @param $key
 * @param $value
 * @return boolean
 */
function updateSetting($dbTable, $key, $value) {
    global $dbCon;
	if (is_array($value))
        $update_value = serialize($value);
    else
    	$update_value = $value;
    $sql = "UPDATE ".$dbTable." SET tf_value = '".$update_value."' WHERE tf_key = '".$key."'";
    $dbCon->Execute($sql);
    if ($dbCon->ErrorNo() != 0)
		return false;
	return true;
}

/**
 * write the db-conf file.
 *
 * @param $type
 * @param $host
 * @param $user
 * @param $pass
 * @param $name
 * @param $pcon
 * @return boolean
 */
function writeDatabaseConfig($type, $host, $user, $pass, $name, $pcon) {
	global $databaseConfWriteOk, $databaseConfWriteError, $databaseConfContent;
	$databaseConfContent = '<?php

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

/******************************************************************************/
// YOUR DATABASE CONNECTION INFORMATION
/******************************************************************************/
$cfg["db_type"] = "'.strtolower($type).'"; // Database-Type : mysql/sqlite/postgres
$cfg["db_host"] = "'.$host.'"; // Database host computer name or IP
$cfg["db_name"] = "'.$name.'"; // Name of the Database
$cfg["db_user"] = "'.$user.'"; // Username for Database
$cfg["db_pass"] = "'.$pass.'"; // Password for Database
$cfg["db_pcon"] = '.$pcon.'; // Persistent Connection enabled : true/false
/******************************************************************************/

?>';
	$configFile = false;
	$configFile = @fopen(_DIR._FILE_DBCONF, "w");
	if (!$configFile) {
		$databaseConfWriteOk = false;
		$databaseConfWriteError = "Cannot open configuration file <em>"._DIR._FILE_DBCONF."</em> for writing.  Please check permissions and try again.";
		return false;
	}
	$result = @fwrite($configFile, $databaseConfContent);
	@fclose($configFile);
	if ($result === false) {
		$databaseConfWriteOk = false;
		$databaseConfWriteError = "Cannot write content to config-file <em>"._DIR._FILE_DBCONF."</em>. Please check any file locking issues and try again.";
		return false;
	}
	$databaseConfWriteOk = true;
	return true;
}

/**
 * get a ado-connection to our database.
 *
 * @param $type
 * @param $host
 * @param $user
 * @param $pass
 * @param $name
 * @return database-connection or false on error
 */
function getAdoConnection($type, $host, $user, $pass, $name = "") {
	require_once('inc/lib/adodb/adodb.inc.php');
	// create ado-object
    $db = &ADONewConnection($type);
    // connect
    $result = @ $db->Connect($host, $user, $pass, $name);
    // check for error
    if ($db->ErrorNo() != 0 || !$result)
    	return false;
    // return db-connection
	return $db;
}

/**
 * send button
 */
function sendButton($name = "", $value = "") {
	send('<form name="setup" action="' . _FILE_THIS . '" method="post"><input type="Hidden" name="'.$name.'" value="'.$value.'"><input type="submit" value="Continue"></form><br>');
}

/**
 * send head
 */
function sendHead($title = "") {
	send('<html>');
	send('<head>');
	send('<title>'._TITLE.$title.'</title>');
	send('<style type="text/css">');
	send('font {font-family: Verdana,Helvetica; font-size: 12px}');
	send('body {font-family: Verdana,Helvetica; font-size: 12px}');
	send('p,td {font-family: Verdana,Helvetica; font-size: 12px}');
	send('h1 {font-family: Verdana,Helvetica; font-size: 15px}');
	send('h2 {font-family: Verdana,Helvetica; font-size: 14px}');
	send('h3 {font-family: Verdana,Helvetica; font-size: 13px}');
	send('</style>');
	send('</head>');
	send('<body topmargin="8" leftmargin="5" bgcolor="#FFFFFF">');
}

/**
 * send foot
 */
function sendFoot() {
	send('</body>');
	send('</html>');
}

/**
 * send - sends a string to the client
 */
function send($string = "") {
	echo $string;
	echo str_pad('', 4096)."\n";
	@ob_flush();
	@flush();
}

/**
 * displaySetupError - displays a setup message
 * @param $msg - message to display
 * @param $status - boolean, true for 'Ok:', false for 'Error:'
 */
function displaySetupMessage($msg="A problem occurred.", $status=false){
	$thisMsg='<p><font color="'.($status ? "green" : "red").'"><strong>';
	$thisMsg.= ($status ? "Ok" : "Error").': </strong></font>'.$msg.'</p>';
	send($thisMsg);
}

/**
 * initQueries - assign SQL to an array for insertion into db
 * @param $type - type of SQL data to get. Valid options are: install + upgrade
 * @param $version - version for upgrade-queries
 * $queries : array of 'type of queries' => 'db type' where type of queries are:
			- data - actual data used by tfb
			- test - queries to test db credentials provided by user
			- create - creation of tables used by tb
 */
 function initQueries($type, $version = "") {
 	global $queries;
	$queries = array();
	$queryFile = 'inc/install/';
	switch ($type) {
		case "install":
			$queryFile .= 'queries.install.php';
			break;
		case "upgrade":
			switch ($version) {
				case '2.1':
					$queryFile .= 'queries.upgrade.tf21.php';
					break;
				case '2.2':
					$queryFile .= 'queries.upgrade.tf22.php';
					break;
				case '2.3':
					$queryFile .= 'queries.upgrade.tf23.php';
					break;
				default:
					$queryFile .= 'queries.upgrade.'.$version.'.php';
					break;
			}
			break;
	}
	if ((@is_file($queryFile)) === true)
		require_once($queryFile);
	else
		die("Fatal Error. queries-file (".$queryFile.") is missing.");
}

?>