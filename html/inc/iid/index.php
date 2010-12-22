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

// prevent direct invocation
if ((!isset($cfg['user'])) or (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

if (isset($_REQUEST['ajax_update'])) {
	$isAjaxUpdate = true;
	$ajaxUpdateParams = tfb_getRequestVar('ajax_update');
	// init template-instance
	tmplInitializeInstance($cfg["theme"], "inc.transferList.tmpl");
} else {
	$isAjaxUpdate = false;
	// init template-instance
	tmplInitializeInstance($cfg["theme"], "page.index.tmpl");
}

// =============================================================================
// set common vars
// =============================================================================

// language
$tmpl->setvar('_STATUS', $cfg['_STATUS']);
$tmpl->setvar('_ESTIMATEDTIME', $cfg['_ESTIMATEDTIME']);
$tmpl->setvar('_RUNTRANSFER', $cfg['_RUNTRANSFER']);
$tmpl->setvar('_STOPTRANSFER', $cfg['_STOPTRANSFER']);
$tmpl->setvar('_DELQUEUE', $cfg['_DELQUEUE']);
$tmpl->setvar('_SEEDTRANSFER', $cfg['_SEEDTRANSFER']);
$tmpl->setvar('_DELETE', $cfg['_DELETE']);
$tmpl->setvar('_WARNING', $cfg['_WARNING']);
$tmpl->setvar('_NOTOWNER', $cfg['_NOTOWNER']);
$tmpl->setvar('_STOPPING', $cfg['_STOPPING']);
$tmpl->setvar('_TRANSFERFILE', $cfg['_TRANSFERFILE']);
$tmpl->setvar('_ADMIN', $cfg['_ADMIN']);
$tmpl->setvar('_USER', $cfg['_USER']);
$tmpl->setvar('_USERS', $cfg['_USERS']);

// username
$tmpl->setvar('user', $cfg["user"]);

// queue
$tmpl->setvar('queueActive', (FluxdQmgr::isRunning()) ? 1 : 0);

// incoming-path
$tmpl->setvar('path_incoming', ($cfg["enable_home_dirs"] != 0) ? $cfg["user"] : $cfg["path_incoming"]);

// some configs
$tmpl->setvar('enable_metafile_download', $cfg["enable_metafile_download"]);
$tmpl->setvar('enable_multiops', $cfg["enable_multiops"]);
$tmpl->setvar('twd', $cfg["transfer_window_default"]);

// =============================================================================
// transfer-list
// =============================================================================
$arUserTorrent = array();
$arListTorrent = array();
// settings
$settings = convertIntegerToArray($cfg["index_page_settings"]);
// sortOrder
$sortOrder = tfb_getRequestVar("so");
$tmpl->setvar('sortOrder', (empty($sortOrder)) ? $cfg["index_page_sortorder"] : $sortOrder);
// t-list
$arList = getTransferArray($sortOrder);

$progress_color = "#22BB22";
$bar_width = "4";

$bUseRPC = false;

// ---------------------------------------------------------------------
if ($cfg["transmission_rpc_enable"]) {
	$bUseRPC = true;

	// add transmission rpc torrent to current user list

	require_once('inc/functions/functions.rpc.transmission.php');
	$result = getUserTransmissionTransfers($cfg['uid']);

	// eta
	//  -1 : Done
	//  -2 : Unknown

	foreach($result as $aTorrent)
	{
		// fill in eta
		if ( $aTorrent['eta'] == '-1' && $aTorrent['percentDone'] != 1 ) {
			$eta = 'n/a';
		} elseif ( $aTorrent['percentDone'] == 1 ) {
			$eta = 'Download Succeeded!';
		} elseif ( $aTorrent['eta'] == '-2' ) {
			$eta = 'Unknown';
		} else {
			$eta = convertTime( $aTorrent['eta'] );
		}

		$status = $aTorrent['status'];
		switch ($aTorrent['status']) {
		case 16:
			$transferRunning = false;
			if ( $aTorrent['percentDone'] >= 1 ) {
				$status = "Done";
				$eta = '';
			} else {
				$status = "Stopped";
				$eta = 'Torrent Stopped'; # this might be fixed in a cleaner way
				if ( $aTorrent['downloadedEver'] == 0 ) {
					$status = "New";
					$eta = '';
				}
			}
			break;
		case 4:
			if ( $aTorrent['rateDownload'] == 0 ) {
				$status = "Idle";
			} else {
				$status = "Downloading";
			}
			$transferRunning = true;
			break;
		case 8:
			$status = "Seeding";
			$transferRunning = true;
			break;
		}

		// TODO: transferowner is always admin... probably not what we want
		$tArray = array(
			'is_owner' => true,
			'transferRunning' => ($transferRunning ? 1 : 0),
			'url_entry' => $aTorrent['hashString'],
			'hd_image' => getTransmissionStatusImage($transferRunning, $aTorrent['trackerStats']['seederCount'], $aTorrent['rateUpload']),
			'hd_title' => $nothing,
			'displayname' => $aTorrent['name'],
			'transferowner' => 'administrator',
			'format_af_size' => formatBytesTokBMBGBTB( $aTorrent['totalSize'] ),
			'format_downtotal' => $nothing,
			'format_uptotal' => $nothing,
			'statusStr' => $status,
			'graph_width' => ( $status==='New' ? -1 : floor($aTorrent['percentDone']*100) ),
			'percentage' => ( $status==='New' ? '' : floor($aTorrent['percentDone']*100) . '%' ),
			'progress_color' => '#22BB22',
			'bar_width' => 4,
			'background' => '#000000',
			'100_graph_width' => 100 - floor($aTorrent['percentDone']*100),
			'down_speed' => formatBytesTokBMBGBTB( $aTorrent['rateDownload'] ) . '/s',
			'up_speed' => formatBytesTokBMBGBTB( $aTorrent['rateUpload'] ) . '/s',
			'seeds' => $nothing,
			'peers' => $nothing,
			'estTime' => $eta,
			'clientType' => 'torrent',
			'upload_support_enabled' => 1,
			'client' => $nothing,
			'url_path' => urlencode( $cfg['user'] . '/' . $aTorrent['name'] ),
			'datapath' => $aTorrent['name'],
			'is_no_file' => 1,
			'show_run' => 1,
			'entry' => $aTorrent['name']
		);

		array_push($arUserTorrent, $tArray);

		// Adds the transfer rate for this torrent to the total transfer rate
		if ($transferRunning) {
			if (!isset($cfg["total_upload"]))
				$cfg["total_upload"] = 0;
			if (!isset($cfg["total_download"]))
				$cfg["total_download"] = 0;
			$cfg["total_upload"] = $cfg["total_upload"] + GetSpeedValue($aTorrent[rateUpload]/1000);
			$cfg["total_download"] = $cfg["total_download"] + GetSpeedValue($aTorrent[rateDownload]/1000);
		}
	}
}
// end of Transmission RPC bloc
// ---------------------------------------------------------------------

if ($cfg["vuze_rpc_enable"]) {
	$bUseRPC = true;
	require_once("inc/classes/VuzeRPC.php");
	//one client for all vuzerpc transferts
	$rpc = VuzeRPC::getInstance($cfg);
	
	$vuzeResults = $rpc->torrent_get_tf_array();
}

// end of Vuze RPC requests
// ---------------------------------------------------------------------

$nbRpc=0;
foreach ($arList as $mtimecrc => $transfer) {
	// displayname
	$displayname = $transfer;

	// may need a special config panel to clean names...
	$displayname = str_replace('.',"_",$displayname);

	$displayname = preg_replace('#([^_]+_(com|org|net|info)_)#i',"",$displayname);
	$displayname = preg_replace('#(_torrent)$#i',"",$displayname);

	$arToClean = array ("http_","_www_","isohunt");
	$displayname = str_ireplace($arToClean,"",$displayname);

	$displayname = str_replace('_',".",$displayname);
	$displayname = str_replace('..',".",$displayname);
	$displayname = trim($displayname,'.');

	$displayname = (strlen($displayname) > 53) ? substr($displayname, 0, 50)."..." : $displayname;

	if ($displayname == 'download.php') {
		//must fix $transfer torrent filename before...
		//$name = getTransferFromHash($hash);
		$displayname = 'dup:'.$mtimecrc;
		addGrowlMessage('[dup]','This torrent has already been downloaded');
	}

	// owner
	$transferowner = getOwner($transfer);
	$owner = IsOwner($cfg["user"], $transferowner);
	// stat
	$sf = new StatFile($transfer, $transferowner);
	// settings
	if (isset($transfers['settings'][$transfer])) {
		$settingsAry = $transfers['settings'][$transfer];
	} else {
		$settingsAry = array();
		if (substr($transfer, -8) == ".torrent") {
			// this is a t-client
			$settingsAry['type'] = "torrent";
			$settingsAry['client'] = $cfg["btclient"];
		} else if (substr($transfer, -5) == ".wget") {
			// this is wget.
			$settingsAry['type'] = "wget";
			$settingsAry['client'] = "wget";
		} else if (substr($transfer, -4) == ".nzb") {
			// this is nzbperl.
			$settingsAry['type'] = "nzb";
			$settingsAry['client'] = "nzbperl";
		} else {
			AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
			@error("Invalid Transfer", "", "", array($transfer));
		}
		//$settingsAry['hash'] = "";
	}
	if (empty($settingsAry['hash'])) {
		$settingsAry['hash'] = getTransferHash($transfer);
		$transfers['settings'][$transfer] = $settingsAry;
	}
	if (empty($settingsAry['datapath'])) {
		$settingsAry["datapath"] = getTransferSavepath($transfer);
		$transfers['settings'][$transfer] = $settingsAry;
	}
	if (empty($settingsAry['savepath'])) {
		$settingsAry["savepath"] = getTransferSavepath($transfer);
		$transfers['settings'][$transfer] = $settingsAry;
	}
	// Fix FluAzu progression 100% (client to check, not set to "azureus", solved by mysql column enum to varchar ?)
	if ($settingsAry["client"] == "") {

		if (!$sf->seedlimit)
			$sf->seedlimit=$settingsAry["sharekill"];

		if ((int)$sf->size > 0) {
			if (!$sf->sharing)
				$sf->sharing=round( ((int)$sf->uptotal / (int)$sf->size) * 100.0 , 2);
		}
		
	} else { // in rpc realtime only working torrents

		if ($bUseRPC) {
			
			switch ($settingsAry["client"]) {
				
			case "vuzerpc":
				if (!empty($vuzeResults)) {
	
					$hash = getTransferHash($transfer);
					$rpcStat = $vuzeResults[strtoupper($hash)];
					if (is_array($rpcStat)) {
						

						//updated often
						$sf->up_speed    = round($rpcStat['speedUp'] / 1024 ,1);
						$sf->down_speed  = round($rpcStat['speedDown'] / 1024 ,1);
						$sf->uptotal     = $rpcStat['upTotal'];
						$sf->downtotal   = $rpcStat['downTotal'];
						$sf->seeds       = $rpcStat['seeds'];
						$sf->peers       = $rpcStat['peers'];
						$sf->cons        = $rpcStat['cons'];
						$sf->sharing     = $rpcStat['sharing'];

						$sf->rpc_status  = $rpcStat['status'];

						if (!$sf->seedlimit)
							$sf->seedlimit=$settingsAry["sharekill"];
							
						if ($rpcStat['status'] == 8 || $rpcStat['status'] == 9)
							$sf->running = 1;

						$sf->percent_done= floatval($rpcStat['percentDone']);
						//if ($sf->percent_done >= 100.0)
						//	$sf->percent_done += floatval($sf->sharing);

						if ($sf->up_speed > 0)
							$sf->up_speed .= " kB/s";
						else
							$sf->up_speed="&nbsp;";
						if ($sf->down_speed > 0)
							$sf->down_speed .= " kB/s";
						else
							$sf->down_speed="&nbsp;";
						if ((int)$rpcStat['error'] != 0 && !empty($rpcStat['errorString'])) {
							$error = $rpcStat['errorString'];
							if (strpos($error,'Scrape') !== false) {
								//ignore scrape error
							} else {
								$sf->status .= 'Error';
							}
						}

						if ($rpcStat['eta'] > 0) {
							$sf->time_left = convertTime($t['eta']);
						} elseif ($rpcStat['eta'] == -1) {
							$sf->time_left = "&#8734;";
						} else
							$sf->time_left="";

						if ($rpcStat['status'] == 16) {
							if (abs($sf->percent_done) >= 100) {
								//$sf->percent_done = 100;
								//$sf->running = 0;
								if ($sf->eta > 0)
									$sf->time_left = "Finished in ".convertTime($sf->eta);
								else
									$sf->time_left = "Download Succeeded";
								
								if ((int) $sf->sharing < (int) $sf->seedlimit - 1) {
									$sf->percent_done = -200; //stopped 100%
								} else {
									//argh, finished up_speed need to be ""
									$sf->up_speed = "";
								}
							}
						} else {
							$nbRpc++;
						}
					}
				}
				break;
			default:
				//todo transmission compatibility
			}
		}
	}

	// running-flag in local var. we will access that often
	$transferRunning = (int) $sf->running;
	// percent-done in local var. ...
	$percentDone = $sf->percent_done;
	// hide seeding - we do it asap to keep things as fast as possible
	if (($_SESSION['settings']['index_show_seeding'] == 0) && ($percentDone >= 100) && ($transferRunning == 1)) {
		$cfg["total_upload"] = $cfg["total_upload"] + GetSpeedValue($sf->up_speed);
		continue;
	}

	// status-image
	$hd = getStatusImage($sf);

	// ---------------------------------------------------------------------
	//XFER: update1: add upload/download stats to the xfer array
	if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1))
		@Xfer::update1($transfer, $transferowner, $settingsAry['client'], $settingsAry['hash'], $sf->uptotal, $sf->downtotal);

	// ---------------------------------------------------------------------
	// injects new
	if (!file_exists($cfg["transfer_file_path"].$transfer.".stat")) {
		$transferRunning = 2;
		$sf->running = "2";
		$sf->size = getTransferSize($transfer);
		injectTransfer($transfer);
	}

	// totals-preparation
	// if downtotal + uptotal + progress > 0
	if (($settings[2] + $settings[3] + $settings[5]) > 0) {
		$ch = ClientHandler::getInstance($settingsAry['client']);
		$transferTotals = $ch->getTransferTotalOP($transfer, $settingsAry['hash'], $sf->uptotal, $sf->downtotal);
	}

	// ---------------------------------------------------------------------
	// preprocess stat-file and get some vars
	$estTime = "&nbsp;";
	$statusStr = "&nbsp;";
	$show_run = true;
	switch ($transferRunning) {
		case 2: // new
			$statusStr = "New";
			$is_no_file = 1;
			break;
		case 3: // queued
			$statusStr = "Queued";
			$estTime = "Waiting...";
			$is_no_file = 1;
			break;
		default: // running
			// increment the totals
			if (!isset($cfg["total_upload"])) $cfg["total_upload"] = 0;
			if (!isset($cfg["total_download"])) $cfg["total_download"] = 0;
			$cfg["total_upload"] += GetSpeedValue($sf->up_speed);
			$cfg["total_download"] += GetSpeedValue($sf->down_speed);
			// $estTime
			if ($transferRunning == 0) {
				$estTime = $sf->time_left;
			} else {
				if ($sf->time_left != "" && $sf->time_left != "0") {
					if (($cfg["display_seeding_time"] == 1) && ($sf->percent_done >= 100) ) {
						$estTime = (($sf->seedlimit > 0) && (!empty($sf->up_speed)) && (intval(($sf->up_speed{0})) > 0))
							? convertTime(((($sf->seedlimit) / 100 * $sf->size) - $sf->uptotal) / GetSpeedInBytes($sf->up_speed))
							: '-';
					} else {
						$estTime = $sf->time_left;
					}
				}
			}
			// $show_run + $statusStr
			if ($percentDone >= 100) {
				$statusStr = (trim($sf->up_speed) != "" && $transferRunning == 1) ? "Seeding" : "Done";
				$show_run = false;
			} else if ($percentDone < 0) {
				$statusStr = "Stopped";
				$show_run = true;
			} else {
				$statusStr = "Leeching";
			}
			// pid-file
			$is_no_file = (is_file($cfg["transfer_file_path"].$transfer.".pid")) ? 0 : 1;
			break;
	}

	// ==================================================================== name

	// =================================================================== owner

	// ==================================================================== size
	$format_af_size = "";
	if($settings[1] != 0)
	{
		$format_af_size = formatBytesTokBMBGBTB($sf->size);
		if(strstr($format_af_size,"G")) $format_af_size = "<b>".$format_af_size."</b>";
		if($format_af_size == "") $format_af_size = "&nbsp;";
	}
	// =============================================================== downtotal
	$format_downtotal = "";
	if($settings[2] != 0)
	{
		$format_downtotal = formatBytesTokBMBGBTB($transferTotals["downtotal"]);
		if($format_downtotal == "") $format_downtotal = "&nbsp;";
	}
	// ================================================================= uptotal
	$format_uptotal = "";
	if($settings[3] != 0)
	{
		$format_uptotal = formatBytesTokBMBGBTB($transferTotals["uptotal"]);
		if($format_uptotal == "") $format_uptotal = "&nbsp;";
	}
	// ================================================================== status

	// ================================================================ progress
	if ($settings[5] != 0) {
		if ((int)$percentDone >= 100 && $sf->size!=0  && trim($sf->up_speed) != "") {
			//finished
			$graph_width = -1;
			$percentage = @number_format((($transferTotals["uptotal"] / $sf->size) * 100), 2) . '%';
		} else {
			if ($percentDone >= 1) {
				//leeching
				$graph_width = $percentDone;
				$percentage = $graph_width . '%';
			} else if ($percentDone < 0) {
				//stopped
				$graph_width = round(($percentDone*-1)-100,1);
				$percentage = $graph_width . '%';
			} else {
				//new
				$graph_width = 0;
				$percentage = '0%';
			}
		}
		$graph_width = min($graph_width,100);
		$background = ($graph_width == 100) ? $progress_color : "#000000";
	} else {
		$graph_width = 0;
		$background = "";
		$percentage = "";
	}

	// ==================================================================== down
	if ($settings[6] != 0) {
		if ($transferRunning == 1)
			$down_speed = (trim($sf->down_speed) != "") ? $sf->down_speed : '&nbsp;';
		else
			$down_speed = "&nbsp;";
	} else {
		$down_speed = "&nbsp;";
	}

	// ====================================================================== up
	if ($settings[7] != 0) {
		if ($transferRunning == 1)
			$up_speed = (trim($sf->up_speed) != "") ? $sf->up_speed : '&nbsp;';
		else
			$up_speed = "&nbsp;";
	} else {
		$up_speed = "&nbsp;";
	}

	// =================================================================== seeds
	if ($settings[8] != 0) {
		$seeds = ($transferRunning == 1)
			? $sf->seeds
			:  "&nbsp;";
	} else {
		$seeds = "&nbsp;";
	}

	// =================================================================== peers
	if ($settings[9] != 0) {
		$peers = ($transferRunning == 1)
			? $sf->peers
			:  "&nbsp;";
	} else {
		$peers = "&nbsp;";
	}

	// ===================================================================== ETA

	// ================================================================== client
	if ($settings[11] != 0) {
		switch ($settingsAry['client']) {
			case "tornado":
				$client = "B";
				break;
			case "transmission":
				$client = "T";
				break;
			case "transmissionrpc":
				$client = "Tr";
				break;
			case "mainline":
				$client = "M";
				break;
			case "azureus":
				$client = "A";
				break;
			case "vuzerpc":
				$client = "V";
				break;
			case "wget":
				$client = "W";
				break;
			case "nzbperl":
				$client = "N";
				break;
			default:
				$client = "U";
		}
	} else {
		$client = "&nbsp;";
	}

	// -------------------------------------------------------------------------
	// create temp-array
	$tArray = array(
		'is_owner' => ($cfg['isAdmin']) ? true : $owner,
		'transferRunning' => $transferRunning,
		'url_entry' => urlencode($transfer),
		'hd_image' => $hd->image,
		'hd_title' => $hd->title,
		'displayname' => $displayname,
		'transferowner' => $transferowner,
		'format_af_size' => $format_af_size,
		'format_downtotal' => $format_downtotal,
		'format_uptotal' => $format_uptotal,
		'statusStr' => $statusStr,
		'graph_width' => $graph_width,
		'percentage' => $percentage,
		'progress_color' => $progress_color,
		'bar_width' => $bar_width,
		'background' => $background,
		'100_graph_width' => (100 - $graph_width),
		'down_speed' => $down_speed,
		'up_speed' => $up_speed,
		'seeds' => $seeds,
		'peers' => $peers,
		'estTime' => $estTime,
		'clientType' => $settingsAry['type'],
		'upload_support_enabled' => $cfg["supportMap"][$settingsAry['client']]['max_upload_rate'],
		'client' => $client,
		'url_path' => urlencode(str_replace($cfg["path"],'', $settingsAry['savepath']).$settingsAry['datapath']),
		'datapath' => $settingsAry['datapath'],
		'is_no_file' => $is_no_file,
		'show_run' => $show_run,
		'entry' => $transfer
	);
	
	// Is this transfer for the user list or the general list?
	if ($owner)
		array_push($arUserTorrent, $tArray);
	else
		array_push($arListTorrent, $tArray);
}
$tmpl->setloop('arUserTorrent', $arUserTorrent);
$tmpl->setloop('arListTorrent', $arListTorrent);

