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
tmplInitializeInstance($cfg["theme"], "page.admin.xferSettings.tmpl");

// set vars
$tmpl->setvar('enable_xfer', $cfg["enable_xfer"]);
$tmpl->setvar('xfer_realtime', $cfg["xfer_realtime"]);
$tmpl->setvar('enable_public_xfer', $cfg["enable_public_xfer"]);
$tmpl->setvar('xfer_total', $cfg["xfer_total"]);
$tmpl->setvar('xfer_month', $cfg["xfer_month"]);
$tmpl->setvar('xfer_week', $cfg["xfer_week"]);
$tmpl->setvar('xfer_day', $cfg["xfer_day"]);
$tmpl->setvar('week_start', $cfg["week_start"]);
$month_list = array();
for ($i = 1; $i <= 31 ; $i++) {
	array_push($month_list, array(
		'i' => $i,
		'month_start_true' => ($cfg["month_start"] == $i) ? 1 : 0
		)
	);
}
$tmpl->setloop('month_list', $month_list);
$tmpl->setvar('SuperAdminLink', getSuperAdminLink('?m=52','<font class="adminlink">reset stats</font></a>'));
//
tmplSetTitleBar("Administration - Xfer Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>