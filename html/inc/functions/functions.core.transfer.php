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
 * getTransferPid
 *
 * @param $transfer
 * @return int
 */
function getTransferPid($transfer) {
	global $cfg;
	return @rtrim(file_get_contents($cfg["transfer_file_path"].$transfer.".pid"));
}

/**
 * checks if transfer is running by checking for existence of pid-file.
 *
 * @param $transfer name of the transfer
 * @return boolean
 */
function isTransferRunning($transfer) {
	global $cfg;
	return file_exists($cfg["transfer_file_path"].$transfer.'.pid');
}

/**
 * checks if transfer exists by checking for existence of meta-file.
 *
 * @param $transfer name of the transfer
 * @return boolean
 */
function transferExists($transfer) {
	global $cfg;
	return file_exists($cfg["transfer_file_path"].$transfer);
}

/**
 * gets the transfer-client
 *
 * @param $transfer name of the transfer
 * @return string
 */
function getTransferClient($transfer) {
	global $cfg, $db, $transfers;
	if (isset($transfers['settings'][$transfer]['client'])) {
		return $transfers['settings'][$transfer]['client'];
	} else {
		$client = $db->GetOne("SELECT client FROM tf_transfers WHERE transfer = ".$db->qstr($transfer));
		if (empty($client)) {
			if (substr($transfer, -8) == ".torrent") {
				// this is a torrent-client
				$client = $cfg["btclient"];
			} else if (substr($transfer, -5) == ".wget") {
				// this is wget.
				$client = "wget";
			} else if (substr($transfer, -4) == ".nzb") {
				// This is nzbperl.
				$client = "nzbperl";
			} else {
				$client = $cfg["btclient"];
			}
		}
		$transfers['settings'][$transfer]['client'] = $client;
		return $client;
	}
}

/**
 * getRunningTransferCount
 *
 * @return int with number of running transfers
 */
function getRunningTransferCount() {
	global $cfg;
	// use pid-files-direct-access for now because all clients of currently
	// available handlers write one. then its faster and correct meanwhile.
	if ($dirHandle = @opendir($cfg["transfer_file_path"])) {
		$tCount = 0;
		while (false !== ($file = @readdir($dirHandle))) {
			if ((substr($file, -4, 4)) == ".pid")
				$tCount++;
		}
		@closedir($dirHandle);
		return $tCount;
	} else {
		return 0;
	}
}

/**
 * get the full size of a transfer
 *
 * @param $transfer
 * @return int
 */
function getTransferSize($transfer) {
	global $cfg;
	// client-switch
	if (substr($transfer, -8) == ".torrent") {
		// this is a t-client
		$file = $cfg["transfer_file_path"].$transfer;
		if ($fd = @fopen($file, "rd")) {
			require_once("inc/classes/BDecode.php");
			$alltorrent = @fread($fd, @filesize($file));
			$array = @BDecode($alltorrent);
			@fclose($fd);
		}
		return ((isset($array["info"]["piece length"])) && (isset($array["info"]["pieces"])))
			? $array["info"]["piece length"] * (strlen($array["info"]["pieces"]) / 20)
			: 0;
	} else if (substr($transfer, -5) == ".wget") {
		// this is wget.
		$ch = ClientHandler::getInstance('wget');
		$ch->setVarsFromFile($transfer);
		require_once("inc/classes/SimpleHTTP.php");
		return SimpleHTTP::getRemoteSize($ch->url);
	} else if (substr($transfer, -4) == ".nzb") {
		// this is nzbperl.
		require_once("inc/classes/NZBFile.php");
		$nzb = new NZBFile($transfer);
		return $nzb->size;
	}
	return 0;
}

/**
 * gets hash of a transfer
 *
 * @param $transfer name of the transfer
 * @return transfer-hash
 */
