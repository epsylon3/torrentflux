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
if ($cfg["enable_sfvcheck"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use checkSFV");
	@error("checkSFV is disabled", "index.php?iid=index", "");
}

// check the needed bins
// cksfv
if (@file_exists($cfg['bin_cksfv']) !== true) {
	@error("Required binary could not be found", "", "",
		(
			($cfg['isAdmin'])
			? array(
				'cksfv is required for sfv-checking',
				'Specified cksfv-binary does not exist: '.$cfg['bin_cksfv'],
				'Check Settings on Admin-Server-Settings Page')
			: array('Please contact an Admin')
		)
	);
}

// target
$dir = tfb_getRequestVar('dir');
$file = tfb_getRequestVar('file');

// validate dir + file
if (!empty($dir)) {
	$dirS = str_replace($cfg["path"], '', $dir);
	if (!((tfb_isValidPath($dir)) &&
		(hasPermission($dirS, $cfg["user"], 'r')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL SFV-ACCESS: ".$cfg["user"]." tried to check ".$dirS);
		@error("Illegal access. Action has been logged.", "", "");
	}
}
if (!empty($file)) {
	$fileS = str_replace($cfg["path"], '', $file);
	if (!((tfb_isValidPath($file)) &&
		(isValidEntry(basename($file))) &&
		(hasPermission($fileS, $cfg["user"], 'r')))) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL SFV-ACCESS: ".$cfg["user"]." tried to check ".$fileS);
		@error("Illegal access. Action has been logged.", "", "");
	}
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.checkSFV.tmpl");

// process
$cmd = $cfg['bin_cksfv'] . ' -C ' . tfb_shellencode($dir) . ' -f ' . tfb_shellencode($file);
$handle = popen($cmd . ' 2>&1', 'r' );
$buff = (isset($cfg["debuglevel"]) && $cfg["debuglevel"] == 2)
	? "<strong>Debug:</strong> Evaluating command:<br/><br/><pre>".tfb_htmlencode($cmd)."</pre><br/>Output follows below:<br/>"
	: "";
$buff .= "<pre>";
while (!feof($handle))
	$buff .= tfb_htmlencode(@fgets($handle, 30));
$tmpl->setvar('buff', $buff);
pclose($handle);
$buff.= "</pre>";

// set vars
tmplSetTitleBar($cfg["pagetitle"].' - checkSFV', false);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>