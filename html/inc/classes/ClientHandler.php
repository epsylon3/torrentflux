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

// states
define('CLIENTHANDLER_STATE_NULL', 0);                                   // null
define('CLIENTHANDLER_STATE_READY', 1);                                 // ready
define('CLIENTHANDLER_STATE_OK', 2);                                  // started
define('CLIENTHANDLER_STATE_ERROR', -1);                                // error

/**
 * base class ClientHandler
 */
class ClientHandler
{
	// public fields

	// client-specific fields
	var $type = "";
	var $client = "";
	var $binSystem = ""; // the system-binary of this client.
	var $binSocket = ""; // the binary this client uses for socket-connections.
	var $binClient = ""; // the binary of this client. (eg. python-script))

	var $useRPC = false; // no client per transfer, use rpc calls

	// generic vars for a transfer-start
	var $rate = "";
	var $drate = "";
	var $superseeder = "";
	var $runtime = "";
	var $maxuploads = "";
	var $minport = "";
	var $maxport = "";
	var $port = "";
	var $maxcons = "";
	var $rerequest = "";
	var $sharekill = "";
	var $sharekill_param = "";
	
	var $skip_hash_check = true;
	var $file_priority = false;
	var $encryption = "";

	// queue
	var $queue = false;

	// transfer
	var $transfer = "";
	var $transferFilePath = "";

	// running
	var $running = 0;

	// hash
	var $hash = "";

	// datapath
	var $datapath = "";

	// savepath
	var $savepath = "";

	// pid
	var $pid = "";

	// owner
	var $owner = "";

	// command (startup)
	var $command = "";

	// umask
	var $umask = "";

	// nice
	var $nice = "";

	// call-result
	var $callResult;

	// messages-array
	var $messages = array();

	// handler-state
	var $state = CLIENTHANDLER_STATE_NULL;
	
	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * get ClientHandler-instance
	 *
	 * @param $client client-type
	 * @return ClientHandler
	 */
	function getInstance($client = "") {
		
		// create and return object-instance
		switch ($client) {
		case "tornado":
			require_once('inc/classes/ClientHandler.tornado.php');
			$ch = new ClientHandlerTornado();
			break;
		case "transmission":
			//patched transmissioncli
			require_once('inc/classes/ClientHandler.transmission.php');
			$ch = new ClientHandlerTransmission();
			break;
		case "transmissionrpc":
			require_once('inc/classes/ClientHandler.transmissionrpc.php');
			$ch = new ClientHandlerTransmissionRPC();
			break;
		case "azureus":
			global $cfg;
			if ($cfg['vuze_rpc_enable']) {
				//vuze rpc torrents are compatible with azureus (both in vuze)
				//so we can ask vuze for old fluazu transfers stats
				require_once('inc/classes/ClientHandler.vuzerpc.php');
				$ch = new ClientHandlerVuzeRPC();
			} else {
				//fluazu/dopal/xml_http (slow)
				require_once('inc/classes/ClientHandler.azureus.php');
				$ch = new ClientHandlerAzureus();
			}
			break;
		case "vuzerpc":
			//xmwebui (json) (fast)
			require_once('inc/classes/ClientHandler.vuzerpc.php');
			$ch = new ClientHandlerVuzeRPC();
			break;
		case "wget":
			require_once('inc/classes/ClientHandler.wget.php');
			$ch = new ClientHandlerWget();
			break;
		case "nzbperl":
			require_once('inc/classes/ClientHandler.nzbperl.php');
			$ch = new ClientHandlerNzbperl();
			break;
		case "mainline":
			require_once('inc/classes/ClientHandler.mainline.php');
			$ch = new ClientHandlerMainline();
			break;
		default:
			global $cfg;
			$ch = ClientHandler::getInstance($cfg["btclient"]);
		}
		return $ch;
		
	}

	// =========================================================================
	// ctor
	// =========================================================================

	/**
	 * ctor
	 */
	function ClientHandler() {
		die('base class -- don\'t do this');
	}

	// =========================================================================
	// public methods (abstract)
	// =========================================================================

	/**
	 * starts a client
	 * @param $transfer name of the transfer
	 * @param $interactive (boolean) : is this a interactive startup with dialog ?
	 * @param $enqueue (boolean) : enqueue ?
	 */
	function start($transfer, $interactive = false, $enqueue = false) { return; }

	/**
	 * deletes cache of a transfer
	 *
	 * @param $transfer
	 */
	function deleteCache($transfer) { return; }

	/**
	 * set upload rate of a transfer
	 *
	 * @param $transfer
	 * @param $uprate
	 * @param $autosend
	 */
	function setRateUpload($transfer, $uprate, $autosend = false) { return; }

