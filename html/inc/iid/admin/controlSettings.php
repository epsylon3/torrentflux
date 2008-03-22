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

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.controlSettings.tmpl");

// set vars
$tmpl->setvar('enable_btclient_chooser', $cfg["enable_btclient_chooser"]);
$tmpl->setvar('transfer_profiles', $cfg["transfer_profiles"]);
$tmpl->setvar('transfer_customize_settings', $cfg["transfer_customize_settings"]);
$tmpl->setvar('showdirtree', $cfg["showdirtree"]);
$tmpl->setvar('maxdepth', $cfg["maxdepth"]);
//
tmplSetTitleBar("Administration - Control Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>