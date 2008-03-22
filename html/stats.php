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

// core-classes
require_once("inc/classes/CoreClasses.php");

// core functions
require_once('inc/functions/functions.core.php');

// Stats-class
require_once('inc/classes/Stats.php');

// start session
@session_start();

// unregister globals
if (@ini_get('register_globals')) {
	require_once('inc/functions/functions.compat.php');
	unregister_GLOBALS();
}

// config
if ((isset($_SESSION['user'])) && (cacheIsSet($_SESSION['user']))) {
	// db-config
	require_once('inc/config/config.db.php');
	// initialize database
	dbInitialize();
	// init cache
	cacheInit($_SESSION['user']);
	// init transfers-cache
	cacheTransfersInit();
} else {
	// main.core
	require_once('inc/main.core.php');
	// set transfers-cache
	cacheTransfersSet();
}

// public-stats-switch
switch ($cfg['stats_enable_public']) {
	case 1:
		// load default-language and transfers if cache not set
		if ((!isset($_SESSION['user'])) || (!(cacheIsSet($_SESSION['user'])))) {
			// common functions
			require_once('inc/functions/functions.common.php');
			// lang file
			loadLanguageFile($cfg["default_language"]);
		}
		// Fluxd
		Fluxd::initialize();
		// Qmgr
		FluxdServiceMod::initializeServiceMod('Qmgr');
		// public stats... show all .. we set the user to superadmin
		$superAdm = GetSuperAdmin();
		if ((isset($superAdm)) && ($superAdm != "")) {
			$cfg["user"] = $superAdm;
			$cfg['isAdmin'] = true;
		} else {
			@ob_end_clean();
			exit();
		}
		break;
	case 0:
	default:
		// main.internal
		require_once("inc/main.internal.php");
}

// process request
Stats::processRequest($_REQUEST);

?>