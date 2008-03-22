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
 * updateServerSettings
 */
function admin_updateServerSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Server Settings");
	@header("location: admin.php?op=serverSettings");
	exit();
}

/**
 * updateTransferSettings
 */
function admin_updateTransferSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Transfer Settings");
	@header("location: admin.php?op=transferSettings");
	exit();
}

/**
 * updateWebappSettings
 */
function admin_updateWebappSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating WebApp Settings");
	@header("location: admin.php?op=webappSettings");
	exit();
}

/**
 * updateIndexSettings
 */
function admin_updateIndexSettings() {
	global $cfg;
	$settings = processSettingsParams(true, true);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Index Settings");
	@header("location: admin.php?op=indexSettings");
	exit();
}

/**
 * updateControlSettings
 */
function admin_updateControlSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Control Settings");
	@header("location: admin.php?op=controlSettings");
	exit();
}

/**
 * updateDirSettings
 */
function admin_updateDirSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings_dir', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Dir Settings");
	@header("location: admin.php?op=dirSettings");
	exit();
}

/**
 * updateStatsSettings
 */
function admin_updateStatsSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings_stats', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Stats Settings");
	@header("location: admin.php?op=statsSettings");
	exit();
}

/**
 * updateXferSettings
 */
function admin_updateXferSettings() {
	global $cfg;
	$settings = processSettingsParams(false, false);
	saveSettings('tf_settings', $settings);
	AuditAction($cfg["constants"]["admin"], " Updating Xfer Settings");
	@header("location: admin.php?op=xferSettings");
	exit();
}

/**
 * updateFluxdSettings
 */
function admin_updateFluxdSettings() {
	global $cfg;
	if ($_POST["fluxd_dbmode"] != $cfg["fluxd_dbmode"] ||
		$_POST["fluxd_loglevel"] != $cfg["fluxd_loglevel"] ||
		$_POST["fluxd_Qmgr_enabled"] != $cfg["fluxd_Qmgr_enabled"] ||
		$_POST["fluxd_Fluxinet_enabled"] != $cfg["fluxd_Fluxinet_enabled"] ||
		$_POST["fluxd_Maintenance_enabled"] != $cfg["fluxd_Maintenance_enabled"] ||
		$_POST["fluxd_Trigger_enabled"] != $cfg["fluxd_Trigger_enabled"] ||
		$_POST["fluxd_Watch_enabled"] != $cfg["fluxd_Watch_enabled"] ||
		$_POST["fluxd_Rssad_enabled"] != $cfg["fluxd_Rssad_enabled"] ||
		$_POST["fluxd_Qmgr_maxTotalTransfers"] != $cfg["fluxd_Qmgr_maxTotalTransfers"] ||
		$_POST["fluxd_Qmgr_maxUserTransfers"] != $cfg["fluxd_Qmgr_maxUserTransfers"] ||
		$_POST["fluxd_Qmgr_interval"] != $cfg["fluxd_Qmgr_interval"] ||
		$_POST["fluxd_Fluxinet_port"] != $cfg["fluxd_Fluxinet_port"] ||
		$_POST["fluxd_Watch_interval"] != $cfg["fluxd_Watch_interval"] ||
		$_POST["fluxd_Rssad_interval"] != $cfg["fluxd_Rssad_interval"] ||
		$_POST["fluxd_Maintenance_interval"] != $cfg["fluxd_Maintenance_interval"] ||
		$_POST["fluxd_Trigger_interval"] != $cfg["fluxd_Trigger_interval"]) {
		$message = 'Settings changed. ';
		// fluxd Running?
		if (Fluxd::isRunning()) {
			// restart ?
			if ($_POST["fluxd_dbmode"] != $cfg["fluxd_dbmode"]) {
				// save settings
				$settings = processSettingsParams(false, false);
				saveSettings('tf_settings', $settings);
				$message .= 'fluxd needs to be restarted to change db-mode.';
			} else {
				// reload ?
				$reloadModules = false;
				if ($_POST["fluxd_Qmgr_enabled"] != $cfg["fluxd_Qmgr_enabled"] ||
					$_POST["fluxd_Fluxinet_enabled"] != $cfg["fluxd_Fluxinet_enabled"] ||
					$_POST["fluxd_Maintenance_enabled"] != $cfg["fluxd_Maintenance_enabled"] ||
					$_POST["fluxd_Trigger_enabled"] != $cfg["fluxd_Trigger_enabled"] ||
					$_POST["fluxd_Watch_enabled"] != $cfg["fluxd_Watch_enabled"] ||
					$_POST["fluxd_Rssad_enabled"] != $cfg["fluxd_Rssad_enabled"]) {
					$reloadModules = true;
				}
				// reconfig of running daemon :
				if ($_POST["fluxd_loglevel"] != $cfg["fluxd_loglevel"]) {
					Fluxd::setConfig('LOGLEVEL', $_POST["fluxd_loglevel"]);
					sleep(2);
				}
				// save settings
				$settings = processSettingsParams(false, false);
				saveSettings('tf_settings', $settings);
				// reload fluxd-database-cache
				Fluxd::reloadDBCache();
				// reload fluxd-modules
				if ($reloadModules) {
					sleep(5);
					Fluxd::reloadModules();
					$message .= 'modules reloaded.';
				} else {
					$message .= ' reload module(s) to use new settings.';
				}
			}
		} else {
			// save settings
			$settings = processSettingsParams(false, false);
			saveSettings('tf_settings', $settings);
			$message .= 'fluxd is not running.';
		}
		// log
		AuditAction($cfg["constants"]["fluxd"], " Updating fluxd Settings");
		// redir
		@header("Location: admin.php?op=fluxdSettings&m=".urlencode($message));
	} else {
		// save settings
		$settings = processSettingsParams(false, false);
		saveSettings('tf_settings', $settings);
		// log
		AuditAction($cfg["constants"]["fluxd"], " Updating fluxd Settings");
		// redir
		@header("Location: admin.php?op=fluxdSettings");
	}
	exit();
}