	/**
	 * set download rate of a transfer
	 *
	 * @param $transfer
	 * @param $downrate
	 * @param $autosend
	 */
	function setRateDownload($transfer, $downrate, $autosend = false) { return; }

	/**
	 * set completion runtime of a transfer
	 *
	 * @param $transfer
	 * @param $runtime bool
	 * @param $autosend
	 * @return boolean
	 */
	function setRuntime($transfer, $runtime, $autosend = false) { return true; }

	/**
	 * set sharekill of a transfer
	 *
	 * @param $transfer
	 * @param $sharekill int
	 * @param $autosend
	 * @return boolean
	 */
	function setSharekill($transfer, $sharekill, $autosend = false) { return true; }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * isWinOS - Check if OS is Windows or Not
	 */
	function isWinOS() {
		return (strncmp(PHP_OS,'WIN',3) === 0);
	}

	/**
	 * sets settings-fields
	 */
	function settingsInit() {
		$this->_settingsInit();
	}

	/**
	 * sets fields from default-vals
	 *
	 * @param $transfer
	 */
	function settingsDefault($transfer = "") {
		global $cfg;
		// transfer vars
		if (empty($transfer) && !empty($this->transfer)) {
			$transfer = $this->transfer;
		}
		if (!empty($transfer)) {
			$this->_setVarsForTransfer($transfer);
			// common vars
			$this->hash        = getTransferHash($this->transfer);
			$this->datapath    = getTransferDatapath($this->transfer);
			$this->savepath    = getTransferSavepath($this->transfer, ""); // default profile
		}
		elseif ($cfg['debuglevel'] > 0) {
			AuditAction($cfg["constants"]["debug"], $this->client." settingsDefault with empty transfer");
		}
		$this->running     = 0;
		$this->rate        = $cfg["max_upload_rate"];
		$this->drate       = $cfg["max_download_rate"];
		$this->maxuploads  = $cfg["max_uploads"];
		$this->superseeder = $cfg["superseeder"];
		$this->runtime     = $cfg["die_when_done"];
		$this->sharekill   = $cfg["sharekill"];
		$this->minport     = $cfg["minport"];
		$this->maxport     = $cfg["maxport"];
		$this->maxcons     = $cfg["maxcons"];
		$this->rerequest   = $cfg["rerequest_interval"];
		$this->skip_hash_check = true;
		$this->encryption      = false;
		$this->file_priority   = false;
	}

	/**
	 * load settings
	 *
	 * @param $transfer
	 * @return boolean
	 */
	function settingsLoad($transfer = "") {
		global $db;
		// transfer vars
		$this->_setVarsForTransfer($transfer);
		// common vars
		$sql = "SELECT * FROM tf_transfers WHERE transfer = ".$db->qstr($this->transfer);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		if ($row = $result->FetchRow()) {
			$this->hash        = $row["hash"];
			$this->datapath    = $row["datapath"];
			$this->savepath    = $row["savepath"];
			$this->running     = $row["running"];
			$this->rate        = $row["rate"];
			$this->drate       = $row["drate"];
			$this->maxuploads  = $row["maxuploads"];
			$this->superseeder = $row["superseeder"];
			$this->runtime     = $row["runtime"];
			$this->sharekill   = $row["sharekill"];
			$this->minport     = $row["minport"];
			$this->maxport     = $row["maxport"];
			$this->maxcons     = $row["maxcons"];
			$this->rerequest   = $row["rerequest"];
			// loaded
			return true;
		} else {
			// not loaded
			return false;
		}
	}

	/**
	 * save settings
	 */
	function settingsSave() {
		global $db;
		// Messy - a not exists would prob work better
		deleteTransferSettings($this->transfer);
		// insert
		$sql = "INSERT INTO tf_transfers "
			."("
			."transfer,"
			."type,"
			."client,"
			."hash,"
			."datapath,"
			."savepath,"
			."running,"
			."rate,"
			."drate,"
			."maxuploads,"
			."superseeder,"
			."runtime,"
			."sharekill,"
			."minport,"
			."maxport,"
			."maxcons,"
			."rerequest"
			.") VALUES ("
			. $db->qstr($this->transfer).","
			. $db->qstr($this->type).","
			. $db->qstr($this->client).","
			. $db->qstr($this->hash).","
			. $db->qstr($this->datapath).","
			. $db->qstr($this->savepath).","
			. $db->qstr($this->running).","
			. $db->qstr($this->rate).","
			. $db->qstr($this->drate).","
			. $db->qstr($this->maxuploads).","
			. $db->qstr($this->superseeder).","
			. $db->qstr($this->runtime).","
			. $db->qstr($this->sharekill).","
			. $db->qstr($this->minport).","
			. $db->qstr($this->maxport).","
			. $db->qstr($this->maxcons).","
			. $db->qstr($this->rerequest)
			.")";
		$db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		// set transfers-cache
		cacheTransfersSet();
		return true;
	}

