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
define('MAINTENANCEANDREPAIR_STATE_NULL', 0);                            // null
define('MAINTENANCEANDREPAIR_STATE_OK', 1);                                // ok
define('MAINTENANCEANDREPAIR_STATE_ERROR', -1);                         // error

// modes
define('MAINTENANCEANDREPAIR_MODE_CLI', 1);                               // cli
define('MAINTENANCEANDREPAIR_MODE_WEB', 2);                               // web

// types
define('MAINTENANCEANDREPAIR_TYPE_INT', 0);                          // internal
define('MAINTENANCEANDREPAIR_TYPE_STD', 1);                          // standard
define('MAINTENANCEANDREPAIR_TYPE_EXT', 2);                          // extended

/**
 * MaintenanceAndRepair
 */
class MaintenanceAndRepair
{
	// public fields
	var $name = "MaintenanceAndRepair";

    // state
    var $state = MAINTENANCEANDREPAIR_STATE_NULL;

    // messages-array
    var $messages = array();

	// private fields

    // mode
    var $_mode = 0;

	// transfer fields
	var $_bogusTransfers = array();
	var $_fixedTransfers = array();
	var $_restartTransfers = false;

	// counter
	var $_count = 0;
	var $_countProblems = 0;
	var $_countFixed = 0;

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return MaintenanceAndRepair
     */
    function getInstance() {
		global $instanceMaintenanceAndRepair;
		// initialize
		MaintenanceAndRepair::initialize();
		// return instance
		return $instanceMaintenanceAndRepair;
    }

    /**
     * initialize MaintenanceAndRepair.
     */
    function initialize() {
    	global $instanceMaintenanceAndRepair;
    	// create instance
    	if (!isset($instanceMaintenanceAndRepair))
    		$instanceMaintenanceAndRepair = new MaintenanceAndRepair();
    }

    /**
     * accessor for state
     *
     * @return int
     */
    function getState() {
		global $instanceMaintenanceAndRepair;
		return (isset($instanceMaintenanceAndRepair))
			? $instanceMaintenanceAndRepair->state
			: MAINTENANCEANDREPAIR_STATE_NULL;
    }

    /**
     * accessor for messages
     *
     * @return array
     */
    function getMessages() {
		global $instanceMaintenanceAndRepair;
		return (isset($instanceMaintenanceAndRepair))
			? $instanceMaintenanceAndRepair->messages
			: array();
    }

	/**
	 * maintenance
	 *
	 * @param $trestart
	 */
	function maintenance($type = MAINTENANCEANDREPAIR_TYPE_STD) {
		global $instanceMaintenanceAndRepair;
		// initialize
		MaintenanceAndRepair::initialize();
		// maintenance run
		$instanceMaintenanceAndRepair->instance_maintenance($type);
	}

