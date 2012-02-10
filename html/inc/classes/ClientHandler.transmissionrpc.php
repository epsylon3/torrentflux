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

require_once("inc/classes/Transmission.class.php");

// Transmission RPC functions
require_once("inc/functions/functions.rpc.transmission.php");

/**
 * class ClientHandler for future compatible transmission-daemon RPC interface...
 */
class ClientHandlerTransmissionRPC extends ClientHandler
{

	// =========================================================================
	// constructor
	// =========================================================================

	public function __construct() {
		global $cfg;

		$this->type = "torrent";
		$this->client = "transmissionrpc";

		$this->binSocket = "transmission-daemon"; //for ps grep
		$this->binSystem = "transmission-daemon"; //script lang, not used in rpc
		$this->binClient = "transmission-daemon"; //for ps grep (ClientHandler.php)

		$this->useRPC = true;
	}

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * starts a transfer
	 *
	 * @param $transfer name of the transfer
	 * @param $interactive (boolean) : is this a interactive startup with dialog ?
	 * @param $enqueue (boolean) : enqueue ?
	 */
	function start($transfer, $interactive = false, $enqueue = false) {
		global $cfg, $db;

		// set vars
		$this->_setVarsForTransfer($transfer);
		addGrowlMessage($this->client."-start",$transfer);

		if (!Transmission::isRunning()) {
			$msg = "Transmission RPC not reacheable, cannot start transfer ".$transfer;
			$this->logMessage($this->client."-start : ".$msg."\n", true);
			AuditAction($cfg["constants"]["error"], $msg);
			$this->logMessage($msg."\n", true);
			addGrowlMessage($this->client."-start",$msg);
			
			// write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error: RPC down';
			$sf->write();
			
			// return
			return false;
		}

		// init properties
		$this->_init($interactive, $enqueue, true, false);

		/*
		if (!is_dir($cfg["path"].'.config/transmissionrpc/torrents')) {
			if (!is_dir($cfg["path"].'.config'))
				mkdir($cfg["path"].'.config',0775);
			
			if (!is_dir($cfg["path"].'.config/transmissionrpc'))
				mkdir($cfg["path"].'.config/transmissionrpc',0775);
			
			mkdir($cfg["path"].'.config/transmissionrpc/torrents',0775);
		}
		*/
		if (!is_dir($cfg['path'].$cfg['user'])) {
			mkdir($cfg['path'].$cfg['user'],0777);
		}
		
		$this->command = "";
		if (getOwner($transfer) != $cfg['user']) {
			//directory must be changed for different users ?
			changeOwner($transfer,$cfg['user']);
			$this->owner = $cfg['user'];
			
			// change savepath
			$this->savepath = ($cfg["enable_home_dirs"] != 0)
				? $cfg['path'].$this->owner."/"
				: $cfg['path'].$cfg["path_incoming"]."/";
			
			$this->command = "re-downloading to ".$this->savepath;
			
		} else {
			$this->command = "downloading to ".$this->savepath;
		}

		// no client needed
		$this->state = CLIENTHANDLER_STATE_READY;

		// ClientHandler _start()
		$this->_start();

		$hash = getTransferHash($transfer);
		
		if (empty($hash) || !isTransmissionTransfer($hash)) {
			$hash = addTransmissionTransfer( $cfg['uid'], $cfg['transfer_file_path'].$transfer, $cfg['path'].$cfg['user'] );
			if (is_array($hash) && $hash["result"] == "duplicate torrent") {
				$this->command = 'torrent-add skipped, already exists '.$transfer; //log purpose
				$hash="";
				$sql = "SELECT hash FROM tf_transfers WHERE transfer = ".$db->qstr($transfer);
				$result = $db->Execute($sql);
				$row = $result->FetchRow();
				if (!empty($row)) {
					$hash=$row['hash'];
				}
			} else {
				$this->command .= "\n".'torrent-add '.$transfer.' '.$hash; //log purpose
			}
		} else {
			$this->command .= "\n". 'torrent-start '.$transfer.' '.$hash; //log purpose
		}
		if (!empty($hash)) {
			
			if ($this->sharekill > 100) {
				// bad sharekill, must be 2.5 for 250%
				$this->sharekill = round((float) $this->sharekill / 100.0,2);
			}

			$params = array(
			'downloadLimit'  => intval($this->drate),
			'downloadLimited'=> intval($this->drate > 0),
			'uploadLimit'    => intval($this->rate),
			'uploadLimited'  => intval($this->rate > 0),
			'seedRatioLimit' => (float) $this->sharekill,
			'seedRatioMode' => intval($this->sharekill > 0.1)
			);
			$res = (int) startTransmissionTransfer($hash, $enqueue, $params);
		}
		if (!$res) {
			$this->command .= "\n".$rpc->LastError;
		}

		$this->updateStatFiles($transfer);

		// log (for the torrent stats window)
		$this->logMessage($this->client."-start : hash=$hash\ndownload rate=".$this->drate.", res=$res\n", true);
	}

