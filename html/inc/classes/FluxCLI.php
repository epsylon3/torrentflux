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

// defines
define('_DUMP_DELIM', '*');
preg_match('|.*\s(\d+)\s.*|', '$Revision$', $revisionMatches);
define('_REVISION_FLUXCLI', $revisionMatches[1]);

/**
 * FluxCLI
 */
class FluxCLI
{
	// public fields

	// name
	var $name = "FluxCLI";

    // private fields

	// script
	var $_script = "fluxcli.php";

    // action
    var $_action = "";

    // args
    var $_args = array();
    var $_argc = 0;

    // arg-errors-array
    var $_argErrors = array();

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return FluxCLI
     */
    function getInstance() {
		global $instanceFluxCLI;
		return (isset($instanceFluxCLI))
			? $instanceFluxCLI
			: false;
    }

    /**
     * getAction
     *
     * @return string
     */
    function getAction() {
		global $instanceFluxCLI;
		return (isset($instanceFluxCLI))
			? $instanceFluxCLI->_action
			: "";
    }

    /**
     * getArgs
     *
     * @return array
     */
    function getArgs() {
		global $instanceFluxCLI;
		return (isset($instanceFluxCLI))
			? $instanceFluxCLI->_args
			: array();
    }

	/**
	 * process a request
	 *
	 * @param $args
	 * @return mixed
	 */
    function processRequest($args) {
		global $instanceFluxCLI;
    	// create new instance
    	$instanceFluxCLI = new FluxCLI($args);
		// call instance-method
		return (!$instanceFluxCLI)
			? false
			: $instanceFluxCLI->instance_processRequest();
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the public static methods !
     *
	 * @param $args
     * @return FluxCLI
     */
    function FluxCLI($args) {
    	global $cfg;

		// set user-var
		$cfg["user"] = GetSuperAdmin();

		// set admin-var
		$cfg['isAdmin'] = true;

		// set user-agent
		$cfg['user_agent'] = $this->name."/" . _REVISION_FLUXCLI;
		$_SERVER['HTTP_USER_AGENT'] = $this->name."/" . _REVISION_FLUXCLI;

		// parse args and set fields
		$argCount = count($args);
		if ($argCount < 1) {
			// invalid args
			$this->_outputError("invalid args.\n");
			return false;
		}
		$this->_script = basename($args[0]);
		$this->_action = (isset($args[1])) ? $args[1] : "";
		if ($argCount > 2) {
			$prm = array_splice($args, 2);
			$this->_args = array_map('trim', $prm);
			$this->_argc = count($this->_args);
		} else {
			$this->_args = array();
			$this->_argc = 0;
		}
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * process a request
	 *
	 * @return mixed
	 */
    function instance_processRequest() {
    	global $cfg;

		// action-switch
		switch ($this->_action) {

			/* netstat */
			case "netstat":
				return $this->_netstat();

			/* transfers */
			case "transfers":
				return $this->_transfers();

			/* start */
			case "start":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferStart(
						$this->_args[0],
						(isset($this->_args[1])) ? $this->_args[1] : "",
						($this->_argc > 2) ? array_slice($this->_args, 2) : array()
					);
				}

			/* stop */
			case "stop":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferStop($this->_args[0]);
				}

			/* enqueue */
			case "enqueue":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferEnqueue($this->_args[0]);
				}

			/* dequeue */
			case "dequeue":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferDequeue($this->_args[0]);
				}