/*
if (!empty($vuzeResults)) {
	$nbInVuze = count($vuzeResults);
	addGrowlMessage("Vuze"," Active: $nbRpc - Loaded: $nbInVuze");
}
*/

//XFER: update 2
if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1))
	@Xfer::update2();

$tmpl->setvar('settings_0', $settings[0]);
$tmpl->setvar('settings_1', $settings[1]);
$tmpl->setvar('settings_2', $settings[2]);
$tmpl->setvar('settings_3', $settings[3]);
$tmpl->setvar('settings_4', $settings[4]);
$tmpl->setvar('settings_5', $settings[5]);
$tmpl->setvar('settings_6', $settings[6]);
$tmpl->setvar('settings_7', $settings[7]);
$tmpl->setvar('settings_8', $settings[8]);
$tmpl->setvar('settings_9', $settings[9]);
$tmpl->setvar('settings_10', $settings[10]);
$tmpl->setvar('settings_11', $settings[11]);

if (sizeof($arUserTorrent) > 0)
	$tmpl->setvar('are_user_transfer', 1);
$boolCond = true;
if ($cfg['enable_restrictivetview'] == 1)
	$boolCond = $cfg['isAdmin'];
$tmpl->setvar('are_transfer', (($boolCond) && (sizeof($arListTorrent) > 0)) ? 1 : 0);


