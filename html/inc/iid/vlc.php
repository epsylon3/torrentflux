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

// vlc class
require_once('inc/classes/Vlc.php');

// common functions
require_once('inc/functions/functions.common.php');

// dir functions
require_once('inc/functions/functions.dir.php');

// is enabled ?
if ($cfg["enable_vlc"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use vlc");
	@error("vlc is disabled. Action has been logged.", "", "");
}

// check the needed bins
// vlc
if (@file_exists($cfg['bin_vlc']) !== true) {
	@error("Required binary could not be found", "", "",
		(
			($cfg['isAdmin'])
			? array(
				'vlc is required for vlc-streaming',
				'Specified vlc-binary does not exist: '.$cfg['bin_vlc'],
				'Check Settings on Admin-Server-Settings Page')
			: array('Please contact an Admin')
		)
	);
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.vlc.tmpl");

// pageop
//
// * default
// * start
// * stop
//
$pageop = tfb_getRequestVar('pageop');
$tmpl->setvar('pageop', (empty($pageop)) ? "default" : $pageop);
// op-switch
switch ($pageop) {
	default:
	case "default":
		// fill lists
		// vidc
		$vidcList = Vlc::getList('vidc');
		$list_vidc = array();
		foreach ($vidcList as $vidcT)
			array_push($list_vidc, array('name' => $vidcT));
		$tmpl->setloop('list_vidc', $list_vidc);
		// vbit
		$vbitList = Vlc::getList('vbit');
		$list_vbit = array();
		foreach ($vbitList as $vbitT)
			array_push($list_vbit, array('name' => $vbitT));
		$tmpl->setloop('list_vbit', $list_vbit);
		// audc
		$audcList = Vlc::getList('audc');
		$list_audc = array();
		foreach ($audcList as $audcT)
			array_push($list_audc, array('name' => $audcT));
		$tmpl->setloop('list_audc', $list_audc);
		// abit
		$abitList = Vlc::getList('abit');
		$list_abit = array();
		foreach ($abitList as $abitT)
			array_push($list_abit, array('name' => $abitT));
		$tmpl->setloop('list_abit', $list_abit);
		// requested file
		$dirName = urldecode($_REQUEST['dir']);
		$fileName = urldecode(stripslashes($_REQUEST['file']));
		$targetFile = $dirName.$fileName;
		// only valid dirs + entries with permission
		if (!((tfb_isValidPath($targetFile)) &&
			(isValidEntry(basename($targetFile))) &&
			(hasPermission($targetFile, $cfg["user"], 'r')))) {
			AuditAction($cfg["constants"]["error"], "ILLEGAL VLC-ACCESS: ".$cfg["user"]." tried to view ".$fileName." in ".$dirName);
			@error("Illegal access. Action has been logged.", "", "");
		}
		// set vars
		$tmpl->setvar('file', $fileName);
		$tmpl->setvar('target', urlencode(addslashes($targetFile)));
		// host vars
		$tmpl->setvar('addr', Vlc::getAddr());
		$tmpl->setvar('port', Vlc::getPort());
		// already streaming
		if (Vlc::isStreamRunning(Vlc::getPort()) === true) {
			$tmpl->setvar('is_streaming', 1);
			$streams = Vlc::getRunning(Vlc::getPort());
			$currentStream = (empty($streams))
				? ""
				: array_pop($streams);
			$tmpl->setvar('current_stream', $currentStream);
		} else {
			$tmpl->setvar('is_streaming', 0);
		}
		break;
	case "start":
		// get vars
		$fileName = urldecode(stripslashes($_REQUEST['file']));
		$targetFile = urldecode(stripslashes($_POST['target']));
		$target_vidc = $_POST['vidc'];
		$target_vbit = $_POST['vbit'];
		$target_audc = $_POST['audc'];
		$target_abit = $_POST['abit'];
		// only valid dirs + entries with permission
		if (!((tfb_isValidPath($targetFile)) &&
			(isValidEntry(basename($targetFile))) &&
			(hasPermission($targetFile, $cfg["user"], 'r')))) {
			AuditAction($cfg["constants"]["error"], "ILLEGAL VLC-ACCESS: ".$cfg["user"]." tried to view ".$fileName." in ".$dirName);
			@error("Illegal access. Action has been logged.", "", "");
		}
		// set template vars
		$tmpl->setvar('file', $fileName);
		$tmpl->setvar('vidc', $target_vidc);
		$tmpl->setvar('vbit', $target_vbit);
		$tmpl->setvar('audc', $target_audc);
		$tmpl->setvar('abit', $target_abit);
		$tmpl->setvar('addr', Vlc::getAddr());
		$tmpl->setvar('port', Vlc::getPort());
		// start vlc
		Vlc::start($cfg["path"].$targetFile, $target_vidc, $target_vbit, $target_audc, $target_abit);
		break;
	case "stop":
		// stop vlc
		Vlc::stop();
		break;
}

// title-bar
tmplSetTitleBar($cfg["pagetitle"]." - "."vlc", false);

// iid
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>