/**
 * controlFluxd
 */
function admin_controlFluxd() {
	global $cfg;
	$message = "";
	$action = tfb_getRequestVar('a');
	switch($action) {
		case "start":
			// start fluxd
			if (Fluxd::isReadyToStart()) {
				Fluxd::start();
				if (Fluxd::isRunning()) {
					$message = 'fluxd started';
				} else {
					$message = 'Error starting fluxd.';
					$msgs = Fluxd::getMessages();
					Fluxd::logError("Error starting fluxd.".( (!empty($msgs)) ? "\n".implode("\n", $msgs)."\n" : "\n"), true);
					array_push($msgs, "please check fluxd-logs");
					@error($message, "admin.php?op=fluxdSettings", "fluxd-Settings", $msgs);
				}
				break;
			}
			$message = 'fluxd not ready to start';
			break;
		case "stop":
			// kill fluxd
			if (Fluxd::isRunning()) {
				Fluxd::stop();
				$message = (Fluxd::isRunning())
					? 'Stop-Command sent.'
					: 'fluxd stopped.';
			}
			break;
		default:
			$message = 'Error : no control-operation.';
			break;
	}
	if ($message != "")
		@header("Location: admin.php?op=fluxdSettings&m=".urlencode($message));
	else
		@header("Location: admin.php?op=fluxdSettings");
	exit();
}

/**
 * updateFluAzuSettings
 */
function admin_updateFluAzuSettings() {
	global $cfg;
	// FluAzu
	require_once("inc/classes/FluAzu.php");
	$message = "";
	if ($_POST["fluazu_host"] != $cfg["fluazu_host"] ||
		$_POST["fluazu_port"] != $cfg["fluazu_port"] ||
		$_POST["fluazu_secure"] != $cfg["fluazu_secure"] ||
		$_POST["fluazu_user"] != $cfg["fluazu_user"] ||
		$_POST["fluazu_pw"] != $cfg["fluazu_pw"]) {
		// fluazu Running?
		if (FluAzu::isRunning()) {
			$message = 'fluazu needs to be stopped before settings can be changed.';
		} else {
			// save settings
			$settings = processSettingsParams(false, false);
			saveSettings('tf_settings', $settings);
			$message = 'Settings changed.';
			// log
			AuditAction($cfg["constants"]["admin"], " Updating fluazu Settings");
		}
	}
	if ($message != "")
		@header("Location: admin.php?op=fluazuSettings&m=".urlencode($message));
	else
		@header("Location: admin.php?op=fluazuSettings");
	exit();
}

/**
 * controlFluAzu
 */
function admin_controlFluAzu() {
	global $cfg;
	// FluAzu
	require_once("inc/classes/FluAzu.php");
	$message = "";
	$action = tfb_getRequestVar('a');
	switch($action) {
		case "start":
			// start fluazu
			if (!FluAzu::isRunning()) {
				if (FluAzu::start()) {
					$message = 'fluazu started';
				} else {
					$message = 'Error starting fluazu.';
					$msgs = FluAzu::getMessages();
					FluAzu::logMessage("Error starting fluazu.".( (!empty($msgs)) ? "\n".implode("\n", $msgs)."\n" : "\n"), true);
					array_push($msgs, "please check fluazu-log");
					@error($message, "admin.php?op=fluazuSettings", "fluazu-Settings", $msgs);
				}
				break;
			}
			break;
		case "stop":
			// kill fluazu
			if (FluAzu::isRunning()) {
				FluAzu::stop();
				$message = (FluAzu::isRunning())
					? 'Stop-Command sent.'
					: 'fluazu stopped.';
			}
			break;
		default:
			$message = 'Error : no control-operation.';
			break;
	}
	if ($message != "")
		@header("Location: admin.php?op=fluazuSettings&m=".urlencode($message));
	else
		@header("Location: admin.php?op=fluazuSettings");
	exit();
}

/**
 * updateAzureusSettings
 */
