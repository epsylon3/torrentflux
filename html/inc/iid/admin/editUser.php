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

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.editUser.tmpl");

// set vars
$editUserImage = "themes/".$cfg['theme']."/images/user.gif";
$selected_n = "selected";
$selected_a = "";
$hide_checked = "";
$total_activity = GetActivityCount();
$sql= "SELECT user_id, hits, last_visit, time_created, user_level, hide_offline, theme, language_file FROM tf_users WHERE user_id=".$db->qstr($user_id);
list($user_id, $hits, $last_visit, $time_created, $user_level, $hide_offline, $theme, $language_file) = $db->GetRow($sql);
$user_type = $cfg['_NORMALUSER'];
if ($user_level == 1) {
	$user_type = $cfg['_ADMINISTRATOR'];
	$selected_n = "";
	$selected_a = "selected";
	$editUserImage = "themes/".$cfg['theme']."/images/admin_user.gif";
}
if ($user_level >= 2) {
	$user_type = $cfg['_SUPERADMIN'];
	$editUserImage = "themes/".$cfg['theme']."/images/superadmin.gif";
}
if ($hide_offline == 1)
	$hide_checked = "checked";
$user_activity = GetActivityCount($user_id);
$user_percent = ($user_activity != 0) ? number_format(($user_activity / $total_activity) * 100) : 0;
$tmpl->setvar('editUserImage', $editUserImage);
$tmpl->setvar('user_id', $user_id);
$tmpl->setvar('time_created', date($cfg['_DATETIMEFORMAT'], $time_created));
$tmpl->setvar('last_visit', date($cfg['_DATETIMEFORMAT'], $last_visit));
$tmpl->setvar('percent1', $user_percent*2);
$tmpl->setvar('percent2', (200 - ($user_percent*2)));
$tmpl->setvar('user_activity', $user_activity);
$tmpl->setvar('user_percent', $user_percent);
$tmpl->setvar('days_to_keep', $cfg["days_to_keep"]);
$tmpl->setvar('hits', $hits);
$tmpl->setvar('language_file', GetLanguageFromFile($language_file));
$tmpl->setvar('user_type', $user_type);
$tmpl->setvar('user_level', $user_level);
$tmpl->setvar('selected_n', $selected_n);
$tmpl->setvar('selected_a', $selected_a);
$tmpl->setvar('hide_checked', $hide_checked);
$tmpl->setvar('enable_xfer', $cfg["enable_xfer"]);
//
$tmpl->setvar('_EDITUSER', $cfg['_EDITUSER']);
$tmpl->setvar('_LASTVISIT', $cfg['_LASTVISIT']);
$tmpl->setvar('_JOINED', $cfg['_JOINED']);
$tmpl->setvar('_UPLOADPARTICIPATION', $cfg['_UPLOADPARTICIPATION']);
$tmpl->setvar('_UPLOADS', $cfg['_UPLOADS']);
$tmpl->setvar('_PERCENTPARTICIPATION', $cfg['_PERCENTPARTICIPATION']);
$tmpl->setvar('_PARTICIPATIONSTATEMENT', $cfg['_PARTICIPATIONSTATEMENT']);
$tmpl->setvar('_DAYS', $cfg['_DAYS']);
$tmpl->setvar('_TOTALPAGEVIEWS', $cfg['_TOTALPAGEVIEWS']);
$tmpl->setvar('_THEME', $cfg['_THEME']);
$tmpl->setvar('_LANGUAGE', $cfg['_LANGUAGE']);
$tmpl->setvar('_USERTYPE', $cfg['_USERTYPE']);
$tmpl->setvar('_USERSACTIVITY', $cfg['_USERSACTIVITY']);
$tmpl->setvar('_USER', $cfg['_USER']);
$tmpl->setvar('_NEWPASSWORD', $cfg['_NEWPASSWORD']);
$tmpl->setvar('_CONFIRMPASSWORD', $cfg['_CONFIRMPASSWORD']);
$tmpl->setvar('_NORMALUSER', $cfg['_NORMALUSER']);
$tmpl->setvar('_ADMINISTRATOR', $cfg['_ADMINISTRATOR']);
$tmpl->setvar('_SUPERADMIN', $cfg['_SUPERADMIN']);
$tmpl->setvar('_HIDEOFFLINEUSERS', $cfg['_HIDEOFFLINEUSERS']);
$tmpl->setvar('_UPDATE', $cfg['_UPDATE']);
$tmpl->setvar('_USERIDREQUIRED', $cfg['_USERIDREQUIRED']);
$tmpl->setvar('_PASSWORDLENGTH', $cfg['_PASSWORDLENGTH']);
$tmpl->setvar('_PASSWORDNOTMATCH', $cfg['_PASSWORDNOTMATCH']);
$tmpl->setvar('_PLEASECHECKFOLLOWING', $cfg['_PLEASECHECKFOLLOWING']);
//
tmplSetTitleBar("Administration - Edit User");
tmplSetAdminMenu();
tmplSetUserSection();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>