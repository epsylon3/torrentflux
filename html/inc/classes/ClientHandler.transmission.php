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
 * class ClientHandler for transmission-client
 */
class ClientHandlerTransmission extends ClientHandler
{

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function ClientHandlerTransmission() {
    	$this->type = "torrent";
        $this->client = "transmission";
        $this->binSystem = "transmission";
        $this->binSocket = "transmission";
        $this->binClient = "transmission";
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

        // do transmission special-pre-start-checks
        // check to see if the path to the transmission-bin is valid
        if (!is_executable($cfg["btclient_transmission_bin"])) {
        	$this->state = CLIENTHANDLER_STATE_ERROR;
        	$msg = "transmissioncli cannot be executed";
        	AuditAction($cfg["constants"]["error"], $msg);
        	$this->logMessage($msg."\n", true);
        	array_push($this->messages, $msg);
            array_push($this->messages, "btclient_transmission_bin : ".$cfg["btclient_transmission_bin"]);
            // write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error';
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

        /*
        // workaround for bsd-pid-file-problem : touch file first
        if ((!$this->queue) && ($cfg["_OS"] == 2))
        	@touch($this->transferFilePath.".pid");
        */

        // build the command-string
		// note : order of args must not change for ps-parsing-code in
		// RunningTransferTransmission
        $this->command  = "cd ".tfb_shellencode($this->savepath).";";
        $this->command .= " HOME=".tfb_shellencode($cfg["path"])."; export HOME;".
        $this->command .= $this->umask;
        $this->command .= " nohup ";
        $this->command .= $this->nice;
        $this->command .= tfb_shellencode($cfg["btclient_transmission_bin"]);
        $this->command .= " -d ".tfb_shellencode($this->drate);
        $this->command .= " -u ".tfb_shellencode($this->rate);
        $this->command .= " -p ".tfb_shellencode($this->port);
		$this->command .= " -W ".tfb_shellencode(($this->runtime == "True") ? 1 : 0);
        $this->command .= " -L ".tfb_shellencode($this->sharekill_param);
        $this->command .= " -E 6";
        $this->command .= " -O ".tfb_shellencode($this->owner);
        if (strlen($cfg["btclient_transmission_options"]) > 0)
        	$this->command .= " ".$cfg["btclient_transmission_options"];
        $this->command .= " ".tfb_shellencode($this->transferFilePath);
        $this->command .= " 1>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " 2>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " &";

        // start the client
        $this->_start();
    }

    /**
     * deletes cache of a transfer
     *
     * @param $transfer
     */
    function deleteCache($transfer) {
    	global $cfg;
    	$cFile = $cfg["path"].".transmission/cache/resume.".getTransferHash($transfer);
    	if (@file_exists($cFile))
        	return @unlink($cFile);
        return false;
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
		CommandHandler::add($transfer, "w".(($this->runtime == "True") ? "1" : "0"));
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
		CommandHandler::add($transfer, "l".$this->sharekill);
		// send command to client
        if ($autosend)
			CommandHandler::send($transfer);
    	// return
    	return true;
    }

}

?>