function admin_updateAzureusSettings() {
	global $cfg;
	// FluAzu
	require_once("inc/classes/FluAzu.php");
	$message = "";
	// fluazu Running?
	if (FluAzu::isRunning()) {
		$status = FluAzu::getStatus();
		$statusKeys = FluAzu::getStatusKeys();
		// current settings
		$settingsCurrent = array();
		foreach ($statusKeys as $statusKey)
			$settingsCurrent[$statusKey] = $status[$statusKey];
		// new settings
		$settingsNew = array();
		foreach ($statusKeys as $statusKey) {
			$settingsNew[$statusKey] = tfb_getRequestVar($statusKey);
			if ($settingsNew[$statusKey] == "")
				$settingsNew[$statusKey] = $settingsCurrent[$statusKey];
		}
		// customize settings
		if ($cfg['transfer_customize_settings'] == 2)
			$customize_settings = 1;
		elseif ($cfg['transfer_customize_settings'] == 1 && $cfg['isAdmin'])
			$customize_settings = 1;
		else
			$customize_settings = 0;
		// get changes
		$settingsChanged = array();
		foreach ($statusKeys as $statusKey) {
			if ($settingsNew[$statusKey] != $settingsCurrent[$statusKey]) {
				if ($customize_settings == 1)
					array_push($settingsChanged, $statusKey);
			}
		}
		if (empty($settingsChanged)) { /* no changes */
			$message = 'no changes.';
		} else { /* something changed */
			// set
			foreach ($settingsChanged as $statusKey)
				FluAzu::setAzu($statusKey, $settingsNew[$statusKey], false);
			// send
			FluAzu::sendCommands();
			// give fluazu some time
			sleep(2);
			$message = 'Settings changed.';
			// log
			AuditAction($cfg["constants"]["admin"], "Updating azureus Settings");
		}
	} else {
		$message = 'fluazu/azureus not running.';
	}
	if ($message != "")
		@header("Location: admin.php?op=fluazuSettings&m=".urlencode($message));
	else
		@header("Location: admin.php?op=fluazuSettings");
	exit();
}

/**
 * updateSearchSettings
 */
function admin_updateSearchSettings() {
	global $cfg;
	$settings = array();
	foreach ($_POST as $key => $value) {
		if ($key != "searchEngine")
			$settings[$key] = $value;
	}
	if (count($settings) > 0) {
		saveSettings('tf_settings', $settings);
		AuditAction($cfg["constants"]["admin"], "Updating Search Settings");
	}
	$searchEngine = tfb_getRequestVar('searchEngine');
	if (empty($searchEngine))
		$searchEngine = $cfg["searchEngine"];
	@header("location: admin.php?op=searchSettings&searchEngine=".$searchEngine);
	exit();
}

/**
 * addLink
 */
function admin_addLink() {
	global $cfg;
	$newLink = tfb_getRequestVarRaw('newLink');
	$newSite = tfb_getRequestVarRaw('newSite');
	if (!empty($newLink)){
		if (strpos($newLink, "http://" ) !== 0 && strpos($newLink, "https://" ) !== 0 && strpos($newLink, "ftp://" ) !== 0)
			$newLink = "http://".$newLink;
		empty($newSite) && $newSite = $newLink;
		addNewLink($newLink, $newSite);
		AuditAction($cfg["constants"]["admin"], "New ".$cfg['_LINKS_MENU'].": ".addslashes($newSite)." [".addslashes($newLink)."]");
	}
	@header("location: admin.php?op=editLinks");
	exit();
}

/**
 * editLink
 */
function admin_editLink() {
	global $cfg;
	$lid = tfb_getRequestVar('lid');
	$newLink = tfb_getRequestVarRaw('editLink');
	$newSite = tfb_getRequestVarRaw('editSite');
	if (!empty($newLink)){
		if(strpos($newLink, "http://" ) !== 0 && strpos($newLink, "https://" ) !== 0 && strpos($newLink, "ftp://" ) !== 0)
			$newLink = "http://".$newLink;
		empty($newSite) && $newSite = $newLink;
		$oldLink = getLink($lid);
		$oldSite = getSite($lid);
		alterLink($lid, $newLink, $newSite);
		AuditAction($cfg["constants"]["admin"], "Change Link: ".addslashes($oldSite)." [".addslashes($oldLink)."] -> ".addslashes($newSite)." [".addslashes($newLink)."]");
	}
	@header("location: admin.php?op=editLinks");
	exit();
}

/**
 * moveLink
 */
function admin_moveLink() {
	global $db;
	$lid = tfb_getRequestVar('lid');
	$direction = tfb_getRequestVar('direction');
	if (!isset($lid) && !isset($direction) && $direction !== "up" && $direction !== "down") {
		@header("location: admin.php?op=editLinks");
		exit();
	}
	$idx = getLinkSortOrder($lid);
	$position = array("up"=>-1, "down"=>1);
	$new_idx = $idx + $position[$direction];
	$sql = "UPDATE tf_links SET sort_order = ".$db->qstr($idx)." WHERE sort_order = ".$db->qstr($new_idx);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$sql = "UPDATE tf_links SET sort_order = ".$db->qstr($new_idx)." WHERE lid = ".$db->qstr($lid);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
	@header("Location: admin.php?op=editLinks");
	exit();
}