function getTransferHash($transfer) {
	global $cfg, $db, $transfers;
	if (isset($transfers['settings'][$transfer]['hash'])) {
		return $transfers['settings'][$transfer]['hash'];
	} else {
		$hash = $db->GetOne("SELECT hash FROM tf_transfers WHERE transfer = ".$db->qstr($transfer));
		if (empty($hash)) {
			if (substr($transfer, -8) == ".torrent") {
				// this is a torrent-client
				$metainfo = getTorrentMetaInfo($transfer);
				if (empty($metainfo)) {
					$hash = "";
				} else {
					$resultAry = explode("\n", $metainfo);
					$hashAry = array();
					switch ($cfg["metainfoclient"]) {
						case "transmissioncli":
						case "ttools.pl":
							$hashAry = explode(":", trim($resultAry[0]));
							break;
						case "btshowmetainfo.py":
						case "torrentinfo-console.py":
						default:
							$hashAry = explode(":", trim($resultAry[3]));
							break;
					}
					$hash = (isset($hashAry[1])) ? trim($hashAry[1]) : "";
				}
			} else if (substr($transfer, -5) == ".wget") {
				// this is wget.
				$metacontent = @file_get_contents($cfg["transfer_file_path"].$transfer);
				$hash = (empty($metacontent))
					? ""
					: sha1($metacontent);
			} else if (substr($transfer, -4) == ".nzb") {
				// This is nzbperl.
				$metacontent = @file_get_contents($cfg["transfer_file_path"].$transfer);
				$hash = (empty($metacontent))
					? ""
					: sha1($metacontent);
			} else {
				$hash = "";
			}
		}
		$transfers['settings'][$transfer]['hash'] = $hash;
		return $hash;
	}
}

/**
 * gets metainfo of a torrent as string
 *
 * @param $transfer name of the torrent
 * @return string with torrent-meta-info
 */
function getTorrentMetaInfo($transfer) {
	global $cfg;
	switch ($cfg["metainfoclient"]) {
		case "transmissioncli":
			return shell_exec("HOME=".tfb_shellencode($cfg["path"])."; export HOME; ".$cfg["btclient_transmission_bin"]." -i ".tfb_shellencode($cfg["transfer_file_path"].$transfer));
		case "ttools.pl":
			return shell_exec($cfg["perlCmd"].' -I '.tfb_shellencode($cfg["docroot"].'bin/ttools').' '.tfb_shellencode($cfg["docroot"].'bin/ttools/ttools.pl').' -i '.tfb_shellencode($cfg["transfer_file_path"].$transfer));
		case "torrentinfo-console.py":
			return shell_exec("cd ".tfb_shellencode($cfg["transfer_file_path"])."; ".$cfg["pythonCmd"]." -OO ".tfb_shellencode($cfg["docroot"]."bin/clients/mainline/torrentinfo-console.py")." ".tfb_shellencode($transfer));
		case "btshowmetainfo.py":
		default:
			return shell_exec("cd ".tfb_shellencode($cfg["transfer_file_path"])."; ".$cfg["pythonCmd"]." -OO ".tfb_shellencode($cfg["docroot"]."bin/clients/tornado/btshowmetainfo.py")." ".tfb_shellencode($transfer));
	}
}

/**
 * gets details of a transfer as array
 *
 * @param $transfer
 * @param $full
 * @return array with details
 *
 * array-keys :
 *
 * running
 * speedDown
 * speedUp
 * downCurrent
 * upCurrent
 * downTotal
 * upTotal
 * percentDone
 * sharing
 * timeLeft
 * seeds
 * peers
 * cons
 *
 * owner
 * size
 * maxSpeedDown
 * maxSpeedUp
 * maxcons
 * sharekill
 * port
 *
 */
