<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
        by Epsylon3 on gmail.com, Nov 2010
*/
	
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

function vuze_rpc($method, $arguments=NULL) {

	$HOST = '127.0.0.1';
	$PORT = '19091';
	$USER = 'vuze';
	$PASS = 'mypassword';
	
	$DEBUG= false;
	
	$ch = curl_init("http://$HOST:$PORT/transmission/rpc");
	
	curl_setopt($ch, CURLOPT_MAXCONNECTS, 8);
	curl_setopt($ch, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_OLDEST);
	//curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
	//curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
	//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 2); 
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
	
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, "$USER:$PASS");
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, array (
	'Accept: application/json',
	'Content-type: application/json; charset=UTF-8'
	));
	
	$tag = date('U');
	
	$postData = '{"method":"'.$method.'", "tag":"'.$tag.'"}';
	if (isset($arguments))
		$postData = '{"method":"'.$method.'", "arguments": '.json_encode($arguments).', "tag":"'.$tag.'" }';
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	$info = curl_getinfo($ch);
	
	$data = false;
	if ($info["http_code"] != 200) {
		if ($DEBUG) {
			//error
			echo '<pre>'.curl_error($ch);
			var_dump($info);
			echo "</pre>";
		}
	}
	elseif ($res{0} == "{") {
		
		//ok
		$data=json_decode($res);
		
	}
	elseif ($DEBUG) {
		//not json ???
		echo '<pre>';
		var_dump($res);
		echo "</pre>";
	}
	
	curl_close($ch);
	return $data;
}

$session = vuze_rpc('session-get');
if ($session && $session->result == 'success') {

	//choose wanted torrents fields
	$fields = array(
/*		"addedDate", 
		"announceURL", 
		"comment", 
		"creator", 
		"dateCreated", 
		"downloadedEver", 
		"error", 
		"errorString", 
		"eta", 
		"hashString", 
		"haveUnchecked", 
		"haveValid", 
		"id", 
		"isPrivate", 
		"leechers", 
		"leftUntilDone", 
		"name", 
		"peersConnected", 
		"peersGettingFromUs", 
		"peersSendingToUs", 
		"rateDownload", 
		"rateUpload", 
		"seeders", 
		"sizeWhenDone", 
		"status", 
		"swarmSpeed", 
		"totalSize", 
		"uploadedEver"
*/
		"id", 
		"name", 
		"downloadedEver", 
		"totalSize", 
		"uploadedEver"
	);
	$args = new stdclass;
	//$args->ids = array(1160,950);
	$args->fields = $fields;
	$req = vuze_rpc('torrent-get',$args);
	if ($req && $req->result == 'success') {
		$torrents = (array) $req->arguments->torrents;
		echo "<pre>";
		var_dump($torrents);
		echo "</pre>";
	}

}
echo date('H:i:s');

?>