/**
 * deleteLink
 */
function admin_deleteLink() {
	global $cfg;
	$lid = tfb_getRequestVar('lid');
	AuditAction($cfg["constants"]["admin"], $cfg['_DELETE']." Link: ".getSite($lid)." [".getLink($lid)."]");
	deleteOldLink($lid);
	// flush session-cache
	cacheFlush();
	@header("location: admin.php?op=editLinks");
	exit();
}

/**
 * addRSS
 */
function admin_addRSS() {
	global $cfg;
	$newRSS = tfb_getRequestVarRaw('newRSS');
	if(!empty($newRSS)){
		addNewRSS($newRSS);
		AuditAction($cfg["constants"]["admin"], "New RSS: ".addslashes($newRSS));
	}
	@header("location: admin.php?op=editRSS");
	exit();
}

/**
 * deleteRSS
 */
function admin_deleteRSS() {
	global $cfg;
	$rid = tfb_getRequestVar('rid');
	AuditAction($cfg["constants"]["admin"], $cfg['_DELETE']." RSS: ".getRSS($rid));
	deleteOldRSS($rid);
	@header("location: admin.php?op=editRSS");
	exit();
}

/**
 * deleteUser
 */
function admin_deleteUser() {
	global $cfg;
	$user_id = tfb_getRequestVar('user_id');
	if (!IsSuperAdmin($user_id)) {
		DeleteThisUser($user_id);
		AuditAction($cfg["constants"]["admin"], $cfg['_DELETE']." ".$cfg['_USER'].": ".$user_id);
	}
	@header("location: admin.php");
	exit();
}

/**
 * setUserState
 */
function admin_setUserState() {
	setUserState();
	@header("location: admin.php?op=showUsers");
	exit();
}

/**
 * admin menu
 */
function tmplSetAdminMenu() {
	global $cfg, $tmpl;
	$tmpl->setvar('_SETTINGS_MENU', $cfg['_SETTINGS_MENU']);
	$tmpl->setvar('_FLUXD_MENU', $cfg['_FLUXD_MENU']);
	$tmpl->setvar('_SEARCHSETTINGS_MENU', $cfg['_SEARCHSETTINGS_MENU']);
	$tmpl->setvar('_LINKS_MENU', $cfg['_LINKS_MENU']);
	$tmpl->setvar('_ACTIVITY_MENU', $cfg['_ACTIVITY_MENU']);
	$tmpl->setvar('table_admin_border', $cfg["table_admin_border"]);
	// superadmin
	if (IsSuperAdmin()) {
		$tmpl->setvar('is_superadmin', 1);
		$tmpl->setvar('adminmenu_superAdminLink', getSuperAdminLink('','<font class="adminlink">superadmin</font></a>'));
	}
}

/**
 * get Activity
 *
 * @param $min
 * @param $user
 * @param $srchFile
 * @param $srchAction
 */
