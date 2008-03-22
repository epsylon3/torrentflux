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

// main.internal
require_once("inc/main.internal.php");

// all functions
require_once('inc/functions/functions.all.php');

// superadmin functions
require_once('inc/functions/functions.superadmin.php');

// defines
define('_DIR_BACKUP','.backup');
define('_URL_HOME','http://tf-b4rt.berlios.de/');
define('_URL_RELEASE','http://tf-b4rt.berlios.de/current');
define('_SUPERADMIN_URLBASE','http://tf-b4rt.berlios.de/');
define('_SUPERADMIN_PROXY','tf-b4rt.php');
define('_FILE_CHECKSUMS_PRE','checksums-');
define('_FILE_CHECKSUMS_SUF','.txt');
define('_FILE_THIS', 'superadmin.php');
define('_UPDATE_ARCHIVE','update.tar.bz2');

// global fields
$error = "";
$statusImage = "black.gif";
$statusMessage = "";
$htmlTitle = "";
$htmlTop = "";
$htmlMain = "";

// authenticate first
superadminAuthentication();

// fopen
@ini_set("allow_url_fopen", "1");

// version
if (is_file('version.php'))
	require_once('version.php');
else
	@error("version.php is missing");

// -----------------------------------------------------------------------------
// transfers "t"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["t"]))
	sa_transfers(tfb_getRequestVar("t"));

// -----------------------------------------------------------------------------
// processes "p"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["p"]))
	sa_processes(tfb_getRequestVar("p"));

// -----------------------------------------------------------------------------
// maintenance "m"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["m"]))
	sa_maintenance(tfb_getRequestVar("m"));

// -----------------------------------------------------------------------------
// backup "b"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["b"]))
	sa_backup(tfb_getRequestVar("b"));

// -----------------------------------------------------------------------------
// log "l"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["l"]))
	sa_log(tfb_getRequestVar("l"));

// -----------------------------------------------------------------------------
// misc "y"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["y"]))
	sa_misc(tfb_getRequestVar("y"));

// -----------------------------------------------------------------------------
// tfb "z"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["z"]))
	sa_tfb(tfb_getRequestVar("z"));

// -----------------------------------------------------------------------------
// update "u"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["u"]))
	sa_update(tfb_getRequestVar("u"));

// -----------------------------------------------------------------------------
// fluxd "f"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["f"]))
	sa_fluxd(tfb_getRequestVar("f"));

// -----------------------------------------------------------------------------
// fluazu "a"
// -----------------------------------------------------------------------------
if (isset($_REQUEST["a"]))
	sa_fluazu(tfb_getRequestVar("a"));

// -----------------------------------------------------------------------------
// default
// -----------------------------------------------------------------------------
buildPage("_");
printPage();
exit();

?>