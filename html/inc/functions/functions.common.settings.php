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
 * load Settings
 *
 * @param $dbTable
 */
function loadSettings($dbTable) {
    global $cfg, $db;
    // pull the config params out of the db
    $sql = "SELECT tf_key, tf_value FROM ".$dbTable;
    $recordset = $db->Execute($sql);
    if ($db->ErrorNo() != 0) dbError($sql);
    while(list($key, $value) = $recordset->FetchRow()) {
		$tmpValue = '';
		if (strpos($key,"Filter") > 0)
			$tmpValue = unserialize($value);
		elseif ($key == 'searchEngineLinks')
			$tmpValue = unserialize($value);
		if (is_array($tmpValue))
			$value = $tmpValue;
		$cfg[$key] = $value;
    }
}

/**
 * insert Setting
 *
 * @param $dbTable
 * @param $key
 * @param $value
 */
function insertSetting($dbTable, $key, $value) {
    global $cfg, $db;
	// flush session-cache
	cacheFlush();
    $insert_value = (is_array($value)) ? serialize($value) : $value;
    $sql = "INSERT INTO ".$dbTable." VALUES (".$db->qstr($key).", ".$db->qstr($insert_value).")";
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// update the Config.
	$cfg[$key] = $value;
}

/**
 * updateSetting
 *
 * @param $dbTable
 * @param $key
 * @param $value
 */
function updateSetting($dbTable, $key, $value) {
    global $cfg, $db;
	// flush session-cache
	cacheFlush();
    $update_value = (is_array($value)) ? serialize($value) : $value;
    $sql = "UPDATE ".$dbTable." SET tf_value = ".$db->qstr($update_value)." WHERE tf_key = ".$db->qstr($key);
    $db->Execute($sql);
    if ($db->ErrorNo() != 0) dbError($sql);
    // update the Config.
    $cfg[$key] = $value;
}

/**
 * save Settings
 *
 * @param $dbTable
 * @param $settings
 */
function saveSettings($dbTable, $settings) {
    global $cfg, $db;
    foreach ($settings as $key => $value) {
        if (array_key_exists($key, $cfg)) {
            if (is_array($cfg[$key]) || is_array($value)) {
                if (serialize($cfg[$key]) != serialize($value))
                    updateSetting($dbTable, $key, $value);
            } elseif ($cfg[$key] != $value) {
                updateSetting($dbTable, $key, $value);
            }
        } else {
            insertSetting($dbTable, $key, $value);
        }
    }
}

/*
 * Function for saving user Settings
 *
 * @param $uid uid of the user
 * @param $settings settings-array
 */
function saveUserSettings($uid, $settings) {
	global $cfg;
	// Messy - a not exists would prob work better. but would have to be done
	// on every key/value pair so lots of extra-statements.
	deleteUserSettings($uid);
	// load global settings + overwrite per-user settings
	loadSettings('tf_settings');
	// insert new settings
	foreach ($settings as $key => $value) {
		if (in_array($key, $cfg['validUserSettingsKeys']))
			insertUserSettingPair($uid, $key, $value);
		else
			AuditAction($cfg["constants"]["error"], "ILLEGAL SETTING: ".$cfg["user"]." tried to insert ".$value." for key ".$key);
	}
	// flush session-cache
	cacheFlush($cfg["user"]);
	// return
	return true;
}

/*
 * insert setting-key/val pair for user into db
 *
 * @param $uid uid of the user
 * @param $key
 * @param $value
 * @return boolean
 */
function insertUserSettingPair($uid, $key, $value) {
	global $cfg, $db;
	$insert_value = $value;
	if (is_array($value)) {
		$insert_value = serialize($value);
	} else {
		// only insert if setting different from global settings or has changed
		if ($cfg[$key] == $value)
			return true;
	}
	$sql = "INSERT INTO tf_settings_user VALUES (".$db->qstr($uid).", ".$db->qstr($key).", ".$db->qstr($insert_value).")";
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// update the Config.
	$cfg[$key] = $value;
	// return
	return true;
}

/*
 * Function to delete saved user Settings
 *
 * @param $uid uid of the user
 */