function tmplSetActivity($min = 0, $user = "", $srchFile = "", $srchAction = "") {
	global $cfg, $db, $tmpl;
	$sqlForSearch = "";
	$userdisplay = $user;
	if ($user != "")
		$sqlForSearch .= "user_id=".$db->qstr($user)." AND ";
	else
		$userdisplay = $cfg['_ALLUSERS'];
	if ($srchFile != "")
		$sqlForSearch .= "file like ".$db->qstr("%".$srchFile."%")." AND ";
	if ($srchAction != "")
		$sqlForSearch .= "action like ".$db->qstr("%".$srchAction."%")." AND ";
	$offset = 50;
	$inx = 0;
	if (!isset($min))
		$min=0;
	$max = $min + $offset;
	$sql = "SELECT user_id, file, action, ip, ip_resolved, user_agent, time FROM tf_log WHERE ".$sqlForSearch."action!=".$db->qstr($cfg["constants"]["hit"])." ORDER BY time desc";
	$result = $db->SelectLimit($sql, $offset, $min);
	if ($db->ErrorNo() != 0) dbError($sql);
	$act_list = array();
	while (list($user_id, $file, $action, $ip, $ip_resolved, $user_agent, $time) = $result->FetchRow()) {
		$user_icon = (IsOnline($user_id))
			? "themes/".$cfg['theme']."/images/user.gif"
			: "themes/".$cfg['theme']."/images/user_offline.gif";
		$is_superuser = (IsUser($user_id)) ? 1 : 0;
		array_push($act_list, array(
			'is_superuser' => $is_superuser,
			'user_id' => $user_id,
			'user_icon' => $user_icon,
			'action' => htmlentities($action, ENT_QUOTES),
			'file' => htmlentities($file, ENT_QUOTES),
			'ip_resolved' => htmlentities($ip_resolved, ENT_QUOTES),
			'user_agent' => htmlentities($user_agent, ENT_QUOTES),
			'ip' => htmlentities($ip, ENT_QUOTES),
			'date' => date($cfg['_DATETIMEFORMAT'], $time)
			)
		);
		$inx++;
	}
	$prev = ($min - $offset);
	$selected = "";
	$action_list = array();
	foreach ($cfg["constants"] as $action) {
		if ($action != $cfg["constants"]["hit"]) {
			array_push($action_list, array(
				'action' => htmlentities($action, ENT_QUOTES),
				'selected' => ($srchAction == $action) ? "selected" : ""
				)
			);
		}
	}
	$user_list = array();
	$selected = "";
	for ($inx2 = 0; $inx2 < sizeof($cfg['users']); $inx2++) {
		array_push($user_list, array(
			'user' => htmlentities($cfg['users'][$inx2], ENT_QUOTES),
			'selected' => ($user == $cfg['users'][$inx2]) ? "selected" : ""
			)
		);
	}
	// set vars
	$tmpl->setvar('_USER', $cfg['_USER']);
	$tmpl->setvar('_ACTION', $cfg['_ACTION']);
	$tmpl->setvar('_FILE', $cfg['_FILE']);
	$tmpl->setvar('_IP', $cfg['_IP']);
	$tmpl->setvar('_TIMESTAMP', $cfg['_TIMESTAMP']);
	$tmpl->setvar('_NORECORDSFOUND', $cfg['_NORECORDSFOUND']);
	$tmpl->setvar('_SENDMESSAGETO', $cfg['_SENDMESSAGETO']);
	$tmpl->setvar('_ACTIVITYSEARCH', $cfg['_ACTIVITYSEARCH']);
	$tmpl->setvar('_FILE', $cfg['_FILE']);
	$tmpl->setvar('_SHOWPREVIOUS', $cfg['_SHOWPREVIOUS']);
	$tmpl->setvar('_SHOWMORE', $cfg['_SHOWMORE']);
	$tmpl->setvar('_ALL', $cfg['_ALL']);
	$tmpl->setvar('_DAYS', $cfg['_DAYS']);
	$tmpl->setvar('_SEARCH', $cfg['_SEARCH']);
	$tmpl->setvar('_ACTIVITYLOG', $cfg['_ACTIVITYLOG']);
	$tmpl->setvar('table_admin_border', $cfg["table_admin_border"]);
	$tmpl->setloop('activity_act_list', $act_list);
	$tmpl->setloop('activity_action_list', $action_list);
	$tmpl->setloop('activity_user_list', $user_list);
	$tmpl->setvar('activity_srchFile', $srchFile);
	$tmpl->setvar('activity_srchAction', $srchAction);
	$tmpl->setvar('activity_prev', $prev);
	$tmpl->setvar('activity_user', $user);
	$tmpl->setvar('activity_min', $min);
	$tmpl->setvar('activity_max', $max);
	$tmpl->setvar('activity_days_to_keep', $cfg["days_to_keep"]);
	$tmpl->setvar('activity_userdisplay', $userdisplay);
	if ($prev >= 0)
		$tmpl->setvar('activity_is_prev', 1);
	if ($inx>=$offset)
		$tmpl->setvar('activity_is_more', 1);
	if ($prev >= 0 || $inx>=$offset)
		$tmpl->setvar('activity_both_set', 1);
}

/**
 * sets vars for the user section
 */