// =============================================================================

$onLoad = "";

$msgGrowl = "";
$msgSticky = false;

// pm
$nbMsg = numUnreadMsg();
if ($nbMsg > 0) {
	if (IsForceReadMsg()) {
		//disabled page lock
		//$tmpl->setvar('IsForceReadMsg', 1);

		 //Dont use " dblquotes in msgs, because this javascript will be in a onload="" attribute, quoted dblquotes (\") seems not working
		$msgGrowl .= "<img src=images/msg_new.png width=16 height=16> You have an important message !<br/><br/><a href=index.php?iid=readmsg>Please read...</a>";

		//dont auto-hide growl message
		$msgSticky = true;

	} elseif ($nbMsg > 1) {
		$msgGrowl .= "<img src=images/msg_new.png width=16 height=16> You have new messages !<br/><br/><a href=index.php?iid=readmsg>Please read...</a>";
	} else {
		$msgGrowl .= "<img src=images/msg_new.png width=16 height=16> You have a new message !<br/><br/><a href=index.php?iid=readmsg>Please read...</a>";
	}
}

// =============================================================================
// ajax-index
// =============================================================================

if ($isAjaxUpdate) {
	$ajax_delim = "|#|";
	$content = "";
	$isFirst = true;
	// server stats
	if ($ajaxUpdateParams{0} == "1") {
		$isFirst = false;
		$serverStats = getServerStats();
		$serverCount = count($serverStats);
		for ($i = 0; $i < $serverCount; $i++) {
			$content .= $serverStats[$i];
			if ($i < ($serverCount - 1))
				$content .= $cfg['stats_txt_delim'];
		}
	}
	// xfer
	if ($ajaxUpdateParams{1} == "1") {
		if ($isFirst)
			$isFirst = false;
		else
			$content .= $ajax_delim;
		$xferStats = Xfer::getStatsFormatted();
		$xferCount = count($xferStats);
		for ($i = 0; $i < $xferCount; $i++) {
			$content .= $xferStats[$i];
			if ($i < ($xferCount - 1))
				$content .= $cfg['stats_txt_delim'];
		}
	}
	// users
	if ($ajaxUpdateParams{2} == "1") {
		if ($isFirst)
			$isFirst = false;
		else
			$content .= $ajax_delim;
		$countUsers = count($cfg['users']);
		$arOnlineUsers = array();
		$arOfflineUsers = array();
		for ($i = 0; $i < $countUsers; $i++) {
			if (IsOnline($cfg['users'][$i]))
				array_push($arOnlineUsers, $cfg['users'][$i]);
			else
				array_push($arOfflineUsers, $cfg['users'][$i]);
		}
		$countOnline = count($arOnlineUsers);
		for ($i = 0; $i < $countOnline; $i++) {
			$content .= $arOnlineUsers[$i];
			if ($i < ($countOnline - 1))
				$content .= $cfg['stats_txt_delim'];
		}
		if ($cfg["hide_offline"] == 0) {
			$content .= "+";
			$countOffline = count($arOfflineUsers);
			for ($i = 0; $i < $countOffline; $i++) {
				$content .= $arOfflineUsers[$i];
				if ($i < ($countOffline - 1))
					$content .= $cfg['stats_txt_delim'];
			}
		}
	}
	// transfer list
	if ($ajaxUpdateParams{3} == "1") {
		if ($isFirst)
			$isFirst = false;
		else
			$content .= $ajax_delim;
		$content .= $tmpl->grab();
	}
	// javascript
	if (true) {
		if ($isFirst)
			$isFirst = false;
		else
			$content .= $ajax_delim;

		//Messages jGrowl
		$jGrowls = "";
		if (!empty($cfg['growl'])) {
			$jGrowls .= getGrowlMessages();
			clearGrowlMessages();
		}
		//Growl message on ajax refresh
		if (!empty($msgGrowl)) {
			$jGrowls .= "jQuery.jGrowl('".addslashes($msgGrowl)."',{sticky:".($msgSticky ?'true':'false')."});";
		}
		$content .= $jGrowls;

	}
	// send and out
	@header("Cache-Control: no-cache");
	@header("Pragma: no-cache");
	@header("Content-Type: text/plain");
	echo $content;
	exit();
}