function getTransferDetails($transfer, $full) {
	global $cfg, $transfers;
	$details = array();
	// common functions
	require_once('inc/functions/functions.common.php');
	$transferowner = getOwner($transfer);
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
		$settingsAry['hash'] = "";
		$settingsAry["savepath"] = ($cfg["enable_home_dirs"] != 0)
			? $cfg["path"].$transferowner.'/'
			: $cfg["path"].$cfg["path_incoming"].'/';
		$settingsAry['datapath'] = "";
	}
	// size
	$size = floatval($sf->size);
	// totals
	$afu = $sf->uptotal;
	$afd = $sf->downtotal;
	$ch = ClientHandler::getInstance($settingsAry['client']);
	$totalsCurrent = $ch->getTransferCurrentOP($transfer, $settingsAry['hash'], $afu, $afd);
	$totals = $ch->getTransferTotalOP($transfer, $settingsAry['hash'], $afu, $afd);
	// running
	$running = $sf->running;
	$details['running'] = $running;
	// speed_down + speed_up + seeds + peers + cons
	if ($running == 1) {
		// pid
		$pid = getTransferPid($transfer);
		// speed_down
		$details['speedDown'] = (trim($sf->down_speed) != "") ? $sf->down_speed : '0.0 kB/s';
		// speed_up
		$details['speedUp'] = (trim($sf->up_speed) != "") ? $sf->up_speed : '0.0 kB/s';
		// down_current
		$details['downCurrent'] = @formatFreeSpace($totalsCurrent["downtotal"] / 1048576);
		// up_current
		$details['upCurrent'] = @formatFreeSpace($totalsCurrent["uptotal"] / 1048576);
		// seeds
		$details['seeds'] = $sf->seeds;
		// peers
		$details['peers'] = $sf->peers;
		// cons
		$details['cons'] = netstatConnectionsByPid($pid);
	} else {
		// speed_down
		$details['speedDown'] = "";
		// speed_up
		$details['speedUp'] = "";
		// down_current
		$details['downCurrent'] = "";
		// up_current
		$details['upCurrent'] = "";
		// seeds
		$details['seeds'] = "";
		// peers
		$details['peers'] = "";
		// cons
		$details['cons'] = "";
	}
	// down_total
	$details['downTotal'] = @formatFreeSpace($totals["downtotal"] / 1048576);
	// up_total
	$details['upTotal'] = @formatFreeSpace($totals["uptotal"] / 1048576);
	// percentage
	$percentage = $sf->percent_done;
	if ($percentage < 0) {
		$percentage = round(($percentage * -1) - 100, 1);
		$sf->time_left = $cfg['_INCOMPLETE'];
	} elseif ($percentage > 100) {
		$percentage = 100;
	}
	$details['percentDone'] = $percentage;
	// eta
	$details['eta'] = $sf->time_left;
	// sharing
	$details['sharing'] = ($totals["downtotal"] > 0) ? @number_format((($totals["uptotal"] / $totals["downtotal"]) * 100), 2) : 0;
	// full (including static) details
	if ($full) {
		// owner
		$details['owner'] = $transferowner;
		// size
		$details['size'] = @formatBytesTokBMBGBTB($size);
		if ($running == 1) {
			// max_download_rate
			$details['maxSpeedDown'] = number_format($cfg["max_download_rate"], 2);
			// max_upload_rate
			$details['maxSpeedUp'] = number_format($cfg["max_upload_rate"], 2);
			// maxcons
			$details['maxcons'] = $cfg["maxcons"];
			// sharekill
			$details['sharekill'] = $cfg["sharekill"];
			// port
			$details['port'] = netstatPortByPid($pid);
		} else {
			// max_download_rate
			$details['maxSpeedDown'] = "";
			// max_upload_rate
			$details['maxSpeedUp'] = "";
			// maxcons
			$details['maxcons'] = "";
			// sharekill
			$details['sharekill'] = "";
			// port
			$details['port'] = "";
		}
	}
	// return
	return $details;
}

/**
 * This method gets transfers from database in an array
 *
 * @return array with transfers
 */
function getTransferArrayFromDB() {
	global $db;
	$retVal = array();
	$sql = "SELECT transfer FROM tf_transfers ORDER BY transfer ASC";
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	while(list($transfer) = $recordset->FetchRow())
		array_push($retVal, $transfer);
	return $retVal;
}

/**
 * This method gets transfers in an array
 *
 * @param $sortOrder
 * @return array
 */
