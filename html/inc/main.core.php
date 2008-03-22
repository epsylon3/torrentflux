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

// cache
require_once('inc/main.cache.php');

// core classes
require_once("inc/classes/CoreClasses.php");

// core functions
require_once('inc/functions/functions.core.php');

// common functions
require_once('inc/functions/functions.common.php');

// torrentflux-b4rt Version from version-file
if (@is_file('version.php')) {
	require_once('version.php');
	$cfg["version"] = _VERSION;
} else {
	$cfg["version"] =  "unknown";
}

// constants
$cfg["constants"] = array();
$cfg["constants"]["url_upload"] = "URL Upload";
$cfg["constants"]["reset_owner"] = "Reset Owner";
$cfg["constants"]["start_torrent"] = "Started Transfer";
$cfg["constants"]["stop_transfer"] = "Stopped Transfer";
$cfg["constants"]["queued_transfer"] = "Added to Queue";
$cfg["constants"]["unqueued_transfer"] = "Removed from Queue";
$cfg["constants"]["QManager"] = "QManager";
$cfg["constants"]["fluxd"] = "fluxd";
$cfg["constants"]["access_denied"] = "ACCESS DENIED";
$cfg["constants"]["delete_transfer"] = "Delete Transfer";
$cfg["constants"]["fm_delete"] = "File Manager Delete";
$cfg["constants"]["fm_download"] = "File Download";
$cfg["constants"]["kill_transfer"] = "Kill Transfer";
$cfg["constants"]["file_upload"] = "File Upload";
$cfg["constants"]["error"] = "ERROR";
$cfg["constants"]["hit"] = "HIT";
$cfg["constants"]["update"] = "UPDATE";
$cfg["constants"]["admin"] = "ADMIN";
$cfg["constants"]["debug"] = "DEBUG";
asort($cfg["constants"]);

// valid file extensions
$cfg["file_types_array"] = array(".torrent", ".wget", ".nzb");
// do NOT (!) touch the next 2 lines
$cfg["file_types_regexp"] = implode("|", $cfg["file_types_array"]);
$cfg["file_types_label"] = implode(", ", $cfg["file_types_array"]);

// upload-limit (metafiles)
$cfg["upload_limit"] = 8000000;

// username
$cfg["user"] = "";

// ip + hostname
if (isset($_SERVER['REMOTE_ADDR'])) {
	$cfg['ip'] = htmlentities($_SERVER['REMOTE_ADDR'], ENT_QUOTES);
	$cfg['ip_resolved'] = htmlentities(@gethostbyaddr($_SERVER['REMOTE_ADDR']), ENT_QUOTES);
} else {
	$cfg['ip'] = "127.0.0.1";
	$cfg['ip_resolved'] = "localhost";
}

// user-agent
$cfg['user_agent'] = (isset($_SERVER['HTTP_USER_AGENT']))
	? htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES)
	: "torrentflux-b4rt/".$cfg["version"];

// get os
$osString = @php_uname('s');
if (isset($osString)) {
    if (!(stristr($osString, 'linux') === false)) /* linux */
    	$cfg["_OS"] = 1;
    elseif (!(stristr($osString, 'bsd') === false)) /* bsd */
    	$cfg["_OS"] = 2;
    elseif (!(stristr($osString, 'darwin') === false)) /* darwin */
        $cfg["_OS"] = 2;
    else /* well... linux ;) */
    	$cfg["_OS"] = 1;
} else { /* well... linux ;) */
	$cfg["_OS"] = 1;
}

// main menu
$cfg['mainMenu'] = array(
	"index" => "home",
	"readrss" => "home",
	"multiup" => "home",
	"serverStats" => "home",
	"images" => "home",
	"dir" => "dir",
	"history" => "history",
	"profile" => "profile",
	"readmsg" => "msg",
	"message" => "msg",
	"admin" => "admin"
);

// db
if (@is_file('inc/config/config.db.php')) {

	// db-config
	require_once('inc/config/config.db.php');

	// check db-type
	$databaseTypes = array();
	$databaseTypes['mysql'] = 'mysql_connect';
	$databaseTypes['sqlite'] = 'sqlite_open';
	$databaseTypes['postgres'] = 'pg_connect';
	if (array_key_exists($cfg["db_type"], $databaseTypes)) {
		if (!function_exists($databaseTypes[$cfg["db_type"]]))
			@error("Database Problems", "", "", array('This PHP installation does not have support for '.$cfg["db_type"].' built into it. Please reinstall PHP and ensure support for the selected database is built in.'));
	} else {
		@error("Database Problems", "", "", array('Error in database-config, database-type '.$cfg["db_type"].' is not supported.', "Check your database-config-file. (inc/config/config.db.php)"));
	}

	// initialize database
	dbInitialize();

	// load global settings
	loadSettings('tf_settings');

	// load dir-settings
	loadSettings('tf_settings_dir');

	// load stats-settings
	loadSettings('tf_settings_stats');

	// load users
	$arUsers = GetUsers();
	$cfg['users'] = ((isset($arUsers)) && (is_array($arUsers)))
		? $arUsers
		: array($cfg['user']);

	// load links
	$arLinks = GetLinks();
	if ((isset($arLinks)) && (is_array($arLinks))) {
		$linklist = array();
		foreach ($arLinks as $link) {
			array_push($linklist, array(
				'link_url' => $link['url'],
				'link_sitename' => $link['sitename']
				)
			);
		}
		$cfg['linklist'] = $linklist;
	}

	// Path to where the meta files will be stored... usually a sub of $cfg["path"]
	$cfg["transfer_file_path"] = $cfg["path"].".transfers/";

	// Free space in MB
	$cfg["free_space"] = @disk_free_space($cfg["path"]) / (1048576);

} else {

	// error in cli-mode, send redir in webapp
    if (empty($argv[0])) {
    	if (!isset($_SESSION['check']['dbconf'])) {
    		$_SESSION['check']['dbconf'] = 1;
	    	// redir to login ... (which may redir to upgrade.php / setup.php)
			@ob_end_clean();
			@header("location: login.php");
			exit();
    	} else {
    		@error("database-settings-file config.db.php is missing");
    	}
    } else {
		@error("database-settings-file config.db.php is missing");
    }
}

// load configs
$configs = array(
	'config.clients.php' => 'clients-config-file config.clients.php is missing',
	'config.profile.php' => 'profile-config-file config.profile.php is missing',
	'config.fluxd.php'   => 'fluxd-config-file config.fluxd.php is missing'
);
foreach ($configs as $configFile => $configError) {
	if (@is_file('inc/config/'.$configFile)) {
		// load config-file
		require_once('inc/config/'.$configFile);
	} else {
		// error
		@error($configError);
    }
}

?>