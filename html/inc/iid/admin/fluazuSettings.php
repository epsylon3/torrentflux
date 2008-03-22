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
	@header("location: ../../../index.php");
	exit();
}

/******************************************************************************/

// FluAzu
require_once("inc/classes/FluAzu.php");

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.fluazuSettings.tmpl");

// superadmin-links
$tmpl->setvar('SuperAdminLink1', getSuperAdminLink('?a=1','<font class="adminlink">log</font></a>'));
$tmpl->setvar('SuperAdminLink3', getSuperAdminLink('?a=3','<font class="adminlink">ps</font></a>'));
$tmpl->setvar('SuperAdminLink9', getSuperAdminLink('?a=9','<font class="adminlink">version</font></a>'));

// message section
$message = tfb_getRequestVar('m');
if ($message != "")
	$tmpl->setvar('message', urldecode($message));

// check the needed bins
// python
if (@file_exists($cfg['pythonCmd']) !== true)
	$tmpl->setvar('pythonMissing', 1);

// fluazu status
if (FluAzu::isRunning()) {
	$tmpl->setvar('fluazuRunning', 1);
	$tmpl->setvar('fluazuPid', FluAzu::getPid());
	$status = FluAzu::getStatus();
	$statusKeys = FluAzu::getStatusKeys();
	foreach ($statusKeys as $statusKey)
		$tmpl->setvar($statusKey, $status[$statusKey]);
} else {
	$tmpl->setvar('fluazuRunning', 0);
}

// settings
$tmpl->setvar('fluazu_host', $cfg['fluazu_host']);
$tmpl->setvar('fluazu_port', $cfg['fluazu_port']);
$tmpl->setvar('fluazu_secure', $cfg['fluazu_secure']);
$tmpl->setvar('fluazu_user', $cfg['fluazu_user']);
$tmpl->setvar('fluazu_pw', $cfg['fluazu_pw']);

// templ-calls
tmplSetTitleBar("Administration - fluazu Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>