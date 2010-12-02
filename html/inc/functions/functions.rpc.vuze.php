<?php

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