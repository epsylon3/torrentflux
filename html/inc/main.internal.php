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

// start session
@session_start();

// unregister globals
if (@ini_get('register_globals')) {
	require_once('inc/functions/functions.compat.php');
	unregister_GLOBALS();
}

// init
if (isset($_SESSION['user'])) {
	// cache
	require_once("inc/main.cache.php");
	// set current user
	$currentUser = $_SESSION['user'];
	// check if cache set
	if (cacheIsSet($currentUser)) {
		// core classes
		require_once("inc/classes/CoreClasses.php");
		// core functions
		require_once('inc/functions/functions.core.php');
		// init cache
		cacheInit($currentUser);
		// init transfers-cache
		cacheTransfersInit();
		// initialize database
		dbInitialize();
	} else {
		// main.core
		require_once('inc/main.core.php');
	}
    $cfg["user"] = $currentUser;
} else {
	// reset user
    $cfg["user"] = "";
    $currentUser = "";
	// main.core
	require_once('inc/main.core.php');
}

// authenticate
if (isAuthenticated() == 1) {
	// check if we are locked
	if ($cfg["webapp_locked"] == 1) {
		// only superadmin can login when we are locked
		if (! IsSuperAdmin()) {
			@header('location: locked.php');
			exit();
		}
	}
} else {
	// try to auth with supplied credentials
	$credentials = getCredentials();
	if ($credentials !== false) {
		if (performAuthentication($credentials['username'], $credentials['password'], $credentials['md5pass']) == 1) {
			if (isAuthenticated() != 1) {
				@header('location: login.php');
				exit();
			}
			$currentUser = $cfg["user"];
			// check if we are locked
			if ($cfg["webapp_locked"] == 1) {
				// only superadmin can login when we are locked
				if (! IsSuperAdmin()) {
					@header('location: locked.php');
					exit();
				}
			}
		} else {
			@header('location: login.php');
			exit();
		}
	} else {
		@header('location: login.php');
		exit();
	}
}

// log the hit
AuditAction($cfg["constants"]["hit"], $_SERVER['PHP_SELF']);

// if at this moment no user is set there is something wrong
if(!isset($currentUser) || empty($currentUser))
{
	@header('location: login.php');
	exit();
}
	