function tmplSetUserSection() {
	global $cfg, $db, $tmpl;
	// xfer-prepare
	$tmpl->setvar('enable_xfer', $cfg["enable_xfer"]);
	if ($cfg['enable_xfer'] == 1) {
		$tmpl->setvar('userSection_colspan', 8);
		// getTransferListArray to update xfer-stats
    	// xfer-init
    	if ($cfg['xfer_realtime'] == 0) {
			$cfg['xfer_realtime'] = 1;
			// set xfer-newday
			Xfer::setNewday();
    	}
		@getTransferListArray();
	} else {
		$tmpl->setvar('userSection_colspan', 7);
	}
	// activity-prepare
	$total_activity = GetActivityCount();
	$sql = "SELECT user_id, hits, last_visit, time_created, user_level, state FROM tf_users ORDER BY user_id";
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// user-details
	$user_details_list = array();
	while (list($user_id, $hits, $last_visit, $time_created, $user_level, $user_state) = $result->FetchRow()) {
		// disk-usage
		$disk_usage = "0";
		$tDir = $cfg["path"].$user_id."/";
		if (is_dir($tDir)) {
			switch ($cfg["_OS"]) {
				case 1: //Linux
					$dudir = shell_exec($cfg['bin_du']." -sk -h -D ".tfb_shellencode($tDir));
					break;
				case 2: //BSD
					$dudir = shell_exec($cfg['bin_du']." -sk -h -L ".tfb_shellencode($tDir));
					break;
			}
			$dusize = explode("\t", $dudir);
			$disk_usage = array_shift($dusize);
		}
		// xfer-usage
		$xfer_usage = ($cfg['enable_xfer'] == 1)
			? Xfer::getUsage($user_id)
			: 0;
		// activity
		$user_activity = GetActivityCount($user_id);
		$user_percent = ($user_activity == 0)
			? 0
			: number_format(($user_activity / $total_activity) * 100);
		// online
		$user_icon = "themes/".$cfg['theme']."/images/user_offline.gif";
		if (IsOnline($user_id))
			$user_icon = "themes/".$cfg['theme']."/images/user.gif";
		// level
		$user_image = "themes/".$cfg['theme']."/images/user.gif";
		$type_user = $cfg['_NORMALUSER'];
		if ($user_level == 1) {
			$user_image = "themes/".$cfg['theme']."/images/admin_user.gif";
			$type_user = $cfg['_ADMINISTRATOR'];
		}
		if ($user_level == 2) {
			$user_image = "themes/".$cfg['theme']."/images/superadmin.gif";
			$type_user = $cfg['_SUPERADMIN'];
		}
		$is_superadmin = ($user_level <= 1 || IsSuperAdmin()) ? 1 : 0;
		// add to list
		array_push($user_details_list, array(
			'is_user' => IsUser($user_id),
			'user_id' => $user_id,
			'user_icon' => $user_icon,
			'hits' => $hits,
			'disk_usage' => $disk_usage,
			'xfer_usage' => $xfer_usage,
			'user_percent' => $user_percent,
			'user_percent2' => $user_percent*2,
			'user_percent3' => (200 - ($user_percent*2)),
			'time_created' => date($cfg['_DATEFORMAT'], $time_created),
			'last_visit' => date($cfg['_DATETIMEFORMAT'], $last_visit),
			'user_image' => $user_image,
			'type_user' => $type_user,
			'user_level' => $user_level,
			'user_state' => $user_state,
			'is_superadmin' => $is_superadmin
			)
		);
	}
	// set vars
	$tmpl->setvar('_USERDETAILS', $cfg['_USERDETAILS']);
	$tmpl->setvar('_USER', $cfg['_USER']);
	$tmpl->setvar('_HITS', $cfg['_HITS']);
	$tmpl->setvar('_UPLOADACTIVITY', $cfg['_UPLOADACTIVITY']);
	$tmpl->setvar('_JOINED', $cfg['_JOINED']);
	$tmpl->setvar('_LASTVISIT', $cfg['_LASTVISIT']);
	$tmpl->setvar('_ADMIN', $cfg['_ADMIN']);
	$tmpl->setvar('_DAYS', $cfg['_DAYS']);
	$tmpl->setvar('_SENDMESSAGETO', $cfg['_SENDMESSAGETO']);
	$tmpl->setvar('_WARNING', $cfg['_WARNING']);
	$tmpl->setvar('_ABOUTTODELETE', $cfg['_ABOUTTODELETE']);
	$tmpl->setvar('_USERSACTIVITY', $cfg['_USERSACTIVITY']);
	$tmpl->setvar('_EDIT', $cfg['_EDIT']);
	$tmpl->setvar('_DELETE', $cfg['_DELETE']);
	$tmpl->setvar('table_admin_border', $cfg["table_admin_border"]);
	$tmpl->setvar('days_to_keep', $cfg["days_to_keep"]);
	$tmpl->setloop('user_details_list', $user_details_list);
}

/**
 * shell_exec php binary with given command-line,
 * taking necessary precautions in case binary
 * it is not really a php-cli
 *
 * @param $php string
 * @param $args string
 * @return string
 */
function safePhpCli($php, $args) {
	# Need to be careful in order to avoid problems with cgi-fcgi SAPI php's:
	# - they detect whether they are in fcgi mode by testing whether their stdin is
	#   a socket -- so if this code is run by a fcgi php (whose stdin is a socket),
	#   child will inherit the same stdin and thus believe it is invoked in fcgi mode
	#    => '< /dev/null'
	# - they detect whether they are in cgi mode by testing the presence of environment
	#   variables -- so if this code is run by a cgi php (with those env. vars set),
	#   child will inherit those and thus believe it is invoked in cgi mode
	#    => 'unset ...'
	$cmd = 'unset SERVER_SOFTWARE SERVER_NAME GATEWAY_INTERFACE REQUEST_METHOD ; ';
	$cmd .= $php . ' ' . $args . ' < /dev/null';
	return shell_exec($cmd);
}

/**
 * Returns the status image after a validation
 *
 * @param $ok bool
 * @param $msg string
 * @return string
 */
function validationMsg($ok, $msg = null) {
	global $cfg;
	return
		'<img src="themes/'.$cfg['theme'].'/images/'.($ok ? 'green.gif' : 'red.gif').
		'" align="absmiddle" title="'.(!empty($msg) ? $msg : ($ok ? 'Valid' : 'Invalid')).'">'.
		(empty($msg) ? '' : ('<br><font color="'.($ok ? '#008000' : '#ff0000').'">'.$msg.'</font>'));
}

/**
 * Validates the existence of a file and returns the status image
 *
 * @param $the_file
 * @return string
 */
function validateFile($the_file) {
	if (!isFile($the_file))
		return validationMsg(false, 'Path is not valid');
	return validationMsg(true);
}

/**
 * Validates existence + exec of a file and returns the status image
 *
 * @param $the_file
 * @return string
 */