	/**
	 * stops a client
	 *
	 * @param $transfer name of the transfer
	 * @param $kill kill-param (optional)
	 * @param $transferPid transfer Pid (optional)
	 */
	function stop($transfer, $kill = false, $transferPid = 0) {
		// set vars
		$this->_setVarsForTransfer($transfer);
		// stop the client
		$this->_stop($kill, $transferPid);
	}

	/**
	 * deletes a transfer
	 *
	 * @param $transfer name of the transfer
	 * @return boolean of success
	 */
	function delete($transfer) {
		// set vars
		$this->_setVarsForTransfer($transfer);
		// delete
		return $this->_delete();
	}

	/**
	 * gets current transfer-vals of a transfer
	 *
	 * @param $transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferCurrent($transfer) {
		global $transfers;
		// transfer from stat-file
		$sf = new StatFile($transfer);
		return array("uptotal" => $sf->uptotal, "downtotal" => $sf->downtotal);
	}

	/**
	 * gets current transfer-vals of a transfer. optimized version
	 *
	 * @param $transfer
	 * @param $tid of the transfer
	 * @param $sfu stat-file-uptotal of the transfer
	 * @param $sfd stat-file-downtotal of the transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferCurrentOP($transfer, $tid, $sfu, $sfd) {
		return array("uptotal" => $sfu, "downtotal" => $sfd);
	}

	/**
	 * gets total transfer-vals of a transfer
	 *
	 * @param $transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferTotal($transfer) {
		global $db, $transfers;
		
		// set vars
		$this->_setVarsForTransfer($transfer);
		
		$retVal = array();
		// transfer from db
		$uid = (int) GetUID($this->owner);
		$sql = "SELECT uptotal,downtotal FROM tf_transfer_totals WHERE tid = ".$db->qstr($transfer)." AND uid IN(0, $uid)";
		$result = $db->Execute($sql);
		$row = $result->FetchRow();
		if (empty($row)) {
			$retVal["uptotal"] = 0;
			$retVal["downtotal"] = 0;
		} else {
			$retVal["uptotal"] = $row["uptotal"];
			$retVal["downtotal"] = $row["downtotal"];
		}
		// transfer from stat-file
		$sf = new StatFile($transfer);
		$retVal["uptotal"] += $sf->uptotal;
		$retVal["downtotal"] += $sf->downtotal;
		return $retVal;
	}

	/**
	 * gets total transfer-vals of a transfer. optimized version
	 *
	 * @param $transfer
	 * @param $tid of the transfer
	 * @param $sfu stat-file-uptotal of the transfer
	 * @param $sfd stat-file-downtotal of the transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferTotalOP($transfer, $tid, $sfu, $sfd) {
		global $transfers;
		$retVal = array();
		$retVal["uptotal"] = (isset($transfers['totals'][$tid]['uptotal']))
			? $transfers['totals'][$tid]['uptotal'] + $sfu
			: $sfu;
		$retVal["downtotal"] = (isset($transfers['totals'][$tid]['downtotal']))
			? $transfers['totals'][$tid]['downtotal'] + $sfd
			: $sfd;
		return $retVal;
	}

	/**
	 * gets ary of running clients (via call to ps)
	 *
	 * @return array
	 */
	function runningProcesses() {
		global $cfg;
		$retAry = array();
		
		//no clients process to kill in RPC mode
		if ($this->useRPC)
			return array();
		
		$screenStatus = shell_exec("ps x a -o pid -o ppid -o command | ".$cfg['bin_grep']." ".tfb_shellencode($this->binClient)." | ".$cfg['bin_grep']." ".tfb_shellencode($cfg["transfer_file_path"])." | ".$cfg['bin_grep']." -v grep");
		$arScreen = array();
		$tok = strtok($screenStatus, "\n");
		while ($tok) {
			array_push($arScreen, $tok);
			$tok = strtok("\n");
		}
		$arySize = sizeof($arScreen);
		for ($i = 0; $i < $arySize; $i++) {
			if(strpos($arScreen[$i], $this->binClient) !== false) {
				$pinfo = new ProcessInfo($arScreen[$i]);
				if (intval($pinfo->ppid) == 1) {
					if (!strpos($pinfo->cmdline, "rep ". $this->binSystem) > 0) {
						if (!strpos($pinfo->cmdline, "ps x") > 0) {
							array_push($retAry, array(
								'client' => $this->client,
								'pinfo' => $pinfo->pid." ".$pinfo->cmdline
								)
							);
						}
					}
				}
			}
		}
		return $retAry;
	}