// =============================================================================
// standard-index
// =============================================================================

// goodlookingstats-init
if ($cfg["enable_goodlookstats"] != "0") {
	$tmpl->setvar('enable_goodlookstats', 1);
	$settingsHackStats = convertByteToArray($cfg["hack_goodlookstats_settings"]);
}

// page refresh
if ($_SESSION['settings']['index_meta_refresh'] != 0) {
	$tmpl->setvar('page_refresh', $cfg["page_refresh"]);
	$tmpl->setvar('meta_refresh', $cfg["page_refresh"].';URL=index.php?iid=index');
	$onLoad .= "initRefresh(".$cfg["page_refresh"].");";
	$tmpl->setvar('_PAGEWILLREFRESH', $cfg['_PAGEWILLREFRESH']);
} else {
	$tmpl->setvar('_TURNONREFRESH', $cfg['_TURNONREFRESH']);
}

// AJAX update
if ($_SESSION['settings']['index_ajax_update'] != 0) {
	$tmpl->setvar('index_ajax_update', $cfg["index_ajax_update"]);
	$ajaxInit = "ajax_initialize(";
	$ajaxInit .= (intval($cfg['index_ajax_update']) * 1000);
	$ajaxInit .= ",'".$cfg['stats_txt_delim']."'";
	$ajaxInit .= ",".$cfg["enable_index_ajax_update_silent"];
	$ajaxInit .= ",".$cfg["enable_index_ajax_update_title"];
	$ajaxInit .= ",'".$cfg['pagetitle']."'";
	$ajaxInit .= ",".$cfg["enable_goodlookstats"];
	if ($cfg["enable_goodlookstats"] != "0")
		$ajaxInit .= ",'".$settingsHackStats[0].':'.$settingsHackStats[1].':'.$settingsHackStats[2].':'.$settingsHackStats[3].':'.$settingsHackStats[4].':'.$settingsHackStats[5]."'";
	else
		$ajaxInit .= ",'0:0:0:0:0:0'";
	$ajaxInit .= ",".$cfg["index_page_stats"];
	if (FluxdQmgr::isRunning())
		$ajaxInit .= ",1";
	else
		$ajaxInit .= ",0";
	if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1))
		$ajaxInit .= ",1";
	else
		$ajaxInit .= ",0";
	if (($cfg['ui_displayusers'] == 1) && ($cfg['enable_index_ajax_update_users'] == 1))
		$ajaxInit .= ",1";
	else
		$ajaxInit .= ",0";
	$ajaxInit .= ",".$cfg["hide_offline"];
	$ajaxInit .= ",".$cfg["enable_index_ajax_update_list"];
	$ajaxInit .= ",".$cfg["enable_sorttable"];
	$ajaxInit .= ",'".$cfg['drivespacebar']."'";
	$ajaxInit .= ",".$cfg["ui_displaybandwidthbars"];
	$ajaxInit .= ",'".$cfg['bandwidthbar']."'";
	$ajaxInit .= ");onbeforeunload = ajax_unload;";
	
	$onLoad .= $ajaxInit;
} 

