<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
        by Epsylon3 on gmail.com, Nov 2010
*/

class VuzeRPC {

	public $DEBUG = false;

	public $HOST = '127.0.0.1';
	public $PORT = '19091';
	public $USER = 'vuze';
	public $PASS = 'mypassword';

	//curl token
	protected $ch = NULL;

	//vuze general config
	public $session;
	
	//last request info
	public $curl_info;

	/*
	 * Constructor 
	*/
	public function __construct($cfg = array()) {

		if ($this->DEBUG) {
			error_reporting(E_ALL);
		}

		if (isset($cfg['vuze_rpc_host']))
			$this->HOST = $cfg['vuze_rpc_host'];
		if (isset($cfg['vuze_rpc_port']))
			$this->PORT = $cfg['vuze_rpc_port'];
		if (isset($cfg['vuze_rpc_user']))
			$this->USER = $cfg['vuze_rpc_user'];
		if (isset($cfg['vuze_rpc_pass']))
			$this->PASS = $cfg['vuze_rpc_pass'];

	}

	/*
	 * Destructor 
	*/
	public function __destruct() {
		if (!is_null($this->ch)) {
			curl_close($this->ch);
		}
	}

	/*
	 * General Options to curl http requests
	*/
	public function set_curl_options() {

		$HOST = $this->HOST;
		$PORT = $this->PORT;
		$this->ch = curl_init("http://$HOST:$PORT/transmission/rpc");

		curl_setopt($this->ch, CURLOPT_MAXCONNECTS, 8);
		curl_setopt($this->ch, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_OLDEST);
		//curl_setopt($this->ch, CURLOPT_FORBID_REUSE, 1);
		//curl_setopt($this->ch, CURLOPT_FRESH_CONNECT, 1);
		//curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLOPT_MAXREDIRS, 2);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 3);

		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array (
			'Accept: application/json',
			'Content-type: application/json; charset=UTF-8'
		));

		curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($this->ch, CURLOPT_USERPWD, $this->USER.':'.$this->PASS);
	}

	/*
	 * RPC Call to get Torrent List
	 * @return false or object
	*/
	public function vuze_rpc($method, $arguments=NULL) {
	
		if (is_null($this->ch)) {
			$this->set_curl_options();
		}
		
		$tag = date('U');
		
		$postData = '{"method":"'.$method.'", "tag":"'.$tag.'"}';
		if (isset($arguments))
			$postData = '{"method":"'.$method.'", "arguments": '.json_encode($arguments).', "tag":"'.$tag.'" }';
		
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
		
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($this->ch);
		$this->curl_info = curl_getinfo($this->ch);
		
		$data = false;
		if ($this->curl_info["http_code"] != 200) {
			if ($this->DEBUG) {
				//error
				echo '<pre>'.curl_error($this->ch);
				var_dump($this->curl_info);
				echo "</pre>";
			}
		}
		elseif ($res{0} == "{") {
			
			//ok
			$data=json_decode($res);
			
		}
		elseif ($this->DEBUG) {
			//not json ???
			echo '<pre>';
			var_dump($res);
			echo "</pre>";
		}
		
		return $data;
	}

	// Get Vuze data (general config)
	public function session_get() {
		$this->session = $this->vuze_rpc('session-get');
		
		return $this->session;
	}

	// Get Vuze data (all torrents)
	public function torrent_get() {

		//choose wanted torrents fields
		$fields = array(
			/*
			"addedDate", 
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
			"hashString", 
			"status", 
			"rateDownload", 
			"rateUpload", 
			"downloadedEver", 
			"uploadedEver",
			"sizeWhenDone",
			"totalSize", 
			"eta", 
			"leechers", 
			"seeders", 
			"peersConnected"
		);

		$args = new stdclass;
		//$args->ids = array(1160,950);
		$args->fields = $fields;
		$req = $this->vuze_rpc('torrent-get',$args);

		return $req;
	}

	/*
	 * Vuze RPC Struct to TorrentFlux Names
	 * @param $stat : (1) torrent data object
	 * @return array
	*/
	public function vuze_to_tf($stat) {
		$tfStat = array(
			'running' => $stat->status,
			'speedDown' => $stat->rateDownload,
			'speedUp' => $stat->rateUpload,
			'downCurrent' => $stat->rateDownload,
			'upCurrent' => $stat->rateUpload,
			'downTotal' => $stat->downloadedEver,
			'upTotal' => $stat->uploadedEver,
			'percentDone' => 0.0,
			'sharing' => 0.0,
			'eta' => $stat->eta,
			'seeds' => $stat->seeders,
			'peers' => $stat->leechers,
			'cons' => $stat->peersConnected,
			'hashString' => $stat->hashString
		);
		//'cons' => $stat->peersGettingFromUs + $stat->peersSendingToUs
		if ($stat->totalSize > 0) {
			$tfStat['percentDone'] = round(100.0 * ($stat->downloadedEver / $stat->totalSize) ,1);
			$tfStat['sharing'] = round(100.0 * ($stat->uploadedEver / $stat->totalSize) ,1);
		}
		
		return $tfStat;
	}

	/*
	 * Get all torrents in torrentflux compatible format
	 * @return array
	*/
	public function torrent_get_tf_array() {

		$torrents = array();

		$req = $this->torrent_get();
		if ($req && $req->result == 'success') {
			$vuze = (array) $req->arguments->torrents;
			
			foreach($vuze as $t) {
				$torrents[$t->name] = $this->vuze_to_tf($t);
			}
		}

		return $torrents;

	}

	/*
	 * Get all torrents in torrentflux compatible format (json)
	 * @return object
	*/
	public function torrent_get_tf_json() {

		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		header('Content-type: application/json; charset=UTF-8');

		$request = new stdClass;
		$request->datetime = date('Y-M-d H:i:s');
		$request->ts = date('U');
		$request->status = '';

		$session = $this->session_get();
		if ($session && $session->result == 'success') {
			
			$request->torrents = $this->torrent_get_tf_array();
			$request->status = 'OK';
			
		}
		
		echo json_encode($request);

	}

} //end of VuzeRPC class


//--------------------------------------------------------
//Test config

//commented to keep default
//$rpc_cfg['vuze_rpc_host']='mytesthost.com';
//$rpc_cfg['vuze_rpc_port']='19091';
//$rpc_cfg['vuze_rpc_user']='vuze';
//$rpc_cfg['vuze_rpc_pass']='blabla';

//$v = new VuzeRPC($rpc_cfg);
//$v->torrent_get_tf_json();

?>
