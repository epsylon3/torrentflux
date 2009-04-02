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
 * This method Gets Download profiles for a specific user (given by uid)
 *
 * @param $uid
 * @param $profile
 * @return array
 */
function GetProfiles($uid, $profile) {
	global $cfg, $db;
	$profiles_array = array();
	$sql = "SELECT name FROM tf_trprofiles WHERE owner=".$db->qstr($uid)." AND public='0'";
	$rs = $db->GetCol($sql);
	if ($rs) {
		foreach($rs as $arr) {
			array_push($profiles_array, array(
				'name' => $arr,
				'is_selected' => ($arr == $profile) ? 1 : 0
				)
			);
		}
	}
	if ($db->ErrorNo() != 0) dbError($sql);
	return $profiles_array;
}

/**
 * This method Gets Download profiles for a specific user (given by username)
 *
 * @param $user
 * @param $profile
 * @return array
 */
function GetProfilesByUserName($user, $profile) {
	global $cfg, $db;
	$profiles_array = array();
	$sql = "SELECT p.name AS name FROM tf_users AS u LEFT JOIN tf_trprofiles AS p ON (u.uid = p.owner) WHERE u.user_id=".$db->qstr($user)." AND p.public='0'";
	$rs = $db->GetCol($sql);
	if ($rs) {
		foreach($rs as $arr) {
			array_push($profiles_array, array(
				'name' => $arr,
				'is_selected' => ($arr == $profile) ? 1 : 0
				)
			);
		}
	}
	if ($db->ErrorNo() != 0) dbError($sql);
	return $profiles_array;
}

/**
 * This method Gets public Download profiles
 *
 * @param $profile
 * @return array
 */
function GetPublicProfiles($profile) {
	global $cfg, $db;
	$profiles_array = array();
	$sql = "SELECT name FROM tf_trprofiles WHERE public= '1'";
	$rs = $db->GetCol($sql);
	if ($rs) {
		foreach($rs as $arr) {
			array_push($profiles_array, array(
				'name' => $arr,
				'is_selected' => ($arr == $profile) ? 1 : 0
				)
			);
		}
	}
	if ($db->ErrorNo() != 0) dbError($sql);
	return $profiles_array;
}

/**
 * This method fetch settings for an specific profile
 *
 * @param $profile
 * @return array
 */
function GetProfileSettings($profile) {
	global $cfg, $db;
	$sql = "SELECT minport, maxport, maxcons, rerequest, rate, maxuploads, drate, runtime, sharekill, superseeder, savepath from tf_trprofiles where name=".$db->qstr($profile);
	$settings = $db->GetRow($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	return $settings;
}

/**
 * Add New Profile Information
 *
 * @param $newProfile
 */
function AddProfileInfo( $newProfile ) {
	global $db, $cfg;
	$sql = 'INSERT INTO tf_trprofiles ( name , owner , minport , maxport , maxcons , rerequest , rate , maxuploads , drate , runtime , sharekill , superseeder , savepath, public )'
		." VALUES ("
		.     $db->qstr($newProfile["name"])
		.", ".$db->qstr($cfg['uid'])
		.", ".$db->qstr($newProfile["minport"])
		.", ".$db->qstr($newProfile["maxport"])
		.", ".$db->qstr($newProfile["maxcons"])
		.", ".$db->qstr($newProfile["rerequest"])
		.", ".$db->qstr($newProfile["rate"])
		.", ".$db->qstr($newProfile["maxuploads"])
		.", ".$db->qstr($newProfile["drate"])
		.", ".$db->qstr($newProfile["runtime"])
		.", ".$db->qstr($newProfile["sharekill"])
		.", ".$db->qstr($newProfile["superseeder"])
		.", ".$db->qstr($newProfile["public"])
		.", ".$db->qstr($newProfile["savepath"]).")";
	$db->Execute( $sql );
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * getProfile
 *
 * @param $pid
 * @return
 */
function getProfile($pid) {
	global $cfg, $db;
	$rtnValue = "";
	$sql = "SELECT id , name , minport , maxport , maxcons , rerequest , rate , maxuploads , drate , runtime , sharekill , superseeder , public, savepath FROM tf_trprofiles WHERE id=".$db->qstr($pid);
	$rtnValue = $db->GetAll($sql);
	return $rtnValue[0];
}

/**
 * Modify Profile Information
 *
 * @param $pid
 * @param $newProfile
 */
function modProfileInfo($pid, $newProfile) {
	global $cfg, $db;
	
	// b4rt: var not used, commented this out
	//	$default_savepath = " value=\"" . ($cfg["enable_home_dirs"] != 0)
	//		? $cfg['path'].$cfg["user"].'/'
	//		: $cfg['path'].$cfg["path_incoming"].'/' . "\"";
	
	// in case of homedirs, ensure profile doesnt go out of savepath.
	if ($cfg["enable_home_dirs"] != 0) {
		if (substr($newProfile["savepath"], 0, strlen($cfg['path'].$cfg["user"])) != $cfg['path'].$cfg["user"]) {
			AuditAction($cfg["constants"]["error"], "INVALID TRANSFER DIRECTORY.: ");
			@error("Invalid directory. You can only set transfer paths within your own directory.", "", "", array());
		}
	}
	else {
		if (substr($newProfile["savepath"], 0, strlen($cfg['path'])) != $cfg['path']) {
			AuditAction($cfg["constants"]["error"], "INVALID TRANSFER DIRECTORY.: ");
			@error("Invalid directory. You can only set transfer paths within the root directory ".$cfg['path'].".", "", "", array());
		}	
	}
				
	$sql = "UPDATE tf_trprofiles SET"
	." owner = ".$db->qstr($cfg['uid'])
	.", name = ".$db->qstr($newProfile["name"])
	.", minport = ".$db->qstr($newProfile["minport"])
	.", maxport = ".$db->qstr($newProfile["maxport"])
	.", maxcons = ".$db->qstr($newProfile["maxcons"])
	.", rerequest = ".$db->qstr($newProfile["rerequest"])
	.", rate = ".$db->qstr($newProfile["rate"])
	.", maxuploads = ".$db->qstr($newProfile["maxuploads"])
	.", drate = ".$db->qstr($newProfile["drate"])
	.", runtime = ".$db->qstr($newProfile["runtime"])
	.", sharekill = ".$db->qstr($newProfile["sharekill"])
	.", superseeder = ".$db->qstr($newProfile["superseeder"])
	.", public = ".$db->qstr($newProfile["public"])
	.", savepath = ".$db->qstr($newProfile["savepath"])
	." WHERE id = ".$db->qstr($pid);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Delete Profile Information
 *
 * @param $pid
 */
function deleteProfileInfo($pid) {
	global $db;
	$sql = "DELETE FROM tf_trprofiles WHERE id=".$db->qstr($pid);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

?>