//Index Growl messages
$jGrowls = "";
if (!empty($cfg['growl'])) {
	$jGrowls .= getGrowlMessages();
	clearGrowlMessages();
}
if (!empty($msgGrowl)) {
	$jGrowls .= "jQuery.jGrowl('".addslashes($msgGrowl)."',{sticky:".($msgSticky ?'true':'false')."});";
}
$onLoad .= $jGrowls;


//Hide Seeds
if ($_SESSION['settings']['index_show_seeding'] != 0) {
	$tmpl->setvar('index_show_seeding', $_SESSION['settings']['index_show_seeding']);
}

// onLoad
if ($onLoad != "") {
	$tmpl->setvar('onLoad', $onLoad);
	$tmpl->setvar('_SECONDS', $cfg['_SECONDS']);
	$tmpl->setvar('_TURNOFFREFRESH', $cfg['_TURNOFFREFRESH']);
}

// connections
if ($cfg["index_page_connections"] != 0) {
	$netstatConnectionsSum = @netstatConnectionsSum();
	$netstatConnectionsMax = (isset($transfers['sum']['maxcons']))
		? "(".$transfers['sum']['maxcons'].")"
		: "(0)";
} else {
	$netstatConnectionsSum = "n/a";
	$netstatConnectionsMax = "";
}
// loadavg
$loadavgString = ($cfg["show_server_load"] != 0) ? @getLoadAverageString() : "n/a";