	/**
	 * stops a transfer
	 *
	 * @param $transfer name of the transfer
	 * @param $kill kill-param (optional)
	 * @param $transferPid transfer Pid (optional)
	 */
	function stop($transfer, $kill = false, $transferPid = 0) {
		global $cfg;

		// set vars
		$this->_setVarsForTransfer($transfer);

		// log
		$this->logMessage($this->client."-stop : ".$transfer."\n", true);

		// only if Transmission running
		if (!Transmission::isRunning()) {
			array_push($this->messages , "Transmission not running, cannot stop transfer ".$transfer);
			return false;
		}

		$hash = getTransferHash($transfer);
		if (empty($hash)) {
			//not in db, clean it
			@unlink($this->transferFilePath.".pid");
			AuditAction($cfg["constants"]["debug"], $this->client."-stop : $transfer not in db, cleaning...");
			$this->delete($transfer);
			return true;
		}

		$this->updateStatFiles($transfer); //update before stopping

		if (!stopTransmissionTransfer($hash)) {
			$rpc = Transmission::getInstance();
			$msg = $transfer." :". $rpc->lastError;
			$this->logMessage($msg."\n", true);
			AuditAction($cfg["constants"]["debug"], $this->client."-stop : error $msg.");
		}

		// delete .pid
		$this->_stop($kill, $transferPid);

		// set .stat stopped
		$this->cleanStoppedStatFile($transfer);
	}

	/**
	 * deletes a transfer
	 *
	 * @param $transfer name of the transfer
	 * @return boolean of success
	 */
	function delete($transfer) {
		global $cfg;

		// set vars
		$this->_setVarsForTransfer($transfer);

		// log
		$this->logMessage($this->client."-delete : ".$transfer."\n", true);

		// only if vuze running and transfer exists in fluazu
		if (!Transmission::isRunning()) {
			array_push($this->messages , "Transmission not running, cannot stop transfer ".$transfer);
			return false;
		}

		$hash = getTransferHash($transfer);
		deleteTransmissionTransfer($cfg['uid'], $hash, false);

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
		global $db, $transfers;
		// set vars
		$this->_setVarsForTransfer($transfer);
		
		$retVal = array();
		// transfer from stat-file
		$sf = new StatFile($transfer);
		$retVal["uptotal"] = $sf->uptotal;
		$retVal["downtotal"] = $sf->downtotal;
		// transfer from db
		$torrentId = getTransferHash($transfer);
		$uid = (int) GetUID($this->owner);
		$sql = "SELECT uptotal,downtotal FROM tf_transfer_totals WHERE tid = ".$db->qstr($torrentId)." AND uid=$uid";
		$result = $db->Execute($sql);
		$row = $result->FetchRow();
		if (!empty($row)) {
			// to check
			//$retVal["uptotal"] -= $row["uptotal"];
			//$retVal["downtotal"] -= $row["downtotal"];
		}
		return $retVal;
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
		global $transfers;
		$retVal = array();
		$retVal["uptotal"] = (isset($transfers['totals'][$tid]['uptotal']))
			? abs($sfu - $transfers['totals'][$tid]['uptotal'])
			: $sfu;
		$retVal["downtotal"] = (isset($transfers['totals'][$tid]['downtotal']))
			? abs($sfd - $transfers['totals'][$tid]['downtotal'])
			: $sfd;
		return $retVal;
	}