function validateBinary($the_file) {
	if (!isFile($the_file))
		return validationMsg(false, 'Path is not valid');
	if (!is_executable($the_file))
		return validationMsg(false, 'File exists but is not executable');
	return validationMsg(true);
}

/**
 * Validates existence + exec + valid version of PHP-cli and returns the status image
 *
 * @param $the_file
 * @return string
 */
function validatePhpCli($the_file) {
	if (!isFile($the_file))
		return validationMsg(false, 'Path is not valid');
	if (!is_executable($the_file))
		return validationMsg(false, 'File exists but is not executable');
	$phpVersion = safePhpCli($the_file, '-v');
	if ((strpos($phpVersion, 'PHP')) === false || (strpos($phpVersion, '(cli)')) === false)
		return validationMsg(false, 'Executable is not PHP-CLI');
	return validationMsg(true);
}

/**
 * Validates existence + exec + valid version of transmissioncli and returns the status image
 *
 * @param $the_file
 * @return string
 */
function validateTransmissionCli($the_file) {
	global $cfg;
	if (!isFile($the_file))
		return validationMsg(false, 'Path is not valid');
	if (!is_executable($the_file))
		return validationMsg(false, 'File exists but is not executable');
	$transmissionHelp = strtolower(shell_exec("HOME=".tfb_shellencode($cfg["path"])."; export HOME; ".$the_file.' --help'));
	return (
		strpos($transmissionHelp, 'transmission') === false ||
		((strpos($transmissionHelp, 'tfcli') === false) &&
		 (strpos($transmissionHelp, 'torrentflux') === false))
	)
		? validationMsg(false, 'Executable is not TorrentFlux-bundled transmissioncli')
		: validationMsg(true);
}

/**
 * setUserState
 */
function setUserState() {
	global $cfg, $db;
	$user_id = tfb_getRequestVar('user_id');
	$user_state = tfb_getRequestVar('state');
	// check params
	if (!(isset($user_id)) && (isset($user_state)))
		return false;
	// sanity-check, don't allow setting state of superadmin to 0
	if (($user_state == 0) && (IsSuperAdmin($user_id))) {
		AuditAction($cfg["constants"]["error"], "Invalid try to deactivate superadmin account.");
		return false;
	}
	// set new state
	$sql = "SELECT * FROM tf_users WHERE user_id = ".$db->qstr($user_id);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$rec = array('state'=>$user_state);
	$sql = $db->GetUpdateSQL($rs, $rec);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	switch ($user_state) {
		case 0:
			AuditAction($cfg["constants"]["admin"], "User ".$user_id." deactivated.");
			break;
		case 1:
			AuditAction($cfg["constants"]["admin"], "User ".$user_id." activated.");
			break;
	}
	return true;
}

/**
 * Add New Link
 *
 * @param $newLink
 * @param $newSite
 */
function addNewLink($newLink,$newSite) {
	global $db;
	//$rec = array('url'=>$newLink);
	// Link sort order index:
	$idx = -1;
	// Get current highest link index:
	$sql = "SELECT sort_order FROM tf_links ORDER BY sort_order DESC";
	$result = $db->SelectLimit($sql, 1);
	if ($db->ErrorNo() != 0) dbError($sql);
	$idx = ($result->fields === false)
		? 0 /* No links currently in db */
		: $result->fields["sort_order"] + 1;
	$rec = array(
		'url' => $newLink,
		'sitename' => $newSite,
		'sort_order' => $idx
	);
	$sTable = 'tf_links';
	$sql = $db->GetInsertSql($sTable, $rec);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
}

/**
 * This function updates the database and alters the selected links values
 *
 * @param $lid
 * @param $newLink
 * @param $newSite
 */
function alterLink($lid,$newLink,$newSite) {
	global $cfg, $db;
	$sql = "UPDATE tf_links SET url=".$db->qstr($newLink).",sitename=".$db->qstr($newSite)." WHERE lid = ".$db->qstr($lid);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
}

/**
 * get link
 *
 * @param $lid
 * @return string
 */
function getLink($lid) {
	global $cfg, $db;
	$rtnValue = "";
	$sql = "SELECT url FROM tf_links WHERE lid=".$db->qstr($lid);
	$rtnValue = $db->GetOne($sql);
	return $rtnValue;
}

/**
 * Delete Link
 *
 * @param $lid
 */
function deleteOldLink($lid) {
	global $db;
	// Link Mod
	//$sql = "delete from tf_links where lid=".$lid;
	// Get Current sort order index of link with this link id:
	$idx = getLinkSortOrder($lid);
	// Fetch all link ids and their sort orders where the sort order is greater
	// than the one we're removing - we need to shuffle each sort order down
	// one:
	$sql = "SELECT sort_order, lid FROM tf_links ";
	$sql .= "WHERE sort_order > ".$db->qstr($idx)." ORDER BY sort_order ASC";
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$arLinks = $result->GetAssoc();
	// Decrement the sort order of each link:
	foreach ($arLinks as $sid=>$this_lid) {
		$sql = "UPDATE tf_links SET sort_order=sort_order-1 WHERE lid=".$db->qstr($this_lid);
		$db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
	}
	// Finally delete the link:
	$sql = "DELETE FROM tf_links WHERE lid=".$db->qstr($lid);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
}

