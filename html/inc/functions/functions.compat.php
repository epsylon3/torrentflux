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
 * unregister_GLOBALS
 *
 * function is taken from :
 * http://php.net/manual/pl/faq.misc.php#faq.misc.registerglobals
 *
 */
function unregister_GLOBALS() {
	// Might want to change this perhaps to a nicer error
	if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS']))
		die('GLOBALS overwrite attempt detected');
	// Variables that shouldn't be unset
	$noUnset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');
	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES,
		isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
	foreach ($input as $k => $v) {
		if (!in_array($k, $noUnset) && isset($GLOBALS[$k]))
			unset($GLOBALS[$k]);
	}
}

?>