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
if ($cfg["enable_rar"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use uncompress");
	@error("uncompress is disabled. Action has been logged.", "", "");
}

// check the needed bins
// php
if (@file_exists($cfg['bin_php']) !== true) {
	@error("Required binary could not be found", "", "",
		(
			($cfg['isAdmin'])
			? array(
				'PHP-cli is required for uncompress',
				'Specified PHP-cli-binary does not exist: '.$cfg['bin_php'],
				'Check Settings on Admin-Server-Settings Page')
			: array('Please contact an Admin')
		)
	);
}
// unrar / unzip
$uncompbin = "";
$uncomplabel = "";
if (isset($_REQUEST['type'])) {
	if (strcasecmp('rar', $_REQUEST['type']) == 0) {
		$uncompbin = $cfg['bin_unrar'];
		$uncomplabel = "unrar";
	} else if (strcasecmp('zip', $_REQUEST['type']) == 0) {
		$uncompbin = $cfg['bin_unzip'];
		$uncomplabel = "unzip";
	}
}
if (($uncompbin != "") && (@file_exists($uncompbin) !== true)) {
	@error("Required binary could not be found", "", "",
		(
			($cfg['isAdmin'])
			? array(
				$uncomplabel.' is required',
				'Specified '.$uncomplabel.' does not exist: '.$uncompbin,
				'Check Settings on Admin-Server-Settings Page')
			: array('Please contact an Admin')
		)
	);
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.uncomp.tmpl");

// process
if ((isset($_POST['exec'])) && ($_POST['exec'] == true)) {
	$file = tfb_getRequestVar('file');
	$dir = tfb_getRequestVar('dir');
	// only valid dirs + entries with permission
	$fileS = str_replace($cfg["path"], '', $file);
	$dirS = str_replace($cfg["path"], '', $dir);
	if (!((tfb_isValidPath($file)) &&
		(isValidEntry(basename($file))) &&
		(hasPermission($fileS, $cfg["user"], 'r')) &&
		(hasPermission($dirS, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL UNCOMPRESS-ACCESS: ".$cfg["user"]." tried to uncompress ".$fileS." in ".$dirS);
		@error("Illegal access. Action has been logged.", "", "");
	}
	//
	$passwd = isset($_POST['passwd']) ? $_POST['passwd'] : "";
	if ($passwd == "")
		$passwd = "-";
	$cmd = $cfg['bin_php']." bin/uncompress.php";
	$cmd .= " ".tfb_shellencode($file);
	$cmd .= " ".tfb_shellencode($dir);
	$cmd .= " ".tfb_shellencode(tfb_getRequestVar('type'));
	if (strcasecmp('rar', $_REQUEST['type']) == 0)
		$cmd .= " ".tfb_shellencode($cfg['bin_unrar']);
	else if (strcasecmp('zip', $_REQUEST['type']) == 0)
		$cmd .= " ".tfb_shellencode($cfg['bin_unzip']);
	$cmd .= " ".tfb_shellencode($passwd);
	// os-switch
	switch ($cfg["_OS"]) {
		case 1: // linux
			$cmd .= ' 2>&1';
			break;
		case 2: // bsd (snip from khr0n0s)
			$cmd .= ' 2>&1 &';
			break;
	}
	@session_write_close();
	$handle = popen($cmd, 'r' );
	$buff= "";
	while (!feof($handle))
		$buff .= fgets($handle,30);
	$tmpl->setvar('buff', nl2br($buff));
	pclose($handle);
}

// set vars
if ((isset($_REQUEST['file'])) && ($_REQUEST['file'] != "")) {
	$file = tfb_getRequestVar('file');
	$dir = tfb_getRequestVar('dir');
	$file = str_replace($cfg["path"], '', $file);
	$dir = str_replace($cfg["path"], '', $dir);
	$targetFile = $cfg["path"].$file;
	// only valid dirs + entries with permission
	if (!((tfb_isValidPath($targetFile)) &&
		(isValidEntry(basename($targetFile))) &&
		(hasPermission($file, $cfg["user"], 'r')) &&
		(hasPermission($dir, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL UNCOMPRESS-ACCESS: ".$cfg["user"]." tried to uncompress ".$file);
		@error("Illegal access. Action has been logged.", "", "");
	}
	//
	$tmpl->setvar('is_file', 1);
	$tmpl->setvar('url_file', str_replace('%2F', '/', urlencode($cfg["path"].$file)));
	$tmpl->setvar('url_dir', str_replace('%2F', '/', urlencode($cfg["path"].$dir)));
	$tmpl->setvar('type', tfb_getRequestVar('type'));
} else {
	$tmpl->setvar('is_file', 0);
}
//
tmplSetTitleBar('Uncompress File', false);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>