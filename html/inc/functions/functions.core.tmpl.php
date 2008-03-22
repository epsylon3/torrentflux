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
 * initialize global template-instance "$tmpl"
 *
 * @param $theme
 * @param $template
 */
function tmplInitializeInstance($theme, $template) {
	global $cfg, $tmpl;
	// theme-switch
	$path = ((strpos($theme, '/')) === false)
		? "themes/".$theme."/tmpl/"
		: "themes/tf_standard_themes/tmpl/";
	// template-cache-switch
	$tmpl = ($cfg['enable_tmpl_cache'] != 0)
		? new vlibTemplateCache($path.$template)
		: new vlibTemplate($path.$template);
	//  set common template-vars
	$tmpl->setvar('theme', $theme);
    $tmpl->setvar('pagetitle', @ $cfg["pagetitle"]);
    $tmpl->setvar('main_bgcolor', @ $cfg["main_bgcolor"]);
    $tmpl->setvar('table_border_dk', @ $cfg["table_border_dk"]);
    $tmpl->setvar('table_header_bg', @ $cfg["table_header_bg"]);
    $tmpl->setvar('table_data_bg', @ $cfg["table_data_bg"]);
    $tmpl->setvar('body_data_bg', @ $cfg["body_data_bg"]);
    $tmpl->setvar('isAdmin', @ $cfg['isAdmin']);
    $tmpl->setvar('_CHARSET', @ $cfg['_CHARSET']);
}

/**
 * template-factory.
 *
 * @param $theme
 * @param $template
 * @return vlib-template-instance
 */
function tmplGetInstance($theme, $template) {
	global $cfg;
	// theme-switch
	$path = ((strpos($theme, '/')) === false)
		? "themes/".$theme."/tmpl/"
		: "themes/tf_standard_themes/tmpl/";
	// template-cache-switch
	$_tmpl = ($cfg['enable_tmpl_cache'] != 0)
		? new vlibTemplateCache($path.$template)
		: new vlibTemplate($path.$template);
	//  set common template-vars
	$_tmpl->setvar('theme', $theme);
    $_tmpl->setvar('pagetitle', @ $cfg["pagetitle"]);
    $_tmpl->setvar('main_bgcolor', @ $cfg["main_bgcolor"]);
    $_tmpl->setvar('table_border_dk', @ $cfg["table_border_dk"]);
    $_tmpl->setvar('table_header_bg', @ $cfg["table_header_bg"]);
    $_tmpl->setvar('table_data_bg', @ $cfg["table_data_bg"]);
    $_tmpl->setvar('body_data_bg', @ $cfg["body_data_bg"]);
    $_tmpl->setvar('isAdmin', @ $cfg['isAdmin']);
    $_tmpl->setvar('_CHARSET', @ $cfg['_CHARSET']);
    // return template-instance
    return $_tmpl;
}

/**
 * set Title Bar vars.
 *
 * @param $pageTitleText
 * @param $showButtons
 */
function tmplSetTitleBar($pageTitleText, $showButtons = true) {
	global $cfg, $db, $tmpl;
	// set some vars
	$tmpl->setvar('titleBar_title', $pageTitleText);
	$tmpl->setvar('titleBar_showButtons', $showButtons);
	$tmpl->setvar('_TORRENTS', $cfg['_TORRENTS']);
	$tmpl->setvar('_DIRECTORYLIST', $cfg['_DIRECTORYLIST']);
	$tmpl->setvar('_UPLOADHISTORY', $cfg['_UPLOADHISTORY']);
	$tmpl->setvar('_MYPROFILE', $cfg['_MYPROFILE']);
	$tmpl->setvar('_MESSAGES', $cfg['_MESSAGES']);
	$tmpl->setvar('_ADMINISTRATION', $cfg['_ADMINISTRATION']);
	if ($showButtons)
		$tmpl->setvar('titleBar_number_messages', $db->GetOne("select count(*) from tf_messages where to_user=".$db->qstr($cfg["user"])." and IsNew = 1"));
}

/**
 * set sub-foot vars
 *
 * @param $showReturn
 */
function tmplSetFoot($showReturn = true) {
	global $cfg, $tmpl;
	// set some vars
	$tmpl->setvar('_RETURNTOTRANSFERS', $cfg['_RETURNTOTRANSFERS']);
	$tmpl->setvar('subfoot_showReturn', $showReturn);
}

/**
 * set iid vars
 */