	/**
	 * get info of running clients (via call to ps)
	 *
	 * @return string
	 */
	function runningProcessInfo() {
		if ($this->useRPC)
			return $this->runningDaemonInfo();

		global $cfg;

		// ps-string
		$screenStatus = shell_exec("ps x a -o pid -o ppid -o command | ".$cfg['bin_grep']." ".tfb_shellencode($this->binClient)." | ".$cfg['bin_grep']." ".tfb_shellencode($cfg["transfer_file_path"])." | ".$cfg['bin_grep']." -v grep");
		$arScreen = array();
		$tok = strtok($screenStatus, "\n");
		while ($tok) {
			array_push($arScreen, $tok);
			$tok = strtok("\n");
		}
		$cProcess = array();
		$cpProcess = array();
		$pProcess = array();
		$ProcessCmd = array();
		for ($i = 0; $i < sizeof($arScreen); $i++) {
			if (strpos($arScreen[$i], $this->binClient) !== false) {
				$pinfo = new ProcessInfo($arScreen[$i]);
				if (intval($pinfo->ppid) == 1) {
					if (strpos($pinfo->cmdline, "rep ". $this->binSystem) === false) {
						if (strpos($pinfo->cmdline, "ps x") === false) {
							array_push($pProcess,$pinfo->pid);
							$rt = RunningTransfer::getInstance($pinfo->pid." ".$pinfo->cmdline, $this->client);
							array_push($ProcessCmd, $rt->transferowner."\t".$rt->transferFile);
						}
					}
				} else {
					if (strpos($pinfo->cmdline, "rep ". $this->binSystem) === false) {
						if (strpos($pinfo->cmdline, "ps x") === false) {
							array_push($cProcess, $pinfo->pid);
							array_push($cpProcess, $pinfo->ppid);
						}
					}
				}
			}
		}
		$retVal  = " --- Running Processes ---\n";
		$retVal .= " Parents  : " . count($pProcess) . "\n";
		$retVal .= " Children : " . count($cProcess) . "\n";
		$retVal .= "\n";
		$retVal .= " PID \tOwner\tCommand\n";
		foreach ($pProcess as $key => $value)
			$retVal .= " " . $value . "\t" . $ProcessCmd[$key] . "\n";
		return $retVal;
	}

	function runningDaemonInfo() {
		global $cfg;
		// ps-string
		$screenStatus = shell_exec("ps x a -o pid -o %cpu -o command | ".$cfg['bin_grep']." ".tfb_shellencode($this->binClient)." | ".$cfg['bin_grep']." -v grep");
		$arScreen = array();
		$tok = strtok($screenStatus, "\n");
		while ($tok) {
			array_push($arScreen, $tok);
			$tok = strtok("\n");
		}
		$retVal  = " --- Running Processes ---\n";
		$retVal .= " Daemon  : " . count($screenStatus) . "\n";
		$retVal .= "\n";
		$retVal .= " PID  %CPU Command\n";
		$retVal .= $screenStatus . "\n";
		return $retVal;
	}

	/**
	 * writes a message to the per-transfer-logfile
	 *
	 * @param $message
	 * @param $withTS
	 */
	function logMessage($message, $withTS = true) {
		global $cfg;
		// return if transfer-file-field not set
		if ($this->transferFilePath == "") {
			AuditAction($cfg["constants"]["debug"], "(no log file set) : $message");
			return false;
		}
		// log
		if ($handle = @fopen($this->transferFilePath.".log", "a+")) {
			if ($withTS) {
				$dateformat = "[".$cfg['_DATETIMEFORMAT']."]";
				$content = date($dateformat)." ".$message;
			} else
				$content = $message;
			$resultSuccess = (@fwrite($handle, $content) !== false);
			@fclose($handle);
			if (!$resultSuccess) {
				AuditAction($cfg["constants"]["debug"], "(log not writable) : $content");
			}
			return $resultSuccess;
		}
		return false;
	}

	// =========================================================================
	// protected methods
	// =========================================================================

	/**
	 * sets all fields depending on "transfer"-value
	 *
	 * @param $transfer
	 */
	function _setVarsForTransfer($transfer) {
		global $cfg;
		if (empty($transfer))
			AuditAction($cfg["constants"]["error"], "_setVarsForTransfer empty $transfer");
		else {
			$this->transfer = $transfer;
			$this->transferFilePath = $cfg["transfer_file_path"].$transfer;
			$this->owner = getOwner($transfer);
		}
	}

