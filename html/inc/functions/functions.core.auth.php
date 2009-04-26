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
 * try to get Credentials
 *
 * @return array with credentials or false if no credentials found.
 */
function getCredentials() {
	global $cfg;
	// check for basic-auth-supplied credentials (only if activated or there may
	// be wrong credentials fetched)
	if (($cfg['auth_type'] == 2) || ($cfg['auth_type'] == 3)) {
		if ((isset($_SERVER['PHP_AUTH_USER'])) && (isset($_SERVER['PHP_AUTH_PW']))) {
			$retVal = array();
			$retVal['username'] = strtolower($_SERVER['PHP_AUTH_USER']);
			$retVal['password'] = addslashes($_SERVER['PHP_AUTH_PW']);
			$retVal['md5pass'] = "";
			return $retVal;
		}
	}
	// check for http-post/get-supplied credentials (only if auth-type not 4 or 5)
	if (($cfg['auth_type'] != 4) && ($cfg['auth_type'] != 5)) {
		if (isset($_REQUEST['username'])) {
			if (isset($_REQUEST['md5pass'])) {
				$retVal = array();
				$retVal['username'] = strtolower($_REQUEST['username']);
				$retVal['password'] = "";
				$retVal['md5pass'] = $_REQUEST['md5pass'];
				return $retVal;
			} elseif (isset($_REQUEST['iamhim'])) {
				$retVal = array();
				$retVal['username'] = strtolower($_REQUEST['username']);
				$retVal['password'] = addslashes($_REQUEST['iamhim']);
				$retVal['md5pass'] = "";
				return $retVal;
			}
		}
	}
	// check for cookie-supplied credentials (only if activated)
	if ($cfg['auth_type'] == 1) {
		if (isset($_COOKIE["autologin"])) {
			$creds = explode('|', $_COOKIE["autologin"]);
			$retVal = array();
			$retVal['username'] = strtolower($creds[0]);
			$retVal['password'] = "";
			$retVal['md5pass'] = $creds[1];
			return $retVal;
		}
	}
	// no credentials found, return false
	return false;
}

/**
 * check if user authenticated
 *
 * @return int with :
 *                     1 : user authenticated
 *                     0 : user not authenticated
 */
function isAuthenticated() {
	global $cfg, $db;
	// hold time
	$create_time = time();
	// user not set
	if (!isset($_SESSION['user']))
		return 0;
	// user changed password and needs to login again
	if ($_SESSION['user'] == md5($cfg["pagetitle"])) {
		// flush users cookie
		@setcookie("autologin", "", time() - 3600);
		// return
		return 0;
	}
	// user exists ?
	$recordset = $db->Execute("SELECT uid, hits FROM tf_users WHERE user_id=".$db->qstr($cfg["user"]));
	if ($recordset->RecordCount() != 1) {
		AuditAction($cfg["constants"]["access_denied"], "FAILED AUTH: ".$cfg["user"]);
		@session_destroy();
		return 0;
	}
	list($uid, $hits) = $recordset->FetchRow();
	// hold the uid in cfg-array
	$cfg["uid"] = $uid;
	// increment hit-counter
	$hits++;
	$db->Execute("UPDATE tf_users SET hits = ".$db->qstr($hits).", last_visit = ".$db->qstr($create_time)." WHERE uid = ".$db->qstr($uid));
	// return auth suc.
	return 1;
}

?>