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

// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

// common functions
require_once('inc/functions/functions.common.php');

// dir functions
require_once('inc/functions/functions.dir.php');

// is enabled ?
if ($cfg["enable_rename"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use rename");
	@error("rename is disabled. Action has been logged.", "", "");
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.rename.tmpl");

// process move and set vars
if ((isset($_REQUEST['start'])) && ($_REQUEST['start'] == true)) {
	$file = UrlHTMLSlashesDecode($_REQUEST['file']);
	$dir = UrlHTMLSlashesDecode($_REQUEST['dir']);
	$sourceDir = $cfg["path"].$dir;
	// only valid dirs + entries with permission
	if (!((tfb_isValidPath($sourceDir)) &&
		(tfb_isValidPath($sourceDir.$file)) &&
		(isValidEntry($file)) &&
		(hasPermission($dir, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL RENAME: ".$cfg["user"]." tried to rename ".$file." in ".$dir);
		@error("Illegal rename. Action has been logged.", "", "");
	}
	// template
	$tmpl->setvar('is_start', 1);
	$tmpl->setvar('file', $file);
	$tmpl->setvar('dir', $dir);
	$tmpl->setvar('_REN_FILE', $cfg['_REN_FILE']);
	$tmpl->setvar('_REN_STRING', $cfg['_REN_STRING']);
} else {
	$file = tfb_getRequestVar('fileFrom');
	$fileTo = tfb_getRequestVar('fileTo');
	$dir = tfb_getRequestVar('dir');
	$sourceDir = $cfg["path"].$dir;
	$targetDir = $cfg["path"].$dir.$fileTo;
	// Add slashes if magic_quotes off:
	if (get_magic_quotes_gpc() !== 1) {
		$targetDir = addslashes($targetDir);
		$sourceDir = addslashes($sourceDir);
	}
	// only valid dirs + entries with permission
	if (!((tfb_isValidPath($sourceDir)) &&
		(tfb_isValidPath($sourceDir.$file)) &&
		(tfb_isValidPath($targetDir)) &&
		(isValidEntry($file)) &&
		(isValidEntry($fileTo)) &&
		(hasPermission($dir, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL RENAME: ".$cfg["user"]." tried to rename ".$file." in ".$dir." to ".$fileTo);
		@error("Illegal rename. Action has been logged.", "", "");
	}
	// Use single quote to escape mv args:
	$cmd = "mv '".$sourceDir.$file."' '".$targetDir."'";
    $cmd .= ' 2>&1';
    $handle = popen($cmd, 'r' );
    $gotError = -1;
    $buff = fgets($handle);
    $gotError = $gotError + 1;
    pclose($handle);
    // template
    $tmpl->setvar('is_start', 0);
    $tmpl->setvar('messages', nl2br($buff));
    if ($gotError <= 0) {
		$tmpl->setvar('no_error', 1);
		$tmpl->setvar('fileFrom', $file);
		$tmpl->setvar('fileTo', $fileTo);
		$tmpl->setvar('_REN_DONE', $cfg['_REN_DONE']);
	} else {
		$tmpl->setvar('no_error', 0);
		$tmpl->setvar('_REN_ERROR', $cfg['_REN_ERROR']);
	}
}
//
tmplSetTitleBar($cfg["pagetitle"]." - ".$cfg['_REN_TITLE'], false);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>