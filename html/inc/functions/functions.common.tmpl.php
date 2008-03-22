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
 * set vars for form of index page settings
 */
function tmplSetIndexPageFormVars() {
	global $cfg, $tmpl;
	// set vars
	$tmpl->setvar('enable_index_meta_refresh', $cfg["enable_index_meta_refresh"]);
	$tmpl->setvar('page_refresh', $cfg["page_refresh"]);
	$tmpl->setvar('enable_index_ajax_update', $cfg["enable_index_ajax_update"]);
	$tmpl->setvar('enable_index_ajax_update_title', $cfg["enable_index_ajax_update_title"]);
	$tmpl->setvar('enable_index_ajax_update_users', $cfg["enable_index_ajax_update_users"]);
	$tmpl->setvar('enable_index_ajax_update_list', $cfg["enable_index_ajax_update_list"]);
	$tmpl->setvar('enable_index_ajax_update_silent', $cfg["enable_index_ajax_update_silent"]);
	$tmpl->setvar('index_ajax_update', $cfg["index_ajax_update"]);
	$tmpl->setvar('index_show_seeding', $cfg["index_show_seeding"]);
	$tmpl->setvar('enable_multiupload', $cfg["enable_multiupload"]);
	$tmpl->setvar('hack_multiupload_rows', $cfg["hack_multiupload_rows"]);
	$tmpl->setvar('ui_displaylinks', $cfg["ui_displaylinks"]);
	$tmpl->setvar('ui_displayusers', $cfg["ui_displayusers"]);
	$tmpl->setvar('ui_displaybandwidthbars', $cfg["ui_displaybandwidthbars"]);
	$tmpl->setvar('bandwidthbar', $cfg["bandwidthbar"]);
	$tmpl->setvar('bandwidth_up', $cfg["bandwidth_up"]);
	$tmpl->setvar('bandwidth_down', $cfg["bandwidth_down"]);
	$tmpl->setvar('enable_goodlookstats', $cfg["enable_goodlookstats"]);
	$tmpl->setvar('enable_bigboldwarning', $cfg["enable_bigboldwarning"]);
	$tmpl->setvar('enable_search', $cfg["enable_search"]);
	$tmpl->setvar('index_page_stats', $cfg["index_page_stats"]);
	$tmpl->setvar('show_server_load', $cfg["show_server_load"]);
	$tmpl->setvar('index_page_connections', $cfg["index_page_connections"]);
	$tmpl->setvar('enable_restrictivetview', $cfg["enable_restrictivetview"]);
	$tmpl->setvar('enable_metafile_download', $cfg["enable_metafile_download"]);
	$tmpl->setvar('enable_sorttable', $cfg["enable_sorttable"]);
	$tmpl->setvar('enable_multiops', $cfg["enable_multiops"]);
	$tmpl->setvar('enable_bulkops', $cfg["enable_bulkops"]);
	$tmpl->setvar('display_seeding_time', $cfg["display_seeding_time"]);
	$tmpl->setvar('index_page_sortorder', $cfg["index_page_sortorder"]);
	$tmpl->setloop('Engine_List', tmplSetSearchEngineDDL($cfg["searchEngine"]));
	$transferWindowDefaultList = array();
	array_push($transferWindowDefaultList, array(
		'name' => 'Stats',
		'value' => 'transferStats',
		'is_selected' => ('transferStats' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Hosts',
		'value' => 'transferHosts',
		'is_selected' => ('transferHosts' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Scrape',
		'value' => 'transferScrape',
		'is_selected' => ('transferScrape' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Images',
		'value' => 'transferImages',
		'is_selected' => ('transferImages' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Log',
		'value' => 'transferLog',
		'is_selected' => ('transferLog' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Details',
		'value' => 'transferDetails',
		'is_selected' => ('transferDetails' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Files',
		'value' => 'transferFiles',
		'is_selected' => ('transferFiles' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Settings',
		'value' => 'transferSettings',
		'is_selected' => ('transferSettings' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	array_push($transferWindowDefaultList, array(
		'name' => 'Control',
		'value' => 'transferControl',
		'is_selected' => ('transferControl' == $cfg["transfer_window_default"]) ? 1 : 0
		)
	);
	$tmpl->setloop('transfer_window_default_list', $transferWindowDefaultList);
	//
	tmplSetGoodLookingStatsForm();
	tmplSetIndexPageSettingsForm();
}

/**
 * set vars for form of index page settings (0-2047)
 *
 * User			  [0]
 * Size			  [1]
 * DLed			  [2]
 * ULed			  [3]
 * Status		  [4]
 * Progress		  [5]
 * DL Speed		  [6]
 * UL Speed		  [7]
 * Seeds		  [8]
 * Peers		  [9]
 * ETA			 [10]
 * TorrentClient [11]
 *
 */
function tmplSetIndexPageSettingsForm() {
	global $cfg, $tmpl;
	$settingsIndexPage = convertIntegerToArray($cfg["index_page_settings"]);
	$tmpl->setvar('indexPageSettingsForm_settings_0', $settingsIndexPage[0]);
	$tmpl->setvar('indexPageSettingsForm_settings_1', $settingsIndexPage[1]);
	$tmpl->setvar('indexPageSettingsForm_settings_2', $settingsIndexPage[2]);
	$tmpl->setvar('indexPageSettingsForm_settings_3', $settingsIndexPage[3]);
	$tmpl->setvar('indexPageSettingsForm_settings_4', $settingsIndexPage[4]);
	$tmpl->setvar('indexPageSettingsForm_settings_5', $settingsIndexPage[5]);
	$tmpl->setvar('indexPageSettingsForm_settings_6', $settingsIndexPage[6]);
	$tmpl->setvar('indexPageSettingsForm_settings_7', $settingsIndexPage[7]);
	$tmpl->setvar('indexPageSettingsForm_settings_8', $settingsIndexPage[8]);
	$tmpl->setvar('indexPageSettingsForm_settings_9', $settingsIndexPage[9]);
	$tmpl->setvar('indexPageSettingsForm_settings_10', $settingsIndexPage[10]);
	$tmpl->setvar('indexPageSettingsForm_settings_11', $settingsIndexPage[11]);
}

/**
 * set vars for form of good looking stats (0-63)
 */
function tmplSetGoodLookingStatsForm() {
	global $cfg, $tmpl;
	$settingsHackStats = convertByteToArray($cfg["hack_goodlookstats_settings"]);
	$tmpl->setvar('goodLookingStatsForm_settings_0', $settingsHackStats[0]);
	$tmpl->setvar('goodLookingStatsForm_settings_1', $settingsHackStats[1]);
	$tmpl->setvar('goodLookingStatsForm_settings_2', $settingsHackStats[2]);
	$tmpl->setvar('goodLookingStatsForm_settings_3', $settingsHackStats[3]);
	$tmpl->setvar('goodLookingStatsForm_settings_4', $settingsHackStats[4]);
	$tmpl->setvar('goodLookingStatsForm_settings_5', $settingsHackStats[5]);
}

/**
 * Set Client Select Form vars
 *
 * @param $client
 */
function tmplSetClientSelectForm($client = 'tornado') {
	global $cfg, $tmpl;
	$clients = array("tornado", "transmission", "mainline", "azureus");
	$client_list = array();
	foreach ($clients as $clnt) {
		array_push($client_list, array(
			'client' => $clnt,
			'selected' => ($client == $clnt) ? 1 : 0
			)
		);
	}
	$tmpl->setloop('clientSelectForm_client_list', $client_list);
}

/**
 * set dir tree vars
 *
 * @param $dir
 * @param $maxdepth
 */
function tmplSetDirTree($dir, $maxdepth) {
	global $cfg, $tmpl;
	$tmpl->setvar('dirtree_dir', $dir);
	if (is_numeric($maxdepth)) {
		$retvar_list = array();
		$last = ($maxdepth == 0)
			? exec("find ".tfb_shellencode($dir)." -type d | sort && echo", $retval)
			: exec("find ".tfb_shellencode($dir)." -maxdepth ".tfb_shellencode($maxdepth)." -type d | sort && echo", $retval);
		for ($i = 1; $i < (count ($retval) - 1); $i++)
			array_push($retvar_list, array('retval' => $retval[$i]));
		$tmpl->setloop('dirtree_retvar_list', $retvar_list);
	}
}

/**
 * set vars for form of move-settings
 */
function tmplSetMoveSettings() {
	global $cfg, $tmpl;
	if ((isset($cfg["move_paths"])) && (strlen($cfg["move_paths"]) > 0)) {
		$dirs = split(":", trim($cfg["move_paths"]));
		$dir_list = array();
		foreach ($dirs as $dir) {
			$target = trim($dir);
			if ((strlen($target) > 0) && ((substr($target, 0, 1)) != ";"))
				array_push($dir_list, array('target' => $target));
		}
		$tmpl->setloop('moveSettings_move_list', $dir_list);
	}
	$tmpl->setvar('moveSettings_move_paths', $cfg["move_paths"]);
}

/**
 * get superadmin-popup-link-html-snip.
 *
 * @param $param
 * @param $linkText
 * @return string
 */
function getSuperAdminLink($param = "", $linkText = "") {
	global $cfg;
	// create template-instance
	$_tmpl = tmplGetInstance($cfg["theme"], "component.superAdminLink.tmpl");
	$_tmpl->setvar('param', $param);
	if ((isset($linkText)) && ($linkText != ""))
		$_tmpl->setvar('linkText', $linkText);
	// grab the template
	$output = $_tmpl->grab();
	return $output;
}

?>