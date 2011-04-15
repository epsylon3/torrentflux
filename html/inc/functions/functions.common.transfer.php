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
 * get log of a Transfer
 *
 * @param $transfer
 * @return string
 */
function getTransferLog($transfer) {
	global $cfg;
	$emptyLog = "log empty";
	// sanity-check
	if (!isset($transfer) || (tfb_isValidTransfer($transfer) !== true))
		return "invalid transfer";
	// log-file
	$transferLogFile = $cfg["transfer_file_path"].$transfer.".log";
	// check
	if (!(file_exists($transferLogFile)))
		return $emptyLog;
	// open
	$handle = false;
	$handle = @fopen($transferLogFile, "r");
	if (!$handle)
		return $emptyLog;
	// read
	$data = "";
	while (!@feof($handle))
		$data .= @fgets($handle, 8192);
	@fclose ($handle);
	if ($data == "")
		return $emptyLog;
	// return
	return $data;
}

/**
 * Function to delete saved Settings
 *
 * @param $transfer
 * @return boolean
 */
function deleteTransferSettings($transfer) {
	global $db;
	$sql = "DELETE FROM tf_transfers WHERE transfer = ".$db->qstr($transfer);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// set transfers-cache
	cacheTransfersSet();
	return true;
}

/**
 * sets the running flag in the db to stopped.
 *
 * @param $transfer name of the transfer
 */
function stopTransferSettings($transfer) {
	global $db;
	$db->Execute("UPDATE tf_transfers SET running = '0' WHERE transfer = ".$db->qstr($transfer));
	// set transfers-cache
	cacheTransfersSet();
	return true;
}

/**
 * waits until transfer is up/down
 *
 * @param $transfer name of the transfer
 * @param $state : true = start, false = stop
 * @param $maxWait in seconds
 * @return boolean
 */
function waitForTransfer($transfer, $state, $maxWait = 15) {
	$maxLoops = $maxWait * 5;
	$loopCtr = 0;
	for (;;) {
		@clearstatcache();
		if (isTransferRunning($transfer) === $state) {
			return true;
		} else {
			$loopCtr++;
			if ($loopCtr > $maxLoops)
				return false;
			else
				usleep(200000); // wait for 0.2 seconds
		}
	}
	return true;
}

/**
 * resets totals of a transfer
 *
 * @param $transfer name of the transfer
 * @param $delete boolean if to delete meta-file
 * @return array
 */
function resetTransferTotals($transfer, $delete = false) {
	global $cfg, $db, $transfers;
	$msgs = array();
	$tid = getTransferHash($transfer);
	$client = getTransferClient($transfer);
	// delete meta-file
	if ($delete) {
		$ch = ClientHandler::getInstance($client);
		$ch->delete($transfer);
		if (count($ch->messages) > 0)
			$msgs = array_merge($msgs, $ch->messages);
	} else {
		// reset in stat-file
		$sf = new StatFile($transfer, getOwner($transfer));
		$sf->uptotal = 0;
		$sf->downtotal = 0;
		$sf->write();
		if ($client == "vuzerpc") {
			require_once("inc/functions/functions.rpc.vuze.php");
			vuzeResetUpload($tid);
		}
	}
	// reset in db
	$uid = (int) getTransferOwnerID($transfer);
	$sql = "UPDATE tf_transfer_totals SET uptotal=0 WHERE tid = ".$db->qstr($tid)." AND uid IN (0,$uid)";
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	// set transfers-cache
	cacheTransfersSet();
	return $msgs;
}

/**
 * deletes data of a transfer
 *
 * @param $transfer name of the transfer
 * @return array
 */
