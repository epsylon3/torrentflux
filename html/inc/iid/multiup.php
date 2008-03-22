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

// is enabled ?
if ($cfg["enable_multiupload"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use multiupload");
	@error("multiupload is disabled", "index.php?iid=index", "");
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.multiup.tmpl");

// form
$row_list = array();
for ($j = 0; $j < $cfg["hack_multiupload_rows"]; ++$j)
	array_push($row_list, array());
$tmpl->setloop('row_list', $row_list);

// queue
$tmpl->setvar('queueActive', (FluxdQmgr::isRunning()) ? 1 : 0);
//
$tmpl->setvar('file_types_label', $cfg['file_types_label']);
//
$tmpl->setvar('_UPLOAD', $cfg['_UPLOAD']);
$tmpl->setvar('_SELECTFILE', $cfg['_SELECTFILE']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
$tmpl->setvar('_MULTIPLE_UPLOAD', $cfg['_MULTIPLE_UPLOAD']);
//
$tmpl->setvar('enable_multiupload', $cfg["enable_multiupload"]);
tmplSetTitleBar($cfg["pagetitle"].' - '.$cfg['_MULTIPLE_UPLOAD']);
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>