// Width of top right stats cell:
$stats_cell_width=0;

// links
if ($cfg["ui_displaylinks"] != "0") {
	$stats_cell_width+=200;
	if (isset($cfg['linklist']))
		$tmpl->setloop('linklist', $cfg['linklist']);
}

// goodlookingstats
if ($cfg["enable_goodlookstats"] != "0") {
	$stats_cell_width+=180;
	if ($settingsHackStats[0] == 1) {
		$tmpl->setvar('settingsHackStats1', 1);
		$tmpl->setvar('settingsHackStats11', @number_format($cfg["total_download"], 2));
	}
	if ($settingsHackStats[1] == 1) {
		$tmpl->setvar('settingsHackStats2', 1);
		$tmpl->setvar('settingsHackStats22', @number_format($cfg["total_upload"], 2));
	}
	if ($settingsHackStats[2] == 1) {
		$tmpl->setvar('settingsHackStats3', 1);
		$tmpl->setvar('settingsHackStats33', @number_format($cfg["total_download"]+$cfg["total_upload"], 2));
	}
	if ($settingsHackStats[3] == 1) {
		$tmpl->setvar('settingsHackStats4', 1);
		$tmpl->setvar('settingsHackStats44', $netstatConnectionsSum);
	}
	if ($settingsHackStats[4] == 1) {
		$tmpl->setvar('settingsHackStats5', 1);
		$tmpl->setvar('settingsHackStats55', $cfg['freeSpaceFormatted']);
	}
	if ($settingsHackStats[5] == 1) {
		$tmpl->setvar('settingsHackStats6', 1);
		$tmpl->setvar('settingsHackStats66', $loadavgString);
	}
}