function getTransferArray($sortOrder = '') {
	global $cfg;
	$retVal = array();
	$handle = @opendir($cfg["transfer_file_path"]);
	if (!$handle) {
		AuditAction($cfg["constants"]["error"], "error when opening transfers-dir ".$cfg["transfer_file_path"]);
		return $retVal;
	}
	while ($transfer = @readdir($handle)) {
		if ($transfer{0} != ".") {
			switch (substr($transfer, -4)) {
				case 'stat':
				case '.log':
				case '.pid':
				case '.cmd':
				case 'prio':
					break;
				default:
					if (tfb_isValidTransfer($transfer))
						$retVal[filemtime($cfg["transfer_file_path"].$transfer).md5($transfer)] = $transfer;
					else
						AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
					break;
			}
		}
	}
	@closedir($handle);
	// sort transfer-array
	$sortId = ($sortOrder != "") ? $sortOrder : $cfg["index_page_sortorder"];
	switch ($sortId) {
		case 'da': // sort by date ascending
			ksort($retVal);
			break;
		case 'dd': // sort by date descending
			krsort($retVal);
			break;
		case 'na': // sort alphabetically by name ascending
			natcasesort($retVal);
			break;
		case 'nd': // sort alphabetically by name descending
			natcasesort($retVal);
			$retVal = array_reverse($retVal, true);
			break;
	}
	return $retVal;
}

/**
 * This method gets the head of the transfer-list
 *
 * @param $settings
 * @return transfer-list-head array
 */
function getTransferListHeadArray($settings = null) {
	global $cfg;
	// settings
	if (!(isset($settings)))
		$settings = convertIntegerToArray($cfg["index_page_settings"]);
	// retval
	$retVal = array();
	// =================================================================== owner
	if ($settings[0] != 0)
		array_push($retVal, $cfg['_USER']);
	// ==================================================================== size
	if ($settings[1] != 0)
		array_push($retVal, "Size");
	// =============================================================== downtotal
	if ($settings[2] != 0)
		array_push($retVal, "T. Down");
	// ================================================================= uptotal
	if ($settings[3] != 0)
		array_push($retVal, "T. Up");
	// ================================================================== status
	if ($settings[4] != 0)
		array_push($retVal, "Status");
	// ================================================================ progress
	if ($settings[5] != 0)
		array_push($retVal, "Progress");
	// ==================================================================== down
	if ($settings[6] != 0)
		array_push($retVal, "Down");
	// ====================================================================== up
	if ($settings[7] != 0)
		array_push($retVal, "Up");
	// =================================================================== seeds
	if ($settings[8] != 0)
		array_push($retVal, "Seeds");
	// =================================================================== peers
	if ($settings[9] != 0)
		array_push($retVal, "Peers");
	// ===================================================================== ETA
	if ($settings[10] != 0)
		array_push($retVal, "Estimated Time");
	// ================================================================== client
	if ($settings[11] != 0)
		array_push($retVal, "Client");
	// return
	return $retVal;
}

/**
 * This method gets the list of transfer
 *
 * @return array
 */
