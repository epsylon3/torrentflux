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

// readrss functions
require_once('inc/functions/functions.readrss.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.editRSS.tmpl");

// set vars
$arLinks = GetRSSLinks();
$arRid = Array_Keys($arLinks);
$inx = 0;
$link_rss = array();
foreach($arLinks as $link) {
	$rid = $arRid[$inx++];
	array_push($link_rss, array(
		'true' => true,
		'rid' => $rid,
		'link' => $link
		)
	);
}
$tmpl->setloop('link_rss', $link_rss);
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
//
$tmpl->setvar('_FULLURLLINK', $cfg['_FULLURLLINK']);
$tmpl->setvar('_UPDATE', $cfg['_UPDATE']);
$tmpl->setvar('_DELETE', $cfg['_DELETE']);
//
tmplSetTitleBar("Administration - RSS");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>