function deleteTransferData($transfer) {
	global $cfg, $transfers;
	$msgs = array();

	$isTransmissionTorrent = false;
	if ($cfg["transmission_rpc_enable"]==2 && isHash($transfer)) {
		require_once('inc/classes/Transmission.class.php');
		$trans = new Transmission();
		require_once('inc/functions/functions.rpc.transmission.php');
		$theTorrent = getTransmissionTransfer($transfer, array('hashString', 'id', 'name'));
		$isTransmissionTorrent = is_array($theTorrent);
	}

	if ( $isTransmissionTorrent ) {
		$response = $trans->remove($theTorrent['id'], true);
		if ( $response[result] != "success" ) @error("Delete of torrent failed", "", "", $response[result]);
	} else {
		if (($cfg['isAdmin']) || (IsOwner($cfg["user"], getOwner($transfer)))) {
			// only torrent
			if (substr($transfer, -8) != ".torrent")
				return $msgs;
			// delete data
			$datapath = getTransferDatapath($transfer);
			if (($datapath != "") && ($datapath != ".")) {
				$targetPath = getTransferSavepath($transfer).$datapath;
				if (tfb_isValidPath($targetPath)) {
					if ((@is_dir($targetPath)) || (@is_file($targetPath))) {
						avddelete($targetPath);
						AuditAction($cfg["constants"]["fm_delete"], $targetPath);
					}
				} else {
					$msg = "ILLEGAL DELETE: ".$cfg["user"]." attempted to delete data of ".$transfer;
					AuditAction($cfg["constants"]["error"], $msg);
					array_push($msgs, $msg);
				}
			}
		} else {
			$msg = "ILLEGAL DELETE: ".$cfg["user"]." attempted to delete data of ".$transfer;
			AuditAction($cfg["constants"]["error"], $msg);
			array_push($msgs, $msg);
		}
	}
	return $msgs;
}

/**
 * gets size of data of a torrent
 *
 * @param $transfer name of the torrent
 * @return int with size of data of torrent.
 *		   -1 if error
 *		   4096 if dir (lol ~)
 */
function getTorrentDataSize($transfer) {
	global $cfg;
	$datapath = getTransferDatapath($transfer);
	return (($datapath != "") && ($datapath != "."))
		? file_size(getTransferSavepath($transfer).$datapath)
		: -1;
}

/**
 * gets savepath of a transfer for a given profile.
 *
 * @param $transfer name of the torrent
 * @param $profile name of profile to be used. if not given, attempt
 *	to grab it from request vars is made.
 * @return var with transfer-savepath
 */
function calcTransferSavepath($transfer, $profile = NULL) {
	global $cfg, $transfers;
	require_once('functions.common.trprofile.php');
	// meh, my hack
	if ($profile == NULL) {
		$profile = tfb_getRequestVar("profile");
	}
	$settings = GetProfileSettings($profile);
	$savepath = "";
	if (isset($settings["savepath"]))
		$savepath = $settings["savepath"];
	// no savepath set in profile or profile not set.
	// so: take default save path.
	if ($savepath == "") {		
		$savepath = ($cfg["enable_home_dirs"] != 0)
			? $cfg["path"].getOwner($transfer).'/'
			: $cfg["path"].$cfg["path_incoming"].'/';
	}
	// Save path is set. if using homedirs, we're fine as savepaths must lie
	// below the users homedir. This is enforced when saving the savepath.
	// If not using homedirs, incoming path should be appended.
	else if ($cfg["enable_home_dirs"] == 0) { 
		$savepath .= $cfg["path_incoming"].'/';
	}
	
	return $savepath;
}

/**
 * set file prio
 *
 * @param $transfer
 */