function getTransferListArray() {
	global $cfg, $db, $transfers;
	$kill_id = "";
	$lastUser = "";
	$arUserTransfers = array();
	$arListTransfers = array();
	// settings
	$settings = convertIntegerToArray($cfg["index_page_settings"]);
	// sortOrder
	$sortOrder = tfb_getRequestVar("so");
	if ($sortOrder == "")
		$sortOrder = $cfg["index_page_sortorder"];
	// t-list
	$arList = getTransferArray($sortOrder);
	foreach ($arList as $transfer) {
		// init some vars
		$displayname = $transfer;
		$show_run = true;
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
			$settingsAry['hash'] = "";
			$settingsAry["savepath"] = ($cfg["enable_home_dirs"] != 0)
				? $cfg["path"].$transferowner.'/'
				: $cfg["path"].$cfg["path_incoming"].'/';
			$settingsAry['datapath'] = "";
		}
		// cache running-flag in local var. we will access that often
		$transferRunning = $sf->running;
		// cache percent-done in local var. ...
		$percentDone = $sf->percent_done;

		// ---------------------------------------------------------------------
		//XFER: update1: add upload/download stats to the xfer array
		if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1))
			@Xfer::update1($transfer, $transferowner, $settingsAry['client'], $settingsAry['hash'], $sf->uptotal, $sf->downtotal);

		// ---------------------------------------------------------------------
		// injects
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
		$estTime = "";
		$statusStr = "";
		switch ($transferRunning) {
			case 2: // new
				$statusStr = 'New';
				break;
			case 3: // queued
				$statusStr = 'Queued';
				$estTime = 'Waiting';
				break;
			default: // running + stopped
				// increment the totals
				if (!isset($cfg["total_upload"])) $cfg["total_upload"] = 0;
				if (!isset($cfg["total_download"])) $cfg["total_download"] = 0;
				$cfg["total_upload"] = $cfg["total_upload"] + GetSpeedValue($sf->up_speed);
				$cfg["total_download"] = $cfg["total_download"] + GetSpeedValue($sf->down_speed);
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
				// $lastUser
				$lastUser = $transferowner;
				// $show_run + $statusStr
				if($percentDone >= 100) {
					$statusStr = (($transferRunning == 1) && (trim($sf->up_speed) != "")) ? 'Seeding' : 'Done';
					$show_run = false;
				} else if ($percentDone < 0) {
					$statusStr = 'Stopped';
					$show_run = true;
				} else {
					$statusStr = 'Leeching';
				}
				break;
		}

		// ---------------------------------------------------------------------
		// fill temp array
		$transferAry = array();

		// ================================================================ name
		array_push($transferAry, $transfer);

		// =============================================================== owner
		if ($settings[0] != 0)
			array_push($transferAry, $transferowner);

		// ================================================================ size
		if ($settings[1] != 0)
			array_push($transferAry, @formatBytesTokBMBGBTB($sf->size));

		// =========================================================== downtotal
		if ($settings[2] != 0)
			array_push($transferAry, @formatBytesTokBMBGBTB($transferTotals["downtotal"]));

		// ============================================================= uptotal
		if ($settings[3] != 0)
			array_push($transferAry, @formatBytesTokBMBGBTB($transferTotals["uptotal"]));

		// ============================================================== status
		if ($settings[4] != 0)
			array_push($transferAry, $statusStr);

		// ============================================================ progress
		if ($settings[5] != 0) {
			$percentage = "";
			if (($percentDone >= 100) && (trim($sf->up_speed) != "")) {
				$percentage = @number_format((($transferTotals["uptotal"] / $sf->size) * 100), 2) . '%';
			} else {
				if ($percentDone >= 1)
					$percentage = $percentDone . '%';
				else if ($percentDone < 0)
					$percentage = round(($percentDone*-1)-100,1) . '%';
				else
					$percentage = '0%';
			}
			array_push($transferAry, $percentage);
		}

		// ================================================================ down
		if ($settings[6] != 0) {
			$down = "";
			if ($transferRunning == 1)
				$down = (trim($sf->down_speed) != "") ? $sf->down_speed : '0.0 kB/s';
			array_push($transferAry, $down);
		}

		// ================================================================== up
		if ($settings[7] != 0) {
			$up = "";
			if ($transferRunning == 1)
				$up = (trim($sf->up_speed) != "") ? $sf->up_speed : '0.0 kB/s';
			array_push($transferAry, $up);
		}

		// =============================================================== seeds
		if ($settings[8] != 0) {
			$seeds = ($transferRunning == 1)
			? $sf->seeds
			:  "";
			array_push($transferAry, $seeds);
		}

		// =============================================================== peers
		if ($settings[9] != 0) {
			$peers = ($transferRunning == 1)
			? $sf->peers
			:  "";
			array_push($transferAry, $peers);
		}

		// ================================================================= ETA
		if ($settings[10] != 0)
			array_push($transferAry, $estTime);

		// ============================================================== client
		if ($settings[11] != 0) {
			switch ($settingsAry['client']) {
				case "tornado":
					array_push($transferAry, "B");
					break;
				case "transmission":
					array_push($transferAry, "T");
					break;
				case "mainline":
					array_push($transferAry, "M");
					break;
				case "azureus":
					array_push($transferAry, "A");
					break;
				case "wget":
					array_push($transferAry, "W");
					break;
				case "nzbperl":
					array_push($transferAry, "N");
					break;
				default:
					array_push($transferAry, "U");
			}
		}

		// ---------------------------------------------------------------------
		// Is this transfer for the user list or the general list?
		if ($owner)
			array_push($arUserTransfers, $transferAry);
		else
			array_push($arListTransfers, $transferAry);
	}

	//XFER: update 2
	if (($cfg['enable_xfer'] == 1) && ($cfg['xfer_realtime'] == 1))
		@Xfer::update2();

	// -------------------------------------------------------------------------
	// build output-array
	$retVal = array();
	if (sizeof($arUserTransfers) > 0) {
		foreach($arUserTransfers as $torrentrow)
			array_push($retVal, $torrentrow);
	}
	$boolCond = true;
	if ($cfg['enable_restrictivetview'] == 1)
		$boolCond = $cfg['isAdmin'];
	if (($boolCond) && (sizeof($arListTransfers) > 0)) {
		foreach($arListTransfers as $torrentrow)
			array_push($retVal, $torrentrow);
	}
	return $retVal;
}

