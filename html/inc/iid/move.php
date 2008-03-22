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
if ($cfg["enable_move"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use move");
	@error("move is disabled. Action has been logged.", "", "");
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.move.tmpl");

// set vars
if((isset($_REQUEST['start'])) && ($_REQUEST['start'] == true)) {
	$path = stripslashes($_REQUEST['path']);
	$dir = tfb_getRequestVar('path');
	$source = $cfg["path"].$dir;
	// only valid paths + entries with permission
	if (!((tfb_isValidPath($source)) &&
		(isValidEntry(basename($source))) &&
		(hasPermission($dir, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL MOVE: ".$cfg["user"]." tried to move ".$dir);
		@error("Illegal move. Action has been logged.", "", "");
	}
	//
	$tmpl->setvar('is_start', 1);
	$tmpl->setvar('path', $path);
	$tmpl->setvar('_MOVE_STRING', $cfg['_MOVE_STRING']);
	$tmpl->setvar('_MOVE_FILE', $cfg['_MOVE_FILE']);
	if ((isset($cfg["move_paths"])) && (strlen($cfg["move_paths"]) > 0)) {
		$tmpl->setvar('move_start', 1);
		$dirs = split(":", trim($cfg["move_paths"]));
		$dir_list = array();
		foreach ($dirs as $dir) {
			$target = trim($dir);
			if ((strlen($target) > 0) && ((substr($target, 0, 1)) != ";"))
				array_push($dir_list, array('target' => $target));
		}
		$tmpl->setloop('dir_list', $dir_list);
	} else {
		$tmpl->setvar('move_start', 0);
	}
} else {
	$file = $_POST['file'];
	$targetDir = "";
	if (isset($_POST['dest'])) {
		 $tempDir = trim(rawurldecode($_POST['dest']));
		 if (strlen($tempDir) > 0) {
			$targetDir = $tempDir;
		 } else {
			if (isset($_POST['selector']))
				$targetDir = trim(urldecode($_POST['selector']));
		 }
	}
	// only valid dirs + entries with permission
	if (!((tfb_isValidPath($cfg["path"].$file)) &&
		(tfb_isValidPath($targetDir)) &&
		(isValidEntry(basename($cfg["path"].$file))) &&
		(hasPermission($file, $cfg["user"], 'w')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL MOVE: ".$cfg["user"]." tried to move ".$file." to ".$targetDir);
		@error("Illegal move. Action has been logged.", "", "");
	}
	// we need absolute paths or stuff will end up in docroot
	// inform user .. don't move it into a fallback-dir which may be a hassle
	$dirValid = true;
	if (strlen($targetDir) <= 0) {
		 $dirValid = false;
	} else {
		if ($targetDir{0} != '/') {
			$tmpl->setvar('not_absolute', 1);
			$dirValid = false;
		} else {
			$tmpl->setvar('not_absolute', 0);
		}
	}
	$tmpl->setvar('is_start', 0);
	$tmpl->setvar('targetDir', $targetDir);
	// check dir
	if (($dirValid) && (checkDirectory($targetDir, 0777))) {
		$tmpl->setvar('is_valid', 1);
		$targetDir = checkDirPathString($targetDir);
		// Add slashes if magic_quotes off:
		if (get_magic_quotes_gpc() !== 1)
			$targetDir = addslashes($targetDir);
		// Use single quote to escape mv args:
		$cmd = "mv '".$cfg["path"].$file."' '".$targetDir."'";
		$cmd .= ' 2>&1';
		$handle = popen($cmd, 'r');
		// get the output and print it.
		$gotError = -1;
		$buff= "";
		while (!feof($handle)) {
			$buff .= @fgets($handle,30);
			$gotError = $gotError + 1;
		}
		$tmpl->setvar('messages', nl2br($buff));
		pclose($handle);
		if ($gotError <= 0) {
			$tmpl->setvar('got_no_error', 1);
			$tmpl->setvar('file', $file);
		} else {
			$tmpl->setvar('got_no_error', 0);
		}
	} else {
		$tmpl->setvar('is_valid', 1);
	}
}

//
tmplSetTitleBar($cfg["pagetitle"]." - ".$cfg['_MOVE_FILE_TITLE'], false);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>