/**
 * get Link Sort Order
 *
 * @param $lid
 * @return string
 */
function getLinkSortOrder($lid) {
    global $db;
    // Get Current sort order index of link with this link id:
    $sql="SELECT sort_order FROM tf_links WHERE lid=".$db->qstr($lid);
    $rtnValue=$db->GetOne($sql);
    if ($db->ErrorNo() != 0) dbError($sql);
    return $rtnValue;
}

/**
 * get Site
 *
 * @param $lid
 * @return string
 */
function getSite($lid) {
    global $cfg, $db;
    $rtnValue = "";
    $sql = "SELECT sitename FROM tf_links WHERE lid=".$db->qstr($lid);
    $rtnValue = $db->GetOne($sql);
    return $rtnValue;
}

/**
 * Add New RSS Link
 *
 * @param $newRSS
 */
function addNewRSS($newRSS) {
	global $db;
	$rec = array('url'=>$newRSS);
	$sTable = 'tf_rss';
	$sql = $db->GetInsertSql($sTable, $rec);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Delete RSS
 *
 * @param $rid
 */
function deleteOldRSS($rid) {
	global $db;
	$sql = "delete from tf_rss where rid=".$db->qstr($rid);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * get RSS
 *
 * @param $rid
 * @return string
 */
function getRSS($rid) {
	global $cfg, $db;
	$rtnValue = "";
	$sql = "SELECT url FROM tf_rss WHERE rid=".$db->qstr($rid);
	$rtnValue = $db->GetOne($sql);
	return $rtnValue;
}

/**
 * Delete User
 *
 * @param $user_id
 */
function DeleteThisUser($user_id) {
	global $db;
	$sql = "SELECT uid FROM tf_users WHERE user_id = ".$db->qstr($user_id);
	$uid = $db->GetOne( $sql );
	if ($db->ErrorNo() != 0) dbError($sql);
	// delete any cookies this user may have had
	//$sql = "DELETE tf_cookies FROM tf_cookies, tf_users WHERE (tf_users.uid = tf_cookies.uid) AND tf_users.user_id=".$db->qstr($user_id);
	$sql = "DELETE FROM tf_cookies WHERE uid=".$db->qstr($uid);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// Now cleanup any message this person may have had
	$sql = "DELETE FROM tf_messages WHERE to_user=".$db->qstr($user_id);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// now delete the user from the table
	$sql = "DELETE FROM tf_users WHERE user_id=".$db->qstr($user_id);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
}

/**
 * Update User -- used by admin
 *
 * @param $user_id
 * @param $org_user_id
 * @param $pass1
 * @param $userType
 * @param $hideOffline
 */
function updateThisUser($user_id, $org_user_id, $pass1, $userType, $hideOffline) {
	global $db;
	$user_id = strtolower($user_id);
	if ($hideOffline == "")
		$hideOffline = 0;
	$sql = "select * from tf_users where user_id = ".$db->qstr($org_user_id);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$rec = array();
	$rec['user_id'] = $user_id;
	$rec['user_level'] = $userType;
	$rec['hide_offline'] = $hideOffline;
	if ($pass1 != "")
		$rec['password'] = md5($pass1);
	$sql = $db->GetUpdateSQL($rs, $rec);
	if ($sql != "") {
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
	}
	// if the original user id and the new id do not match, we need to update messages and log
	if ($user_id != $org_user_id) {
		$sql = "UPDATE tf_messages SET to_user=".$db->qstr($user_id)." WHERE to_user=".$db->qstr($org_user_id);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$sql = "UPDATE tf_messages SET from_user=".$db->qstr($user_id)." WHERE from_user=".$db->qstr($org_user_id);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$sql = "UPDATE tf_log SET user_id=".$db->qstr($user_id)." WHERE user_id=".$db->qstr($org_user_id);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		// flush session-cache
		cacheFlush();
	}
}

/**
 * Change User Level
 *
 * @param $user_id
 * @param $level
 */
function changeUserLevel($user_id, $level) {
	global $db;
	$sql = "select * from tf_users where user_id = ".$db->qstr($user_id);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$rec = array('user_level'=>$level);
	$sql = $db->GetUpdateSQL($rs, $rec);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * sets webapp-lock
 *
 * @param $lock 1|0
 * @return true or function exits with error
 */
function setWebappLock($lock) {
	global $db;
	$db->Execute("UPDATE tf_settings SET tf_value = ".$db->qstr($lock)." WHERE tf_key = 'webapp_locked'");
	// flush session-cache
	cacheFlush();
	// return
	return ($db->ErrorNo() == 0)
		? true
		: $db->ErrorMsg();
}

/**
 * reset transfer-Totals
 *
 * @return true or function exits with error
 */
function resetAllTransferTotals() {
	global $db;
	$db->Execute("DELETE FROM tf_transfer_totals");
	// set transfers-cache
	cacheTransfersSet();
	return ($db->ErrorNo() == 0)
		? true
		: $db->ErrorMsg();
}

?>