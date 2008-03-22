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
 * class ClientHandler for nzbperl-client
 */
class ClientHandlerNzbperl extends ClientHandler
{

	// public fields

	// nzbperl bin
	var $nzbbin = "";

	// =====================================================================
	// ctor
	// =====================================================================

    /**
     * ctor
     */
	function ClientHandlerNzbperl() {
		global $cfg;
		$this->type = "nzb";
		$this->client = "nzbperl";
        $this->binSystem = "perl";
        $this->binSocket = "perl";
        $this->binClient = "tfnzbperl.pl";
		$this->nzbbin = $cfg["docroot"]."bin/clients/nzbperl/tfnzbperl.pl";
	}

	// =====================================================================
	// Public Methods
	// =====================================================================

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

		// do nzbperl special-pre-start-checks
		// check to see if the path to the nzbperl script is valid
		if (!is_file($this->nzbbin)) {
			$this->state = CLIENTHANDLER_STATE_ERROR;
			$msg = "path for tfnzbperl.pl is not valid";
			AuditAction($cfg["constants"]["error"], $msg);
			$this->logMessage($msg."\n", true);
			array_push($this->messages, $msg);
			array_push($this->messages, "nzbbin : ".$this->nzbbin);
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

		// Build Command String (do not change order of last args !)
		$this->command  = "cd ".tfb_shellencode($this->savepath).";";
		$this->command .= " HOME=".tfb_shellencode(substr($cfg["path"], 0, -1));
		$this->command .= "; export HOME;";
		$this->command .= $this->umask;
		$this->command .= " nohup ";
		$this->command .= $this->nice;
		$this->command .= $cfg['perlCmd'];
		$this->command .= " -I ".tfb_shellencode($cfg["docroot"]."bin/lib");
		$this->command .= " ".tfb_shellencode($this->nzbbin);
		$this->command .= " --conn ".tfb_shellencode($cfg['nzbperl_conn']);
		$this->command .= " --uudeview ".tfb_shellencode($cfg["bin_uudeview"]);
		$this->command .= ($cfg['nzbperl_badAction'])
			? " --insane --keepbrokenbin"
			: " --dropbad";
		switch ($cfg['nzbperl_create']) {
			case 1:
				$this->command .= " --dlcreate";
				break;
			case 2:
				$this->command .= " --dlcreategrp";
				break;
		}
		$this->command .= " --dthreadct ".tfb_shellencode($cfg['nzbperl_threads']);
		$this->command .= " --speed ".tfb_shellencode($this->drate);
		$this->command .= " --server ".tfb_shellencode($cfg['nzbperl_server']);
		if ($cfg['nzbperl_user'] != "") {
			$this->command .= " --user ".tfb_shellencode($cfg['nzbperl_user']);
			$this->command .= " --pw ".tfb_shellencode($cfg['nzbperl_pw']);
		}
		if (strlen($cfg["nzbperl_options"]) > 0)
			$this->command .= " ".$cfg['nzbperl_options'];
		// do NOT change anything below (not even order)
		$this->command .= " --dlpath ".tfb_shellencode($this->savepath);
		$this->command .= " --tfuser ".tfb_shellencode($this->owner);
		$this->command .= " ".tfb_shellencode($this->transferFilePath);
        $this->command .= " 1>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " 2>> ".tfb_shellencode($this->transferFilePath.".log");
        $this->command .= " &";

		// state
		$this->state = CLIENTHANDLER_STATE_READY;

		// Start the client
		$this->_start();
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
		$this->drate       = $cfg["nzbperl_rate"];
		$this->maxuploads  = 1;
		$this->superseeder = 0;
		$this->runtime     = "True";
		$this->sharekill   = 0;
		$this->minport     = 1;
		$this->maxport     = 65535;
		$this->maxcons     = $cfg["nzbperl_conn"];
		$this->rerequest   = 1;
    }

}

?>