	/**
	 * sets common settings-fields
	 */
	function _settingsInit() {
		global $cfg;
		// customize settings
		if ($cfg['transfer_customize_settings'] == 2)
			$customize_settings = 1;
		elseif ($cfg['transfer_customize_settings'] == 1 && $cfg['isAdmin'])
			$customize_settings = 1;
		else
			$customize_settings = 0;
		// init default-settings
		$this->settingsDefault();
		// only read request-vars if enabled
		if ($customize_settings == 1) {
			$this->rate = tfb_getRequestVar('max_upload_rate', $this->rate);
			$this->drate = tfb_getRequestVar('max_download_rate', $this->drate);
			$this->maxuploads = tfb_getRequestVar('max_uploads', $this->maxuploads);
			$this->superseeder = tfb_getRequestVar('superseeder', $this->superseeder);
			$this->runtime = tfb_getRequestVar('die_when_done', $this->runtime);
			$this->sharekill = tfb_getRequestVar('sharekill', $this->sharekill);
			$this->minport = tfb_getRequestVar('minport', $this->minport);
			$this->maxport = tfb_getRequestVar('maxport', $this->maxport);
			$this->maxcons = tfb_getRequestVar('maxcons', $this->maxcons);
			$this->rerequest = tfb_getRequestVar('rerequest', $this->rerequest);
		}
		// savepath
		if ($cfg["showdirtree"] == 1) {
			$this->savepath = tfb_getRequestVar('savepath', $this->savepath);
		}
		
		//todo...
		$this->skip_hash_check = tfb_getRequestVar('skiphashcheck', true);
		$this->file_priority = tfb_getRequestVar('file_priority', $this->file_priority);
		$this->encryption = tfb_getRequestVar('skiphashcheck', $this->encryption);
	}

	/**
	 * init start of a client.
	 *
	 * @param $interactive
	 * @param $enqueue
	 * @param $setPort
	 * @param $recalcSharekill
	 */
	function _init($interactive, $enqueue = false, $setPort = false, $recalcSharekill = false) {
		global $cfg;
		if ($cfg['debuglevel'] > 0) {
			AuditAction($cfg["constants"]["debug"], "_start $this->transfer");
		}
		// request-vars / defaults / database
		if ($interactive) { // interactive, get vars from request vars
			$this->settingsInit();
		} else { // non-interactive, load settings from db
			$this->skip_hash_check = $cfg["skiphashcheck"];
			// load settings, default if settings could not be loaded (fresh transfer)
			if ($this->settingsLoad() !== true)
				$this->settingsDefault();
		}
		// queue
		if ($enqueue) {
			$this->queue = ($cfg['isAdmin'])
				? $enqueue
				: true;
		} else {
			$this->queue = false;
		}
		// savepath-check
		if (empty($this->savepath))
			$this->savepath = ($cfg["enable_home_dirs"] != 0)
				? $cfg['path'].$this->owner."/"
				: $cfg['path'].$cfg["path_incoming"]."/";
		else
			$this->savepath = checkDirPathString($this->savepath);

		// check target-directory, create if not present
		if (!(checkDirectory($this->savepath, 0777))) {
			$this->state = CLIENTHANDLER_STATE_ERROR;
			$msg = "Error checking savepath ".$this->savepath;
			array_push($this->messages, $msg);
			AuditAction($cfg["constants"]["error"], $msg);
			$this->logMessage($msg."\n", true);
			// write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error';
			$sf->write();
			return false;
		}
		// umask
		$this->umask = ($cfg["enable_umask"] != 0)
			? " umask 0000;"
			: "";
		// nice
		$this->nice = ($cfg["nice_adjust"] != 0)
			? "nice -n ".$cfg["nice_adjust"]." "
			: "";
		// set param for sharekill
		$this->sharekill = intval($this->sharekill);
		// recalc sharekill
		if ($recalcSharekill) {
			if ($this->_recalcSharekill() === false)
				return false;
		} else {
			$this->sharekill_param = $this->sharekill;
			$this->logMessage("setting sharekill-param to ".$this->sharekill_param."\n", true);
		}
		// set port if start (only if not queue)
		if (($setPort) && (!$this->queue)) {
			if ($this->_setClientPort() === false)
				return false;
		}
		// get current transfer
		$transferTotals = $this->getTransferCurrent($this->transfer);
		//XFER: before a transfer start/restart save upload/download xfer to SQL
		if ($cfg['enable_xfer'] == 1)
			Xfer::save($this->owner,($transferTotals["downtotal"]),($transferTotals["uptotal"]));
		// update totals for this transfer
		$this->_updateTotals();
		// set state
		$this->state = CLIENTHANDLER_STATE_READY;
	}