function setFilePriority($transfer) {
	global $cfg;

	$isTransmissionTorrent = false;
	if ($cfg["transmission_rpc_enable"] && isHash($transfer)) {
		require_once('inc/functions/functions.rpc.transmission.php');
		$theTorrent = getTransmissionTransfer($transfer, array('hashString', 'id', 'name'));
		$isTransmissionTorrent = is_array($theTorrent);
	}
	
	if ( $isTransmissionTorrent ) {
		foreach ($_REQUEST['files'] as $fileid ) {
			$selectedFiles[] = (int)$fileid;
		}
		# Get files that are wanted or not for download, then we can compare.
		$responseWantedFiles = getTransmissionTransfer( $transfer, array('wanted') );
		$wantedFiles = $responseWantedFiles['wanted'];
		
		$thearray = array_fill(0, count($wantedFiles), 0);
		foreach ( $selectedFiles as $fileid ) {
			$thearray[$fileid] = 1;
		}
		
		$counter = 0;
		foreach ( $wantedFiles as $fileid => $wanted ) {
			if ( $thearray[$counter] == 1 && $wantedFiles[$counter] == 0 ) { // the file is not marked as selected in the gui but it has been saved as "wanted"
					$includeFiles[]=(int)$counter; // deselect this files
			}
			if ( $thearray[$counter] == 0 && $wantedFiles[$counter] == 1 ) { // the file is not marked as selected in the gui but it has been saved as "wanted"
				$excludeFiles[]=(int)$counter; // deselect this files
			}
			$counter++;
		}
		
		if (count($includeFiles)>0) {
			$includeFiles = array_values($includeFiles);
			setTransmissionTransferProperties( $transfer, array("files-wanted" => $includeFiles) );
		}
		if (count($excludeFiles)>0) {
			$excludeFiles = array_values($excludeFiles);
			setTransmissionTransferProperties( $transfer, array("files-unwanted" => $excludeFiles) );

		}
	} else {
		// we will use this to determine if we should create a prio file.
		// if the user passes all 1's then they want the whole thing.
		// so we don't need to create a prio file.
		// if there is a -1 in the array then they are requesting
		// to skip a file. so we will need to create the prio file.
		$okToCreate = false;
		if (!empty($transfer)) {
		$fileName = $cfg["transfer_file_path"].$transfer.".prio";
		$result = array();
		$files = array();
		if (isset($_REQUEST['files'])) {
			$filesTemp = (is_array($_REQUEST['files']))
				? $_REQUEST['files']
				: array($_REQUEST['files']);
			$files = array_filter($filesTemp, "getFile");
		}
		// if there are files to get then process and create a prio file.
		if (count($files) > 0) {
			for ($i=0; $i <= tfb_getRequestVar('count'); $i++) {
			if (in_array($i,$files)) {
				array_push($result, 1);
			} else {
				$okToCreate = true;
				array_push($result, -1);
			}
			}
			if ($okToCreate) {
			$fp = fopen($fileName, "w");
			fwrite($fp,tfb_getRequestVar('filecount').",");
			fwrite($fp,implode($result,','));
			fclose($fp);
			} else {
			// No files to skip so must be wanting them all.
			// So we will remove the prio file.
			@unlink($fileName);
			}
		} else {
			// No files selected so must be wanting them all.
			// So we will remove the prio file.
			@unlink($fileName);
		}
		}
	}
}

/**
 * gets scrape-info of a torrent as string
 *
 * @param $transfer name of the torrent
 * @return string with torrent-scrape-info
 */
function getTorrentScrapeInfo($transfer) {
	global $cfg;
	$hasClient = false;
	// transmissioncli
	if (!$cfg["transmission_rpc_enable"]) {
		$hasClient = true;
		$retVal = "";
		$retVal = @shell_exec("HOME=".tfb_shellencode($cfg["path"])."; export HOME; ".$cfg["btclient_transmission_bin"] . " -s ".tfb_shellencode($cfg["transfer_file_path"].$transfer));
		if ((isset($retVal)) && ($retVal != "") && (!preg_match('/.*failed.*/i', $retVal)))
			return trim($retVal);
	}
	// ttools.pl
	if (is_executable($cfg["perlCmd"])) {
		$hasClient = true;
		$retVal = "";
		$retVal = @shell_exec($cfg["perlCmd"].' -I '.tfb_shellencode($cfg["docroot"].'bin/ttools').' '.tfb_shellencode($cfg["docroot"].'bin/ttools/ttools.pl').' -s '.tfb_shellencode($cfg["transfer_file_path"].$transfer));
		if ((isset($retVal)) && ($retVal != "") && (!preg_match('/.*failed.*/i', $retVal)))
			return trim($retVal);
	}
	// failed
	return ($hasClient)
		? "Scrape failed"
		: "No Scrape-Client";
}

/**
 * gets ary of running clients (via call to ps)
 *
 * @param $client
 * @return array
 */
function getRunningClientProcesses($client = '') {
	// client-array
	$clients = ($client == '')
		? array('tornado', 'transmission', 'mainline', 'wget', 'nzbperl', 'azureus')
		: array($client);
	// get clients
	$retVal = array();
	foreach ($clients as $client) {
		// client-handler
		$ch = ClientHandler::getInstance($client);
		$procs = $ch->runningProcesses();
		if (!empty($procs))
			$retVal = array_merge($retVal, $procs);
	}
	// return
	return $retVal;
}

/**
 * get info of running clients (via call to ps)
 *
 * @param $client
 * @return string
 */
function getRunningClientProcessInfo($client = '') {
	// client-array
	$clients = ($client == '')
		? array('tornado', 'transmission', 'mainline', 'wget', 'nzbperl', 'azureus')
		: array($client);
	// get clients
	$retVal = "";
	foreach ($clients as $client) {
		// client-handler
		$ch = ClientHandler::getInstance($client);
		$retVal .= $ch->runningProcessInfo();
	}
	// return
	return $retVal;
}

?>
