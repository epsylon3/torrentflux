<?php
/*
VUZE xmwebui (0.2.9) RPC interface for PHP
		by tanguy.pruvot on gmail.com, Nov 2010-Apr 2011

	Require PHP 5 for public/protected members

	Require PHP curl extension

	Require PHP 5 >= 5.2.0 for json_encode()

	Settings needed in DB (need logout)
		$cfg['vuze_rpc_enable'];
		$cfg['vuze_rpc_host'];
		$cfg['vuze_rpc_port'];
		$cfg['vuze_rpc_user'];
		$cfg['vuze_rpc_password'];
*/

// xmwebui seems to accept only urls and magnet to add torrents
// so i've added that to download local torrent file.
// edit: fixed in vuze core 4.5.1.1-B30
if (isset($_REQUEST['getUrl'])) {

	header("Content-type: application/octet-stream\n");

	// main.core to get $cfg
	chdir('../../');
	require_once('inc/main.core.php');

	// security replace (dl only local torrent files)
	$transfer = str_replace('/','',$_REQUEST['getUrl']);

	global $cfg;
	$path = $cfg["transfer_file_path"];
	//$data = file_get_contents($path.$transfer);
	if (is_file($path.$transfer)) {
		$fp = popen("cat ".tfb_shellencode($path.$transfer), "r");
		fpassthru($fp);
		pclose($fp);
		exit(0);
	}
}

// for static calls, first create will affect this var
// next calls will reuse it with VuzeRPC::getInstance()
// usefull for curl, to keep connection (no curl_close)
$VuzeRPC_instance=NULL;

class VuzeRPC {

	public $DEBUG = false;

	public $HOST = '127.0.0.1';
	public $PORT = '9091';
	public $USER = 'vuze';
	public $PASS = 'mypassword';

	//info about last error
	public $lastError="";
	public $errMethod="";

	//vuze general config
	public $session;

	//full torrents array
	public $torrents = array();

	//last request info
	public $curl_info;

	//filters
	public $filter=array();

	//internal vars, dont touch them

		//torrentflux config array
		protected $cfg;
		//curl token
		protected $ch = NULL;

	//xmwebui version (from session vars)
	public $xmwebui_ver;
	//vuze version (from session vars)
	public $vuze_ver;