	/**
	 * start a client.
	 */
	function _start() {
		global $cfg;
		if ($this->state != CLIENTHANDLER_STATE_READY) {
			$this->state = CLIENTHANDLER_STATE_ERROR;
			array_push($this->messages , "Error. ClientHandler in wrong state on start-request.");
			// write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error';
			$sf->write();
			// return
			return;
		}
		// Save transfer settings
		$this->settingsSave();
		// flush session-cache (trigger transfers-cache-set on next page-load)
		cacheFlush($cfg['user']);
		// write the session to close so older version of PHP will not hang
		@session_write_close();
		if ($this->useRPC) {
			file_put_contents($this->transferFilePath.".pid","rpc");
		}
		// sf
		$sf = new StatFile($this->transfer, $this->owner);
		// queue or start ?
		if ($this->queue) { // queue
			if (FluxdQmgr::isRunning()) {
				// write stat-file
				$sf->queue();
				// send command
				FluxdQmgr::enqueueTransfer($this->transfer, $cfg['user']);
				// log
				AuditAction($cfg["constants"]["queued_transfer"], $this->transfer);
				$this->logMessage("transfer enqueued : ".$this->transfer."\n", true);
			} else {
				$msg = "queue-request (".$this->transfer."/".$cfg['user'].") but Qmgr not active";
				array_push($this->messages , $msg);
				AuditAction($cfg["constants"]["error"], $msg);
				$this->logMessage($msg."\n", true);
			}
			// set flag
			$this->running = 0;
		} else { // start
			// write stat-file
			if (intval($sf->downtotal) == 0)
			$sf->start();
			// log the command
			$this->logMessage("executing command : \n".$this->command."\n", true);
			// startup
			$this->callResult = exec($this->command);
			AuditAction($cfg["constants"]["start_torrent"], $this->transfer);
			// set flag
			$this->running = 1;
			// wait until transfer is up
			waitForTransfer($this->transfer, true, 20);
		}
		if (empty($this->messages)) {
			// set state
			$this->state = CLIENTHANDLER_STATE_OK;
		} else {
			// error
			$this->state = CLIENTHANDLER_STATE_ERROR;
			$msg = "error starting client. messages :\n";
			$msg .= implode("\n", $this->messages);
			$this->logMessage($msg."\n", true);
			// write error to stat
			$sf->time_left = 'Error';
			$sf->write();
		}
	}

	/**
	 * stop a client
	 *
	 * @param $kill kill-param (optional)
	 * @param $transferPid transfer Pid (optional)
	 */
	function _stop($kill = false, $transferPid = 0) {
		global $cfg;
		// log
		AuditAction($cfg["constants"]["stop_transfer"], $this->transfer);
		
		if ($this->useRPC) {
			unlink($this->transferFilePath.".pid");
			stopTransferSettings($this->transfer);
			return;
		}
		
		// send quit-command to client
		CommandHandler::add($this->transfer, "q");
		CommandHandler::send($this->transfer);

		// wait until transfer is down
		waitForTransfer($this->transfer, false, 20);

		// see if the transfer process is hung.
		$running = $this->runningProcesses();
		$isHung = false;
		foreach ($running as $rng) {
			$rt = RunningTransfer::getInstance($rng['pinfo'], $this->client);
			if ($rt->transferFile == $this->transfer) {
				$isHung = true;
				AuditAction($cfg["constants"]["error"], "Possible Hung Process for ".$rt->transferFile." (".$rt->processId.")");
				//$kill = true;
				break;
			}
		}
		if (!$isHung) {
			// flag the transfer as stopped (in db)
			stopTransferSettings($this->transfer);
		}
		// kill-request
		if ($kill && $isHung) {
			AuditAction($cfg["constants"]["kill_transfer"], $this->transfer);
			// set pid
			if (!empty($transferPid)) {
				// test for valid pid-var
				if (preg_match('/^[0-9]+$/D', $transferPid)) {
					$this->pid = $transferPid;
				} else {
					$this->state = CLIENTHANDLER_STATE_ERROR;
						AuditAction($cfg["constants"]["error"], "INVALID PID: ".$transferPid);
						array_push($this->messages, "INVALID PID: ".$transferPid);
						return false;
				}
			} else {
				$this->pid = getTransferPid($this->transfer);;
			}
			// kill it
			require_once('inc/defines/defines.signals.php');
			if ($this->pid > 0)
				$this->callResult = posix_kill($this->pid, SIGKILL);
			// try to remove the pid file
			@unlink($this->transferFilePath.".pid");
		}
	}

