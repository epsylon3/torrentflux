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

// file-checks
// check for upgrade.php
if (!isset($_SESSION['check']['upgrade'])) {
	if (@file_exists("upgrade.php") === true) {
		@ob_end_clean();
		@header("location: upgrade.php");
		exit();
	}
}
// check for setup.php
if (!isset($_SESSION['check']['setup'])) {
	if (@file_exists("setup.php") === true) {
		@ob_end_clean();
		@header("location: setup.php");
		exit();
	}
}

// main.core
require_once('inc/main.core.php');

// load default-language
loadLanguageFile($cfg["default_language"]);

// Check for valid theme
$cfg["default_theme"] = CheckandSetDefaultTheme();

// default-theme
require("themes/".$cfg["default_theme"]."/index.php");

// set admin-var
$cfg['isAdmin'] = false;

// vlib
require_once("inc/lib/vlib/vlibTemplate.php");

?>