// users
if ($cfg["ui_displayusers"] != "0") {
	$stats_cell_width+=100;
	$tmpl->setvar('ui_displayusers',1);
	$tmpl->setvar('hide_offline', $cfg["hide_offline"]);
	$userCount = count($cfg['users']);
	$arOnlineUsers = array();
	$arOfflineUsers = array();
	for ($inx = 0; $inx < $userCount; $inx++) {
		if (IsOnline($cfg['users'][$inx]))
			array_push($arOnlineUsers, array('user' => $cfg['users'][$inx]));
		else
			array_push($arOfflineUsers, array('user' => $cfg['users'][$inx]));
	}
	if (count($arOnlineUsers) > 0)
		$tmpl->setloop('arOnlineUsers', $arOnlineUsers);
	if (count($arOfflineUsers) > 0)
		$tmpl->setloop('arOfflineUsers', $arOfflineUsers);
}

// Width of top right stats cell:
$tmpl->setvar('stats_cell_width',$stats_cell_width);

// xfer
if ($cfg['enable_xfer'] == 1) {
	if ($cfg['enable_public_xfer'] == 1)
		$tmpl->setvar('enable_xfer', 1);
	if ($cfg['xfer_realtime'] == 1) {
		$xfer_total = Xfer::getStatsTotal();
		$tmpl->setvar('xfer_realtime', 1);
		if ($cfg['xfer_day'])
			$tmpl->setvar('xfer_day', tmplGetXferBar($cfg['xfer_day'],$xfer_total['day']['total'],$cfg['_XFERTHRU'].' Today:'));
		if ($cfg['xfer_week'])
			$tmpl->setvar('xfer_week', tmplGetXferBar($cfg['xfer_week'],$xfer_total['week']['total'],$cfg['_XFERTHRU'].' '.$cfg['week_start'].':'));
		$monthStart = strtotime(date('Y-m-').$cfg['month_start']);
		$monthText = (date('j') < $cfg['month_start']) ? date('M j',strtotime('-1 Day',$monthStart)) : date('M j',strtotime('+1 Month -1 Day',$monthStart));
		if ($cfg['xfer_month'])
			$tmpl->setvar('xfer_month', tmplGetXferBar($cfg['xfer_month'],$xfer_total['month']['total'],$cfg['_XFERTHRU'].' '.$monthText.':'));
		if ($cfg['xfer_total'])
			$tmpl->setvar('xfer_total', tmplGetXferBar($cfg['xfer_total'],$xfer_total['total']['total'],$cfg['_TOTALXFER'].':'));
	}
}

// drivespace-warning
if ($cfg['driveSpace'] >= 98) {
	if ($cfg['enable_bigboldwarning'] != 0)
		$tmpl->setvar('enable_bigboldwarning', 1);
	else
		$tmpl->setvar('enable_jswarning', 1);
}