	/**
	 * deletes a transfer
	 *
	 * @return boolean
	 */
	function _delete() {
		global $cfg;
		// delete
		if (($cfg["user"] == $this->owner) || $cfg['isAdmin']) {
			// XFER: before deletion save upload/download xfer data to SQL
			if ($cfg['enable_xfer'] == 1) {
				$transferTotals = $this->getTransferCurrent($this->transfer);
				Xfer::save($this->owner, $transferTotals["downtotal"], $transferTotals["uptotal"]);
			}
			// update totals
			$this->_updateTotals();
			// remove settings from db
			deleteTransferSettings($this->transfer);
			// client-cache
			$this->deleteCache($this->transfer);
			// command-clean
			CommandHandler::clean($this->transfer);
			// remove meta-file
			if (@file_exists($this->transferFilePath))
				@unlink($this->transferFilePath);
			// remove stat-file
			if (@file_exists($this->transferFilePath.".stat"))
				@unlink($this->transferFilePath.".stat");
			// if exist remove pid file
			if (@file_exists($this->transferFilePath.".pid"))
				@unlink($this->transferFilePath.".pid");
			// if exist remove log-file
			if (@file_exists($this->transferFilePath.".log"))
				@unlink($this->transferFilePath.".log");
			// if exist remove prio-file
			if (@file_exists($this->transferFilePath.".prio"))
				@unlink($this->transferFilePath.".prio");
			AuditAction($cfg["constants"]["delete_transfer"], $this->transfer);
			return true;
		} else {
			AuditAction($cfg["constants"]["error"], "ILLEGAL DELETE: ".$this->transfer);
			return false;
		}
	}

	// =========================================================================
	// private methods
	// =========================================================================

	/**
	 * updates totals of a transfer
	 */
	function _updateTotals() {
		global $db;
		
		$tid = getTransferHash($this->transfer);
		$transferTotals = $this->getTransferTotal($this->transfer);
		$uid = (int) GetUID($this->owner);
		$sql = ($db->GetOne("SELECT 1 FROM tf_transfer_totals WHERE tid = ".$db->qstr($tid)." AND uid = $uid"))
			? "UPDATE tf_transfer_totals SET uptotal = ".$db->qstr($transferTotals["uptotal"]).", downtotal = ".$db->qstr($transferTotals["downtotal"])." WHERE tid = ".$db->qstr($tid)." AND uid = $uid"
			: "INSERT INTO tf_transfer_totals (tid,uid,uptotal,downtotal) VALUES (".$db->qstr($tid).",".$db->qstr($uid).",".$db->qstr($transferTotals["uptotal"]).",".$db->qstr($transferTotals["downtotal"]).")";
		$db->Execute($sql);
		// set transfers-cache
		cacheTransfersSet();
	}