// cache is not set
if (!cacheIsSet($currentUser)) {

	// login-check-tasks
	if (!isset($_SESSION['login_tasks'])) {
		// check for setup.php
		if (!isset($_SESSION['check']['setup'])) {
			$_SESSION['check']['setup'] = 1;
			// check for setup.php and upgrade.php
			if (@file_exists("setup.php") === true)
				@error("setup.php must be deleted", "index.php?iid=index", "");
		}
		// check for upgrade.php
		if (!isset($_SESSION['check']['upgrade'])) {
			$_SESSION['check']['upgrade'] = 1;
			if (@file_exists("upgrade.php") === true)
				@error("upgrade.php must be deleted", "index.php?iid=index", "");
		}
		// safe_mode
		if (!isset($_SESSION['check']['safe_mode'])) {
			$_SESSION['check']['safe_mode'] = 1;
			if (@ini_get('safe_mode') == '1' || strtolower(@ini_get('safe_mode')) == 'on')
				@error("safe_mode enabled", "index.php?iid=index", "", array("tf-b4rt will not run with this setting", "PHP-setting : safe_mode"));
		}
		// allow_url_fopen
		if (!isset($_SESSION['check']['allow_url_fopen'])) {
			$_SESSION['check']['allow_url_fopen'] = 1;
			if (@ini_get('allow_url_fopen') == '0' || strtolower(@ini_get('allow_url_fopen')) == 'off')
				@error("allow_url_fopen disabled", "index.php?iid=index", "", array("tf-b4rt will not run flawless with this setting", "PHP-setting : allow_url_fopen"));
		}
		// register_globals
		if (!isset($_SESSION['check']['register_globals'])) {
			$_SESSION['check']['register_globals'] = 1;
			if (@ini_get('register_globals') == '1' || strtolower(@ini_get('register_globals')) == 'on')
				@error("register_globals enabled", "index.php?iid=index", "", array("tf-b4rt may not run flawless with this setting", "PHP-setting : register_globals"));
		}
	}

	// set admin-var
	$cfg['isAdmin'] = IsAdmin();

	// load some settings from users-table
	$sql = "SELECT hide_offline, theme, language_file FROM tf_users WHERE user_id=".$db->qstr($cfg["user"]);
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	list ($cfg["hide_offline"], $cfg["theme"], $cfg["language_file"]) = $recordset->FetchRow();

	// Check for valid language file
	if (!ereg('^[^./][^/]*$', $cfg["language_file"])) {
		AuditAction($cfg["constants"]["error"], "LANGUAGE VARIABLE CHANGE ATTEMPT: ".$cfg["language_file"]." from ".$cfg["user"]);
		$cfg["language_file"] = $cfg["default_language"];
	}
	if (!is_file("inc/language/".$cfg["language_file"]))
		$cfg["language_file"] = $cfg["default_language"];

	// load per user settings
	// activated ?
	if ($cfg["enable_personal_settings"] == 1)
		loadUserSettingsToConfig($cfg["uid"]);

	$cfg["theme"] = CheckandSetUserTheme();
	// theme
	require_once("themes/".$cfg["theme"]."/index.php");

	// load language
	loadLanguageFile($cfg["language_file"]);

	// set cache
	cacheSet($currentUser);

	// login-tasks
	if (!isset($_SESSION['login_tasks'])) {

		// check main-directories.
		checkMainDirectories();

		// maintenance-run
		require_once("inc/classes/MaintenanceAndRepair.php");
		MaintenanceAndRepair::maintenance(MAINTENANCEANDREPAIR_TYPE_STD);
		$_SESSION['next_int_maintenance'] = null;

		// set flag
		$_SESSION['login_tasks'] = 1;
	}

	// set transfers-cache
	cacheTransfersSet();

	// set session-settings
	$_SESSION['settings']['index_meta_refresh'] = ($cfg["enable_index_meta_refresh"] != 0) ? 1 : 0;
	$_SESSION['settings']['index_ajax_update'] = ($cfg["enable_index_ajax_update"] != 0) ? 1 : 0;
	$_SESSION['settings']['index_show_seeding'] = ($cfg["index_show_seeding"] != 0) ? 1 : 0;

	// xfer
	if ($cfg['enable_xfer'] == 1) {
		// xfer-init
		Xfer::init();
	}
}
else
{
	// Check for valid theme
	if(isset($cfg["theme"]))
		$cfg["theme"] = CheckandSetUserTheme();

	// Run internal maintenance regularly
	if (!empty($_SESSION['next_int_maintenance']) && $_SESSION['next_int_maintenance'] < time()) {
		require_once("inc/classes/MaintenanceAndRepair.php");
		MaintenanceAndRepair::maintenance(MAINTENANCEANDREPAIR_TYPE_INT);
		$_SESSION['next_int_maintenance'] = null;
	}
}

// schedule next internal maintenance if needed
if (empty($_SESSION['next_int_maintenance']))
	$_SESSION['next_int_maintenance'] = time() + 2 * 3600 + mt_rand(-1200, 1200);	// 2h (+/- 20m)

// free space in MB var
$cfg["free_space"] = @disk_free_space($cfg["path"]) / 1048576;

// drive space var
$cfg['driveSpace'] = getDriveSpace($cfg["path"]);

// free space formatted var
$cfg['freeSpaceFormatted'] = formatFreeSpace($cfg["free_space"]);

// Fluxd
Fluxd::initialize();

// Qmgr
FluxdServiceMod::initializeServiceMod('Qmgr');

// xfer
if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1)) {
	// set xfer-newday
	Xfer::setNewday();
}

// vlib
require_once("inc/lib/vlib/vlibTemplate.php");

?>