function tmplSetIidVars() {
	global $cfg, $tmpl;
	// set some vars
	$_iid = tfb_getRequestVar('iid');
	$tmpl->setvar('iid', $_iid);
	$tmpl->setvar('mainMenu', (isset($cfg['mainMenu'][$_iid])) ? $cfg['mainMenu'][$_iid] : "home");
	$tmpl->setvar('fluxlink_display', $cfg['ui_displayfluxlink']);
	$fluxlink = getTorrentFluxLink();
	$tmpl->setvar( 'fluxlink_url', $fluxlink['address'] );
	$tmpl->setvar( 'fluxlink_name', $fluxlink['name'] );
	// The width should be used on all sites
	$tmpl->setvar('ui_dim_main_w', $cfg["ui_dim_main_w"]);
}

/**
 * set vars for Search Engine Drop Down List
 *
 * @param $selectedEngine
 * @param $autoSubmit
 */
function tmplSetSearchEngineDDL($selectedEngine = 'TorrentSpy', $autoSubmit = false) {
	global $cfg, $tmpl;
	// set some vars
	$tmpl->setvar('autoSubmit', $autoSubmit);
	$handle = opendir("./inc/searchEngines");
	while($entry = readdir($handle))
		$entrys[] = $entry;
	natcasesort($entrys);
	$Engine_List = array();
	foreach($entrys as $entry) {
		if ($entry != "." && $entry != ".." && substr($entry, 0, 1) != "." && strpos($entry,"Engine.php")) {
			$tmpEngine = str_replace("Engine",'',substr($entry,0,strpos($entry,".")));
			if (array_key_exists($tmpEngine,$cfg['searchEngineLinks'])) {
				$hreflink = $cfg['searchEngineLinks'][$tmpEngine];
				$settings['searchEngineLinks'][$tmpEngine] = $hreflink;
			} else {
				$hreflink = getEngineLink($tmpEngine);
				$settings['searchEngineLinks'][$tmpEngine] = $hreflink;
				$settingsNeedsSaving = true;
			}
			array_push($Engine_List, array(
				'selected' => ($selectedEngine == $tmpEngine) ? 1 : 0,
				'Engine' => $tmpEngine,
				'hreflink' => $hreflink,
				)
			);
		}
	}
	return $Engine_List;
}

/**
 * drivespace bar
 *
 */
function tmplSetDriveSpaceBar() {
	global $cfg, $tmpl;
	$tmpl->setvar('_STORAGE', $cfg['_STORAGE']);
	$tmpl->setvar('drivespacebar_type', $cfg['drivespacebar']);
	$tmpl->setvar('drivespacebar_space', $cfg['driveSpace']);
	$tmpl->setvar('drivespacebar_space2', (100 - $cfg['driveSpace']));
	$tmpl->setvar('drivespacebar_freeSpace', $cfg['freeSpaceFormatted']);
	// color for xfer
	switch ($cfg['drivespacebar']) {
		case "xfer":
			$bgcolor = '#';
			$bgcolor .= str_pad(dechex(256 - 256 * ((100 - $cfg['driveSpace']) / 100)), 2, 0, STR_PAD_LEFT);
			$bgcolor .= str_pad(dechex(256 * ((100 - $cfg['driveSpace']) / 100)), 2, 0, STR_PAD_LEFT);
			$bgcolor .= '00';
			$tmpl->setvar('drivespacebar_bgcolor', $bgcolor);
			break;
	}
}

/**
 * bandwidth bars
 *
 */
function tmplSetBandwidthBars() {
	global $cfg, $tmpl;
	$tmpl->setvar('bandwidthbars_type', $cfg['bandwidthbar']);
	// upload
	$max_upload = $cfg["bandwidth_up"] / 8;
	$percent_upload = ($max_upload > 0)
		? @number_format(($cfg["total_upload"] / $max_upload) * 100, 0)
		: 0;
	$tmpl->setvar('bandwidthbars_upload_text',
		($percent_upload > 0)
			? @number_format($cfg["total_upload"], 2)
			: "0.00");
	$percent_upload = ($percent_upload >= 100)? 100:$percent_upload;
	$tmpl->setvar('bandwidthbars_upload_percent', $percent_upload);
	$tmpl->setvar('bandwidthbars_upload_percent2', (100 - $percent_upload));
	// download
	$max_download = $cfg["bandwidth_down"] / 8;
	$percent_download = ($max_download > 0)
		? @number_format(($cfg["total_download"] / $max_download) * 100, 0)
		: 0;
	$tmpl->setvar('bandwidthbars_download_text',
		($percent_download > 0)
			? @number_format($cfg["total_download"], 2)
			: "0.00");
	$percent_download = ($percent_download >= 100)? 100:$percent_download;
	$tmpl->setvar('bandwidthbars_download_percent', $percent_download);
	$tmpl->setvar('bandwidthbars_download_percent2', (100 - $percent_download));
	// colors for xfer
	switch ($cfg['bandwidthbar']) {
		case "xfer":
			// upload
			$bgcolor = '#';
			$bgcolor .= str_pad(dechex(255 - 255 * ((100 - $percent_upload) / 150)), 2, 0, STR_PAD_LEFT);
			$bgcolor .= str_pad(dechex(255 * ((100 - $percent_upload) / 150)), 2, 0, STR_PAD_LEFT);
			$bgcolor .='00';
			$tmpl->setvar('bandwidthbars_upload_bgcolor', $bgcolor);
			// download
			$bgcolor = '#';
			$bgcolor .= str_pad(dechex(255 - 255 * ((100 - $percent_download) / 150)), 2, 0, STR_PAD_LEFT);
			$bgcolor .= str_pad(dechex(255 * ((100 - $percent_download) / 150)), 2, 0, STR_PAD_LEFT);
			$bgcolor .='00';
			$tmpl->setvar('bandwidthbars_download_bgcolor', $bgcolor);
	}
}

