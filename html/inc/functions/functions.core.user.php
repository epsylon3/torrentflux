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
 * IsUser
 *
 * @param $user
 * @return boolean
 */
function IsUser($user) {
	global $db;
	return ($db->GetOne("SELECT count(*) FROM tf_users WHERE user_id=".$db->qstr($user)) > 0);
}

/**
 * Is User Admin : user is Admin if level is 1 or higher
 *
 * @param $user
 * @return boolean
 */
function IsAdmin($user = "") {
	global $cfg, $db;
	if ($user == "")
		$user = $cfg["user"];
	return ($db->GetOne("SELECT user_level FROM tf_users WHERE user_id=".$db->qstr($user)) >= 1);
}

/**
 * Is User SUPER Admin : user is Super Admin if level is higher than 1
 *
 * @param $user
 * @return boolean
 */
function IsSuperAdmin($user = "") {
	global $cfg, $db;
	if ($user == "")
		$user = $cfg["user"];
	return ($db->GetOne("SELECT user_level FROM tf_users WHERE user_id=".$db->qstr($user)) > 1);
}

/**
 * IsOnline
 *
 * @param $user
 * @return boolean
 */
function IsOnline($user) {
	global $cfg, $db;
	return ($db->GetOne("SELECT count(*) FROM tf_log WHERE user_id=" . $db->qstr($user)." AND action=".$db->qstr($cfg["constants"]["hit"])) > 0);
}

/**
 * Get Users in an array
 *
 * @return array
 */
function GetUsers() {
	global $db;
	$user_array = array();
	$user_array = $db->GetCol("select user_id from tf_users order by user_id");
	return $user_array;
}

/**
 * Get Super Admin User ID as a String
 *
 * @return string
 */
function GetSuperAdmin() {
	global $db;
	return $db->GetOne("select user_id from tf_users WHERE user_level=2");
}

?>