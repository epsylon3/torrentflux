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

function rpc_error($errorstr,$dummy="",$dummy="",$response="") {
	global $cfg;
	AuditAction($cfg["constants"]["error"], "Transmission RPC : $errorstr - $response");
	@error($errorstr, "", "", $response);
	addGrowlMessage('transmission-rpc',$errorstr.$response);
	//dbError($errorstr);
}

/**
 * get one Transmission transfer data array
 *
 * @param $transfer hash of the transfer
 * @param $fields array of fields needed
 * @return array or false
 */
function getTransmissionTransfer($transfer, $fields=array() ) {
	//$fields = array("id", "name", "eta", "downloadedEver", "hashString", "fileStats", "totalSize", "percentDone", 
	//			"metadataPercentComplete", "rateDownload", "rateUpload", "status", "files", "trackerStats" )
	$required = array('hashString');
	$afields = array_merge($required, $fields);
	
	require_once('inc/classes/Transmission.class.php');
	$trans = new Transmission();
	$response = $trans->get(array(), $afields);
	$torrentlist = $response['arguments']['torrents'];
	
	if (!empty($torrentlist)) {
		foreach ($torrentlist as $aTorrent) {
			if ( $aTorrent['hashString'] == $transfer )
				return $aTorrent;
		}
	}
	return false;
}

/**
 * set a property for a Transmission transfer identified by hash
 *
 * @param $transfer hash of the transfer
 * @param array of properties to set
 **/
function setTransmissionTransferProperties($transfer, $fields=array()) {
	require_once('inc/classes/Transmission.class.php');
	$trans = new Transmission();
	$transferId = getTransmissionTransferIdByHash($transfer);
	
	$response = $trans->set($transferId, $fields);
	if ( $response['result'] !== 'success' )
		rpc_error("Setting transfer properties failed", "", "", $response['result']);
}


/**
 * checks if transfer is running
 *
 * @param $transfer hash of the transfer
 * @return boolean
 */
function isTransmissionTransferRunning($transfer) {
	$aTorrent = getTransmissionTransfer($transfer, array('status'));
	if (is_array($aTorrent)) {
		return ( $aTorrent['status'] != 16 );
	}
	return false;
}

/**
 * checks if transfer is Transmission
 *
 * @param $transfer hash of the transfer
 * @return boolean
 */
function isTransmissionTransfer($transfer) {
	$aTorrent = getTransmissionTransfer($transfer);
	return is_array($aTorrent);
}

/**
 * getRunningTransmissionTransferCount
 *
 * @return int with number of running transfers for transmission daemon
 * TODO: make it return a correct value
 */
function getRunningTransmissionTransferCount() {
	$result = getUserTransmissionTransfers(0);
	$count = 0;

	// Note that this also counts the downloads that are not added through torrentflux
	foreach ($result as $aTorrent) {
		if ( $aTorrent['status']==4 || $aTorrent['status']==8 ) $count++;
	}
	return $count;
}

/**
 * This method gets Transmission transfers from a certain user from database in an array
 *
 * @return array with uid and transmission transfer hash
 */
function getUserTransmissionTransferArrayFromDB($uid = 0) {
	global $db;
	$retVal = array();
	$sql = "SELECT tid FROM tf_transmission_user" . ($uid!=0 ? ' WHERE uid=' . $uid : '' );
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	while(list($transfer) = $recordset->FetchRow())
		array_push($retVal, $transfer);
	return $retVal;
}

/**
 * This method checks if a certain transfer is existing and from the same user
 *
 * @return array with uid and transmission transfer hash
 * TODO: check if $tid is filled in and return error
 * TODO: check that uid being zero cannot lead to security breach (information disclosure)
 */
function isValidTransmissionTransfer($uid = 0,$tid) {
	global $db;
	$retVal = array();
	$sql = "SELECT tid FROM tf_transmission_user WHERE tid='$tid' AND uid='$uid'";
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	if ( sizeof($recordset)!=0 ) return true;
	else return false;
}

/**
 * This method returns the owner name of a certain transmission transfer
 * 
 * @return string with owner of transmission transfer
 */
function getTransmissionTransferOwner($transfer) {
	global $db;
	$retVal = array();
	$sql = "SELECT user_id FROM tf_users u join tf_transmission_user t on (t.uid = u.uid) WHERE t.tid = '$transfer';";
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	if ( sizeof($recordset)!=0 ) {
		$row = $recordset->FetchRow();
		return $row['user_id'];
	}
	else return "Unknown";
}

/**
 * This method starts the Transmission transfer with the matching hash
 *
 * @return void
 */
function startTransmissionTransfer($hash,$startPaused=false) {
	global $cfg;
	require_once('inc/classes/Transmission.class.php');
	$trans = new Transmission();

	if ( isValidTransmissionTransfer($cfg['uid'],$hash) ) {
		$transmissionId = getTransmissionTransferIdByHash($hash);
		$response = $trans->start($transmissionId);
		if ( $response['result'] != "success" ) {
			rpc_error("Start failed", "", "", $response['result']);
			return false;
		}
		return true;
	} else {
		rpc_error("startTransmissionTransfer : Not ValidTransmissionTransfer hash=$hash ");
		return false;
	}
}

/**
 * This method stops the Transmission transfer with the matching hash
 *
 * @return void
 */