			/* reset */
			case "reset":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferReset($this->_args[0]);
				}

			/* delete */
			case "delete":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferDelete($this->_args[0]);
				}

			/* wipe */
			case "wipe":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: name of transfer. (extra-arg 1)");
					break;
				} else {
					return $this->_transferWipe($this->_args[0]);
				}

			/* start-all */
			case "start-all":
				return $this->_transfersStart(
					(isset($this->_args[0])) ? $this->_args[0] : "",
					($this->_argc > 1) ? array_slice($this->_args, 1) : array()
				);

			/* resume-all */
			case "resume-all":
				return $this->_transfersResume(
					(isset($this->_args[0])) ? $this->_args[0] : "",
					($this->_argc > 1) ? array_slice($this->_args, 1) : array()
				);

			/* stop-all */
			case "stop-all":
				return $this->_transfersStop();

			/* tset */
			case "tset":
				if ($this->_argc < 3) {
					array_push($this->_argErrors, "missing argument(s) for tset.");
					break;
				} else {
					return $this->_tset(
						$this->_args[0], $this->_args[1], $this->_args[2],
						(isset($this->_args[3])) ? $this->_args[3] : "s"
					);
				}

			/* inject */
			case "inject":
				if ($this->_argc < 2) {
					array_push($this->_argErrors, "missing argument(s) for inject.");
					break;
				} else {
					return $this->_inject(
						$this->_args[0], $this->_args[1],
						(isset($this->_args[2])) ? $this->_args[2] : "",
						($this->_argc > 3) ? array_slice($this->_args, 3) : array()
					);
				}

			/* watch */
			case "watch":
				if ($this->_argc < 2) {
					array_push($this->_argErrors, "missing argument(s) for watch.");
					break;
				} else {
					return $this->_watch(
						$this->_args[0], $this->_args[1],
						(isset($this->_args[2])) ? $this->_args[2] : "ds",
						($this->_argc > 3) ? array_slice($this->_args, 3) : array()
					);
				}

			/* rss */
			case "rss":
				if ($this->_argc < 4) {
					array_push($this->_argErrors, "missing argument(s) for rss.");
					break;
				} else {
					return $this->_rss(
						$this->_args[0], $this->_args[1],
						$this->_args[2], $this->_args[3],
						(isset($this->_args[4])) ? $this->_args[4] : ""
					);
				}

			/* xfer */
			case "xfer":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: time-delta of xfer to use : (all/total/month/week/day) (extra-arg 1)");
					break;
				} else {
					return $this->_xfer($this->_args[0]);
				}

			/* repair */
			case "repair":
				return $this->_repair();

	        /* maintenance */
			case "maintenance":
				return $this->_maintenance(((isset($this->_args[0])) && (strtolower($this->_args[0]) == "true")) ? true : false);

	        /* dump */
			case "dump":
				if (empty($this->_args[0])) {
					array_push($this->_argErrors, "missing argument: type. (settings/users) (extra-arg 1)");
					break;
				} else {
					return $this->_dump($this->_args[0]);
				}

			/* filelist */
			case "filelist":
				printFileList((empty($this->_args[0])) ? $cfg['docroot'] : $this->_args[0], 1, 1);
				return true;

			/* checksums */
			case "checksums":
				printFileList((empty($this->_args[0])) ? $cfg['docroot'] : $this->_args[0], 2, 1);
				return true;

			/* version */
			case "version":
			case "-version":
			case "--version":
			case "-v":
				return $this->_printVersion();

			/* help */
			case "help":
			case "-help":
			case "--help":
			case "-h":
			default:
				return $this->_printUsage();

		}

		// help
		return $this->_printUsage();
    }

	// =========================================================================
	// private methods -- options parsing helpers
	// =========================================================================

	/**
	 * Parse a list of options, with optional extra-args.
	 * Returns false in case of error, e.g. missing extra-arg
	 * (error already reported via $this->_outputError).
	 *
	 * Example:
	 *	_parseOptions(
	 *		array(							// $desc: array of possible options with number of extra-args.
	 *			'd' => 0,						// Options 'd' and 's' take no extra-arg.
	 *			's' => 0,
	 *			'p' => 1,						// Option 'p' takes one extra-arg.
	 *			'x' => 2						// Option 'x' takes two extra-args.
	 *		),
	 *		'spx',							// $options
	 *		array('profname', 'x1', 'x2')	// $extra
	 *	) === array(
	 *		's' => array(),						// Option 's' was found, no extra-arg.
	 *		'p' => array('profname'),			// Option 'p' was found, its extra-arg's value was 'profname'.
	 *		'x' => array('x1', 'x2')			// Option 'x' was found, its extra-args' values were 'x1' and 'x2'.
	 *	)
	 *
	 * @param array  $desc
	 * @param string $options
	 * @param array  $extra
	 * @return array
	 */
	function _parseOptions($desc, $options = '', $extra = array()) {
		$return = array();

		// This preg_split merely does a str_split, but works on PHP4.
		foreach (preg_split('/(?<=.)(?=.)/s', $options) as $option) {

			// Unknown option, ignore it.
			if (!array_key_exists($option, $desc))
				continue;

			$needed = $desc[$option];
			$found = array();

			// Option needs at least one extra-arg, ensure there are enough and load it/them.
			if ($needed > 0) {
				if (!is_array($extra) || count($extra) < $needed) {
					$this->_outputError("missing extra argument(s) for option '".$option."'.\n");
					return false;
				}
				for ($i = 0; $i < $needed; $i++)
					array_push($found, array_shift($extra));
			}

			$return[$option] = $found;
		}

		return $return;
	}

	/**
	 * Build a list of options for a new function call, by passing
	 * thru some options from the current call (works on an options
	 * set, returned previously by $this->_parseOptions).
	 *
	 * Example:
	 *	_buildOptions(
	 *		'spx',							// $options: list of options to pass-thru
	 *		array(							// $optionsSet: options of current call
	 *			'd' => array(),
	 *			's' => array(),
	 *			'x' => array('x1', 'x2'),
	 *			'p' => array('profname')
	 *		)
	 *	) === array(
	 *		'sxp',							// $options for new call // The three options 's', 'p' and 'x' are
	 *		array('x1', 'x2', 'profname')	// $extra   for new call // forwarded, with their extra-args.
	 *	)
	 *
	 * @param string $options
	 * @param array  $optionsSet
	 * @return array
	 */
	function _buildOptions($options, $optionsSet) {
		$returnOptions = '';
		$returnExtra = array();

		// Iterate on current call's options.
		foreach ($optionsSet as $option => $extra) {
			// If this option should be passed thru to next call, add it.
			if (strpos($options, $option) !== false) {
				$returnOptions .= $option;
				$returnExtra = array_merge($returnExtra, $extra);
			}
		}

		return array($returnOptions, $returnExtra);
	}


	// =========================================================================
	// private methods
	// =========================================================================

	/**
	 * Print Net Stat
	 *
	 * @return mixed
	 */
	function _netstat() {
		global $cfg;
		echo $cfg['_ID_CONNECTIONS'].":\n";
		echo netstatConnectionsSum()."\n";
		echo $cfg['_ID_PORTS'].":\n";
		echo netstatPortList();
		echo $cfg['_ID_HOSTS'].":\n";
		echo netstatHostList();
		return true;
	}

	/**
	 * Show Transfers
	 *
	 * @return mixed
	 */
	function _transfers() {
		global $cfg;
		// print out transfers
		echo "Transfers:\n";
		$transferHeads = getTransferListHeadArray();
		echo "* Name * ".implode(" * ", $transferHeads)."\n";
		$transferList = getTransferListArray();
		foreach ($transferList as $transferAry)
			echo "- ".implode(" - ", $transferAry)."\n";
		// print out stats
		echo "Server:\n";
	    if (! array_key_exists("total_download", $cfg))
	        $cfg["total_download"] = 0;
	    if (! array_key_exists("total_upload", $cfg))
	        $cfg["total_upload"] = 0;
		echo $cfg['_UPLOADSPEED']."\t".': '.number_format($cfg["total_upload"], 2).' kB/s'."\n";
		echo $cfg['_DOWNLOADSPEED']."\t".': '.number_format($cfg["total_download"], 2).' kB/s'."\n";
		echo $cfg['_TOTALSPEED']."\t".': '.number_format($cfg["total_download"]+$cfg["total_upload"], 2).' kB/s'."\n";
		echo $cfg['_ID_CONNECTIONS']."\t".': '.netstatConnectionsSum()."\n";
		return true;
	}

	/**
	 * Start Transfer
	 *
	 * @param $transfer
	 * @param $options
	 * @param array $extra
	 * @return mixed
	 */
	function _transferStart($transfer, $options = '', $extra = array()) {
		global $cfg;
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		// check running
		if (isTransferRunning($transfer)) {
			$this->_outputError("transfer already running.\n");
			return false;
		}
		// parse options
		$optionsSet = $this->_parseOptions(
			array( 'p' => 1 ),	// Only recognized option is 'p'.
			$options, $extra
		);
		if ($optionsSet === false)
			return false;
		$profile = isset($optionsSet['p']) ? $optionsSet['p'][0] : null;

		// set user
		$cfg["user"] = getOwner($transfer);
		// output
		$this->_outputMessage(
			"Starting ".$transfer." for user ".$cfg["user"].
			(!empty($profile) ? " using profile ".$profile : '').
			" ...\n"
		);

		$ch = ClientHandler::getInstance(getTransferClient($transfer));
		// load and apply profile, if specified (just ignore
		// it if profiles are disabled, no error)
		if ($cfg['transfer_profiles'] >= 1 && !empty($profile)) {
			$ch->settingsDefault($transfer);
			$settings = GetProfileSettings($profile);
			if (empty($settings) || $settings === false) {
				$this->_outputError("profilename ".$profile." is no valid profile.\n");
				return false;
			}
			$ch->rate = $settings['rate'];
			$ch->drate = $settings['drate'];
			$ch->maxuploads = $settings['maxuploads'];
			$ch->superseeder = $settings['superseeder'];
			$ch->runtime = $settings['runtime'];
			$ch->sharekill = $settings['sharekill'];
			$ch->minport = $settings['minport'];
			$ch->maxport = $settings['maxport'];
			$ch->maxcons = $settings['maxcons'];
			$ch->rerequest = $settings['rerequest'];
			$ch->settingsSave();
		}
		// force start, don't queue
		$ch->start($transfer, false, false);
		if ($ch->state == CLIENTHANDLER_STATE_OK) { /* hooray */
			$this->_outputMessage("done.\n");
			return true;
		} else {
			$this->_outputError("failed: ".implode("\n", $ch->messages)."\n");
			return false;
		}
	}

	/**
	 * Stop Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferStop($transfer) {
		global $cfg;
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		// check running
		if (!isTransferRunning($transfer)) {
			$this->_outputError("transfer not running.\n");
			return false;
		}
		// set user
		$cfg["user"] = getOwner($transfer);
		// output
		$this->_outputMessage("Stopping ".$transfer." for user ".$cfg["user"]."...\n");
		// stop
		$ch = ClientHandler::getInstance(getTransferClient($transfer));
        $ch->stop($transfer);
        $this->_outputMessage("done.\n");
		return true;
	}

	/**
	 * Enqueue Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferEnqueue($transfer) {
		global $cfg;
		// initialize service-mod (why here ? see "fluxd-single-thread-problem")
		FluxdServiceMod::initializeServiceMod('Qmgr');
		// check queue
		if (!FluxdQmgr::isRunning()) {
			$this->_outputError("Qmgr is not running.\n");
			return false;
		}
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		// check running
		if (isTransferRunning($transfer)) {
			$this->_outputError("transfer already running.\n");
			return false;
		}
		// set user
		$cfg["user"] = getOwner($transfer);
		// output
		$this->_outputMessage("Enqueue ".$transfer." for user ".$cfg["user"]."...\n");
		// force start, don't queue
		$ch = ClientHandler::getInstance(getTransferClient($transfer));
		$ch->start($transfer, false, true);
		if ($ch->state == CLIENTHANDLER_STATE_OK) { /* hooray */
			$this->_outputMessage("done.\n");
			return true;
		} else {
			$this->_outputError("failed: ".implode("\n", $ch->messages)."\n");
			return false;
		}
	}

	/**
	 * Dequeue Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferDequeue($transfer) {
		global $cfg;
		// initialize service-mod (why here ? see "fluxd-single-thread-problem")
		FluxdServiceMod::initializeServiceMod('Qmgr');
		// check queue
		if (!FluxdQmgr::isRunning()) {
			$this->_outputError("Qmgr is not running.\n");
			return false;
		}
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		// set user
		$cfg["user"] = getOwner($transfer);
		// output
		$this->_outputMessage("Dequeue ".$transfer." for user ".$cfg["user"]."...\n");
		// dequeue
		FluxdQmgr::dequeueTransfer($transfer, $cfg["user"]);
		$this->_outputMessage("done.\n");
		return true;
	}

	/**
	 * Reset Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferReset($transfer) {
		$this->_outputMessage("Resetting totals of ".$transfer." ...\n");
		$msgs = resetTransferTotals($transfer, false);
		if (count($msgs) == 0) {
			$this->_outputMessage("done.\n");
			return true;
		} else {
			$this->_outputError("failed: ".implode("\n", $msgs)."\n");
			return false;
		}
	}

	/**
	 * Delete Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferDelete($transfer) {
		global $cfg;
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		$this->_outputMessage("Delete ".$transfer." ...\n");
		// set user
		$cfg["user"] = getOwner($transfer);
		// delete
		$ch = ClientHandler::getInstance(getTransferClient($transfer));
		$tRunningFlag = isTransferRunning($transfer);
		if ($tRunningFlag) {
			// stop transfer first
			$this->_outputMessage("transfer is running, stopping first...\n");
			$ch->stop($transfer);
			$tRunningFlag = isTransferRunning($transfer);
        }
        if (!$tRunningFlag) {
        	$this->_outputMessage("Deleting...\n");
        	$ch->delete($transfer);
			$this->_outputMessage("done.\n");
			return true;
        } else {
        	$this->_outputError("transfer still up... cannot delete\n");
        	return false;
        }
	}

	/**
	 * Wipe Transfer
	 *
	 * @param $transfer
	 * @return mixed
	 */
	function _transferWipe($transfer) {
		global $cfg;
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		$this->_outputMessage("Wipe ".$transfer." ...\n");
		// set user
		$cfg["user"] = getOwner($transfer);
		// wipe
		$ch = ClientHandler::getInstance(getTransferClient($transfer));
		$tRunningFlag = isTransferRunning($transfer);
		if ($tRunningFlag) {
			// stop transfer first
			$this->_outputMessage("transfer is running, stopping first...\n");
			$ch->stop($transfer);
			$tRunningFlag = isTransferRunning($transfer);
        }
        if (!$tRunningFlag) {
        	$this->_outputMessage("Deleting...\n");
    		$msgsDelete = deleteTransferData($transfer);
    		$countDelete = count($msgsDelete);
			$msgsReset = resetTransferTotals($transfer, true);
			$countReset = count($msgsReset);
        	if (($countDelete + $countReset) == 0) {
				$this->_outputMessage("done.\n");
				return true;
        	} else {
				$this->_outputError("there were problems: "
					.(($countDelete > 0) ? implode("\n", $msgsDelete)."\n" : "")
					.(($countReset > 0) ? implode("\n", $msgsReset) : "")
					."\n"
				);
				return false;
        	}
        } else {
        	$this->_outputError("transfer still up... cannot delete\n");
        	return false;
        }
	}

	/**
	 * Start Transfers
	 *
	 * @param $options
	 * @param array $extra
	 * @return mixed
	 */
	function _transfersStart($options = '', $extra = array()) {
		// parse options
		$optionsSet = $this->_parseOptions(
			array( 'p' => 1 ),	// Only recognized option is 'p'.
			$options, $extra
		);
		if ($optionsSet === false)
			return false;
		$profile = isset($optionsSet['p']) ? $optionsSet['p'][0] : null;

		$this->_outputMessage(
			"Starting all transfers".
			(!empty($profile) ? " using profile ".$profile : '').
			" ...\n"
		);

		// build args for _transferStart
		$newOptions = $this->_buildOptions('p', $optionsSet);	// Pass-thru option 'p'.

		$transferList = getTransferArray();
		foreach ($transferList as $transfer) {
			if (!isTransferRunning($transfer))
				$this->_transferStart($transfer, $newOptions[0], $newOptions[1]);
		}
	}

	/**
	 * Resume Transfers
	 *
	 * @param $options
	 * @param array $extra
	 * @return mixed
	 */
	function _transfersResume($options = '', $extra = array()) {
		// parse options
		$optionsSet = $this->_parseOptions(
			array( 'p' => 1 ),	// Only recognized option is 'p'.
			$options, $extra
		);
		if ($optionsSet === false)
			return false;
		$profile = isset($optionsSet['p']) ? $optionsSet['p'][0] : null;

		$this->_outputMessage(
			"Resuming all transfers".
			(!empty($profile) ? " using profile ".$profile : '').
			" ...\n"
		);

		// build args for _transferStart
		$newOptions = $this->_buildOptions('p', $optionsSet);	// Pass-thru option 'p'.

		$transferList = getTransferArray();
		$sf = new StatFile("");
		foreach ($transferList as $transfer) {
			$sf->init($transfer);
			if (trim($sf->running) == 0)
				$this->_transferStart($transfer, $newOptions[0], $newOptions[1]);
		}
	}

	/**
	 * Stop Transfers
	 *
	 * @return mixed
	 */
	function _transfersStop() {
		$this->_outputMessage("Stopping all transfers ...\n");
		$transferList = getTransferArray();
		foreach ($transferList as $transfer) {
			if (isTransferRunning($transfer))
				$this->_transferStop($transfer);
		}
	}

	/**
	 * set transfer setting
	 *
	 * @param $transfer
	 * @param $key
	 * @param $val
	 * @param $options
	 * @return mixed
	 */
	function _tset($transfer, $key, $val, $options) {
		global $cfg;
		// check transfer
		if (!transferExists($transfer)) {
			$this->_outputError("transfer does not exist.\n");
			return false;
		}
		// check params
		$settingsKeys = array(
			'uprate' => 'NUMBER',
			'downrate' => 'NUMBER',
			'completion' => 'BOOL',
			'sharekill' => 'NUMBER'
		);
		if (!array_key_exists($key, $settingsKeys)) {
			$this->_outputError("invalid settings-key: ".$key."\n");
			return false;
		}
		if (strlen($val) < 1) {
			$this->_outputError("value for ".$key." invalid.\n");
			return false;
		}
		switch ($settingsKeys[$key]) {
			case 'NUMBER':
				if (!preg_match('/^[0-9\-]+$/D', $val)) {
					$this->_outputError("value for ".$key." must be a number: ".$val."\n");
					return false;
				}
				break;
			case 'BOOL':
				$val = strtolower($val);
				if (($val != 'true') && ($val != 'false')) {
					$this->_outputError("value for ".$key." must be true or false: ".$val."\n");
					return false;
				}
				break;
		}
		// set user
		$cfg["user"] = getOwner($transfer);
		// output
		$this->_outputMessage("Setting ".$key." to ".$val." for ".$transfer." for user ".$cfg["user"]."...\n");
		// init ch-instance
		$ch = ClientHandler::getInstance(getTransferClient($transfer));
		// load settings, default if settings could not be loaded (fresh transfer)
		if ($ch->settingsLoad($transfer) !== true)
			$ch->settingsDefault();
		// autosend
		$send = ((strpos($options, 's') !== false) && (isTransferRunning($transfer)));
		// set setting
		switch ($key) {
			case 'uprate':
				if ($ch->rate != $val) {
					$ch->setRateUpload($transfer, $val, $send);
					break;
				} else {
					$this->_outputMessage("no changes.\n");
					return false;
				}
			case 'downrate':
				if ($ch->drate != $val) {
					$ch->setRateDownload($transfer, $val, $send);
					break;
				} else {
					$this->_outputMessage("no changes.\n");
					return false;
				}
			case 'completion':
				if (strtolower($ch->runtime) != $val) {
					$ch->setRuntime($transfer, ($val == 'true') ? 'True' : 'False', $send);
					break;
				} else {
					$this->_outputMessage("no changes.\n");
					return false;
				}
			case 'sharekill':
				if ($ch->sharekill != $val) {
					$ch->setSharekill($transfer, $val, $send);
					break;
				} else {
					$this->_outputMessage("no changes.\n");
					return false;
				}
		}
		// save
		$ch->settingsSave();
		// output + return
		if ($send)
			$this->_outputMessage("settings saved + changes sent to client.\n");
		else
			$this->_outputMessage("settings saved.\n");
		return true;
	}

	/**
	 * Inject Transfer
	 *
	 * @param $transferFile
	 * @param $username
	 * @param $options
	 * @param array $extra
	 * @return mixed
	 */
	function _inject($transferFile, $username, $options = '', $extra = array()) {
		global $cfg;
		// check file
		if (!@is_file($transferFile)) {
			$this->_outputError("transfer-file ".$transferFile." is no file.\n");
			return false;
		}
		// check username
		if (!IsUser($username)) {
			$this->_outputError("username ".$username." is no valid user.\n");
			return false;
		}
		// parse options
		$optionsSet = $this->_parseOptions(
			array( 'd' => 0, 's' => 0, 'p' => 1 ),	// Recognized options are 'd', 's' and 'p'.
			$options, $extra
		);
		if ($optionsSet === false)
			return false;
		$profile = isset($optionsSet['p']) ? $optionsSet['p'][0] : null;

		$this->_outputMessage(
			"Inject ".$transferFile." for user ".$username.
			(!empty($profile) ? " using profile ".$profile : '').
			" ...\n"
		);

		// set user
	    $cfg["user"] = $username;
	    // set filename
	    $transfer = basename($transferFile);
        $transfer = tfb_cleanFileName($transfer);
        // only inject valid transfers
        $msgs = array();
        if ($transfer !== false) {
        	$targetFile = $cfg["transfer_file_path"].$transfer;
            if (@is_file($targetFile)) {
            	array_push($msgs, "transfer ".$transfer.", already exists.");
            } else {
            	$this->_outputMessage("copy ".$transferFile." to ".$targetFile." ...\n");
                if (@copy($transferFile, $targetFile)) {
                	// chmod
                    @chmod($cfg["transfer_file_path"].$transfer, 0644);
                    // make owner entry
                    AuditAction($cfg["constants"]["file_upload"], $transfer);
                    // inject
                    $this->_outputMessage("injecting ".$transfer." ...\n");
                    injectTransfer($transfer);
	            	// delete source-file
	            	if (isset($optionsSet['d'])) {
		            	$this->_outputMessage("deleting source-file ".$transferFile." ...\n");
		            	@unlink($transferFile);
	            	}
					// start
					if (isset($optionsSet['s'])) {
						// build args for _transferStart
						$newOptions = $this->_buildOptions('p', $optionsSet);	// Pass-thru option 'p'.
						return $this->_transferStart($transfer, $newOptions[0], $newOptions[1]);
					}
					// return
					else
						return true;
                } else {
                	array_push($msgs, "File could not be copied: ".$transferFile);
                }
            }
        } else {
        	array_push($msgs, "The type of file you are injecting is not allowed.");
			array_push($msgs, "valid file-extensions: ");
			array_push($msgs, $cfg["file_types_label"]);
        }
		if (count($msgs) == 0) {
			$this->_outputMessage("done.\n");
			return true;
		} else {
			$this->_outputError("failed: ".implode("\n", $msgs)."\n");
			return false;
		}
	}

	/**
	 * Watch Dir
	 *
	 * @param $watchDir
	 * @param $username
	 * @param $options
	 * @param array $extra
	 * @return mixed
	 */
	function _watch($watchDir, $username, $options = '', $extra = array()) {
		global $cfg;
		// check dir
		if (!@is_dir($watchDir)) {
			$this->_outputError("watch-dir ".$watchDir." is no dir.\n");
			return false;
		}
		// check username
		if (!IsUser($username)) {
			$this->_outputError("username ".$username." is no valid user.\n");
			return false;
		}
		// parse options
		$optionsSet = $this->_parseOptions(
			array( 'd' => 0, 's' => 0, 'p' => 1 ),	// Recognized options are 'd', 's' and 'p'.
			$options, $extra
		);
		if ($optionsSet === false)
			return false;
		$profile = isset($optionsSet['p']) ? $optionsSet['p'][0] : null;

		$this->_outputMessage(
			"Processing watch-dir ".$watchDir." for user ".$username.
			(!empty($profile) ? " using profile ".$profile : '').
			" ...\n"
		);

        // process dir
        if ($dirHandle = @opendir($watchDir)) {
        	// get input-files
        	$input = array();
			while (false !== ($file = @readdir($dirHandle)))
				if (@is_file($watchDir.$file))
        			array_push($input, $file);
            @closedir($dirHandle);
            if (empty($input)) {
            	$this->_outputMessage("done. no files found.\n");
            	return true;
            }
			// trailing slash
        	$watchDir = checkDirPathString($watchDir);
			// build args for _inject
			$newOptions = $this->_buildOptions('dsp', $optionsSet);	// Pass-thru options 'd', 's' and 'p'.
            // process input-files
            $ctr = array('files' => count($input), 'ok' => 0);
            foreach ($input as $transfer) {
            	// inject, increment if ok
            	if ($this->_inject($watchDir.$transfer, $username, $newOptions[0], $newOptions[1]) !== false)
            		$ctr['ok']++;
            }
            if ($ctr['files'] == $ctr['ok']) {
            	$this->_outputMessage("done. files: ".$ctr['files']."; ok: ".$ctr['ok']."\n");
            	return true;
            } else {
            	$this->_outputError("done with errors. files: ".$ctr['files']."; ok: ".$ctr['ok']."\n");
            	return false;
            }
        } else {
        	$this->_outputError("failed to open watch-dir ".$watchDir.".\n");
			return false;
        }
	}

	/**
	 * Xfer Shutdown
	 *
	 * @param $delta
	 * @return mixed
	 */
	function _xfer($delta) {
		global $cfg, $db;
		// check xfer
		if ($cfg['enable_xfer'] != 1) {
			$this->_outputError("xfer must be enabled.\n");
			return false;
		}
		// check arg
		if (($delta != "all") && ($delta != "total") && ($delta != "month") && ($delta != "week") && ($delta != "day")) {
			$this->_outputMessage('invalid delta : "'.$delta.'"'."\n");
			return false;
		}
		$this->_outputMessage('checking xfer-limit(s) for "'.$delta.'" ...'."\n");
    	// set xfer-realtime
		$cfg['xfer_realtime'] = 1;
		// set xfer-newday
		Xfer::setNewday();
    	// getTransferListArray to update xfer-stats
		$transferList = @getTransferListArray();
		// get xfer-totals
		$xfer_total = Xfer::getStatsTotal();
		// check if break needed
		// total
		if (($delta == "total") || ($delta == "all")) {
			// only do if a limit is set
			if ($cfg["xfer_total"] > 0) {
				if ($xfer_total['total']['total'] >= $cfg["xfer_total"] * 1048576) {
					// limit met, stop all Transfers now.
					$this->_outputMessage('Limit met for "total" : '.formatFreeSpace($xfer_total['total']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_total"])."\n");
					return $this->_transfersStop();
				} else {
					$this->_outputMessage('Limit not met for "total" : '.formatFreeSpace($xfer_total['total']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_total"])."\n");
				}
			} else {
				$this->_outputMessage('no limit set for "total"'."\n");
			}
		}
		// month
		if (($delta == "month") || ($delta == "all")) {
			// only do if a limit is set
			if ($cfg["xfer_month"] > 0) {
				if ($xfer_total['month']['total'] >= $cfg["xfer_month"] * 1048576) {
					// limit met, stop all Transfers now.
					$this->_outputMessage('Limit met for "month" : '.formatFreeSpace($xfer_total['month']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_month"])."\n");
					return $this->_transfersStop();
				} else {
					$this->_outputMessage('Limit not met for "month" : '.formatFreeSpace($xfer_total['month']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_month"])."\n");
				}
			} else {
				$this->_outputMessage('no limit set for "month"'."\n");
			}
		}
		// week
		if (($delta == "week") || ($delta == "all")) {
			// only do if a limit is set
			if ($cfg["xfer_week"] > 0) {
				if ($xfer_total['week']['total'] >= $cfg["xfer_week"] * 1048576) {
					// limit met, stop all Transfers now.
					$this->_outputMessage('Limit met for "week" : '.formatFreeSpace($xfer_total['week']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_week"])."\n");
					return $this->_transfersStop();
				} else {
					$this->_outputMessage('Limit not met for "week" : '.formatFreeSpace($xfer_total['week']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_week"])."\n");
				}
			} else {
				$this->_outputMessage('no limit set for "week"'."\n");
			}
		}
		// day
		if (($delta == "day") || ($delta == "all")) {
			// only do if a limit is set
			if ($cfg["xfer_day"] > 0) {
				if ($xfer_total['day']['total'] >= $cfg["xfer_day"] * 1048576) {
					// limit met, stop all Transfers now.
					$this->_outputMessage('Limit met for "day" : '.formatFreeSpace($xfer_total['day']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_day"])."\n");
					return $this->_transfersStop();
				} else {
					$this->_outputMessage('Limit not met for "day" : '.formatFreeSpace($xfer_total['day']['total'] / (1048576))." / ".formatFreeSpace($cfg["xfer_day"])."\n");
				}
			} else {
				$this->_outputMessage('no limit set for "day"'."\n");
			}
		}
		// done
		$this->_outputMessage("done.\n");
		return true;
	}

	/**
	 * rss download
	 *
	 * @param $saveDir
	 * @param $filterFile
	 * @param $historyFile
	 * @param $url
	 * @param $username
	 * @return mixed
	 */
	function _rss($saveDir, $filterFile, $historyFile, $url, $username = "") {
		global $cfg;
		// set user
		if (!empty($username)) {
			// check first
			if (IsUser($username)) {
				$cfg["user"] = $username;
			} else {
				$this->_outputError("username ".$username." is no valid user.\n");
				return false;
			}
		}
		// process Feed
		require_once("inc/classes/Rssd.php");
		return Rssd::processFeed($saveDir, $filterFile, $historyFile, $url);
	}

	/**
	 * Repair
	 *
	 * @return mixed
	 */
	function _repair() {
		require_once("inc/classes/MaintenanceAndRepair.php");
		MaintenanceAndRepair::repair();
		return true;
	}

	/**
	 * Maintenance
	 *
	 * @param $trestart
	 * @return mixed
	 */
	function _maintenance($trestart) {
		// initialize service-mod (why here ? see "fluxd-single-thread-problem")
		FluxdServiceMod::initializeServiceMod('Qmgr');
		require_once("inc/classes/MaintenanceAndRepair.php");
		MaintenanceAndRepair::maintenance(
			$trestart ? MAINTENANCEANDREPAIR_TYPE_EXT : MAINTENANCEANDREPAIR_TYPE_STD);
		return true;
	}

	/**
	 * Dump Database
	 *
	 * @param $type
	 * @return mixed
	 */
	function _dump($type) {
		global $cfg, $db;
		switch ($type) {
			case "settings":
			    $sql = "SELECT tf_key, tf_value FROM tf_settings";
				break;
			case "users":
				$sql = "SELECT uid, user_id FROM tf_users";
				break;
			default:
				$this->_outputError("invalid type : ".$type."\n");
				return false;
		}
	    $recordset = $db->Execute($sql);
	    if ($db->ErrorNo() != 0) dbError($sql);
	    $content = "";
	    while (list($a, $b) = $recordset->FetchRow())
	    	 $content .= $a._DUMP_DELIM.$b."\n";
	    echo $content;
		return ($content != "");
	}

    /**
     * output message
     *
     * @param $message
     */
	function _outputMessage($message) {
		printMessage($this->name, $message);
    }

    /**
     * output error
     *
     * @param $message
     */
	function _outputError($message) {
		printError($this->name, $message);
    }

    /**
     * prints version
     *
	 * @return mixed
     */
    function _printVersion() {
    	echo $this->name." Revision "._REVISION_FLUXCLI."\n";
    	return (_REVISION_FLUXCLI > 0);
    }

    /**
     * prints usage
     *
	 * @return mixed
     */
    function _printUsage() {
		$this->_printVersion();
		echo "\n"
		. "Usage: ".$this->_script." action [extra-args]\n"
		. "\n"
		. "action: \n"
		. "  transfers   : show transfers.\n"
		. "  netstat     : show netstat.\n"
		. "  start       : start a transfer.\n"
		. "                extra-arg 1 : name of transfer as known inside webapp\n"
		. "                extra-arg 2 : options (p) (optional, default: none)\n"
		. "                              options-arg contains 'p' : use transfer-profile\n"
		. "                extra-arg 3 : transfer-profile name (optional, default: none)\n"
		. "  stop        : stop a transfer.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
		. "  reset       : reset totals of a transfer.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
		. "  delete      : delete a transfer.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
		. "  wipe        : reset totals, delete metafile, delete data.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
		. "  enqueue     : enqueue a transfer.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
		. "  dequeue     : dequeue a transfer.\n"
		. "                extra-arg : name of transfer as known inside webapp\n"
	    . "  start-all   : start all transfers.\n"
		. "                extra-arg 1 : options (p) (optional, default: none)\n"
		. "                              options-arg contains 'p' : use transfer-profile\n"
		. "                extra-arg 2 : transfer-profile name (optional, default: none)\n"
	    . "  resume-all  : resume all transfers.\n"
		. "                extra-arg 1 : options (p) (optional, default: none)\n"
		. "                              options-arg contains 'p' : use transfer-profile\n"
		. "                extra-arg 2 : transfer-profile name (optional, default: none)\n"
		. "  stop-all    : stop all running transfers.\n"
		. "  tset        : set a transfer-setting.\n"
		. "                extra-arg 1 : name of transfer as known inside webapp\n"
		. "                extra-arg 2 : settings-key\n"
		. "                extra-arg 3 : settings-value\n"
		. "                extra-arg 4 : options (optional, default: s)\n"
		. "                              options-arg contains 's' : send changes to running client\n"
		. "                valid settings :\n"
		. "                uprate     ; value: uprate in kB/s   ; options: s\n"
		. "                downrate   ; value: downrate in kB/s ; options: s\n"
		. "                completion ; value: true/false       ; options: s\n"
		. "                sharekill  ; value: seed-percentage  ; options: s\n"
		. "  inject      : injects (+ starts) a transfer.\n"
		. "                extra-arg 1 : path to transfer-meta-file\n"
		. "                extra-arg 2 : username of fluxuser\n"
		. "                extra-arg 3 : options (d/s/p) (optional, default: none)\n"
		. "                              options-arg contains 'd' : delete source-file after inject\n"
		. "                              options-arg contains 's' : start transfer after inject\n"
		. "                              options-arg contains 'p' : use transfer-profile\n"
		. "                extra-arg 4 : transfer-profile name (optional, default: none)\n"
		. "  watch       : watch a dir and inject (+ start) transfers.\n"
		. "                extra-arg 1 : watch-dir\n"
		. "                extra-arg 2 : username of fluxuser\n"
		. "                extra-arg 3 : options (d/s/p) (optional, default: ds)\n"
		. "                              options-arg contains 'd' : delete source-file(s) after inject\n"
		. "                              options-arg contains 's' : start transfer(s) after inject\n"
		. "                              options-arg contains 'p' : use transfer-profile\n"
		. "                extra-arg 4 : transfer-profile name (optional, default: none)\n"
		. "  rss         : download torrents matching filter-rules from a rss-feed.\n"
		. "                extra-arg 1 : save-dir\n"
		. "                extra-arg 2 : filter-file\n"
		. "                extra-arg 3 : history-file\n"
		. "                extra-arg 4 : rss-feed-url\n"
		. "                extra-arg 5 : use cookies from this torrentflux user (optional, default: superadmin)\n"
		. "  xfer        : xfer-Limit-Shutdown. stop all transfers if xfer-limit is met.\n"
		. "                extra-arg 1 : time-delta of xfer to use : (all/total/month/week/day)\n"
		. "  repair      : repair of torrentflux. DON'T do this unless you have to.\n"
		. "                Doing this on a running ok flux _will_ screw up things.\n"
		. "  maintenance : call maintenance and repair all died transfers.\n"
		. "                extra-arg 1 : restart died transfers (true/false. optional, default: false)\n"
		. "  dump        : dump database.\n"
		. "                extra-arg 1 : type. (settings/users)\n"
		. "  filelist    : print file-list.\n"
		. "                extra-arg 1 : dir (optional, default: docroot)\n"
		. "  checksums   : print checksum-list.\n"
		. "                extra-arg 1 : dir (optional, default: docroot)\n"
		. "\n"
		. "examples:\n"
		. $this->_script." transfers\n"
		. $this->_script." netstat\n"
		. $this->_script." start foo.torrent\n"
		. $this->_script." start foo.torrent p profilename\n"
		. $this->_script." stop foo.torrent\n"
		. $this->_script." enqueue foo.torrent\n"
		. $this->_script." dequeue foo.torrent\n"
		. $this->_script." start-all\n"
		. $this->_script." start-all p profilename\n"
		. $this->_script." resume-all\n"
		. $this->_script." stop-all\n"
		. $this->_script." tset foo.torrent uprate 100\n"
		. $this->_script." tset foo.torrent downrate 100\n"
		. $this->_script." tset foo.torrent completion false\n"
		. $this->_script." tset foo.torrent sharekill 200\n"
		. $this->_script." reset foo.torrent\n"
		. $this->_script." delete foo.torrent\n"
		. $this->_script." wipe foo.torrent\n"
		. $this->_script." inject /path/to/foo.torrent fluxuser\n"
		. $this->_script." inject /path/to/foo.torrent fluxuser ds\n"
		. $this->_script." inject /path/to/foo.torrent fluxuser sp profilename\n"
	    . $this->_script." watch /path/to/watch-dir/ fluxuser\n"
	    . $this->_script." watch /path/to/watch-dir/ fluxuser d\n"
	    . $this->_script." watch /path/to/watch-dir/ fluxuser dsp profilename\n"
	    . $this->_script." rss /path/to/rss-torrents/ /path/to/filter.dat /path/to/filter.hist http://www.example.com/rss.xml\n"
	    . $this->_script." rss /path/to/rss-torrents/ /path/to/filter.dat /path/to/filter.hist http://www.example.com/rss.xml fluxuser\n"
	    . $this->_script." xfer month\n"
		. $this->_script." repair\n"
		. $this->_script." maintenance true\n"
		. $this->_script." dump settings\n"
		. $this->_script." dump users\n"
		. $this->_script." filelist /var/www\n"
		. $this->_script." checksums /var/www\n"
		. "\n";
		if (count($this->_argErrors) > 0) {
			echo "arg-error(s) :\n"
			. implode("\n", $this->_argErrors)
			. "\n\n";
			return false;
		}
		return true;
    }


}

?>