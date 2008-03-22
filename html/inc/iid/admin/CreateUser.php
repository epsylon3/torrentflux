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
tmplInitializeInstance($cfg["theme"], "page.admin.CreateUser.tmpl");

// set vars
$tmpl->setvar('enable_xfer', $cfg["enable_xfer"]);
//
$tmpl->setvar('_NEWUSER', $cfg['_NEWUSER']);
$tmpl->setvar('_USER', $cfg['_USER']);
$tmpl->setvar('_PASSWORD', $cfg['_PASSWORD']);
$tmpl->setvar('_CONFIRMPASSWORD', $cfg['_CONFIRMPASSWORD']);
$tmpl->setvar('_USERTYPE', $cfg['_USERTYPE']);
$tmpl->setvar('_NORMALUSER', $cfg['_NORMALUSER']);
$tmpl->setvar('_ADMINISTRATOR', $cfg['_ADMINISTRATOR']);
$tmpl->setvar('_CREATE', $cfg['_CREATE']);
$tmpl->setvar('_USERIDREQUIRED', $cfg['_USERIDREQUIRED']);
$tmpl->setvar('_PASSWORDLENGTH', $cfg['_PASSWORDLENGTH']);
$tmpl->setvar('_PASSWORDNOTMATCH', $cfg['_PASSWORDNOTMATCH']);
$tmpl->setvar('_PLEASECHECKFOLLOWING', $cfg['_PLEASECHECKFOLLOWING']);
//
tmplSetTitleBar("Administration - Create User");
tmplSetAdminMenu();
tmplSetUserSection();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>