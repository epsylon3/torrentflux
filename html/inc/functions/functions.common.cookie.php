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
 * get cookie
 *
 * @param $cid
 * @return string
 */
function getCookie($cid) {
	global $cfg, $db;
	$rtnValue = "";
	$sql = "SELECT host, data FROM tf_cookies WHERE cid=".$db->qstr($cid);
	$rtnValue = $db->GetAll($sql);
	return $rtnValue[0];
}

/**
 * Delete Cookie Host Information
 *
 * @param $cid
 */
function deleteCookieInfo($cid) {
	global $db;
	$sql = "delete from tf_cookies where cid=".$db->qstr($cid);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Add New Cookie Host Information
 *
 * @param $newCookie
 */
function addCookieInfo( $newCookie ) {
	global $db, $cfg;
	// Get uid of user
	$sql = "SELECT uid FROM tf_users WHERE user_id = ".$db->qstr($cfg["user"]);
	$uid = $db->GetOne( $sql );
	$sql = "INSERT INTO tf_cookies ( uid, host, data ) VALUES ( ".$db->qstr($uid).", ".$db->qstr($newCookie["host"]).", ".$db->qstr($newCookie["data"])." )";
	$db->Execute( $sql );
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Modify Cookie Host Information
 *
 * @param $cid
 * @param $newCookie
 */
function modCookieInfo($cid, $newCookie) {
	global $db;
	$sql = "UPDATE tf_cookies SET host=".$db->qstr($newCookie["host"]).", data=".$db->qstr($newCookie["data"])." WHERE cid=".$db->qstr($cid);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

?>
