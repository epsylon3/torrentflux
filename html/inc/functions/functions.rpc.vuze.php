<?php

/**
 * get Vuze RPC torrent id (use it temporary, dont store it)
 *
 * @param $transfer
 * @return int
 */
function getVuzeTransferRpcId($transfer) {
	global $cfg;
	
	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();
	
	$hash = getTransferHash($transfer);
	$torrents = $rpc->torrent_get_hashids();
	$tid = false;
	if ( array_key_exists(strtoupper($hash),$torrents) ) {
		$tid = $torrents[strtoupper($hash)];
	}
	return $tid;
}

/**
 * get all session variables to prevent multiple calls
 *
 * @return bool
 */
function initVuzeSessionCache() {
	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();
	unset($rpc->session);
	$req = $rpc->session_get();
	return (is_object($rpc->session));
}

/**
 * get Vuze ShareKill value
 *
 * @return int
 */
function getVuzeShareKill($usecache=false) {
	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();
	
	if ($usecache) {
		if (!isset($rpc->session)) initVuzeSessionCache();
		return round($rpc->session->seedRatioLimit * 100.0);
	}

	$req = $rpc->session_get('seedRatioLimit');
	if (is_object($req) && isset($req->arguments->seedRatioLimit)) {
		return round($req->arguments->seedRatioLimit * 100.0);
	}
	
	return 0;
}

/**
 * get Vuze Global Speed Limit Upload
 *
 * @return int
 */
function getVuzeSpeedLimitUpload($usecache=false) {
	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();

	$key = 'speed-limit-up';

	if ($usecache) {
		if (!isset($rpc->session)) initVuzeSessionCache();
		return (int) $rpc->session->$key;
	}

	$req = $rpc->session_get($key);
	if (is_object($req) && isset($req->arguments->$key)) {
		return (int) $req->arguments->$key;
	}
	
	return 0;
}

/**
 * get Vuze Global Speed Limit Download
 *
 * @return int
 */
function getVuzeSpeedLimitDownload($usecache=false) {
	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();

	$key = 'speed-limit-down';

	if ($usecache) {
		if (!isset($rpc->session)) initVuzeSessionCache();
		return (int) $rpc->session->$key;
	}

	$req = $rpc->session_get($key);
	if (is_object($req) && isset($req->arguments->$key)) {
		return (int) $req->arguments->$key;
	}
	
	return 0;
}

/**
 * reset Vuze total uploaded (to seed again)
 */
function vuzeResetUpload($hash) {
	global $cfg;

	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();
	
	$torrents = $rpc->torrent_get_hashids();
	$tid = "";
	if ( array_key_exists(strtoupper($hash),$torrents) ) {
		$tid = $torrents[strtoupper($hash)];
	}
	if (!empty($tid)) {
		$key = 'uploadedEver';
		$rpc->torrent_set(array($tid),'uploadedEver',0);
	}
}

//to check...
function addVuzeMagnetTransfer($userid = 0, $url, $path, $paused=true) {
	global $cfg;

	require_once('inc/classes/VuzeRPC.php');
	$rpc = VuzeRPC::getInstance();

	$content = $path."\n";
	//... max_ul
	//... todo
	$id = $rpc->torrent_add_url( $url, $content);

	$hash = false;
	if ($id !== false) {
		$hash = $rpc->torrent_get_hashids(array($id));
	} else {
		AuditAction($cfg["constants"]["error"], "Download Magnet : ".$rpc->lastError);
	}
	return $hash;
}

?>