/**
 * Function to load the owner for all transfers. returns ref to array
 *
 * @return array-ref
 */
function &loadAllTransferOwner() {
	$ary = array();
	$tary = getTransferArray();
	foreach ($tary as $transfer)
		$ary[$transfer] = getOwner($transfer);
	return $ary;
}

/**
 * Function to load the totals for all transfers. returns ref to array
 *
 * @return array-ref
 */
function &loadAllTransferTotals() {
	global $db;
	$recordset = $db->Execute("SELECT * FROM tf_transfer_totals");
	$ary = array();
	while ($row = $recordset->FetchRow()) {
		if (strlen($row["tid"]) == 40) {
			$ary[$row["tid"]] = array(
				"uptotal" => $row["uptotal"],
				"downtotal" => $row["downtotal"]
			);
		}
	}
	return $ary;
}

/**
 * Function to load the settings for all transfers. returns ref to array
 *
 * @return array-ref
 */
function &loadAllTransferSettings() {
	global $db;
	$recordset = $db->Execute("SELECT * FROM tf_transfers");
	$ary = array();
	while ($row = $recordset->FetchRow()) {
		$ary[$row["transfer"]] = array(
			"type"                   => $row["type"],
			"client"                 => $row["client"],
			"hash"                   => $row["hash"],
			"datapath"               => $row["datapath"],
			"savepath"               => $row["savepath"],
			"running"                => $row["running"],
			"max_upload_rate"        => $row["rate"],
			"max_download_rate"      => $row["drate"],
			"die_when_done"          => $row["runtime"],
			"max_uploads"            => $row["maxuploads"],
			"superseeder"            => $row["superseeder"],
			"minport"                => $row["minport"],
			"maxport"                => $row["maxport"],
			"sharekill"              => $row["sharekill"],
			"maxcons"                => $row["maxcons"],
			"rerequest"              => $row["rerequest"]
		);
	}
	return $ary;
}

/**
 * initGlobalTransfersArray
 */
function initGlobalTransfersArray() {
	global $transfers;
	// transfers
	$transfers = array();
	// settings
	$transferSettings =& loadAllTransferSettings();
	$transfers['settings'] = $transferSettings;
	// totals
	$transferTotals =& loadAllTransferTotals();
	$transfers['totals'] = $transferTotals;
	// sum
	$transfers['sum'] = array(
		'maxcons' => getSumMaxCons(),
		'rate' => getSumMaxUpRate(),
		'drate' => getSumMaxDownRate()
	);
    // owner
	$transferOwner =& loadAllTransferOwner();
	$transfers['owner'] = $transferOwner;
}

/**
 * injects a transfer
 *
 * @param $transfer
 * @return boolean
 */
function injectTransfer($transfer) {
	global $cfg;
	$sf = new StatFile($transfer);
	$sf->running = "2"; // file is new
	$sf->size = getTransferSize($transfer);
	if ($sf->write()) {
		// set transfers-cache
		cacheTransfersSet();
		return true;
	} else {
        AuditAction($cfg["constants"]["error"], "stat-file cannot be written when injecting : ".$transfer);
        return false;
	}
}

/**
 * get Owner
 *
 * @param $transfer
 * @return string
 */
function getOwner($transfer) {
	global $cfg, $db, $transfers;
	if (isset($transfers['owner'][$transfer])) {
		return $transfers['owner'][$transfer];
	} else {
		// Check log to see what user has a history with this file
		$transfers['owner'][$transfer] = $db->GetOne("SELECT user_id FROM tf_log WHERE BINARY file=".$db->qstr($transfer)." AND (action=".$db->qstr($cfg["constants"]["file_upload"])." OR action=".$db->qstr($cfg["constants"]["url_upload"])." OR action=".$db->qstr($cfg["constants"]["reset_owner"]).") ORDER BY time DESC");
		return ($transfers['owner'][$transfer] != "")
			? $transfers['owner'][$transfer]
			: resetOwner($transfer); // try and get the owner from the stat file;
	}
}