/**
 * gets xfer percentage bar
 *
 * @param $total
 * @param $used
 * @param $title
 * @return string
 */
function tmplGetXferBar($total, $used, $title, $type='xfer') {
	global $cfg;
	// create template-instance
	$tmpl = tmplGetInstance($cfg["theme"], "component.xferBar.tmpl");
	$remaining = $total - ($used / 1048576);
	$remaining = max(0, min($total, $remaining));
	$percent = round(($remaining / $total) * 100,0);
	$text = ' ('.formatFreeSpace($remaining).') '.$cfg['_REMAINING'];
	if($type=='xfer')
	{
		$bgcolor = '#';
		$bgcolor .= str_pad(dechex(255 - 255 * ($percent / 150)), 2 ,0, STR_PAD_LEFT);
		$bgcolor .= str_pad(dechex(255 * ($percent / 150)), 2, 0, STR_PAD_LEFT);
		$bgcolor .='00';
		$tmpl->setvar('bgcolor', $bgcolor);
	}	
	$tmpl->setvar('title', $title);
	$tmpl->setvar('percent', $percent);
	$tmpl->setvar('text', $text);
	$tmpl->setvar('type', $type);
	$percent_100 = 100 - $percent;
	$tmpl->setvar('percent_100', $percent_100);
	// grab the template
	$output = $tmpl->grab();
	return $output;
}

/**
 * get TF Link and Version
 *
 * @return string
 */
function getTorrentFluxLink() {
	global $cfg;
	$torrentFluxLink['address'] = "http://tf-b4rt.berlios.de/";
	$torrentFluxLink['name'] = "torrentflux-b4rt ".$cfg["version"];
	return $torrentFluxLink;
}

/**
 * get Engine Link
 *
 * @param $searchEngine
 * @return string
 */
function getEngineLink($searchEngine) {
	$tmpLink = '';
	$engineFile = 'inc/searchEngines/'.$searchEngine.'Engine.php';
	if (is_file($engineFile)) {
		$fp = @fopen($engineFile,'r');
		if ($fp) {
			$tmp = @fread($fp, filesize($engineFile));
			@fclose( $fp );
			$tmp = substr($tmp,strpos($tmp,'$this->mainURL'),100);
			$tmp = substr($tmp,strpos($tmp,"=")+1);
			$tmp = substr($tmp,0,strpos($tmp,";"));
			$tmpLink = trim(str_replace(array("'","\""),"",$tmp));
		}
	}
	return $tmpLink;
}

/**
 * Returns a string "file name" of the status image icon
 *
 * @param $sf
 * @return string
 */
function getStatusImage($sf) {
	$hd = new HealthData();
	$hd->image = "black.gif";
	$hd->title = "";
	if ($sf->running == "1") {
		// running
		if ($sf->seeds < 2)
			$hd->image = "yellow.gif";
		if ($sf->seeds == 0)
			$hd->image = "red.gif";
		if ($sf->seeds >= 2)
			$hd->image = "green.gif";
	}
	if ($sf->percent_done >= 100) {
		$hd->image = (trim($sf->up_speed) != "" && $sf->running == "1")
			? "green.gif" /* seeding */
			: "black.gif"; /* finished */
	}
	if ($hd->image != "black.gif")
		$hd->title = "S:".$sf->seeds." P:".$sf->peers." ";
	if ($sf->running == "3") {
		// queued
		$hd->image = "black.gif";
	}
	return $hd;
}

/**
 * get path to images of current theme
 *
 * @return string
 */
function getImagesPath() {
	global $cfg;
	return "themes/".$cfg['theme']."/images/";
}

?>