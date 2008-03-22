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
define('FLUXD_STATE_NULL', 0);                                           // null
define('FLUXD_STATE_RUNNING', 1);                                    //  running
define('FLUXD_STATE_ERROR', -1);                                        // error

// delims of modList
define('FLUXD_DELIM_MOD', ';');
define('FLUXD_DELIM_STATE', ':');

/**
 * class Fluxd for integration of fluxd
 */
class Fluxd
{
	// public fields

    // state
    var $state = FLUXD_STATE_NULL;

    // messages-array
    var $messages = array();

    // private fields

    // pid
    var $_pid = "";

    // some path-vars for Fluxd
    var $_pathDataDir = "";
    var $_pathPidFile = "";
    var $_pathSocket = "";
    var $_pathLogFile = "";
    var $_pathLogFileError = "";

	// mod-list, only loaded first time it is needed
	var $_modList = null;

    // socket-timeout
    var $_socketTimeout = 5;

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return Fluxd
     */
    function getInstance() {
		global $instanceFluxd;
		// initialize if needed
		if (!isset($instanceFluxd))
			Fluxd::initialize();
		return $instanceFluxd;
    }

    /**
     * initialize Fluxd.
     */
    function initialize() {
    	global $instanceFluxd;
    	// create instance
    	if (!isset($instanceFluxd))
    		$instanceFluxd = new Fluxd();
    }

    /**
     * accessor for state
     *
     * @return int
     */
    function getState() {
		global $instanceFluxd;
		return $instanceFluxd->state;
    }

    /**
     * getMessages
     *
     * @return array
     */
    function getMessages() {
    	global $instanceFluxd;
		return $instanceFluxd->messages;
    }

    /**
     * isRunning
     *
     * @return boolean
     */
    function isRunning() {
    	global $instanceFluxd;
		return ($instanceFluxd->state == FLUXD_STATE_RUNNING);
    }

	/**
     * start
     *
     * @return boolean
     */
    function start() {
		global $instanceFluxd;
		return $instanceFluxd->instance_start();
    }

    /**
     * stop
     */
    function stop() {
		global $instanceFluxd;
		return $instanceFluxd->instance_stop();
    }

    /**
     * getPid
     *
     * @return int with pid
     */
    function getPid() {
    	global $instanceFluxd;
		return $instanceFluxd->instance_getPid();
    }

    /**
     * status
     *
     * @return string
     */
    function status() {
    	global $instanceFluxd;
		return $instanceFluxd->instance_status();
    }

    /**
     * modState
     *
     * @param name of service-module
     * @return int with mod-state
     */
    function modState($mod) {
    	global $instanceFluxd;
		return $instanceFluxd->instance_modState($mod);
    }

    /**
     * modList
     *
     * @return array with mod-list
     */
    function modList() {
    	global $instanceFluxd;
		return $instanceFluxd->instance_modList();
    }

    /**
     * modStatePoll
     *
     * @param name of service-module
     * @return int with mod-state
     */
    function modStatePoll($mod) {
    	global $instanceFluxd;
		return $instanceFluxd->instance_modStatePoll($mod);
    }

    /**
     * modListPoll
     *
     * @return array with mod-list
     */
    function modListPoll() {
    	global $instanceFluxd;
		return $instanceFluxd->instance_modListPoll();
    }

    /**
     * isReadyToStart
     *
     * @return boolean
     */
    function isReadyToStart() {
    	global $instanceFluxd;
		return $instanceFluxd->instance_isReadyToStart();
    }

    /**
     * setConfig
     *
     * @param $key, $value
     * @return Null
     */
    function setConfig($key, $value) {
    	global $instanceFluxd;
		$instanceFluxd->instance_setConfig($key, $value);
    }

	/**
	 * reloadDBCache
	 */
    function reloadDBCache() {
    	global $instanceFluxd;
		$instanceFluxd->instance_reloadDBCache();
    }

	/**
	 * reloadModules
	 */
    function reloadModules() {
    	global $instanceFluxd;
		$instanceFluxd->instance_reloadModules();
    }

    /**
     * writes a message to the log
     *
     * @param $message
     * @param $withTS
     * @return boolean
     */
    function logMessage($message, $withTS = true) {
    	global $instanceFluxd;
		return $instanceFluxd->instance_logMessage($message, $withTS);
    }

