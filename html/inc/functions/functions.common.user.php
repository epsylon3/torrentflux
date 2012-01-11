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

/**
 * add New User
 *
 * @param $newUser
 * @param $pass1
 * @param $userType
 * @param $userEmail
 */
function addNewUser($newUser, $pass1, $userType, $userEmail="") {
	global $cfg, $db;
	$create_time = time();
	$user_id = strtolower($newUser);
	$record = array(
					'user_id'=>$user_id,
					'password'=>md5($pass1),
					'hits'=>0,
					'last_visit'=>$create_time,
					'time_created'=>$create_time,
					'user_level'=>$userType,
					'hide_offline'=>"0",
					'theme'=>$cfg["default_theme"],
					'language_file'=>$cfg["default_language"],
					'state'=>1
					);
	$sTable = 'tf_users';
	$sql = $db->GetInsertSql($sTable, $record);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) 
		dbError($sql);
	elseif (!empty($userEmail)) {
		UpdateUserEmail($user_id, $userEmail);
	}
	// flush session-cache
	cacheFlush();
}

/**
 * UpdateUserProfile
 *
 * @param $user_id
 * @param $pass1
 * @param $hideOffline
 * @param $theme
 * @param $language
 */
function UpdateUserProfile($user_id, $pass1, $hideOffline, $theme, $language, $userEmail="") {
	global $cfg, $db;
	if (empty($hideOffline) || $hideOffline == "" || !isset($hideOffline))
		$hideOffline = "0";
	// update values
	$rec = array();
	if ($pass1 != "") {
		$rec['password'] = md5($pass1);
		AuditAction($cfg["constants"]["update"], $cfg['_PASSWORD']);
	}
	$sql = "select * from tf_users where user_id = ".$db->qstr($user_id);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$rec['hide_offline'] = $hideOffline;
	$rec['theme'] = $theme;
	$rec['language_file'] = $language;
	$sql = $db->GetUpdateSQL($rs, $rec);
	if ($sql != "") {
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		// flush session-cache
		cacheFlush($cfg["user"]);
	}
	if (!empty($userEmail)) {
		UpdateUserEmail($user_id, $userEmail);
	}
}

/**
 * update the Email
 *
 * @param $user_id string
 * @param $email string
 * @return boolean
 */
function UpdateUserEmail($user_id, $email) {
	global $db;
	$sql = "UPDATE tf_users SET email_address = ".$db->qstr($email)." WHERE user_id = ".$db->qstr($user_id);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) {
		dbError($sql); die();
		return false;
	}
	return true;
}

/**
 * check the username
 *
 * @param $username string
 * @return boolean true or string with error-message
 */
function checkUsername($username) {
	global $cfg;
	if ((isset($username)) && ($username != "")) {
		return (IsUser($username))
			? $username . " " . $cfg['_HASBEENUSED']
			: true;
	} else {
		return $cfg['_USERIDREQUIRED'];
	}
}

/**
 * check the password
 *
 * @param $pass1 string
 * @param $pass2 string
 * @return boolean true or string with error-message
 */
function checkPassword($pass1, $pass2) {
	global $cfg;
	if ((isset($pass1)) && (isset($pass2))) {
		if ($pass1 != $pass2) {
			return $cfg['_PASSWORDNOTMATCH'];
		} else {
			return (strlen($pass1) > 5)
				? true
				: $cfg['_PASSWORDLENGTH'];
		}
	} else {
		return $cfg['_PASSWORDLENGTH'];
	}
}

/**
 * check the Email
 *
 * @param $email string
 * @return boolean true or string with error-message
 */
function checkEmail($email) {
	global $cfg;
	if (empty($email) or trim($email) == '' or strpos($email,'@') === false)
		return 'Bad Email';
	return true;
}
?>
