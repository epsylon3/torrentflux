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
 * perform Authentication
 *
 * @param $username
 * @param $password
 * @param $md5password
 * @return int with :
 *                     1 : user authenticated
 *                     0 : user not authenticated
 */
function performAuthentication($username = '', $password = '', $md5password = '') {
	global $cfg, $db;
	// check username
	if (!isset($username))
		return 0;
	if ($username == '')
		return 0;
	// sql-state
	$sql = "SELECT uid, hits, hide_offline, theme, language_file FROM tf_users WHERE state = 1 AND user_id=".$db->qstr($username)." AND password=";
	if ((isset($md5password)) && (strlen($md5password) == 32)) /* md5-password */
		$sql .= $db->qstr($md5password);
	elseif (isset($password)) /* plaintext-password */
		$sql .= $db->qstr(md5($password));
	else /* no password */
		return 0;
	// exec query
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	list($uid, $hits, $cfg["hide_offline"], $cfg["theme"], $cfg["language_file"]) = $result->FetchRow();
	if ($result->RecordCount() == 1) { // suc. auth.
		// Add a hit to the user
		$hits++;
		$sql = "select * from tf_users where uid = ".$db->qstr($uid);
		$rs = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$rec = array(
						'hits' => $hits,
						'last_visit' => $db->DBDate(time()),
						'theme' => $cfg['theme'],
						'language_file' => $cfg['language_file']
					);
		$sql = $db->GetUpdateSQL($rs, $rec);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$_SESSION['user'] = $username;
		$_SESSION['uid'] = $uid;
		$cfg["user"] = $_SESSION['user'];
		$cfg['uid'] = $uid;
		@session_write_close();
		return 1;
	} else { // wrong credentials
		// log
		AuditAction($cfg["constants"]["access_denied"], "FAILED AUTH: ".$username);
		// unset
		unset($_SESSION['user']);
		unset($_SESSION['uid']);
		unset($cfg["user"]);
		// flush users cookie
		@setcookie("autologin", "", time() - 3600);
		// return
		return 0;
	}
	// return
	return 0;
}

/**
 * get image-code
 *
 * @param $rstr
 * @param $rnd
 * @return string
 */
function loginImageCode($rstr, $rnd) {
    return substr((hexdec(md5($_SERVER['HTTP_USER_AGENT'].$rstr.$rnd.date("F j")))), 3, 6);
}

/**
 * first Login
 *
 * @param $username
 * @param $password
 */
function firstLogin($username = '', $password = '') {
	global $cfg, $db;
	if (!isset($username))
		return 0;
	if (!isset($password))
		return 0;
	if ($username == '')
		return 0;
	if ($password == '')
		return 0;
	$create_time = time();
	// This user is first in DB.  Make them super admin.
	// this is The Super USER, add them to the user table
	$record = array(
					'user_id'=>strtolower($username),
					'password'=>md5($password),
					'hits'=>1,
					'last_visit'=>$create_time,
					'time_created'=>$create_time,
					'user_level'=>2,
					'hide_offline'=>0,
					'theme'=>$cfg["default_theme"],
					'language_file'=>$cfg["default_language"],
					'state'=>1
					);
	$sTable = 'tf_users';
	$sql = $db->GetInsertSql($sTable, $record);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// Test and setup some paths for the TF settings
	// path
	$tfPath = $cfg["path"];
	if (!is_dir($cfg["path"]))
		$tfPath = getcwd() . "/downloads/";
	// settings
	$settings = array(
						"path" => $tfPath,
						"pythonCmd" => $cfg["pythonCmd"],
						"perlCmd" => $cfg["perlCmd"],
						"bin_php" => $cfg["bin_php"],
						"bin_grep" => $cfg["bin_grep"],
						"bin_awk" => $cfg["bin_awk"],
						"bin_du" => $cfg["bin_du"],
						"bin_wget" => $cfg["bin_wget"],
						"bin_unrar" => $cfg["bin_unrar"],
						"bin_unzip" => $cfg["bin_unzip"],
						"bin_cksfv" => $cfg["bin_cksfv"],
						"bin_vlc" => $cfg["bin_vlc"],
						"bin_uudeview" => $cfg["bin_uudeview"],
						"btclient_transmission_bin" => $cfg["btclient_transmission_bin"],
						"bin_netstat" => $cfg["bin_netstat"],
						"bin_sockstat" => $cfg["bin_sockstat"]
					);
	// binaries to test
	$binaries = array(
						"pythonCmd" => $cfg["pythonCmd"],
						"perlCmd" => $cfg["perlCmd"],
						"bin_php" => $cfg["bin_php"],
						"bin_grep" => $cfg["bin_grep"],
						"bin_awk" => $cfg["bin_awk"],
						"bin_du" => $cfg["bin_du"],
						"bin_wget" => $cfg["bin_wget"],
						"bin_unrar" => $cfg["bin_unrar"],
						"bin_unzip" => $cfg["bin_unzip"],
						"bin_cksfv" => $cfg["bin_cksfv"],
						"bin_vlc" => $cfg["bin_vlc"],
						"bin_uudeview" => $cfg["bin_uudeview"],
						"btclient_transmission_bin" => $cfg["btclient_transmission_bin"],
						"bin_netstat" => $cfg["bin_netstat"],
						"bin_sockstat" => $cfg["bin_sockstat"]
					);
	// bins for which
	$bins = array(
						"pythonCmd" => "python",
						"perlCmd" => "perl",
						"bin_php" => "php",
						"bin_grep" => "grep",
						"bin_awk" => "awk",
						"bin_du" => "du",
						"bin_wget" => "wget",
						"bin_unrar" => "unrar",
						"bin_unzip" => "unzip",
						"bin_cksfv" => "cksfv",
						"bin_vlc" => "vlc",
						"bin_uudeview" => "uudeview",
						"btclient_transmission_bin" => "transmissioncli",
						"bin_netstat" => "netstat",
						"bin_sockstat" => "sockstat"
					);
	// check
	foreach ($binaries as $key => $value) {
		if (!is_file($value)) {
			$bin = "";
			$bin = @trim(shell_exec("which ".$bins[$key]));
			if ($bin != "")
				$settings[$key] = $bin;
		}
	}
	// save
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["update"], "Initial Settings Updated for first login.");
}

?>