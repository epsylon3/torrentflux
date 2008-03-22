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
if ($cfg["enable_dereferrer"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use dereferrer");
	@error("dereferrer is disabled", "index.php?iid=index", "");
}

// check param
if (!(isset($_REQUEST["u"]))) {
	@header("location: index.php?iid=index");
	exit();
} else {
	$url = tfb_getRequestVarRaw("u");
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.dereferrer.tmpl");

// set vars
$url2 = tfb_htmlencode($url);
$tmpl->setvar('url', $url2);
$tmpl->setvar('meta_refresh', '0;URL='.$url2);
//
tmplSetTitleBar($cfg["pagetitle"].' - dereferrer', false);
tmplSetFoot(false);
tmplSetIidVars();
$tmpl->pparse();

?>