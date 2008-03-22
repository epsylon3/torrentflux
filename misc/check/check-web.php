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

// defines
define('_NAME', 'torrentflux-b4rt 1.0');
preg_match('|.*\s(\d+)\s.*|', '$Revision$', $revisionMatches);
define('_REVISION', $revisionMatches[1]);
define('_TITLE', _NAME.' - check-web - Revision '._REVISION);

// fields
$errors = 0;
$warnings = 0;
$dbsupported = 0;
$errorsMessages = array();
$warningsMessages = array();

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

// ob-start
if (@ob_get_level() == 0)
	@ob_start();

// head
sendHead();

// title
send('<h1>'._TITLE.'</h1>');

// PHP-Version
send('<h2>1. PHP-Version</h2>');
$phpVersion = 'PHP-Version : <em>'.PHP_VERSION.'</em> ';
if (PHP_VERSION < 4.3) {
	$phpVersion .= '<font color="red">Failed</font>';
	$errors++;
	array_push($errorsMessages, "PHP-Version : 4.3 or higher required.");
} else {
	$phpVersion .= '<font color="green">Passed</font>';
}
send($phpVersion);

// PHP-Extensions
send('<h2>2. PHP-Extensions</h2>');
send("<ul>");
$loadedExtensions = get_loaded_extensions();
// session
$session = '<li>session ';
if (in_array("session", $loadedExtensions)) {
	$session .= '<font color="green">Passed</font>';
} else {
	$session .= '<font color="red">Failed</font>';
	$errors++;
	array_push($errorsMessages, "PHP-Extensions : session required.");
}
send($session.'</li>');
// pcre
$pcre = '<li>pcre ';
if (in_array("pcre", $loadedExtensions)) {
	$pcre .= '<font color="green">Passed</font>';
} else {
	$pcre .= '<font color="red">Failed</font>';
	$errors++;
	array_push($errorsMessages, "PHP-Extensions : pcre required.");
}
send($pcre.'</li>');
// sockets
$sockets = '<li>sockets ';
if (in_array("sockets", $loadedExtensions)) {
	$sockets .= '<font color="green">Passed</font>';
} else {
	$sockets .= '<font color="red">Failed</font>';
	$warnings++;
	array_push($warningsMessages, "PHP-Extensions : sockets required for communication with fluxd. fluxd cannot work without sockets.");
}
send($sockets.'</li>');
//
send("</ul>");

// PHP-Configuration
send('<h2>3. PHP-Configuration</h2>');
send("<ul>");
// safe_mode
$safe_mode = '<li>safe_mode ';
if ((ini_get("safe_mode")) == 0) {
	$safe_mode .= '<font color="green">Passed</font>';
} else {
	$safe_mode .= '<font color="red">Failed</font>';
	$errors++;
	array_push($errorsMessages, "PHP-Configuration : safe_mode must be turned off.");
}
send($safe_mode.'</li>');
// allow_url_fopen
$allow_url_fopen = '<li>allow_url_fopen ';
if ((ini_get("allow_url_fopen")) == 1) {
	$allow_url_fopen .= '<font color="green">Passed</font>';
} else {
	$allow_url_fopen .= '<font color="red">Failed</font>';
	array_push($warningsMessages, "PHP-Configuration : allow_url_fopen must be turned on. some features wont work if it is turned off.");
	$warnings++;
}
send($allow_url_fopen.'</li>');
// register_globals
$register_globals = '<li>register_globals ';
if ((ini_get("register_globals")) == 0) {
	$register_globals .= '<font color="green">Passed</font>';
} else {
	$register_globals .= '<font color="red">Failed</font>';
	$errors++;
	array_push($errorsMessages, "PHP-Configuration : register_globals must be turned off.");
}
send($register_globals.'</li>');
//
send("</ul>");