	/**
	 * gets total transfer-vals of a transfer
	 *
	 * @param $transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferTotal($transfer) {
		global $transfers;
		// transfer from stat-file
		$sf = new StatFile($transfer);
		return array("uptotal" => $sf->uptotal, "downtotal" => $sf->downtotal);
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
		return array("uptotal" => $sfu, "downtotal" => $sfd);
	}

	/**
	 * set runtime of a transfer
	 *
	 * @param $transfer
	 * @param $runtime
	 * @param $autosend
	 * @return boolean
	 */
	function setRuntime($transfer, $runtime, $autosend = false) {
		// set runtime-field
		$this->runtime = $runtime;
		// return
		return true;
	}

	/**
	 * set upload rate of a transfer
	 *
	 * @param $transfer
	 * @param $uprate int
	 * @param $autosend
	 */
	function setRateUpload($transfer, $uprate, $autosend = false) {
		global $cfg;
		// set rate-field
		$this->rate = intval($uprate);

		$result = true;
		
		$msg = "$uprate autosend=".serialize($autosend);
		if ($autosend) {
			$rpc = Transmission::getInstance();
			
			$sess = $rpc->session_get(array("speed-limit-up"));
			if ($sess["arguments"]["speed-limit-up"] < $this->rate) {
				$msg = "session_set speed-limit-up ".$this->rate;
				AuditAction($cfg["constants"]["debug"], $this->client."-setRateUpload : $msg.");
				$rpc->session_set(array("speed-limit-up" => $this->rate));
			}

			if (isHash($transfer))
				$hash = $transfer;
			else
				$hash = getTransferHash($transfer);
				
			$tid = getTransmissionTransferIdByHash($hash);
			if ($tid > 0) {
				//$byterate = 1024 * $this->rate;
				$req = $rpc->set($tid, array('uploadLimit' => $this->rate, 'uploadLimited' => ($this->rate > 0)) );
				if (!isset($req['result']) || $req['result'] != 'success') {
					$msg = $req['result'];
					$result = false;
				} else {
					//Check if setting is applied
					$req = $rpc->get($tid,array('uploadLimit'));
					if (!isset($req['result']) || $req['result'] != 'success') {
						$msg = $req['result'];
						$result = false;
					} elseif (!empty($req['arguments']['torrents'])) {
						$torrent = array_pop($req['arguments']['torrents']);
						if ($torrent['uploadLimit'] != $this->rate) {
							$msg = "byterate not set correctly=".$torrent['uploadLimit'];
						}
					}
				}
			} else
				$msg = "bad tid $hash $transfer ".$req['result'];
			
			$this->logMessage("setRateUpload : ".$msg."\n", true);
		}
		AuditAction($cfg["constants"]["debug"], $this->client."-setRateUpload : $msg.");
		return $result;
	}

	/**
	 * set download rate of a transfer
	 *
	 * @param $transfer
	 * @param $downrate
	 * @param $autosend
	 */
	function setRateDownload($transfer, $downrate, $autosend = false) {
		// set rate-field
		$this->drate = intval($downrate);

		$result = true;

		$msg = "$downrate autosend=".serialize($autosend);
		if ($autosend) {
			$rpc = Transmission::getInstance();

			$sess = $rpc->session_get(array("speed-limit-down"));
			if ($sess["arguments"]["speed-limit-down"] < $this->drate) {
				//in kB
				$msg = "session_set speed-limit-down ".$this->drate;
				$rpc->session_set(array("speed-limit-down" => $this->drate));// ??? doesnt works so... disable limit
				$rpc->session_set(array("speed-limit-down" => 0, "speed-limit-down-enabled" => 0));
				
				AuditAction($cfg["constants"]["debug"], $this->client."->setRateDownload : $msg.".$rpc->lastError);
			}

			if (isHash($transfer))
				$hash = $transfer;
			else
				$hash = getTransferHash($transfer);
				
			$tid = getTransmissionTransferIdByHash($hash);
			if ($tid > 0) {
				//$byterate = 1024 * $this->drate;
				$req = $rpc->set($tid, array('downloadLimit' => $this->drate, 'downloadLimited' => ($this->drate > 0)) );
				if (!isset($req['result']) || $req['result'] != 'success') {
					$msg = $req['result'];
					$result = false;
				} else {
					//Check if setting is applied
					$req = $rpc->get($tid,array('downloadLimit'));
					if (!isset($req['result']) || $req['result'] != 'success') {
						$msg = $req['result'];
						$result = false;
					} elseif (!empty($req['arguments']['torrents'])) {
						$torrent = array_pop($req['arguments']['torrents']);
						if ($torrent['downloadLimit'] != $this->drate) {
							$msg = "byterate not set correctly=".$torrent['downloadLimit'];
							$rpc->set($tid, array('downloadLimit' => 0, 'downloadLimited' => 0));
						}
					}
				}
			} else
				$msg = "bad tid $hash $transfer ".$req['result'];
			
			$this->logMessage("setRateDownload : ".$msg."\n", true);
		}
		AuditAction($cfg["constants"]["debug"], $this->client."->setRateDownload : $msg.");
		return $result;
	}