	/**
	 * repair
	 */
	function repair() {
		global $instanceMaintenanceAndRepair;
		// initialize
		MaintenanceAndRepair::initialize();
		// repair run
		$instanceMaintenanceAndRepair->instance_repair();
	}

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the factory-method !
     *
     * @return MaintenanceAndRepair
     */
    function MaintenanceAndRepair() {
    	global $argv;
        // messages
        $this->messages = array();
        // cli/web
		$this->_mode = (empty($argv[0]))
			? MAINTENANCEANDREPAIR_MODE_WEB
			: MAINTENANCEANDREPAIR_MODE_CLI;
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * instance_maintenance
	 *
	 * @param $type
	 */
	function instance_maintenance($type = MAINTENANCEANDREPAIR_TYPE_STD) {
    	// (re)set state
    	$this->state = MAINTENANCEANDREPAIR_STATE_NULL;
		// output
		$this->_outputMessage("Running Maintenance...\n");

		if ($type == MAINTENANCEANDREPAIR_TYPE_INT) {
			// prune database
			$this->_maintenanceDatabasePrune();
		} else {
			// fluxd
			$this->_maintenanceFluxd();
			// fluazu
			$this->_maintenanceFluazu();
			// transfers
			$this->_maintenanceTransfers($type == MAINTENANCEANDREPAIR_TYPE_EXT);
			// database
			$this->_maintenanceDatabase();
		}

		// output
		$this->_outputMessage("Maintenance done.\n");
		// state
		$this->state = MAINTENANCEANDREPAIR_STATE_OK;
	}

	/**
	 * instance_repair
	 */
	function instance_repair() {
    	// (re)set state
    	$this->state = MAINTENANCEANDREPAIR_STATE_NULL;
		// output
		$this->_outputMessage("Running Repair...\n");
		// fluxd
		$this->_maintenanceFluxd();
		// fluazu
		$this->_maintenanceFluazu();
		// repair app
		$this->_repairApp();
		// database
		$this->_maintenanceDatabase();
		/* done */
		$this->_outputMessage("Repair done.\n");
		// state
		$this->state = MAINTENANCEANDREPAIR_STATE_OK;
	}

	// =========================================================================
	// private methods
	// =========================================================================

	/* maintenance-methods */

	/**
	 * _maintenanceFluxd
	 * delete leftovers of fluxd (only do this if daemon is not running)
	 */
	function _maintenanceFluxd() {
		global $cfg;
		// output
		$this->_outputMessage("fluxd-maintenance...\n");
		// files
		$fdp = $cfg["path"].'.fluxd/fluxd.pid';
		$fds = $cfg["path"].'.fluxd/fluxd.sock';
		$fdpe = file_exists($fdp);
		$fdse = file_exists($fds);
		$fluxdLeftoversFound = false;
		$fctr = 0;
		if ($fdpe)
			$fctr++;
		if ($fdse)
			$fctr++;
		if ($fctr > 0) {
			if ("1" != @trim(shell_exec("ps x -o pid='' -o ppid='' -o command='' -ww 2> /dev/null | ".$cfg['bin_grep']." -v grep | ".$cfg['bin_grep']." 'fluxd running' | ".$cfg['bin_grep']." -c ".tfb_shellencode($cfg["docroot"]))))
				$fluxdLeftoversFound = true;
		}
		if ($fluxdLeftoversFound) {
			// problems
			$this->_outputMessage("found and removing fluxd-leftovers...\n");
			// pid
			if ($fdpe)
				@unlink($fdp);
			// socket
			if ($fdse)
				@unlink($fds);
			// DEBUG : log the repair
			if ($cfg['debuglevel'] > 0)
				AuditAction($cfg["constants"]["debug"], "fluxd-maintenance : found and removed fluxd-leftovers.");
			// output
			$this->_outputMessage("done.\n");
		} else {
			// no problems
			$this->_outputMessage("no problems found.\n");
		}
		/* done */
		$this->_outputMessage("fluxd-maintenance done.\n");
	}

	/**
	 * _maintenanceFluazu
	 * delete leftovers of fluazu (only do this if daemon is not running)
	 */
	function _maintenanceFluazu() {
		global $cfg;
		// output
		$this->_outputMessage("fluazu-maintenance...\n");
		// files
		$fdp = $cfg["path"].'.fluazu/fluazu.pid';
		$fds = $cfg["path"].'.fluazu/fluazu.stat';
		$fdc = $cfg["path"].'.fluazu/fluazu.cmd';
		$fdpe = file_exists($fdp);
		$fdse = file_exists($fds);
		$fdce = file_exists($fdc);
		$leftoversFound = false;
		$fctr = 0;
		if ($fdpe)
			$fctr++;
		if ($fdse)
			$fctr++;
		if ($fdce)
			$fctr++;
		if ($fctr > 0) {
			if ("1" != @trim(shell_exec("ps x -o pid='' -o ppid='' -o command='' -ww 2> /dev/null | ".$cfg['bin_grep']." -v grep | ".$cfg['bin_grep']." 'fluazu.py' | ".$cfg['bin_grep']." -c ".tfb_shellencode($cfg["path"]))))
				$leftoversFound = true;
		}
		if ($leftoversFound) {
			// problems
			$this->_outputMessage("found and removing fluazu-leftovers...\n");
			// pid
			if ($fdpe)
				@unlink($fdp);
			// stat
			if ($fdse)
				@unlink($fds);
			// command
			if ($fdce)
				@unlink($fdc);
			// DEBUG : log the repair
			if ($cfg['debuglevel'] > 0)
				AuditAction($cfg["constants"]["debug"], "fluazu-maintenance : found and removed fluazu-leftovers.");
			// output
			$this->_outputMessage("done.\n");
		} else {
			// no problems
			$this->_outputMessage("no problems found.\n");
		}
		/* done */
		$this->_outputMessage("fluazu-maintenance done.\n");
	}
	
	/**
	 * _maintenanceTransfers
	 *
	 * @param $trestart
	 * @return boolean
	 */
	function _maintenanceTransfers($trestart = false) {
		global $cfg, $db, $transfers;
		// set var
		$this->_restartTransfers = $trestart;
		// output
		$this->_outputMessage("transfers-maintenance...\n");
		// sanity-check for transfers-dir
		if (!is_dir($cfg["transfer_file_path"])) {
			$this->state = MAINTENANCEANDREPAIR_STATE_ERROR;
            $msg = "invalid dir-settings. no dir : ".$cfg["transfer_file_path"];
            array_push($this->messages , $msg);
			$this->_outputError($msg."\n");
			return false;
		}
		// pid-files of transfer-clients
		$pidFiles = array();
		if ($dirHandle = @opendir($cfg["transfer_file_path"])) {
			while (false !== ($file = @readdir($dirHandle))) {
				if ((strlen($file) > 3) && ((substr($file, -4, 4)) == ".pid"))
					array_push($pidFiles, $file);
			}
			@closedir($dirHandle);
		}
		// return if no pid-files found
		if (count($pidFiles) < 1) {
			$this->_outputMessage("no pid-files found.\n");
			$this->_outputMessage("transfers-maintenance done.\n");
			return true;
		}
		// get process-list
		$psString = trim(shell_exec("ps x -o pid='' -o ppid='' -o command='' -ww"));
		// test if client for pid is still up
		$this->_bogusTransfers = array();
		foreach ($pidFiles as $pidFile) {
			$transfer = (substr($pidFile, 0, -4));
			if (stristr($psString, $transfer) === false) {
				if (getTransferClient($transfer) != "azureus")
					array_push($this->_bogusTransfers, $transfer);
			}
		}
		// return if no stale pid-files
		$this->_countProblems = count($this->_bogusTransfers);
		if ($this->_countProblems < 1) {
			$this->_outputMessage("no stale pid-files found.\n");
			$this->_outputMessage("transfers-maintenance done.\n");
			return true;
		}

		/* repair the bogus clients */
		$this->_countFixed = 0;
		$this->_outputMessage("repairing died clients...\n");
		foreach ($this->_bogusTransfers as $transfer) {
			// output
			$this->_outputMessage("repairing ".$transfer." ...\n");
			// set stopped flag in db
			stopTransferSettings($transfer);
			// rewrite stat-file
			$sf = new StatFile($transfer, getOwner($transfer));
			$sf->running = 0;
			$sf->percent_done = -100.0;
			$sf->time_left = 'Transfer Died';
			$sf->down_speed = 0;
			$sf->up_speed = 0;
			$sf->seeds = 0;
			$sf->peers = 0;
			$sf->write();
			// delete pid-file
			@unlink($cfg["transfer_file_path"].$transfer.".pid");
			// DEBUG : log the repair of the bogus transfer
			if ($cfg['debuglevel'] > 0)
				AuditAction($cfg["constants"]["debug"], "transfers-maintenance : transfer repaired : ".$transfer);
			// output
			$this->_outputMessage("done.\n");
			// count
			$this->_countFixed++;
		}
		// output
		if ($this->_countProblems > 0)
			$this->_outputMessage("repaired transfers : ".$this->_countFixed."/".$this->_countProblems."\n");

		/* restart transfers */
		if ($this->_restartTransfers) {
			$this->_fixedTransfers = array();
			$this->_outputMessage("restarting died clients...\n");
			// hold current user
			$whoami = ($this->_mode == MAINTENANCEANDREPAIR_MODE_CLI) ? GetSuperAdmin() : $cfg["user"];
			foreach ($this->_bogusTransfers as $transfer) {
				// output
				$this->_outputMessage("Starting ".$transfer." ...\n");
				// set current user to transfer-owner
				$cfg["user"] = getOwner($transfer);
				// clientHandler + start
				$ch = ClientHandler::getInstance(getTransferClient($transfer));
				$ch->start($transfer, false, FluxdQmgr::isRunning());
				// DEBUG : log the restart of the died transfer
				if ($cfg['debuglevel'] > 0) {
					$staret = ($ch->state == CLIENTHANDLER_STATE_OK) ? "OK" : "FAILED";
					AuditAction($cfg["constants"]["debug"], "transfers-maintenance : restarted transfer ".$transfer." by ".$whoami." : ".$staret);
				}
				if ($ch->state == CLIENTHANDLER_STATE_OK) {
					// output
					$this->_outputMessage("done.\n");
					// add to ary
					array_push($this->_fixedTransfers, $transfer);
					// count
					$this->_countFixed++;
				} else {
					$this->messages = array_merge($this->messages, $ch->messages);
					$this->_outputError(implode("\n", $ch->messages)."\n");
				}
			}
			// set user back
			$cfg["user"] = $whoami;
			// output
			$this->_countFixed = count($this->_fixedTransfers);
			if ($this->_countFixed > 0)
				$this->_outputMessage("restarted transfers : ".$this->_countFixed."/".$this->_countProblems."\n");
		}
		/* done */
		$this->_outputMessage("transfers-maintenance done.\n");
		// return
		return true;
	}

	/**
	 * _maintenanceDatabase
	 */
	function _maintenanceDatabase() {
		global $cfg, $db;
		// output
		$this->_outputMessage("database-maintenance...\n");
		/* tf_transfers */
		$this->_countProblems = 0;
		$this->_countFixed = 0;
		// output
		$this->_outputMessage("table-maintenance : tf_transfers\n");
		// running-flag
		$sql = "SELECT transfer FROM tf_transfers WHERE running = '1'";
		$recordset = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$rc = $recordset->RecordCount();
		if ($rc > 0) {
			while (list($tname) = $recordset->FetchRow()) {
				if (!isTransferRunning($tname)) {
					$this->_countProblems++;
					// t is not running, reset running-flag
					$this->_outputMessage("reset of running-flag for transfer which is not running : ".$tname."\n");
					$sql = "UPDATE tf_transfers SET running = '0' WHERE transfer = ".$db->qstr($tname);
					$db->Execute($sql);
					$this->_countFixed++;
					// output
					$this->_outputMessage("done.\n");
				}
			}
		}
		// empty hash
		$sql = "SELECT transfer FROM tf_transfers WHERE hash = ''";
		$recordset = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$rc = $recordset->RecordCount();
		if ($rc > 0) {
			$this->_countProblems += $rc;
			while (list($tname) = $recordset->FetchRow()) {
				// t has no hash, update
				$this->_outputMessage("updating transfer which has empty hash : ".$tname."\n");
				// get hash
				$thash = getTransferHash($tname);
				// update
				if (!empty($thash)) {
					$sql = "UPDATE tf_transfers SET hash = ".$db->qstr($thash)." WHERE transfer = ".$db->qstr($tname);
					$db->Execute($sql);
					$this->_countFixed++;
					// output
					$this->_outputMessage("done.\n");
				}
			}
		}
		// empty datapath
		$sql = "SELECT transfer FROM tf_transfers WHERE datapath = ''";
		$recordset = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$rc = $recordset->RecordCount();
		if ($rc > 0) {
			$this->_countProblems += $rc;
			while (list($tname) = $recordset->FetchRow()) {
				// t has no datapath, update
				$this->_outputMessage("updating transfer which has empty datapath : ".$tname."\n");
				// get datapath
				$tDatapath = getTransferDatapath($tname);
				// update
				if ($tDatapath != "") {
					$sql = "UPDATE tf_transfers SET datapath = ".$db->qstr($tDatapath)." WHERE transfer = ".$db->qstr($tname);
					$db->Execute($sql);
					$this->_countFixed++;
					// output
					$this->_outputMessage("done.\n");
				} else {
					// output
					$this->_outputMessage("cannot get datapath for ".$tname.".\n");
				}
			}
		}
		// output + log
		if ($this->_countProblems == 0) {
			// output
			$this->_outputMessage("no problems found.\n");
		} else {
			// DEBUG : log
			$msg = "found and fixed problems in tf_transfers : ".$this->_countFixed."/".$this->_countProblems;
			if ($cfg['debuglevel'] > 0)
				AuditAction($cfg["constants"]["debug"], "database-maintenance : table-maintenance : ".$msg);
			// output
			$this->_outputMessage($msg."\n");
		}

		/* tf_transfer_totals */
		$this->_countProblems = 0;
		$this->_countFixed = 0;
		// output
		$this->_outputMessage("table-maintenance : tf_transfer_totals\n");
		$this->_countProblems = $db->GetOne("SELECT COUNT(*) FROM tf_transfer_totals WHERE tid = ''");
		if (($this->_countProblems !== false) && ($this->_countProblems > 0)) {
			// output
			$this->_outputMessage("found ".$this->_countProblems." invalid entries, deleting...\n");
			$sql = "DELETE FROM tf_transfer_totals WHERE tid = ''";
			$result = $db->Execute($sql);
			if ($db->ErrorNo() != 0) dbError($sql);
			$this->_countFixed = $db->Affected_Rows();
			// output
			$this->_outputMessage("done.\n");
			$rCount = ($this->_countFixed !== false) ? $this->_countFixed : $this->_countProblems;
			// DEBUG : log
			$msg = "found and removed invalid totals-entries from tf_transfer_totals : ".$rCount."/".$this->_countProblems;
			if ($cfg['debuglevel'] > 0)
				AuditAction($cfg["constants"]["debug"], "database-maintenance : table-maintenance : ".$msg);
			// output
			$this->_outputMessage($msg."\n");
		} else {
			// output
			$this->_outputMessage("no problems found.\n");
		}
		// prune db
		$this->_maintenanceDatabasePrune();
		/* done */
		$this->_outputMessage("database-maintenance done.\n");

	}

	/**
	 * prune database
	 */
	function _maintenanceDatabasePrune() {
		global $cfg, $db;
		// output
		$this->_outputMessage("pruning database...\n");
		$this->_outputMessage("table : tf_log\n");
		// Prune LOG
		$this->_count = 0;
		$testTime = time() - ($cfg['days_to_keep'] * 86400); // 86400 is one day in seconds
		$sql = "delete from tf_log where time < ".$db->qstr($testTime);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$this->_count += $db->Affected_Rows();
		unset($result);
		$testTime = time() - ($cfg['minutes_to_keep'] * 60);
		$sql = "delete from tf_log where time < ".$db->qstr($testTime)." and action=".$db->qstr($cfg["constants"]["hit"]);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		$this->_count += $db->Affected_Rows();
		unset($result);
		/* done */
		if ($this->_count > 0)
			$this->_outputMessage("deleted entries from tf_log : ".$this->_count."\n");
		else
			$this->_outputMessage("no entries deleted.\n");
		$this->_outputMessage("prune database done.\n");
	}

	/* repair-methods */

	/**
	 * _repairApp
	 */
	function _repairApp() {
		global $cfg, $db;
		// output
		$this->_outputMessage("repairing app...\n");
		// sanity-check for transfers-dir
		if (!is_dir($cfg["transfer_file_path"])) {
			$this->state = MAINTENANCEANDREPAIR_STATE_ERROR;
            $msg = "invalid dir-settings. no dir : ".$cfg["transfer_file_path"];
            array_push($this->messages , $msg);
			$this->_outputError($msg."\n");
			return false;
		}
		// delete pid-files of clients
		if ($dirHandle = opendir($cfg["transfer_file_path"])) {
			while (false !== ($file = readdir($dirHandle))) {
				if ((strlen($file) > 3) && ((substr($file, -4, 4)) == ".pid"))
					@unlink($cfg["transfer_file_path"].$file);
			}
			closedir($dirHandle);
		}
		// rewrite stat-files
		$arList = getTransferArray();
		foreach ($arList as $transfer) {
			$sf = new StatFile($transfer, getOwner($transfer));
			// output
			$this->_outputMessage("rewrite stat-file for ".$transfer." ...\n");
			$sf->running = 0;
			$sf->percent_done = -100.0;
			$sf->time_left = 'repaired';
			$sf->down_speed = 0;
			$sf->up_speed = 0;
			$sf->seeds = 0;
			$sf->peers = 0;
			$sf->write();
			// output
			$this->_outputMessage("done.\n");
		}
		// set flags in db
		$this->_outputMessage("reset running-flag in database...\n");
		$db->Execute("UPDATE tf_transfers SET running = '0'");
		// output
		$this->_outputMessage("done.\n");
		/* done */
		$this->_outputMessage("repair app done.\n");
	}

	/* output-methods */

    /**
     * output message
     *
     * @param $message
     */
	function _outputMessage($message) {
        // only in cli-mode
		if ($this->_mode == MAINTENANCEANDREPAIR_MODE_CLI)
			printMessage($this->name, $message);
    }

    /**
     * output error
     *
     * @param $message
     */
	function _outputError($message) {
        // only in cli-mode
		if ($this->_mode == MAINTENANCEANDREPAIR_MODE_CLI)
			printError($this->name, $message);
    }

}

?>