// PHP-Database-Support
send('<h2>4. PHP-Database-Support</h2>');
send("<ul>");
// define valid db-types
$databaseTypes = array();
$databaseTypes['mysql'] = 'mysql_connect';
$databaseTypes['sqlite'] = 'sqlite_open';
$databaseTypes['postgres'] = 'pg_connect';
// test db-types
foreach ($databaseTypes as $databaseTypeName => $databaseTypeFunction) {
	$dbtest = '<li>'.$databaseTypeName.' ';
	if (function_exists($databaseTypeFunction)) {
		$dbtest .= '<font color="green">Passed</font>';
		$dbsupported++;
	} else {
		$dbtest .= '<font color="red">Failed</font>';
	}
	send($dbtest.'</li>');
}
send("</ul>");
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
send('<h2>5. OS-Specific ('.$osString.' '.php_uname('r').')</h2>');
switch (_OS) {
	case 1: // linux
		send('No Special Requirements on Linux-OS. <font color="green">Passed</font>');
		break;
	case 2: // bsd
		send("<ul>");
		// posix
		$posix = '<li>posix ';
		if ((function_exists('posix_geteuid')) && (function_exists('posix_getpwuid'))) {
			$posix .= '<font color="green">Passed</font>';
		} else {
			$posix .= '<font color="red">Failed</font>';
			$warnings++;
			array_push($warningsMessages, "OS-Specific : PHP-extension posix missing. some netstat-features wont work without.");
		}
		send($posix.'</li>');
		send("</ul>");
		break;
	case 0: // unknown
	default:
		send("OS not supported.<br>");
		$errors++;
		array_push($errorsMessages, "OS-Specific : ".$osString." not supported.");
		break;
}

// summary
send('<h1>Summary</h1>');

// state
$state = "<strong>State : ";
if (($warnings + $errors) == 0) {
	// good
	$state .= '<font color="green">Ok</font>';
	$state .= "</strong><br>";
	send($state);
	send(_NAME." should run on this system.");
} else {
	if (($errors == 0) && ($warnings > 0)) {
		// may run with flaws
		$state .= '<font color="orange">Warning</font>';
		$state .= "</strong><br>";
		send($state);
		send(_NAME." may run on this system, but there may be problems.");
	} else {
		// not ok
		$state .= '<font color="red">Failed</font>';
		$state .= "</strong><br>";
		send($state);
		send(_NAME." cannot run on this system.");
	}
}

// errors
if (count($errorsMessages) > 0) {
	send('<p><strong><font color="red">Errors : </font></strong><br>');
	send("<ul>");
	foreach ($errorsMessages as $errorsMessage) {
		send("<li>".$errorsMessage."</li>");
	}
	send("</ul>");
}

// warnings
if (count($warningsMessages) > 0) {
	send('<p><strong><font color="orange">Warnings : </font></strong><br>');
	send("<ul>");
	foreach ($warningsMessages as $warningsMessage) {
		send("<li>".$warningsMessage."</li>");
	}
	send("</ul>");
}

// foot
sendFoot();

// ob-end + exit
@ob_end_flush();
exit();

// -----------------------------------------------------------------------------
// functions
// -----------------------------------------------------------------------------

/**
 * send head-portion
 */
function sendHead() {
	send('<html>');
	send('<head>');
	send('<title>'._TITLE.'</title>');
	send('<style type="text/css">');
	send('font {font-family: Verdana,Helvetica; font-size: 12px}');
	send('body {font-family: Verdana,Helvetica; font-size: 12px}');
	send('p {font-family: Verdana,Helvetica; font-size: 12px}');
	send('h1 {font-family: Verdana,Helvetica; font-size: 15px}');
	send('h2 {font-family: Verdana,Helvetica; font-size: 14px}');
	send('h3 {font-family: Verdana,Helvetica; font-size: 13px}');
	send('</style>');
	send('</head>');
	send('<body topmargin="8" leftmargin="5" bgcolor="#FFFFFF">');
}

/**
 * send foot-portion
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

?>