    /**
     * writes a message to the error-log
     *
     * @param $message
     * @param $withTS
     * @return boolean
     */
    function logError($message, $withTS = true) {
    	global $instanceFluxd;
		return $instanceFluxd->instance_logError($message, $withTS);
    }

    /**
     * send command
     *
     * @param $command
     * @param $read does this command return something ?
     * @return string with retval or null if error
     */
    function sendCommand($command, $read = 0) {
    	global $instanceFluxd;
		return $instanceFluxd->instance_sendCommand($command, $read);
    }

    /**
     * send service command
     *
     * @param $command
     * @param $read does this command return something ?
     * @return string with retval or null if error
     */
    function sendServiceCommand($mod, $command, $read = 0) {
    	global $instanceFluxd;
    	return $instanceFluxd->instance_sendCommand('!'.$mod.':'.$command, $read);
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function Fluxd() {
    	global $cfg;
    	// paths
        $this->_pathDataDir = $cfg["path"] . '.fluxd/';
        $this->_pathPidFile = $this->_pathDataDir . 'fluxd.pid';
        $this->_pathSocket = $this->_pathDataDir . 'fluxd.sock';
        $this->_pathLogFile = $this->_pathDataDir . 'fluxd.log';
        $this->_pathLogFileError = $this->_pathDataDir . 'fluxd-error.log';
        // check if fluxd running
        if ($this->_isRunning())
        	$this->state = FLUXD_STATE_RUNNING;
    }

	// =========================================================================
	// public methods
	// =========================================================================

    /**
     * instance_start
     *
     * @return boolean
     */
    function instance_start() {
    	global $cfg;
        if ($this->state == FLUXD_STATE_RUNNING) {
            AuditAction($cfg["constants"]["error"], "fluxd already started");
            return false;
        } else {
			// check the needed bins
			// perl
			if (@file_exists($cfg['perlCmd']) !== true) {
				$msg = "cannot start fluxd, specified Perl-binary does not exist: ".$cfg['perlCmd'];
            	AuditAction($cfg["constants"]["error"], $msg);
            	array_push($this->messages , $msg);
            	// Set the state
            	$this->state = FLUXD_STATE_ERROR;
            	// return
            	return false;
			}
			// php-cli
			if (@file_exists($cfg['bin_php']) !== true) {
				$msg = "cannot start fluxd, specified php-cli-binary does not exist: ".$cfg['bin_php'];
            	AuditAction($cfg["constants"]["error"], $msg);
            	array_push($this->messages , $msg);
            	// Set the state
            	$this->state = FLUXD_STATE_ERROR;
            	// return
            	return false;
			}
			// check for sockets
			$loadedExtensions = get_loaded_extensions();
			if (!in_array("sockets", $loadedExtensions)) {
				$msg = "refusing to start fluxd, PHP does not have support for sockets";
            	AuditAction($cfg["constants"]["error"], $msg);
            	array_push($this->messages , $msg);
            	// Set the state
            	$this->state = FLUXD_STATE_ERROR;
            	// return
            	return false;
			}
			// start it
            $startCommand = "cd ".tfb_shellencode($cfg["docroot"])."; HOME=".tfb_shellencode($cfg["path"]).";";
            $startCommand .= " export HOME;";
            $startCommand .= " nohup " . $cfg["perlCmd"];
            $startCommand .= " -I ".tfb_shellencode($cfg["docroot"]."bin/fluxd");
            $startCommand .= " -I ".tfb_shellencode($cfg["docroot"]."bin/lib");
            $startCommand .= " ".tfb_shellencode($cfg["docroot"]."bin/fluxd/fluxd.pl");
            $startCommand .= " start";
            $startCommand .= " ".tfb_shellencode($cfg["docroot"]);
            $startCommand .= " ".tfb_shellencode($cfg["path"]);
            $startCommand .= " ".tfb_shellencode($cfg["bin_php"]);
            $startCommand .= " ".tfb_shellencode($cfg["fluxd_dbmode"]);
	        $startCommand .= " 1>> ".tfb_shellencode($this->_pathLogFile);
	        $startCommand .= " 2>> ".tfb_shellencode($this->_pathLogFileError);
	        $startCommand .= " &";
        	$this->instance_logMessage("executing command : \n".$startCommand."\n", true);
        	// exec
            $result = exec($startCommand);
            // check if fluxd could be started
            $loop = true;
            $maxLoops = 125;
            $loopCtr = 0;
            $started = false;
            while ($loop) {
            	@clearstatcache();
            	if ($this->_isRunning()) {
            		$started = true;
            		$loop = false;
            	} else {
	            	$loopCtr++;
	            	if ($loopCtr > $maxLoops)
	            		$loop = false;
	            	else
	            		usleep(200000); // wait for 0.2 seconds
            	}
            }
            // check if started
            if ($started) {
            	AuditAction($cfg["constants"]["fluxd"], "fluxd started");
            	// Set the state
            	$this->state = FLUXD_STATE_RUNNING;
            	// return
            	return true;
            } else {
            	AuditAction($cfg["constants"]["error"], "errors starting fluxd");
            	// add startcommand to messages for debug
            	// TODO : set better message
            	array_push($this->messages , $startCommand);
            	// Set the state
            	$this->state = FLUXD_STATE_ERROR;
            	// return
            	return false;
            }
        }
    }

    /**
     * instance_stop
     */
    function instance_stop() {
    	global $cfg;
        if ($this->state == FLUXD_STATE_RUNNING) {
        	AuditAction($cfg["constants"]["fluxd"], "Stopping fluxd");
            $this->instance_sendCommand('die', 0);
            // check if fluxd still running
            $maxLoops = 125;
            $loopCtr = 0;
            for (;;) {
            	@clearstatcache();
            	if ($this->_isRunning()) {
	            	$loopCtr++;
	            	if ($loopCtr > $maxLoops)
	            		return 0;
	            	else
	            		usleep(200000); // wait for 0.2 seconds
            	} else {
            		// Set the state
            		$this->state = FLUXD_STATE_NULL;
            		// return
            		return 1;
            	}
            }
            return 0;
        } else {
        	$msg = "errors stopping fluxd as was not running.";
        	AuditAction($cfg["constants"]["error"], $msg);
        	array_push($this->messages , $msg);
            // Set the state
            $this->state = FLUXD_STATE_ERROR;
			return 0;
        }
    }

    /**
     * instance_getPid
     *
     * @return string with pid
     */
    function instance_getPid() {
    	if ($this->_pid != "") {
    		return $this->_pid;
    	} else {
    		$this->_pid = @rtrim(file_get_contents($this->_pathPidFile));
    		return $this->_pid;
    	}
    }

    /**
     * instance_status
     *
     * @return string
     */
    function instance_status() {
    	return ($this->state == FLUXD_STATE_RUNNING)
    		? $this->instance_sendCommand('status', 1)
    		: "";
    }

    /**
     * instance_modState
     *
     * @param name of service-module
     * @return string with mod-state
     */
    function instance_modState($mod) {
		if (is_null($this->_modList))
			$this->_modList = $this->instance_modListPoll();
    	return $this->_modList[$mod];
    }

    /**
     * instance_modList
     *
     * @return array with mod-list
     */
    function instance_modList() {
		if (is_null($this->_modList))
			$this->_modList = $this->instance_modListPoll();
    	return $this->_modList;
    }

    /**
     * instance_modStatePoll
     *
     * @param name of service-module
     * @return string with mod-state
     */
    function instance_modStatePoll($mod) {
		return ($this->state == FLUXD_STATE_RUNNING)
			? $this->instance_sendCommand('modstate '.$mod, 1)
			: 0;
    }

    /**
     * instance_modListPoll
     *
     * @return array with mod-list
     */
    function instance_modListPoll() {
		global $cfg;
    	$retVal = array();
    	// get modlist
    	if ($this->state == FLUXD_STATE_RUNNING) {
			$mods = trim($this->instance_sendCommand('modlist', 1));
			if (strlen($mods) > 0) {
				$modsAry = explode(FLUXD_DELIM_MOD, $mods);
				foreach ($modsAry as $mod)
					$retVal[substr($mod, 0, -2)] = substr($mod, -1);
			}
    	}
    	// make sure retVal contains cfg modules
    	if ((empty($retVal)) && (!empty($cfg['fluxdServiceModList']))) {
    		foreach ($cfg['fluxdServiceModList'] as $mod)
				$retVal[$mod] = 0;
    	}
    	// return
    	return $retVal;
    }

    /**
     * instance_isReadyToStart
     *
     * @return boolean
     */
    function instance_isReadyToStart() {
		return ($this->state == FLUXD_STATE_RUNNING)
			? false
			: (!($this->instance_sendCommand('worker', 0)));
    }

    /**
     * instance_setConfig
     *
     * @param $key, $value
     * @return Null
     */
    function instance_setConfig($key, $value) {
       if ($this->state == FLUXD_STATE_RUNNING)
           $this->instance_sendCommand('set '.$key.' '.$value, 0);
    }

	/**
	 * instance_reloadDBCache
	 */
    function instance_reloadDBCache() {
		if ($this->state == FLUXD_STATE_RUNNING)
			$this->instance_sendCommand('reloadDBCache', 0);
    }

	/**
	 * instance_reloadModules
	 */
    function instance_reloadModules() {
		if ($this->state == FLUXD_STATE_RUNNING)
			$this->instance_sendCommand('reloadModules', 0);
    }

    /**
     * writes a message to the log
     *
     * @param $message
     * @param $withTS
     * @return boolean
     */
    function instance_logMessage($message, $withTS = true) {
		return $this->_log($this->_pathLogFile, $message, $withTS);
    }

    /**
     * writes a message to the error-log
     *
     * @param $message
     * @param $withTS
     * @return boolean
     */
    function instance_logError($message, $withTS = true) {
		return $this->_log($this->_pathLogFileError, $message, $withTS);
    }

    /**
     * send command
     *
     * @param $command
     * @param $read does this command return something ?
     * @return string with retval or null if error
     */
    function instance_sendCommand($command, $read = 0) {
        if ($this->state == FLUXD_STATE_RUNNING) {
        	// create socket
            $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($socket === false) {
            	array_push($this->messages , "socket_create() failed: reason: ".@socket_strerror(@socket_last_error()));
            	$this->state = FLUXD_STATE_ERROR;
                return null;
            }
            //timeout after n seconds
    		@socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->_socketTimeout, 'usec' => 0));
            // connect
            $result = @socket_connect($socket, $this->_pathSocket);
            if ($result === false) {
            	array_push($this->messages , "socket_connect() failed: reason: ".@socket_strerror(@socket_last_error()));
            	$this->state = FLUXD_STATE_ERROR;
            	@socket_close($socket);
                return null;
            }
            // write command
            $result = @socket_write($socket, $command."\n");
            if ($result === false) {
            	array_push($this->messages , "socket_write() failed: reason: ".@socket_strerror(@socket_last_error()));
            	$this->state = FLUXD_STATE_ERROR;
            	@socket_close($socket);
            	return null;
            }
            // read retval
            $return = "";
            if ($read != 0) {
				do {
					// read data
					$data = @socket_read($socket, 4096, PHP_BINARY_READ);
					if ($data === false) {
						array_push($this->messages , "socket_read() failed: reason: ".@socket_strerror(@socket_last_error()));
						// Don't set global error in case of failure,
						// other calls might still succeed (e.g. in
						// case error was just a read timeout).
						//$this->state = FLUXD_STATE_ERROR;
						@socket_close($socket);
						return null;
					}
					$return .= $data;
				} while (isset($data) && ($data != ""));
            }
            // close socket
            @socket_close($socket);
            // return
            return $return;
        } else { // fluxd not running
        	return null;
        }
    }

    // =========================================================================
	// private methods
	// =========================================================================

    /**
     * _isRunning
     *
     * @return boolean
     */
    function _isRunning() {
    	return file_exists($this->_pathPidFile);
    }

    /**
     * log a message
     *
     * @param $logFile
     * @param $message
     * @param $withTS
     * @return boolean
     */
    function _log($logFile, $message, $withTS = false) {
    	$content = "";
    	if ($withTS)
    		$content .= @date("[Y/m/d - H:i:s]");
    	$content .= '[FRONTEND] ';
    	$content .= $message;
		$fp = false;
		$fp = @fopen($logFile, "a+");
		if (!$fp)
			return false;
		$result = @fwrite($fp, $content);
		@fclose($fp);
		if ($result === false)
			return false;
		return true;
    }

}

?>