function deleteUserSettings($uid) {
	global $cfg, $db;
	// delete from db
	$sql = "DELETE FROM tf_settings_user WHERE uid = ".$db->qstr($uid);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush($cfg["user"]);
	// return
	return true;
}

/*
 * Function to delete all saved user Settings
 */
function deleteAllUserSettings() {
	global $cfg, $db;
	// delete from db
	$sql = "DELETE FROM tf_settings_user";
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// flush session-cache
	cacheFlush();
	// return
	return true;
}

/*
 * Function to load the settings for a user to global cfg-array
 *
 * @param $uid uid of the user
 * @return boolean
 */
function loadUserSettingsToConfig($uid) {
	global $cfg, $db;
	// get user-settings from db and set in global cfg-array
	$sql = "SELECT tf_key, tf_value FROM tf_settings_user WHERE uid = ".$db->qstr($uid);
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	if ((isset($recordset)) && ($recordset->NumRows() > 0)) {
		while(list($key, $value) = $recordset->FetchRow())
			$cfg[$key] = $value;
	}
	// return
	return true;
}

/**
 * process post-params on config-update and init settings-array
 *
 * @param $updateIndexSettings
 * @param $updateGoodlookinSettings
 * @return array with settings
 */
function processSettingsParams($updateIndexSettings = true, $updateGoodlookinSettings = true) {
	// move
	if (isset($_POST['categorylist']))
		unset($_POST['categorylist']);
	if (isset($_POST['category']))
		unset($_POST['category']);
	// res-dir
	if (isset($_POST['resdirlist']))
		unset($_POST['resdirlist']);
	if (isset($_POST['resdirentry']))
		unset($_POST['resdirentry']);
	// init settings array from params
	// process and handle all specials and exceptions while doing this.
	$settings = array();
	// index-page
	if ($updateIndexSettings) {
		$indexPageSettingsPrefix = "index_page_settings_";
		$indexPageSettingsPrefixLen = strlen($indexPageSettingsPrefix);
		$settingsIndexPageAry = array();
		for ($j = 0; $j <= 11; $j++)
			$settingsIndexPageAry[$j] = 0;
	}
	// good-look-stats
	if ($updateGoodlookinSettings) {
		$hackStatsPrefix = "hack_goodlookstats_settings_";
		$hackStatsStringLen = strlen($hackStatsPrefix);
		$settingsHackAry = array();
		for ($i = 0; $i <= 5; $i++)
			$settingsHackAry[$i] = 0;
	}
	//
	foreach ($_POST as $key => $value) {
		if (($updateIndexSettings) && ((substr($key, 0, $hackStatsStringLen)) == $hackStatsPrefix)) {
			// good-look-stats
			$idx = intval(substr($key, -1, 1));
			$settingsHackAry[$idx] = ($value != "0") ? 1 : 0;
		} else if (($updateGoodlookinSettings) && ((substr($key, 0, $indexPageSettingsPrefixLen)) == $indexPageSettingsPrefix)) {
			// index-page
			$idx = intval(substr($key, ($indexPageSettingsPrefixLen - (strlen($key)))));
			$settingsIndexPageAry[$idx] = ($value != "0") ? 1 : 0;
		} else {
			switch ($key) {
				case "path": // tf-path
					$settings[$key] = trim(checkDirPathString($value));
					break;
				case "docroot": // tf-docroot
					$settings[$key] = trim(checkDirPathString($value));
					break;
				case "move_paths": // move-hack-paths
					if (strlen($value) > 0) {
						$val = "";
						$dirAry = explode(":",$value);
						for ($idx = 0; $idx < count($dirAry); $idx++) {
							if ($idx > 0)
								$val .= ':';
							$val .= trim(checkDirPathString($dirAry[$idx]));
						}
						$settings[$key] = trim($val);
					} else {
						$settings[$key] = "";
					}
					break;
				default: // "normal" key-val-pair
					$settings[$key] = $value;
			}
		}
	}
	// index-page
	if ($updateIndexSettings)
		$settings['index_page_settings'] = convertArrayToInteger($settingsIndexPageAry);
	// good-look-stats
	if ($updateGoodlookinSettings)
		$settings['hack_goodlookstats_settings'] = convertArrayToByte($settingsHackAry);
	// return
	return $settings;
}

?>