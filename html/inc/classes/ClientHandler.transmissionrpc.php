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
 * class ClientHandler for future transmission-daemon RPC - for superadmin stats...
 */
class ClientHandlerTransmissionRPC extends ClientHandler
{

	// =========================================================================
	// constructor
	// =========================================================================

	public function __construct() {
		global $cfg;

		$this->type = "torrent";
		$this->client = "transmission";
		$this->binSystem = "transmission-daemon";
		$this->binSocket = "transmission-daemon";
		$this->binClient = "transmission-daemon";

		$this->useRPC = true;
	}

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * gets current transfer-vals of a transfer
	 *
	 * @param $transfer
	 * @return array with downtotal and uptotal
	 */
	function getTransferCurrent($transfer) {
		global $db, $transfers;
		$retVal = array();
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
			? $sfu - $transfers['totals'][$tid]['uptotal']
			: $sfu;
		$retVal["downtotal"] = (isset($transfers['totals'][$tid]['downtotal']))
			? $sfd - $transfers['totals'][$tid]['downtotal']
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
	 * set upload rate of a transfer
	 *
	 * @param $transfer
	 * @param $uprate
	 * @param $autosend
	 */
	function setRateUpload($transfer, $uprate, $autosend = false) {
		// set rate-field
		$this->rate = $uprate;
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
		$this->drate = $downrate;
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
	 * set sharekill of a transfer
	 *
	 * @param $transfer
	 * @param $sharekill
	 * @param $autosend
	 * @return boolean
	 */
	function setSharekill($transfer, $sharekill, $autosend = false) {
		// set sharekill
		$this->sharekill = $sharekill;
		// return
		return true;
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
		if (!empty($cfg['transmission_rpc_password'])
			$userpw .= ':'.$cfg['transmission_rpc_password'];

		$screenStatus = shell_exec("/usr/bin/transmission-remote $userpw@$host --list");
		$retAry = explode("\n",$screenStatus);
		print_r($retAry);
		return $retAry;
	}
}

?>
