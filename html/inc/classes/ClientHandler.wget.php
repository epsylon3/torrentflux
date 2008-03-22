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
 * class ClientHandler for wget-client
 */
class ClientHandlerWget extends ClientHandler
{

	// public fields
	var $url = "";

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function ClientHandlerWget() {
    	$this->type = "wget";
        $this->client = "wget";
        $this->binSystem = "php";
        $this->binSocket = "wget";
        $this->binClient = "wget.php";
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

        // set vars from the wget-file
		$this->setVarsFromFile($transfer);

    	// log
    	$this->logMessage($this->client."-start : ".$transfer."\n", true);

        // do wget special-pre-start-checks

        // check to see if the path to the php-bin is valid
        if (@file_exists($cfg['bin_php']) !== true) {
        	$this->state = CLIENTHANDLER_STATE_ERROR;
        	$msg = "php-cli binary does not exist";
        	AuditAction($cfg["constants"]["error"], $msg);
        	$this->logMessage($msg."\n", true);
        	array_push($this->messages, $msg);
            array_push($this->messages, "bin_php : ".$cfg["bin_php"]);
            // write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error';
			$sf->write();
			// return
            return false;
        }

        // check to see if the wget-bin is executable
        if (!is_executable($cfg["bin_wget"])) {
        	$this->state = CLIENTHANDLER_STATE_ERROR;
        	$msg = "wget cannot be executed";
        	AuditAction($cfg["constants"]["error"], $msg);
        	$this->logMessage($msg."\n", true);
        	array_push($this->messages, $msg);
            array_push($this->messages, "bin_wget : ".$cfg["bin_wget"]);
            // write error to stat
			$sf = new StatFile($this->transfer, $this->owner);
			$sf->time_left = 'Error';
			$sf->write();
			// return
            return false;
        }

		// init starting of client
        $this->_init($interactive, $enqueue, false, false);

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
		// note : order of args must not change for ps-parsing-code in
		// RunningTransferWget
        $this->command  = "nohup ".$cfg['bin_php']." -f bin/wget.php";
        $this->command .= " " . tfb_shellencode($this->transferFilePath);
        $this->command .= " " . tfb_shellencode($this->owner);
        $this->command .= " " . tfb_shellencode($this->savepath);
        $this->command .= " " . tfb_shellencode($this->drate * 1024);
        $this->command .= " " . tfb_shellencode($cfg["wget_limit_retries"]);
        $this->command .= " " . tfb_shellencode($cfg["wget_ftp_pasv"]);
        $this->command .= " 1>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " 2>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " &";

		// state
		$this->state = CLIENTHANDLER_STATE_READY;

		// start the client
		$this->_start();
    }

    /**
     * sets fields from default-vals
     *
     * @param $transfer
     */
    function settingsDefault($transfer = "") {
    	global $cfg;
		// transfer vars
        if ($transfer != "")
        	$this->_setVarsForTransfer($transfer);
        // common vars
		$this->hash        = getTransferHash($this->transfer);
        $this->datapath    = getTransferDatapath($this->transfer);
    	$this->savepath    = getTransferSavepath($this->transfer);
		$this->running     = 0;
		$this->rate        = 0;
		$this->drate       = $cfg["wget_limit_rate"];
		$this->maxuploads  = 1;
		$this->superseeder = 0;
		$this->runtime     = "True";
		$this->sharekill   = 0;
		$this->minport     = 1;
		$this->maxport     = 65535;
		$this->maxcons     = 1;
		$this->rerequest   = 1;
    }

    /**
     * setVarsFromUrl
     *
     * @param $transferUrl
     */
    function setVarsFromUrl($transferUrl) {
    	global $cfg;
    	$this->url = $transferUrl;
        $transfer = strrchr($transferUrl,'/');
        if ($transfer{0} == '/')
        	$transfer = substr($transfer, 1);
        $transfer = tfb_cleanFileName($transfer.".wget");
		$this->_setVarsForTransfer($transfer);
        if (empty($this->owner) || (strtolower($this->owner) == "n/a"))
        	$this->owner = $cfg['user'];
    }

    /**
     * setVarsFromFile
     *
     * @param $transfer
     */
    function setVarsFromFile($transfer) {
    	global $cfg;
		$this->_setVarsForTransfer($transfer);
	    $data = "";
	    if ($fileHandle = @fopen($this->transferFilePath,'r')) {
	        while (!@feof($fileHandle))
	            $data .= @fgets($fileHandle, 2048);
	        @fclose ($fileHandle);
	        $this->setVarsFromUrl(trim($data));
	    }
    }

	/**
	 * injects a torrent
	 *
	 * @param $url
	 * @return boolean
	 */
	function inject($url) {
		global $cfg;

		// set vars from the url
		$this->setVarsFromUrl($url);

		// write meta-file
		$resultSuccess = false;
		if ($handle = @fopen($this->transferFilePath, "w")) {
	        $resultSuccess = (@fwrite($handle, $this->url) !== false);
			@fclose($handle);
		}
		if ($resultSuccess) {
			// Make an entry for the owner
			AuditAction($cfg["constants"]["file_upload"], basename($this->transferFilePath));
			// inject stat
			$sf = new StatFile($this->transfer);
			$sf->running = "2"; // file is new
			$sf->size = getTransferSize($this->transfer);
			if (!$sf->write()) {
				$this->state = CLIENTHANDLER_STATE_ERROR;
	            $msg = "wget-inject-error when writing stat-file for transfer : ".$this->transfer;
	            array_push($this->messages , $msg);
	            AuditAction($cfg["constants"]["error"], $msg);
	            $this->logMessage($msg."\n", true);
	            $resultSuccess = false;
			}
		} else {
			$this->state = CLIENTHANDLER_STATE_ERROR;
            $msg = "wget-metafile cannot be written : ".$this->transferFilePath;
            array_push($this->messages , $msg);
            AuditAction($cfg["constants"]["error"], $msg);
            $this->logMessage($msg."\n", true);
		}

		// set transfers-cache
		cacheTransfersSet();

		// return
		return $resultSuccess;
	}

}

?>