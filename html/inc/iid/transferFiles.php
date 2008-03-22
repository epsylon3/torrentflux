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

// transfer functions
require_once('inc/functions/functions.transfer.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.transferFiles.tmpl");

// init transfer
transfer_init();

// init ch-instance
$ch = ClientHandler::getInstance(getTransferClient($transfer));

// set file vars
transfer_setFileVars();

// file-prio
if (($cfg["enable_file_priority"] == 1) && ($cfg["supportMap"][$ch->client]['file_priority'] == 1) && (!isTransferRunning($transfer))) {
	require_once("inc/functions/functions.fileprio.php");
	$tmpl->setvar('filePrio', getFilePrioForm($transfer, true));
	$tmpl->setvar('file_priority_enabled', 1);
} else {
	$tmpl->setvar('file_priority_enabled', 0);
}

// title + foot
tmplSetFoot(false);
tmplSetTitleBar($transferLabel." - Files", false);

// iid
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>