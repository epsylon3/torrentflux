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
 * class ClientHandler for azureus-client
 */
class ClientHandlerAzureus extends ClientHandler
{

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function ClientHandlerAzureus() {
    	$this->type = "torrent";
        $this->client = "azureus";
        $this->binSystem = "java";
        $this->binSocket = "java";
        $this->binClient = "java";
    }

	// =========================================================================
	// public methods
	// =========================================================================

    /**
     * starts a client
     *
     * @param $transfer name of the transfer
     * @param $interactive (boolean) : is this a interactive startup with dialog ?
     * @param $enqueue (boolean) : enqueue ?
     */
    function start($transfer, $interactive = false, $enqueue = false) {
    	global $cfg;

    	// set vars
		$this->_setVarsForTransfer($transfer);

    	// log
    	$this->logMessage($this->client."-start : ".$transfer."\n", true);

		// FluAzu
		require_once("inc/classes/FluAzu.php");

        // do azureus special-pre-start-checks
        // check to see if fluazu is running
        if (!FluAzu::isRunning()) {
        	$this->state = CLIENTHANDLER_STATE_ERROR;
        	$msg = "fluazu not running, cannot start transfer ".$transfer;
        	AuditAction($cfg["constants"]["error"], $msg);
        	$this->logMessage($msg."\n", true);
        	array_push($this->messages, $msg);
            // write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error: fluazu down';
			$sf->write();
			// return
            return false;
        }

        // init starting of client
        $this->_init($interactive, $enqueue, true, false);

		// only continue if init succeeded (skip start / error)
		if ($this->state != CLIENTHANDLER_STATE_READY) {
			if ($this->state == CLIENTHANDLER_STATE_ERROR) {
				$msg = "Error after init (".$transfer.",".$interactive.",".$enqueue.",true,".$cfg['enable_sharekill'].")";
				array_push($this->messages , $msg);
				$this->logMessage($msg."\n", true);
			}
			// return
			return false;
		}

        // build the command-string
        $content  = $cfg['user']."\n";
        $content .= $this->savepath."\n";
        $content .= $this->rate."\n";
        $content .= $this->drate."\n";
        $content .= $this->maxuploads."\n";
        $content .= $this->superseeder."\n";
        $content .= $this->runtime."\n";
        $content .= $this->sharekill_param."\n";
        $content .= $this->minport."\n";
        $content .= $this->maxport."\n";
        $content .= $this->maxcons."\n";
        $content .= $this->rerequest;
		$this->command  = "echo -e ".tfb_shellencode($content)." > ".tfb_shellencode($cfg["path"].'.fluazu/run/'.$transfer);
		$this->command .= " && ";
		$this->command .= "echo r > ".tfb_shellencode($cfg["path"].'.fluazu/fluazu.cmd');

        // start the client
        $this->_start();
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
		// FluAzu
		require_once("inc/classes/FluAzu.php");
		// only if fluazu running and transfer exists in fluazu
		if (!FluAzu::isRunning()) {
        	array_push($this->messages , "fluazu not running, cannot stop transfer ".$transfer);
			return false;
		}
		if (!FluAzu::transferExists($transfer)) {
        	array_push($this->messages , "transfer ".$transfer." does not exist in fluazu, cannot stop it");
			return false;
		}
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
		// FluAzu
		require_once("inc/classes/FluAzu.php");
		// only if transfer exists in fluazu
		if (FluAzu::transferExists($transfer)) {
			// only if fluazu running
			if (!FluAzu::isRunning()) {
	        	array_push($this->messages , "fluazu not running, cannot delete transfer ".$transfer);
				return false;
			}
			// remove from azu
			if (!FluAzu::delTransfer($transfer)) {
	        	array_push($this->messages , $this->client.": error when deleting transfer ".$transfer." :");
	        	$this->messages = array_merge($this->messages, FluAzu::getMessages());
				return false;
			}
		}
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
        $retVal = array();
        // transfer from stat-file
		$sf = new StatFile($transfer);
        $retVal["uptotal"] = $sf->uptotal;
        $retVal["downtotal"] = $sf->downtotal;
        // transfer from db
        $torrentId = getTransferHash($transfer);
        $sql = "SELECT uptotal,downtotal FROM tf_transfer_totals WHERE tid = ".$db->qstr($torrentId);
        $result = $db->Execute($sql);
        $row = $result->FetchRow();
        if (!empty($row)) {
            $retVal["uptotal"] -= $row["uptotal"];
            $retVal["downtotal"] -= $row["downtotal"];
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
    	// add command
		CommandHandler::add($transfer, "u".$uprate);
		// send command to client
        if ($autosend)
			CommandHandler::send($transfer);
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
    	// add command
		CommandHandler::add($transfer, "d".$downrate);
		// send command to client
        if ($autosend)
			CommandHandler::send($transfer);
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
    	// add command
		CommandHandler::add($transfer, "r".(($this->runtime == "True") ? "1" : "0"));
		// send command to client
        if ($autosend)
			CommandHandler::send($transfer);
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
    	// add command
		CommandHandler::add($transfer, "s".$this->sharekill);
		// send command to client
        if ($autosend)
			CommandHandler::send($transfer);
    	// return
    	return true;
    }

}

?>