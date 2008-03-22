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

// to-user
$to_user = tfb_getRequestVar('to_user');
if (empty($to_user) or empty($cfg["user"])) {
	 // the user probably hit this page direct
	@header("location: index.php?iid=index");
	exit();
}

// message
$message = tfb_getRequestVar('message');
if (!empty($message)) {
	$to_all_r = tfb_getRequestVar('to_all');
	$force_read_r = tfb_getRequestVar('force_read');
	$message = check_html($message, "nohtml");
	SaveMessage($to_user, $cfg["user"], htmlentities($message), (empty($to_all_r)) ? 0 : 1, (!empty($force_read_r) && $cfg['isAdmin']) ? 1 : 0);
	@header("location: index.php?iid=readmsg");
	exit();
}

// rmid
if (isset($_REQUEST['rmid'])) {
	$rmid = tfb_getRequestVar('rmid');
	if (!empty($rmid)) {
		list($from_user, $message, $ip, $time) = GetMessage($rmid);
		$message = $cfg['_DATE'].": ".date($cfg['_DATETIMEFORMAT'], $time)."\n".$from_user." ".$cfg['_WROTE'].":\n\n".$message;
		$message = ">".str_replace("\n", "\n>", $message);
		$message = "\n\n\n".$message;
	}
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.message.tmpl");

// set vars
$tmpl->setvar('to_user', $to_user);
$tmpl->setvar('user', $cfg["user"]);
$tmpl->setvar('message', $message);
//
$tmpl->setvar('_TO', $cfg['_TO']);
$tmpl->setvar('_FROM', $cfg['_FROM']);
$tmpl->setvar('_YOURMESSAGE', $cfg['_YOURMESSAGE']);
$tmpl->setvar('_SEND', $cfg['_SEND']);
$tmpl->setvar('_SENDTOALLUSERS', $cfg['_SENDTOALLUSERS']);
$tmpl->setvar('_FORCEUSERSTOREAD', $cfg['_FORCEUSERSTOREAD']);
//
tmplSetTitleBar($cfg["pagetitle"].' - '.$cfg['_SENDMESSAGETITLE']);
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>