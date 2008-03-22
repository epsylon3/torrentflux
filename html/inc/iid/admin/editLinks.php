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
tmplInitializeInstance($cfg["theme"], "page.admin.editLinks.tmpl");

// set vars
$arLinks = GetLinks();
$arLid = Array_Keys($arLinks);
$inx = 0;
$link_count = count($arLinks);
$link_list = array();
foreach($arLinks as $link) {
	$lid = $arLid[$inx++];
	$counter = 0;
	if (isset($_REQUEST["edit"]) && $_REQUEST["edit"] == $link['lid']) {
		$is_edit = 1;
	} else {
		$is_edit = 0;
	}
	if ($inx > 1 )
		$counter = 2;
	if ($inx == 1)
		$counter = 1;

	array_push($link_list, array(
		'is_edit' => $is_edit,
		'url' => $link['url'],
		'sitename' => $link['sitename'],
		'lid' => $lid,
		'counter' => $counter,
		'counter2' => ($inx != count($arLinks)) ? 1 : 0,
		'last_link' => false
		)
	);
}

// Set a tmpl var to indicate this is last link so we can format/align the last
// link correctly:
$link_list[count($link_list)-1]['last_link']=true;

$tmpl->setloop('link_list', $link_list);
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
//
$tmpl->setvar('_ADMINEDITLINKS', $cfg['_ADMINEDITLINKS']);
$tmpl->setvar('_FULLURLLINK', $cfg['_FULLURLLINK']);
$tmpl->setvar('_FULLSITENAME', $cfg['_FULLSITENAME']);
$tmpl->setvar('_UPDATE', $cfg['_UPDATE']);
$tmpl->setvar('_DELETE', $cfg['_DELETE']);
$tmpl->setvar('_EDIT', $cfg['_EDIT']);

//
tmplSetTitleBar($cfg['_ADMINEDITLINKS']);
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>