	/**
	 * set sharekill of a transfer
	 *
	 * @param $transfer
	 * @param $sharekill numeric (100 = 100%)
	 * @param $autosend
	 * @return boolean
	 */
	function setSharekill($transfer, $sharekill, $autosend = false) {
		// set sharekill
		$this->sharekill = round(floatval($sharekill) / 100, 2);
		
		$result = true;
		
		$msg = "$sharekill, autosend=".serialize($autosend);
		if ($autosend) {
			$rpc = Transmission::getInstance();

			if (isHash($transfer))
				$hash = $transfer;
			else
				$hash = getTransferHash($transfer);

			$tid = getTransmissionTransferIdByHash($hash);
			if ($tid > 0) {
				$req = $rpc->set($tid, array('seedRatioLimit' => $this->sharekill, 'seedRatioMode' => 1) );
				if (!isset($req['result']) || $req['result'] != 'success') {
					$msg = $req['result'];
					$result = false;
				} else {
					//Check if setting is applied
					$req = $rpc->get($tid,array('seedRatioLimit'));
					if (!isset($req['result']) || $req['result'] != 'success') {
						$msg = $req['result'];
						$result = false;
					} elseif (!empty($req['arguments']['torrents'])) {
						$torrent = array_pop($req['arguments']['torrents']);
						if (round($torrent['seedRatioLimit'],2) != round($this->sharekill,2)) {
							// $msg = "sharekill not set correctly ".serialize($torrent->seedRatioLimit);
							//if fact, we always need to set it globally (vuze limitation)
							if (getTransmissionShareKill() < (int) $sharekill) {
								$msg = "sharekill set by session ".round($this->sharekill,2);
								$req = $rpc->session_set(array('seedRatioLimit' => $this->sharekill));
							}
						}
					}
				}
			} else
				$msg = "bad tid $hash $transfer ".$req['result'];
			
			$this->logMessage("setSharekill : ".$msg."\n", true);
		}
		global $cfg;
		if ($cfg['debuglevel'] > 0) {
			AuditAction($cfg["constants"]["debug"], $this->client."-setSharekill : $msg.");
		}
		return $result;
	}

	/**
	 * (test) gets array of running transfers (via call to transmission-remote)
	 *
	 * @return array
	 */
	function runningTransfers() {
		global $cfg;

		$host = $cfg['transmission_rpc_host'].":".$cfg['transmission_rpc_port'];
		$userpw = $cfg['transmission_rpc_user'];
		if (!empty($cfg['transmission_rpc_password']))
			$userpw .= ':'.$cfg['transmission_rpc_password'];

		$screenStatus = shell_exec("/usr/bin/transmission-remote $userpw@$host --list");
		$retAry = explode("\n",$screenStatus);
		print_r($retAry);
		return $retAry;
	}

	/**
	 * clean stat file
	 *
	 * @param $transfer
	 * @return boolean
	 */
	function cleanStoppedStatFile($transfer) {
		$stat = new StatFile($this->transfer, $this->owner);
		return $stat->stop();
	}