	/*
	 * Constructor
	*/
	public function __construct($_cfg = array()) {

		if ($this->DEBUG) {
			error_reporting(E_ALL | E_STRICT);
		}

		global $cfg;
		if (!empty($_cfg)) {
		 	if (!empty($cfg))
				$cfg = array_merge($cfg, $_cfg);
			else
				$cfg = $_cfg;
		}

		if (isset($cfg['vuze_rpc_host']))
			$this->HOST = $cfg['vuze_rpc_host'];
		if (isset($cfg['vuze_rpc_port']))
			$this->PORT = $cfg['vuze_rpc_port'];
		if (isset($cfg['vuze_rpc_user']))
			$this->USER = $cfg['vuze_rpc_user'];
		if (isset($cfg['vuze_rpc_password']))
			$this->PASS = $cfg['vuze_rpc_password'];

		$this->cfg = & $cfg;
		
		if (!isset($cfg['vuzerpcinfo_xmwebui_version'])) {
			$req = $this->session_get(array("version","az-version"));
			if ($req->result == "success") {
				$this->xmwebui_ver = $req->arguments->version;
				$key = "az-version";
				$this->vuze_ver    = $req->arguments->$key;
				$cfg['vuzerpcinfo_xmwebui_version']  = $this->xmwebui_ver;
				$cfg['vuzerpcinfo_version'] = $this->vuze_ver;
			}
		}
		$this->xmwebui_ver = $cfg['vuzerpcinfo_xmwebui_version'];
		$this->vuze_ver = $cfg['vuzerpcinfo_version'];

		global $VuzeRPC_instance;
		$VuzeRPC_instance = & $this;

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
			'Content-type: application/json' //no charset needed in json
		));

		curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($this->ch, CURLOPT_USERPWD, $this->USER.':'.$this->PASS);
	}

	/*
	 * RPC Call to xmwebui
	 * @return false or object
	*/
	public function vuze_rpc($method, $arguments=NULL) {

		if (is_null($this->ch)) {
			$this->set_curl_options();
		}

		//timestamp tag
		$tag = date('U');

		$postData = '{"method":"'.$method.'", "tag":"'.$tag.'"}';
		if (isset($arguments))
			$postData = '{"method":"'.$method.'", "arguments": '.json_encode($arguments).', "tag":"'.$tag.'" }';

		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);

		//curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
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

			if ($data->result != 'success') {
				$this->errMethod = $method;
				$this->lastError = $data->result;
				
				//require_once('inc/functions/functions.core.php');
				if (function_exists('AuditAction')) {
					AuditAction($this->cfg["constants"]["debug"],"VuzeRPC.$method :".$this->lastError);
				}
			}

		}
		elseif ($this->DEBUG) {
			//not json ???
			echo '<pre>';
			var_dump($res);
			echo "</pre>";
		}

		return $data;
	}

	// Get xmwebui session variables (general config)
	public function session_get($fields=array()) {
		if (!empty($fields)) {
			$args = new stdClass;
			if (is_array($fields))
				$args->fields = $fields;
			else
				$args->fields = array($fields);
			$req = $this->vuze_rpc('session-get',$args);
		} else {
			//all sessions variables
			$req = $this->vuze_rpc('session-get');
			$this->session = $req->arguments;
		}
		return $req;
	}

	// Set Vuze data (general config)
	public function session_set($key, $value) {
		$args = new stdClass;
		$args->$key = $value;
		$req = $this->vuze_rpc('session-set',$args);
		return $req;
	}
	
	public function session_set_multi($values) {
		$args = new stdClass;
		foreach ($values as $key => $value)
			$args->$key = $value;
		$req = $this->vuze_rpc('session-set',$args);
		return $req;
	}

	// Get Vuze data (all torrents)
	public function torrent_get($ids=array(),$_fields=array()) {

		//dont touch these ones, always required
		$required = array(
			"id",
			"hashString"
		);
		
		if (!empty($_fields)) {
			if (is_array($_fields))
				$fields = array_merge($required,$_fields);
			else
				//string
				$fields[] = $_fields;
		}
		else
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
			"pieceCount",
			"pieceSize",
			"metadataPercentComplete",
			"recheckProgress"
			"uploadRatio"
			"seedRatioLimit"
			"seedRatioMode"
			"downloadDir"
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

			"seeders",
			"leechers",
			"peersConnected",

			"error",
			"errorString",
			"downloadDir",

			"speedLimitDownload",	// 0.2.9 min (xmwebui)
			"speedLimitUpload",		// "
			"trackerSeeds",			// "
			"trackerLeechers",		// "

			"seedRatioMode",
			"seedRatioLimit"
		);

		$args = new stdClass;
		if (!empty($ids))
			$args->ids = $ids;
		$args->fields = $fields;
		$req = $this->vuze_rpc('torrent-get',$args);
		return $req;
	}

	public function torrent_get_hashids($ids=array()) {
		$fields = array(
			"id",
			"hashString"
		);
		$args = new stdClass;
		if (!empty($ids))
			$args->ids = $ids;
		$args->fields = $fields;
		$req = $this->vuze_rpc('torrent-get',$args);
		$hashes = array();
		if ($req && $req->result == 'success') {
			$tids = (array) $req->arguments->torrents;
			foreach ($tids as $k => $obj)
				$hashes[$obj->hashString] = $obj->id;
		}
		return $hashes;
	}

	/*
	 * Add Vuze Torrent (all torrents)
	 * @return error string or object
	*/
	public function torrent_add($filename,$params=array()) {
		$args = new stdClass;
		$args->filename = $filename;
		$args->paused = "false";
		$req = $this->vuze_rpc('torrent-add',$args);

		//O:8:"stdClass":3:{s:3:"tag";s:10:"1290615517";s:6:"result";s:7:"success";s:9:"arguments";O:8:"stdClass":1:{s:13:"torrent-added";O:8:"stdClass":3:{s:2:"id";i:1221;s:4:"name";s:13:"Winamp.v5.581";s:10:"hashString";s:40:"A9BD615FE09B401A770445A4D4FA4254A555577B";}}}
		$member = "torrent-added";
		if ($req->result == "success")
			return $req->arguments->$member;
		else
			return $req->result;
	}
	/*
		JSONArray files_unwanted 	= (JSONArray)args.get( "files-unwanted" );
		JSONArray files_wanted 		= (JSONArray)args.get( "files-wanted" );
		JSONArray priority_high		= (JSONArray)args.get( "priority-high" );
		JSONArray priority_normal	= (JSONArray)args.get( "priority-normal" );
		JSONArray priority_low		= (JSONArray)args.get( "priority-low" );
	*/
	public function torrent_set($ids,$key,$value) {
		$args = new stdClass;
		$args->ids = $ids;
		$args->$key = $value;
		$req = $this->vuze_rpc('torrent-set',$args);
		return $req;
	}

	public function torrent_set_multi($ids,$values) {
		$args = new stdClass;
		$args->ids = $ids;
		foreach ($values as $key => $value)
			$args->$key = $value;
		$req = $this->vuze_rpc('torrent-set',$args);
		return $req;
	}

	public function torrent_stop($ids) {
		$args = new stdClass;
		$args->ids = $ids;
		$req = $this->vuze_rpc('torrent-stop',$args);
		return $req;
	}

	public function torrent_start($ids) {
		$args = new stdClass;
		$args->ids = $ids;
		$req = $this->vuze_rpc('torrent-start',$args);
		return $req;
	}
	
	public function torrent_verify($ids) {
		$args = new stdClass;
		$args->ids = $ids;
		$req = $this->vuze_rpc('torrent-verify',$args);
		return $req;
	}
	
	public function torrent_remove($ids,$bDeleteData=false) {
		$args = new stdClass;
		$args->ids = $ids;

		$member = "delete-local-data";
		$args->$member = ($bDeleteData ? 'true' : 'false');

		$req = $this->vuze_rpc('torrent-remove',$args);
		return $req;
	}

	//##############################################################
	public function http_server() {
		require_once('inc/functions/functions.common.auth.php');
		$host = getHttpServer().getHttpServerRootURL();
		return $host;
		/*
		$host = 'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
		if (isset($_SERVER['HTTPS']))
			$host = str_replace('http:','https:',$host);
		else
			$host = str_replace(':80','',$host);
		return $host.'/';
		*/
	}

	public function torrent_add_tf($transfer,$content) {

		$params = explode("\n",$content);

		$save_path = $params[1]; //ok, by session
		$max_ul_kb = (int)$params[2]; // ok
		//$max_dl_kb = (int)$params[3]; // ok
		$max_uc = (int)$params[4];
		$max_dc = (int)$params[5];
		$diewhenok = (bool) $params[6];
		$sharekill = (int)$params[7]; //ok, by session 4.5.1.1-B31
		$min_port = (int)$params[8];
		$max_port = (int)$params[9];
		$maxcons = (int)$params[10];
		$rerequest = (int)$params[11];

		//local file doesnt work before vuze 4.5.1.1-b30, waiting vuze version info
		if ($this->vuze_ver >= '4.5.1.2')
			$url = "file://".$this->cfg["transfer_file_path"].$transfer;
		else
			$url = $this->http_server()."inc/classes/VuzeRPC.php?getUrl=$transfer";

		//set download directory
		$this->session_set('download-dir',$save_path);

		if ($this->xmwebui_ver <= '0.2.8') {
			// global speed limit, not the right way
			$limits = array(
				'speed-limit-up' => $max_ul * 1024,
				//'speed-limit-down' => $max_dl * 1024,
				'speed-limit-up-enabled' => ($max_ul > 0),
				//'speed-limit-down-enabled' => ($max_dl > 0),
				'encryption' => 'preferred'
			);
			//alt-speed-down
			//alt-speed-up
			//alt-speed-time-day
			//alt-speed-time-begin
			//alt-speed-time-end
			//alt-speed-enabled
			//pex-enabled
			//peer-port
			//blocklist-enabled
			//blocklist-size
			//rpc-version
			//rpc-version-minimum
			//peer-port-random-on-start
			//port-forwarding-enabled
			//dht-enabled
			$this->session_set_multi($limits);
		} else {
			//0.2.9+
			$limits = array(
				'seedRatioLimit' => ((float)$sharekill / 100.0)
			);
			$this->session_set_multi($limits);
		}

		$req = $this->torrent_add($url,$params);
		if (is_object($req)) {
			$id = $req->id;
			$values = array(
				"speedLimitUpload" => $max_ul_kb * 1024,
				//"speedLimitDownload" => $max_dl_kb * 1024,
				'downloadDir' => $save_path,
				'seedRatioLimit' => round((float)$sharekill / 100.0, 2), // need to be set by session but torrent-get value available in 0.2.9+
				'seedRatioMode' => 1
			);
			$req = $this->torrent_set_multi(array($id),$values);
			return $id;
		}

		return $req;
	}

	//for magnets
	public function torrent_add_url($url,$content) {
		$params = explode("\n",$content);
		$save_path  = $params[1]; //ok, by session
		
		//set download directory
		$this->session_set('download-dir',$save_path);
		
		$req = $this->torrent_add($url,$params);
		if (is_object($req))
			return $req->id;

		return false;
	}

	public function torrent_start_tf($hash) {
		$torrents = $this->torrent_get_hashids();
		if ( array_key_exists(strtoupper($hash),$torrents) ) {
			$tid = $torrents[strtoupper($hash)];
			$this->torrent_start(array($tid));
			return true;
		}
		//not found
		return false;
	}

	public function torrent_stop_tf($hash) {
		$torrents = $this->torrent_get_hashids();
		if ( array_key_exists(strtoupper($hash),$torrents) ) {
			$tid = $torrents[strtoupper($hash)];
			$this->torrent_stop(array($tid));
			return true;
		}
		//not found
		return false;
	}

	/*
	 * Vuze RPC Struct to TorrentFlux Names
	 * @param $stat : (1) torrent data object
	 * @return array
	*/
	public function vuze_to_tf($stat) {
		$tfStat = array(
			'running' => $this->vuze_status_to_tf($stat->status),
			'speedDown' => (int) $stat->rateDownload,
			'speedUp' => (int) $stat->rateUpload,
			
			'downTotal' => (float) $stat->downloadedEver,
			'upTotal' => (float) $stat->uploadedEver,
			'percentDone' => 0.0,
			'sharing' => 0.0,
			'eta' => $stat->eta,
			'seeds' => max($stat->trackerSeeds,$stat->leechers),
			'peers' => max($stat->trackerLeechers,$stat->seeders),
			'cons' => $stat->peersConnected,
			
			'size' => (float) $stat->totalSize,
			'status' => $stat->status,

			'rpcid' => $stat->id,
			'name' => $stat->name,
			"error" => $stat->error,
			"errorString" => $stat->errorString,
			'downloadDir' => $stat->downloadDir,
			
			'drate' => $stat->speedLimitDownload,	// xmwebui 0.2.9+
			'urate' => $stat->speedLimitUpload,		// "
			
			'seedRatioLimit' => round($stat->seedRatioLimit, 2), // xmwebui 0.2.9+
			'seedRatioMode' => $stat->seedRatioMode // seems = 1
		);

		if ($tfStat['size'] > 0) {
			$tfStat['percentDone'] = round(100.0 * ($tfStat['downTotal'] / $tfStat['size']) ,2);
			if ($tfStat['percentDone'] > 100)
				$tfStat['percentDone'] = 100;
			$tfStat['sharing'] = round(100.0 * ($tfStat['upTotal'] / $tfStat['size']) ,2);
		}

		return $tfStat;
	}

	public function vuze_status_to_tf($status) {
		// 1 - waiting to verify
		// 2 - verifying
		// 4 - downloading
		// 5 - queued (incomplete)
		// 8 - seeding
		// 9 - queued (complete)
		// 16 - paused
		switch ((int) $status) {
			case 1:
			case 2:
			case 4:
			case 5:
				$tfstatus=1;
				break;
			case 8:
			case 9:
				$tfstatus=1;
				break;
			case 0:
			case 16:
			default:
				$tfstatus=0;
		};
		//0: Stopped
		//1: Running
		//2: New
		//3: Queued (Qmgr)
		return $tfstatus;
	}

	/*
	 * Get all torrents in torrentflux compatible format
	 * @return array
	*/
	public function torrent_get_tf_array($ids=array()) {

		$this->torrents = array();

		$req = $this->torrent_get($ids);
		if ($req && $req->result == 'success') {
			$vuze = (array) $req->arguments->torrents;

			foreach($vuze as $t) {
				$this->torrents[$t->hashString] = $this->vuze_to_tf($t);
			}
		}

		return $this->torrents;

	}

	/*
	 * Filter torrents (torrentflux compatible format)
	 * @return array
	*/
	public function torrent_filter_tf($filter = NULL) {
		if (is_null($filter))
			$filter = $this->filter;
		if (empty($filter))
			return;

		$torrents = array();
		foreach ($this->torrents as $name => $tfstat) {

			$bDrop = false;
			if (isset($filter['running'])) {
				$bDrop = ($tfstat['running'] != $filter['running']);
			}
			if (isset($filter['status'])) {
				$bDrop = ($tfstat['status'] != $filter['status']);
			}
			if (isset($filter['speedUp'])) {
				$bDrop = ($tfstat['speedUp'] == 0);
			}
			if (isset($filter['speedDown'])) {
				$bDrop = ($tfstat['speedDown'] == 0);
			}
			if (isset($filter['speed'])) {
				$bDrop = ($tfstat['speedUp'] == 0 && $tfstat['speedDown'] == 0);
			}

			if (!$bDrop) {
				$torrents[$name] = $tfstat;
			}
		}

		return $torrents;
	}

	/*
	 * Get all torrents in torrentflux compatible format (shell)
	 * @return array
	*/
	public function torrent_get_tf($ids=array()) {
		return $this->torrent_get_tf_array($ids);
	}

	/*
	 * Get all torrents in torrentflux compatible format (json)
	 * @return none
	*/
	public function torrent_get_tf_json() {

		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		header('Content-type: application/json');

		$request = new stdClass;
		$request->datetime = date('Y-M-d H:i:s');
		$request->ts = date('U');
		$request->status = '';

		$session = $this->session_get("version");
		if ($session && $session->result == 'success') {

			$request->torrents = $this->torrent_get_tf_array();
			$request->status = 'OK';

		}

		echo json_encode($request);

	}

	//STATIC HELPERS, usefull to reuse the last instance created
	public function getInstance() {
		global $VuzeRPC_instance;
		if (!is_object($VuzeRPC_instance)) {
			global $cfg;
			$VuzeRPC_instance = new VuzeRPC($cfg);
		}
		return $VuzeRPC_instance;
	}

	//VuzeRPC::isRunning()
	public function isRunning() {
		$instance = VuzeRPC::getInstance();
		$req = $instance->session_get("version");
		return  (is_object($req) && $req->result == 'success');
	}

	//VuzeRPC::transferExists($transfer)
	public function transferExists($hash) {
		$instance = VuzeRPC::getInstance();
		$torrents = $instance->torrent_get_hashids();
		return array_key_exists(strtoupper($hash),$torrents);
	}

	public function delTransfer($hash) {
		$instance = VuzeRPC::getInstance();

		$torrents = $instance->torrent_get_hashids();
		if ( array_key_exists(strtoupper($hash),$torrents) ) {
			$tid = $torrents[strtoupper($hash)];
			$instance->torrent_remove(array($tid),false);
			return true;
		}
		//not found
		return false;
	}

	public function getMessages() {
		$instance = VuzeRPC::getInstance();

		return array($instance->lastError);
	}

} //end of VuzeRPC class


//--------------------------------------------------------
//Test config

//commented to keep default
//$rpc_cfg['vuze_rpc_host']='mytesthost.com';
//$rpc_cfg['vuze_rpc_port']='19091';
//$rpc_cfg['vuze_rpc_user']='vuze';
//$rpc_cfg['vuze_rpc_password']='blabla';

//$v = new VuzeRPC($rpc_cfg);
//$v->torrent_get_tf_json();

?>