function stopTransmissionTransfer($hash) {
	global $cfg;
	require_once('inc/classes/Transmission.class.php');
	$trans = new Transmission();

	if ( isValidTransmissionTransfer($cfg['uid'],$hash) ) {
		$transmissionId = getTransmissionTransferIdByHash($hash);
		$response = $trans->stop($transmissionId);
		if ( $response['result'] != "success" ) rpc_error("Stop failed", "", "", $response['result']);
	}
}

/**
 * This method deletes the Transmission transfer with the matching hash, without removing the data
 *
 * @return void
 * TODO: test delete :)
 */
function deleteTransmissionTransfer($uid, $hash, $deleteData = false) {
	require_once('inc/classes/Transmission.class.php');
	$trans = new Transmission();

	if ( isValidTransmissionTransfer($uid, $hash) ) {
		$transmissionId = getTransmissionTransferIdByHash($hash);
		$response = $trans->remove($transmissionId,$deleteData);
		if ( $response['result'] != "success" )
			rpc_error("Delete failed", "", "", $response['result']);
	}

	deleteTransmissionTransferFromDB($uid, $hash);
}

/**
 * This method deletes the Transmission transfer with the matching hash, and its data
 *
 * @return void
 * TODO: test delete :)
 */
function deleteTransmissionTransferWithData($uid, $hash) {
	deleteTransmissionTransfer($uid, $hash, true);
}

/**
 * This method retrieves the current ID in transmission for the transfer that matches the $hash hash
 *
 * @return transmissionTransferId
 */
function getTransmissionTransferIdByHash($hash) {
	require_once('inc/classes/Transmission.class.php');
	$transmissionTransferId = false;
	$trans = new Transmission();
	$response = $trans->get(array(), array('id','hashString'));
	if ( $response['result'] != "success" ) rpc_error("Getting ID for Hash failed: ".$response['result']);
	$torrentlist = $response['arguments']['torrents'];
	foreach ($torrentlist as $aTorrent) {
		if ( $aTorrent['hashString'] == $hash ) {
			$transmissionTransferId = $aTorrent['id'];
			break;
		}
	}
	return $transmissionTransferId;
}

/**
 * This method deletes a Transmission transfer for a certain user from the database
 *
 * @return void
 * TODO: return error if deletion from db does fail
 */
function deleteTransmissionTransferFromDB($uid = 0,$tid) {
	global $db;
	$retVal = array();
	$sql = "DELETE FROM tf_transmission_user WHERE uid='$uid' AND tid='$tid'";
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	/*return $retVal;*/
}

/**
 * This method adds a Transmission transfer for a certain user in database
 *
 * @return array with uid and transmission transfer hash
 * TODO: check if $tid is filled in and return error
 */
function addTransmissionTransferToDB($uid = 0,$tid) {
	global $db;
	$retVal = array();
	$sql = "INSERT INTO tf_transmission_user (uid,tid) VALUES ('$uid','$tid')";
	$recordset = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	/*return $retVal;*/
}

/**
 * This method adds a Transmission transfer to transmission-daemon
 *
 * @return array with uid and transmission transfer hash
 * TODO: generate an error when adding does fail
 */
function addTransmissionTransfer($uid = 0, $url, $path, $paused=true) {
	// $path holds the download path

	require_once('inc/classes/Transmission.class.php');
	$rpc = new Transmission();

	$result = $rpc->add( $url, $path, array ('paused' => $paused)  );
	if($result["result"]!=="success") rpc_error("addTransmissionTransfer","","",$result["result"]. " url=$url");

	$hash = $result['arguments']['torrent-added']['hashString'];
	//rpc_error("The hash is: $hash. The uid is $uid"); exit();

	addTransmissionTransferToDB($uid, $hash);
	return $hash;
}

/**
 * This method adds a Transmission transfer for a certain user in database
 *
 * @return array with uid and transmission transfer hash
 */
function getUserTransmissionTransfers($uid = 0) {
	$retVal = array();
	if ( $uid!=0 ) {
		$userTransferHashes = getUserTransmissionTransferArrayFromDB($uid);
		if ( empty($userTransferHashes) ) return $retVal;
	}

	require_once('inc/classes/Transmission.class.php');
	$rpc = new Transmission ();
	$fields = array ( "id", "name", "eta", "downloadedEver", "hashString", "fileStats", "totalSize", "percentDone", "metadataPercentComplete", "rateDownload", "rateUpload", "status", "files", "trackerStats" );
	$result = $rpc->get ( array(), $fields );

	if ($result['result']!=="success") rpc_error("Transmission RPC could not get transfers : ".$result['result']);
	foreach ( $result['arguments']['torrents'] as $transfer ) {
		if ( $uid==0 || in_array ( $transfer['hashString'], $userTransferHashes ) ) {
			array_push($retVal, $transfer);
		}
	}
	return $retVal;
}

//used in iid/index
function getTransmissionStatusImage($running, $seederCount, $uploadRate){
	$statusImage = "black.gif";
	if ($running) {
		// running
				if ($seederCount < 2)
						$statusImage = "yellow.gif";
				if ($seederCount == 0)
						$statusImage = "red.gif";
				if ($seederCount >= 2)
						$statusImage = "green.gif";
	}
	if ( floor($aTorrent[percentDone]*100) >= 100 ) {
		$statusImage = ( $uploadRate != 0 && $running )
						? "green.gif" /* seeding */
						: "black.gif"; /* finished */
	}
	return $statusImage;
}

?>