// bottom stats
if ($cfg['index_page_stats'] != 0) {
	$tmpl->setvar('index_page_stats', 1);
	if (!array_key_exists("total_download",$cfg))
		$cfg["total_download"] = 0;
	if (!array_key_exists("total_upload",$cfg))
		$cfg["total_upload"] = 0;
	// xfer
	if (($cfg['enable_xfer'] != 0) && ($cfg['xfer_realtime'] != 0)) {
		$tmpl->setvar('_SERVERXFERSTATS', $cfg['_SERVERXFERSTATS']);
		$tmpl->setvar('_TOTALXFER', $cfg['_TOTALXFER']);
		$tmpl->setvar('_MONTHXFER', $cfg['_MONTHXFER']);
		$tmpl->setvar('_WEEKXFER', $cfg['_WEEKXFER']);
		$tmpl->setvar('_DAYXFER', $cfg['_DAYXFER']);
		$tmpl->setvar('_YOURXFERSTATS', $cfg['_YOURXFERSTATS']);
		$tmpl->setvar('totalxfer1', @formatFreeSpace($xfer_total['total']['total'] / 1048576));
		$tmpl->setvar('monthxfer1', @formatFreeSpace($xfer_total['month']['total'] / 1048576));
		$tmpl->setvar('weekxfer1', @formatFreeSpace($xfer_total['week']['total'] / 1048576));
		$tmpl->setvar('dayxfer1', @formatFreeSpace($xfer_total['day']['total'] / 1048576));
		$xfer = Xfer::getStats();
		$tmpl->setvar('total2', @formatFreeSpace($xfer[$cfg["user"]]['total']['total'] / 1048576));
		$tmpl->setvar('month2', @formatFreeSpace($xfer[$cfg["user"]]['month']['total'] / 1048576));
		$tmpl->setvar('week2', @formatFreeSpace($xfer[$cfg["user"]]['week']['total'] / 1048576));
		$tmpl->setvar('day2', @formatFreeSpace($xfer[$cfg["user"]]['day']['total'] / 1048576));
	}
	// queue
	if (FluxdQmgr::isRunning()) {
		$tmpl->setvar('_QUEUEMANAGER', $cfg['_QUEUEMANAGER']);
		$tmpl->setvar('runningTransferCount', getRunningTransferCount());
		$tmpl->setvar('countQueuedTransfers', FluxdQmgr::countQueuedTransfers());
		$tmpl->setvar('limitGlobal', $cfg["fluxd_Qmgr_maxTotalTransfers"]);
		$tmpl->setvar('limitUser', $cfg["fluxd_Qmgr_maxUserTransfers"]);
	}
	// other
	$tmpl->setvar('_OTHERSERVERSTATS', $cfg['_OTHERSERVERSTATS']);
	$tmpl->setvar('downloadspeed1', @number_format($cfg["total_download"], 2));
	$tmpl->setvar('downloadspeed11', @number_format($transfers['sum']['drate'], 2));
	$tmpl->setvar('uploadspeed1', @number_format($cfg["total_upload"], 2));
	$tmpl->setvar('uploadspeed11', @number_format($transfers['sum']['rate'], 2));
	$tmpl->setvar('totalspeed1', @number_format($cfg["total_download"]+$cfg["total_upload"], 2));
	$tmpl->setvar('totalspeed11', @number_format($transfers['sum']['rate'] + $transfers['sum']['drate'], 2));
	$tmpl->setvar('id_connections1', $netstatConnectionsSum);
	$tmpl->setvar('id_connections11', $netstatConnectionsMax);
	$tmpl->setvar('drivespace1', $cfg['freeSpaceFormatted']);
	$tmpl->setvar('serverload1', $loadavgString);
}

// Graphical Bandwidth Bar
if ($cfg["ui_displaybandwidthbars"] != 0) {
	$tmpl->setvar('ui_displaybandwidthbars', 1);
	tmplSetBandwidthBars();
}

// wget
switch ($cfg["enable_wget"]) {
	case 2:
		$tmpl->setvar('enable_wget', 1);
		break;
	case 1:
		if ($cfg['isAdmin'])
			$tmpl->setvar('enable_wget', 1);
}

// nzbperl
switch ($cfg['enable_nzbperl']) {
	case 2:
		$tmpl->setvar('enable_nzbperl', 1);
		break;
	case 1:
		if ($cfg['isAdmin'])
			$tmpl->setvar('enable_nzbperl', 1);
}

$tmpl->setvar('version', $cfg["version"]);
$tmpl->setvar('enable_multiupload', $cfg["enable_multiupload"]);
$tmpl->setvar('enable_search', $cfg["enable_search"]);
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
$tmpl->setvar('enable_sorttable', $cfg["enable_sorttable"]);
$tmpl->setvar('enable_bulkops', $cfg["enable_bulkops"]);
$tmpl->setvar('ui_displaylinks', $cfg["ui_displaylinks"]);
$tmpl->setvar('drivespace', $cfg['driveSpace']);
$tmpl->setvar('freeSpaceFormatted', $cfg['freeSpaceFormatted']);
$tmpl->setvar('file_types_label', $cfg['file_types_label']);
$tmpl->setloop('Engine_List', tmplSetSearchEngineDDL($cfg["searchEngine"]));
//
$tmpl->setvar('_ABOUTTODELETE', $cfg['_ABOUTTODELETE']);
$tmpl->setvar('_SELECTFILE', $cfg['_SELECTFILE']);
$tmpl->setvar('_UPLOAD', $cfg['_UPLOAD']);
$tmpl->setvar('_MULTIPLE_UPLOAD', $cfg['_MULTIPLE_UPLOAD']);
$tmpl->setvar('_URLFILE', $cfg['_URLFILE']);
$tmpl->setvar('_GETFILE', $cfg['_GETFILE']);
$tmpl->setvar('_SEARCH', $cfg['_SEARCH']);
$tmpl->setvar('_LINKS', $cfg['_LINKS']);
$tmpl->setvar('_DOWNLOADSPEED', $cfg['_DOWNLOADSPEED']);
$tmpl->setvar('_UPLOADSPEED', $cfg['_UPLOADSPEED']);
$tmpl->setvar('_TOTALSPEED', $cfg['_TOTALSPEED']);
$tmpl->setvar('_ID_CONNECTIONS', $cfg['_ID_CONNECTIONS']);
$tmpl->setvar('_SERVERLOAD', $cfg['_SERVERLOAD']);
$tmpl->setvar('_ONLINE', $cfg['_ONLINE']);
$tmpl->setvar('_OFFLINE', $cfg['_OFFLINE']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
$tmpl->setvar('_DIRECTORYLIST', $cfg['_DIRECTORYLIST']);
$tmpl->setvar('_DRIVESPACEUSED', $cfg['_DRIVESPACEUSED']);
$tmpl->setvar('_ADMINMESSAGE', $cfg['_ADMINMESSAGE']);
$tmpl->setvar('_DRIVESPACE', $cfg['_DRIVESPACE']);

//
tmplSetTitleBar($cfg["pagetitle"]);
tmplSetDriveSpaceBar();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>