	/**
	 * updateStatFiles
	 *
	 * @param $transfer string torrent name
	 * @return boolean
	 */
	function updateStatFiles($transfer="") {
		global $cfg, $db;
		
		//$rpc = Transmission::getInstance();
		$tfs = $this->monitorRunningTransfers();
		if (!is_array($tfs)) {
			return false;
		}

		$sql = "SELECT hash, transfer, sharekill FROM tf_transfers WHERE type='torrent' AND client = 'transmissionrpc'";

		if ($transfer != "") {
			//only update one transfer...
			$sql .= " AND transfer=".$db->qstr($transfer);
		} else {
			$hashes = array("''");
			foreach ($tfs as $hash => $t) {
				$hashes[] = "'".strtolower($hash)."'";
			}
			$sql .= " AND hash IN (".implode(',',$hashes).")";
		}

		$recordset = $db->Execute($sql);

		while (list($hash, $transfer, $sharekill) = $recordset->FetchRow()) {
			$hash = strtolower($hash);
			$hashes[$hash] = $transfer;
			$sharekills[$hash] = $sharekill;
		}

		//convertTimeText
		require_once("inc/functions/functions.core.php");
		foreach ($tfs as $hash => $t) {
			if (!isset($hashes[$hash]))
				continue;
			
			$transfer = $hashes[$hash];
			$sf = new StatFile($transfer);
			
			$sf->running = Transmission::status_to_tf($t['status']);
			$sf->percent_done = round($t['percentDone']*100,2);
			if ($t['status']==8 || $t['status']==9) {
				$sf->sharing = round($t['uploadRatio']*100,2);
			}
			
			$sf->downtotal = $t['downloadedEver'];
			$sf->uptotal = $t['uploadedEver'];
			
			$sf->write();
		}

		//SHAREKILLS Checks
		foreach ($tfs as $hash => $t) {
			if (!isset($sharekills[$hash]))
				continue;
			if (($t['status']==8 || $t['status']==9) && ($t['uploadRatio']*100) > $sharekills[$hash]) {
				$transfer = $hashes[$hash];
				if (stopTransmissionTransfer($hash)) {
					AuditAction($cfg["constants"]["stop_transfer"], $this->client."-stat. : sharekill stopped $transfer");
					stopTransferSettings($transfer);
				}
			}
		}
		return true;
	}

	/**
	 * gets current status of one Transfer (realtime)
	 * for transferStat popup
	 *
	 * @return array (stat) or Error String
	 */
	function monitorTransfer($transfer, $format="rpc") {
		//by default, monitoring not available.

		// set vars
		$this->_setVarsForTransfer($transfer);

		if (isHash($transfer))
			$hash = $transfer;
		else
			$hash = getTransferHash($transfer);

		if (empty($hash)) {
			return "Hash for $transfer was not found";
		}

		//original rpc format, you can add fields here
		$fields = array(
			'id', 'name', 'status', 'hashString', 'totalSize',
			'downloadedEver', 'uploadedEver',
			'percentDone', 'uploadRatio',
			'peersConnected', 'peersGettingFromUs', 'peersSendingToUs',
			'rateDownload', 'rateUpload',
			'downloadLimit', 'uploadLimit',
			'downloadLimited', 'uploadLimited',
			'seedRatioLimit','seedRatioMode',
			'downloadDir','eta',
			'error', 'errorString',
			
			//'files', 'fileStats', 'trackerStats'
		);
		$stat_rpc = getTransmissionTransfer($hash, $fields);

		$rpc = Transmission::getInstance();
		if (is_array($stat_rpc)) {
			if ($format=="rpc") 
				return $stat_rpc; 
			else {
				return $rpc->rpc_to_tf($stat_rpc);
			}
		}
		return $rpc->lastError;
	}

	/**
	 * gets current status of all Transfers (realtime)
	 *
	 * @return array (stat) or Error String
	 */
	function monitorAllTransfers() {
		//by default, monitoring not available.
		//$rpc = Transmission::getInstance();

		return getUserTransmissionTransfers();
	}

	/**
	 * gets current status of all Running Transfers (realtime)
	 *
	 * @return array (stat) or Error String
	 */
	function monitorRunningTransfers() {
		//by default, monitoring not available.
		$aTorrent = getUserTransmissionTransfers();

		$stat=array();
		foreach ($aTorrent as $t) {
			if ( $t['status']==4 || $t['status']==8 ) $stat[$t['hashString']]=$t;
		}
		return $stat;
	}
}

?>
