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

$user_id = tfb_getRequestVar('user_id');
$org_user_id = tfb_getRequestVar('org_user_id');
$email_address = tfb_getRequestVar('email_address');
$pass1 = tfb_getRequestVar('pass1');
$pass2 = tfb_getRequestVar('pass2');
$userType = tfb_getRequestVar('userType');
$hideOffline = tfb_getRequestVar('hideOffline');
$user_id = strtolower($user_id);
$email_address = strtolower($email_address);

// check password
$passwordCheck = (($pass1 != '') && ($pass2 != ''))
	? checkPassword($pass1, $pass2)
	: true;
	
// update user
if ( !empty($user_id) && (($passwordCheck === true && IsUser($user_id))
	                 || $user_id == $org_user_id && IsUser($org_user_id)) ) {
	// Admin is changing id or password through edit screen
	if (($user_id == $cfg["user"] || $cfg["user"] == $org_user_id) && $pass1 != "") {
		// this will expire the user
		$_SESSION['user'] = md5($cfg["pagetitle"]);
	}
	updateThisUser($user_id, $org_user_id, $pass1, $userType, $hideOffline, $email_address);
	AuditAction($cfg["constants"]["admin"], $cfg['_EDITUSER'].": ".$user_id);
	@header("location: admin.php?op=editUser&user_id=".urlencode($user_id));
	exit();
} else {
	AuditAction($cfg["constants"]["error"], $cfg['_EDITUSER'].": uname to edit ".$user_id);
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.updateUser.tmpl");

// set vars
$tmpl->setvar('user_id', $user_id);
$tmpl->setvar('org_user_id', $org_user_id);
$tmpl->setvar('email_address', $email_address);
	
// error
	
$tmpl->setvar('_ERROR', $cfg['_ERROR']);

// error-vars
$tmpl->setvar('errUsername', ($user_id == '') ? 1 : 0);
$tmpl->setvar('errMsgUsername', ($user_id == '') ? $cfg['_USERIDREQUIRED'] : '');	
$tmpl->setvar('errPassword', ($passwordCheck !== true) ? 1 : 0);
$tmpl->setvar('errMsgPassword', ($passwordCheck !== true) ? $passwordCheck : '');
	
$tmpl->setvar('_RETURNTOEDIT', $cfg['_RETURNTOEDIT']);
//
tmplSetTitleBar("Administration - Update User");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>