/**
 * reset Owner
 *
 * @param $transfer
 * @return string
 */
function resetOwner($transfer) {
	global $cfg, $db, $transfers;
	// log entry has expired so we must renew it
	$rtnValue = "n/a";
	if (file_exists($cfg["transfer_file_path"].$transfer.".stat")) {
		$sf = new StatFile($transfer);
		$rtnValue = (IsUser($sf->transferowner))
			? $sf->transferowner /* We have an owner */
			: GetSuperAdmin(); /* no owner found, so the super admin will now own it */
	    // add entry to the log
	    $sql = "INSERT INTO tf_log (user_id,file,action,ip,ip_resolved,user_agent,time)"
	    	." VALUES ("
	    	. $db->qstr($rtnValue).","
	    	. $db->qstr($transfer).","
	    	. $db->qstr($cfg["constants"]["reset_owner"]).","
    		. $db->qstr($cfg['ip']).","
    		. $db->qstr($cfg['ip_resolved']).","
	    	. $db->qstr($cfg['user_agent']).","
	    	. $db->qstr(time())
	    	.")";
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
	}
	$transfers['owner'][$transfer] = $rtnValue;
	return $rtnValue;
}

/**
 * IsOwner
 *
 * @param $user
 * @param $owner
 * @return boolean
 */
function IsOwner($user, $owner) {
	return (($user) == ($owner));
}
	
/**
 * gets datapath of a transfer.
 *
 * @param $transfer name of the torrent
 * @return var with transfer-datapath or empty string
 */
function getTransferDatapath($transfer) {
	global $cfg, $db, $transfers;
	if (isset($transfers['settings'][$transfer]['datapath'])) {
		return $transfers['settings'][$transfer]['datapath'];
	} else {
		$datapath = $db->GetOne("SELECT datapath FROM tf_transfers WHERE transfer = ".$db->qstr($transfer));
		if (empty($datapath)) {
			if (substr($transfer, -8) == ".torrent") {
				// this is a torrent-client
				require_once('inc/classes/BDecode.php');
				$ftorrent = $cfg["transfer_file_path"].$transfer;
				$fd = fopen($ftorrent, "rd");
				$alltorrent = fread($fd, filesize($ftorrent));
				$btmeta = @BDecode($alltorrent);
				$datapath = (empty($btmeta['info']['name']))
					? ""
					: trim($btmeta['info']['name']);
			} else if (substr($transfer, -5) == ".wget") {
				// this is wget.
				$datapath = ".";
			} else if (substr($transfer, -4) == ".nzb") {
				// This is nzbperl.
				$datapath = ".";
			} else {
				$datapath = "";
			}
		}
		$transfers['settings'][$transfer]['datapath'] = $datapath;
		return $datapath;
	}
}
	
/**
 * gets savepath of a transfer for a given profile.
 *
 * @param $transfer name of the torrent
 * @param $profile name of profile to be used. if not given, attempt
 *	to grab it from request vars is made.
 * @return var with transfer-savepath or empty string
 */
function getTransferSavepath($transfer, $profile = NULL) {
	global $cfg, $db, $transfers;
	if (isset($transfers['settings'][$transfer]['savepath'])) {
		return $transfers['settings'][$transfer]['savepath'];
	} else {
		$savepath = $db->GetOne("SELECT savepath FROM tf_transfers WHERE transfer = ".$db->qstr($transfer));
		if (empty($savepath)) {
			if ($cfg['transfer_profiles'] <= 0) {
				$savepath = ($cfg["enable_home_dirs"] != 0)
					? $cfg["path"].getOwner($transfer).'/'
					: $cfg["path"].$cfg["path_incoming"].'/';
			} else {
				require_once('inc/functions/functions.common.transfer.php');
				$savepath = calcTransferSavepath($transfer, $profile);
			}
		}
		$transfers['settings'][$transfer]['savepath'] = $savepath;
		return $savepath;
	}
}

?>