	/**
	 * recalc sharekill
	 *
	 * @return boolean
	 */
	function _recalcSharekill() {
		global $cfg;
		$this->logMessage("recalc sharekill for ".$this->transfer."\n", true);
		if ($this->sharekill == 0) { // nice, we seed forever
			$this->sharekill_param = 0;
			$this->logMessage("seed forever\n", true);
			// return
			return true;
		} elseif ($this->sharekill > 0) { // recalc sharekill
			/* get size */
			// try stat-file first
			$sf = new StatFile($this->transfer, $this->owner);
			$transferSize = (empty($sf->size)) ? 0 : floatval($sf->size);
			// try to reget if stat was empty
			if ($transferSize <= 0)
				$transferSize = floatval(getTransferSize($this->transfer));
			// if still no size just pass thru the param
			if ($transferSize <= 0) {
				// message
				$msg = "data-size = '".$transferSize."' when recalcing share-kill for ".$this->transfer.", setting sharekill-param to ".$this->sharekill_param;
				array_push($this->messages , $msg);
				// debug-log
				if ($cfg['debuglevel'] > 0)
					AuditAction($cfg["constants"]["debug"], $msg);
				// log
				$this->logMessage($msg."\n", true);
				// set sharekill param
				$this->sharekill_param = $this->sharekill;
				// return
				return true;
			}
			/* get vars */
			// get totals
			$totalAry = $this->getTransferTotal($this->transfer);
			$upTotal = floatval($totalAry["uptotal"]);
			$downTotal = floatval($totalAry["downtotal"]);
			// check totals
			if (($upTotal < 0) || ($downTotal < 0)) {
				// message
				$msg = "problems getting totals (upTotal: ".$upTotal."; downTotal: ".$downTotal.") when recalcing share-kill for ".$this->transfer.", setting sharekill-param to ".$this->sharekill_param;
				array_push($this->messages , $msg);
				// debug-log
				if ($cfg['debuglevel'] > 0)
					AuditAction($cfg["constants"]["debug"], $msg);
				// log
				$this->logMessage($msg."\n", true);
				// set sharekill param
				$this->sharekill_param = $this->sharekill;
				// return
				return true;
			}
			// wanted
			$upWanted = ($this->sharekill / 100) * $transferSize;
			// check wanted
			if ($upWanted <= 0) {
				// message
				$msg = "problems calculating wanted upload (upWanted: ".$upWanted.") when recalcing share-kill for ".$this->transfer.", setting sharekill-param to ".$this->sharekill_param;
				array_push($this->messages , $msg);
				// debug-log
				if ($cfg['debuglevel'] > 0)
					AuditAction($cfg["constants"]["debug"], $msg);
				// log
				$this->logMessage($msg."\n", true);
				// set sharekill param
				$this->sharekill_param = $this->sharekill;
				// return
				return true;
			}
			// share percentage
			$sharePercentage = ($upTotal / $transferSize) * 100;
			// check percentage
			if (($sharePercentage < 0) || ($sharePercentage >= 2147483647)) {
				// message
				$msg = "problems calculating share percentage (sharePercentage: ".$sharePercentage.") when recalcing share-kill for ".$this->transfer.", setting sharekill-param to ".$this->sharekill_param;
				array_push($this->messages , $msg);
				// debug-log
				if ($cfg['debuglevel'] > 0)
					AuditAction($cfg["constants"]["debug"], $msg);
				// log
				$this->logMessage($msg."\n", true);
				// set sharekill param
				$this->sharekill_param = $this->sharekill;
				// return
				return true;
			}
			/* check */
			if (($upTotal >= $upWanted) && ($downTotal >= $transferSize)) {
				// just pass thru param if runtime is true
				if ($this->runtime == "True") {
					$this->sharekill_param = $this->sharekill;
					$this->logMessage("setting sharekill-param to ".$this->sharekill_param."\n", true);
					// return
					return true;
				}
				// we already have seeded at least wanted percentage. skip start of client
				// set state
				$this->state = CLIENTHANDLER_STATE_NULL;
				// message
				$msg = "skip ".$this->transfer." due to share-ratio (has: ".@number_format($sharePercentage, 2)."; set:".$this->sharekill."; upTotal: ".$upTotal."; upWanted: ".$upWanted.")";
				array_push($this->messages , $msg);
				// debug-log
				if ($cfg['debuglevel'] > 0)
					AuditAction($cfg["constants"]["debug"], $msg);
				// log
				$this->logMessage($msg."\n", true);
				// write Skipped to stat
				$sf = new StatFile($this->transfer, $this->owner);
				$sf->time_left = 'Skipped';
				$sf->write();
				// return
				return false;
			} else {
				// not done seeding wanted percentage
				$this->sharekill_param = intval(ceil($this->sharekill - $sharePercentage));
				// sanity-check.
				if ($this->sharekill_param < 1)
					$this->sharekill_param = 1;
				$this->logMessage("recalcing sharekill. wanted: ".$this->sharekill."; done: ".$sharePercentage."\n", true);
				$this->logMessage("setting sharekill-param to ".$this->sharekill_param."\n", true);
				// return
				return true;
			}
		} else {
			$this->sharekill_param = $this->sharekill;
			$this->logMessage("setting sharekill-param to ".$this->sharekill_param."\n", true);
			// return
			return true;
		}
		// return
		return true;
	}

	/**
	 * gets available port and sets port field
	 *
	 * @return boolean
	 */
	function _setClientPort() {
		global $cfg;
		$portString = netstatPortList();
		$portAry = explode("\n", $portString);

		if ($this->minport == 0)
			$this->minport = (int) $cfg['minport'];
		if ($this->maxport == 0)
			$this->maxport = (int) $cfg['maxport'];

		if ($this->minport == 0)
			$this->minport = 50000;
		if ($this->maxport == 0)
			$this->maxport = 60000;
		
		$this->port = intval($this->minport);

		while (1) {
			if (in_array($this->port, $portAry))
				$this->port += 1;
			else
				return true;
			if ($this->port > $this->maxport) {
				// state
				$this->state = CLIENTHANDLER_STATE_ERROR;
				// message
				$msg = "All ports in use.";
				array_push($this->messages , $msg);
				AuditAction($cfg["constants"]["error"], $msg);
				$this->logMessage($msg."\n", true);
				// write error to stat
				$sf = new StatFile($this->transfer, $this->owner);
				$sf->time_left = 'Error';
				$sf->write();
				// return
				return false;
			}
		}
		return false;
	}

	/**
	 * gets current status (realtime)
	 * for transferStat popup
	 * 
	 * @param string
	 * @return array (stat) or string Error
	 */
	function monitorTransfer($transfer) {
		//by default, realtime monitoring not available.
		return "";
	}

	/**
	 * gets current status of All RPC Transfers (realtime)
	 * for index
	 *
	 * @return array by hash
	 */
	function monitorRunningTransfers() {
		//by default, realtime monitoring not available.
		return array();
	}

} // end class

?>
