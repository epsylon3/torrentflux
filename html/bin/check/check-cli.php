#!/usr/bin/env php
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

// prevent invocation from web
if (empty($argv[0])) die();
if (isset($_REQUEST['argv'])) die();

/******************************************************************************/

// defines
define('_NAME', 'torrentflux-b4rt 1.0');
preg_match('|.* (\d+) .*|', '$Revision$', $revisionMatches);
define('_REVISION', $revisionMatches[1]);
define('_TITLE', _NAME.' - check-cli - Revision '._REVISION);

// fields
$errors = 0;
$warnings = 0;
$dbsupported = 0;
$errorsMessages = array();
$warningsMessages = array();

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

// title
echo _TITLE."\n";

// PHP-Version
echo '1. PHP-Version'."\n";

// version
$phpVersion = 'PHP-Version : '.PHP_VERSION.' ';
if (PHP_VERSION < 4.3) {
	$phpVersion .= 'Failed';
	$errors++;
	array_push($errorsMessages, "PHP-Version : 4.3 or higher required.");
} else {
	$phpVersion .= 'Passed';
}
echo $phpVersion."\n";
// cli
$phpcli = 'PHP-SAPI : ';
$phpsapi = php_sapi_name();
if ($phpsapi != 'cli') {
	$phpcli .= $phpsapi . ' Failed';
	$errors++;
	array_push($errorsMessages, "PHP-SAPI : CLI version of PHP required.");
} else {
	$phpcli .= $phpsapi . ' Passed';
}
echo $phpcli."\n";

// PHP-Extensions
echo '2. PHP-Extensions'."\n";

$loadedExtensions = get_loaded_extensions();
// pcre
$pcre = 'pcre ';
if (in_array("pcre", $loadedExtensions)) {
	$pcre .= 'Passed';
} else {
	$pcre .= 'Failed';
	$errors++;
	array_push($errorsMessages, "PHP-Extensions : pcre required.");
}
echo $pcre."\n";
// sockets
$sockets = 'sockets ';
if (in_array("sockets", $loadedExtensions)) {
	$sockets .= 'Passed';
} else {
	$sockets .= 'Failed';
	$warnings++;
	array_push($warningsMessages, "PHP-Extensions : sockets required for communication with fluxd. fluxd cannot work without sockets.");
}
echo $sockets."\n";
//

// PHP-Configuration
echo '3. PHP-Configuration'."\n";

// safe_mode
$safe_mode = 'safe_mode ';
if ((ini_get("safe_mode")) == 0) {
	$safe_mode .= 'Passed';
} else {
	$safe_mode .= 'Failed';
	$errors++;
	array_push($errorsMessages, "PHP-Configuration : safe_mode must be turned off.");
}
echo $safe_mode."\n";
// allow_url_fopen
$allow_url_fopen = 'allow_url_fopen ';
if ((ini_get("allow_url_fopen")) == 1) {
	$allow_url_fopen .= 'Passed';
} else {
	$allow_url_fopen .= 'Failed';
	array_push($warningsMessages, "PHP-Configuration : allow_url_fopen must be turned on. some features wont work if it is turned off.");
	$warnings++;
}
echo $allow_url_fopen."\n";
// register_globals
$register_globals = 'register_globals ';
if ((ini_get("register_globals")) == 0) {
	$register_globals .= 'Passed';
} else {
	$register_globals .= 'Failed';
	$errors++;
	array_push($errorsMessages, "PHP-Configuration : register_globals must be turned off.");
}
echo $register_globals."\n";
//

// PHP-Database-Support
echo '4. PHP-Database-Support'."\n";

// define valid db-types
$databaseTypes = array();
$databaseTypes['mysql'] = 'mysql_connect';
$databaseTypes['sqlite'] = 'sqlite_open';
$databaseTypes['postgres'] = 'pg_connect';
// test db-types
foreach ($databaseTypes as $databaseTypeName => $databaseTypeFunction) {
	$dbtest = $databaseTypeName.' ';
	if (function_exists($databaseTypeFunction)) {
		$dbtest .= 'Passed';
		$dbsupported++;
	} else {
		$dbtest .= 'Failed';
	}
	echo $dbtest."\n";
}
// db-state
if ($dbsupported == 0) {
	$errors++;
	array_push($errorsMessages, "PHP-Database-Support : no supported database-type found.");
}

// OS-Specific
// get os
$osString = php_uname('s');
if (isset($osString)) {
    if (!(stristr($osString, 'linux') === false)) /* linux */
    	define('_OS', 1);
    else if (!(stristr($osString, 'bsd') === false)) /* bsd */
    	define('_OS', 2);
    else
    	define('_OS', 0);
} else {
	define('_OS', 0);
}
echo '5. OS-Specific ('.$osString.' '.php_uname('r').')'."\n";
switch (_OS) {
	case 1: // linux
		echo 'No Special Requirements on Linux-OS. Passed'."\n";
		break;
	case 2: // bsd
		// posix
		$posix = 'posix ';
		if ((function_exists('posix_geteuid')) && (function_exists('posix_getpwuid'))) {
			$posix .= 'Passed';
		} else {
			$posix .= 'Failed';
			$warnings++;
			array_push($warningsMessages, "OS-Specific : PHP-extension posix missing. some netstat-features wont work without.");
		}
		echo $posix."\n";
		break;
	case 0: // unknown
	default:
		echo "OS not supported.\n";
		$errors++;
		array_push($errorsMessages, "OS-Specific : ".$osString." not supported.");
		break;
}

// summary
echo '----- Summary -----'."\n";

// state
$state = "State : ";
if (($warnings + $errors) == 0) {
	// good
	$state .= 'Ok'."\n";
	echo $state;
	echo _NAME." should run on this system.\n";
} else {
	if (($errors == 0) && ($warnings > 0)) {
		// may run with flaws
		$state .= 'Warning'."\n";
		echo $state;
		echo _NAME." may run on this system, but there may be problems.\n";
	} else {
		// not ok
		$state .= 'Failed'."\n";
		echo $state;
		echo _NAME." cannot run on this system.\n";
	}
}

// errors
if (count($errorsMessages) > 0) {
	echo ('Errors :'."\n");
	foreach ($errorsMessages as $errorsMessage) {
		echo " - ".$errorsMessage."\n";
	}
}

// warnings
if (count($warningsMessages) > 0) {
	echo ('Warnings :'."\n");
	foreach ($warningsMessages as $warningsMessage) {
		echo " - ".$warningsMessage."\n";
	}
}

//  exit
exit();

?>