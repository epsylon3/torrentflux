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
 * class Wrapper for wget-client
 */
class WrapperWget
{
	// private fields

	// vars from args
	var $_transfer = "";
	var $_owner = "";
	var $_path = "";
	var $_drate = 0;
	var $_retries = 0;
	var $_pasv = 0;
	var $_commandFile = "";

	// runtime-vars
	var $_percent_done = 0;
	var $_time_left = "-";
	var $_down_speed = "0.00 kB/s";
	var $_downtotal = 0;
	var $_size = 0;

	// speed as number
	var $_speed = 0;

	// pid
	var $_pid = 0;

	// buffer
	var $_buffer = "";

    // done-flag
    var $_done = false;

	// statfile-object-instance
	var $_sf = null;

	// process-handle
	var $_wget = null;

	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * start
	 *
	 * @param $file
	 * @param $owner
	 * @param $path
	 * @param $drate
	 * @param $retries
	 * @param $pasv
	 */
    function start($file, $owner, $path, $drate, $retries, $pasv) {
		global $instanceWrapperWget;
		$instanceWrapperWget = new WrapperWget($file, $owner, $path, $drate, $retries, $pasv);
		$instanceWrapperWget->instance_start();
    }

    /**
     * stop
     */
    function stop() {
		global $instanceWrapperWget;
		if (isset($instanceWrapperWget))
			$instanceWrapperWget->instance_stop();
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     *
	 * @param $file
	 * @param $owner
	 * @param $path
	 * @param $drate
	 * @param $retries
	 * @param $pasv
     * @return WrapperWget
     */
    function WrapperWget($file, $owner, $path, $drate, $retries, $pasv) {
    	global $cfg;

        // set fields from params
		$this->_transfer = str_replace($cfg['transfer_file_path'], '', $file);
		$this->_owner = $owner;
		$this->_path = $path;
		$this->_drate = $drate;
		$this->_retries = $retries;
		$this->_pasv = $pasv;
		$this->_commandFile = $file.".cmd";

		// set user-var
		$cfg["user"] = $this->_owner;

		// set admin-var
		$cfg['isAdmin'] = IsAdmin($this->_owner);

		// init sf-instance
		$this->_sf = new StatFile($this->_transfer, $this->_owner);
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
		// wrapper start
		$this->_wrapperStart();
		// wrapper main
		$mainRet = $this->_wrapperMain();
		// wrapper stop
		$this->_wrapperStop(!$mainRet);
	}

	/**
	 * instance_stop
	 */
	function instance_stop() {
		// wrapper stop
		$this->_wrapperStop(false);
	}

	// =========================================================================
	// private methods
	// =========================================================================

	/**
	 * start wrapper
	 *
	 * @return boolean
	 */
	function _wrapperStart() {
		global $cfg;

		// print startup
		$this->_outputMessage("wget-wrapper starting up :\n");
		$this->_outputMessage(" - transfer : ".$this->_transfer."\n");
		$this->_outputMessage(" - owner : ".$this->_owner."\n");
		$this->_outputMessage(" - path : ".$this->_path."\n");
		$this->_outputMessage(" - drate : ".$this->_drate."\n");
		$this->_outputMessage(" - retries : ".$this->_retries."\n");
		$this->_outputMessage(" - pasv : ".$this->_pasv."\n");

		// write stat-file
		$this->_statStartup();

		// start client
		if (!$this->_clientStart()) {
			// stop
			$this->_wrapperStop(true);
			// return
			return false;
		}

		// signal-handler
		if (function_exists("pcntl_signal")) {
			$this->_outputMessage("setting up signal-handler...\n");
			pcntl_signal(SIGHUP, array($this, "_sigHandler"));
			pcntl_signal(SIGINT, array($this, "_sigHandler"));
			pcntl_signal(SIGTERM, array($this, "_sigHandler"));
			pcntl_signal(SIGQUIT, array($this, "_sigHandler"));
		}

		// remove command-file if exists
		if (@is_file($this->_commandFile)) {
			// print
			$this->_outputMessage("removing command-file ".$this->_commandFile."...\n");
			// delete command-file
			@unlink($this->_commandFile);
		}

		// return
		return true;
	}

	/**
	 * wrapper main
	 *
	 * @return boolean
	 */
	function _wrapperMain() {

		// print
		$this->_outputMessage("wget up and running\n");

		// write stat with "Connecting..."
		$this->_statRunning($this->_percent_done, "Connecting...", $this->_down_speed, $this->_downtotal);

		// process header
		if ($this->_processHeader() === false) {
			$this->_outputMessage("failed to start download, shutting down... (pid: ".$this->_pid.")\n");
			// print buffer
			$this->_outputMessage("buffer :\n".$this->_buffer);
			return false;
		}

		// check for client before entering main-loop
		if ($this->_clientIsRunning() === false) {
			$this->_outputMessage("wget-client not running, shutting down... (pid: ".$this->_pid.")\n");
			// print buffer
			$this->_outputMessage("buffer :\n".$this->_buffer);
			return false;
		}

		// write stat with "Downloading..."
		$this->_statRunning($this->_percent_done, "Downloading...", $this->_down_speed, $this->_downtotal);

		// flush buffer
		$this->_buffer = "";

		// main loop
		$this->_outputMessage("downloading...\n");
		$tick = 1;
		for (;;) {

			// read to buffer
			if (!@feof($this->_wget))
				$this->_buffer .= @fread($this->_wget, 8192);

			// process buffer
			$this->_done = $this->_processBuffer();

			// return if done
			if ($this->_done)
				return true;

			// process Command Stack, return if quit
			if ($this->_processCommandStack())
				return true;

			// write stat-file every 5 secs
			if (($tick % 5) == 0)
				$this->_statRunning($this->_percent_done, $this->_time_left, $this->_down_speed, $this->_downtotal);

			// check if client is still up once a minute
			if (($tick % 60) == 0) {
				if ($this->_clientIsRunning() === false) {
					$this->_outputMessage("wget-client not running, shutting down... (pid: ".$this->_pid.")\n");
					return false;
				}
			}

			// check buffer-size, truncate if needed
			if (strlen($this->_buffer) > 16384)
				$this->_buffer = substr($this->_buffer, -1024);

			// sleep 1 second and increment tick-count
			sleep(1);
			$tick++;
			if (($tick <= 0) || ($tick >= 2147483647)) $tick = 1;
		}

		// return
		return true;
	}

	/**
	 * stop wrapper
	 *
	 * @param $error
	 */
	function _wrapperStop($error = false) {

		// output
		$this->_outputMessage("wget-wrapper shutting down...\n");

		// stop client
		$this->_clientStop();

		// transfer settings
		stopTransferSettings($this->_transfer);

		// stat
		$this->_statShutdown($error);

		// pid
		$this->_pidFileDelete();

		// output
		$this->_outputMessage("wget-wrapper exit.\n");

		// exit
		exit();
	}

	/**
	 * start client
	 *
	 * @return boolean
	 */
	function _clientStart() {
		global $cfg;

		// print startup
		$this->_outputMessage("starting up wget-client...\n");

		// command-string
		$command = "cd ".tfb_shellencode($this->_path).";";
		$command .= " HOME=".tfb_shellencode($this->_path)."; export HOME;";
		if ($cfg["enable_umask"] != 0)
		    $command .= " umask 0000;";
		if ($cfg["nice_adjust"] != 0)
		    $command .= " nice -n ".$cfg["nice_adjust"];
		$command .= " ".$cfg['bin_wget'];
		$command .= " -c";
		if (($this->_drate != "") && ($this->_drate != "0"))
			$command .= " --limit-rate=" . $this->_drate;
		if ($this->_retries != "")
			$command .= " -t ".$this->_retries;
		if ($this->_pasv == 1)
			$command .= " --passive-ftp";
		$command .= " -i ".tfb_shellencode($cfg['transfer_file_path'].$this->_transfer);
		$command .= " 2>&1"; // direct STDERR to STDOUT
		$command .= " & echo $! > ".tfb_shellencode($cfg['transfer_file_path'].$this->_transfer.".pid"); // write pid-file

		// print startup
		$this->_outputMessage("executing command : \n".$command."\n", true);

		// start process
		$this->_wget = @popen($command, 'r');

		// wait for 0.5 seconds
		usleep(500000);

		// get + set pid
		$this->_pid = getTransferPid($this->_transfer);

		// check for error
		if (($this->_wget === false) || ($this->_clientIsRunning() === false)) {
			// error
			$this->_outputError("error starting up wget-client, shutting down...\n");
			// return
			return false;
		}

		// output
		$this->_outputMessage("wget-client started. (pid: ".$this->_pid.")\n");

		// return
		return true;
	}

	/**
	 * stop client
	 *
	 * @return boolean
	 */
	function _clientStop() {

		// close handle
		if ((!empty($this->_wget)) && (is_resource($this->_wget))) {
			$this->_outputMessage("closing process-handle...\n");
			@pclose($this->_wget);
		}

		// try to kill if running
		if ($this->_clientIsRunning()) {
			// send KILL ("nohup")
			$this->_outputMessage("sending KILL to wget-client... (pid: ".$this->_pid.")\n");
			posix_kill($this->_pid, SIGKILL);
			// wait for 0.25 seconds
			usleep(250000);
			// check if running
			if ($this->_clientIsRunning()) {
				// check if running
				if ($this->_clientIsRunning()) {
					$this->_outputMessage("wget-client still running 0.25 seconds after KILL. (pid: ".$this->_pid.")\n");
					return false;
				}
			}
			// output
			$this->_outputMessage("wget-client stopped. (pid: ".$this->_pid.")\n");
		} else {
			// client not running
			$this->_outputMessage("wget-client not running. (pid: ".$this->_pid.")\n");
		}

		// return
		return true;
	}

	/**
	 * check if client-process is running
	 *
	 * @return boolean
	 */
	function _clientIsRunning() {
		return (strpos(exec('ps -p '.tfb_shellencode($this->_pid)), $this->_pid) !== false);
	}

	/**
	 * process header
	 *
	 * @return boolean
	 */
	function _processHeader() {

		// output
		$this->_outputMessage("starting download...\n");

		// flush buffer
		$this->_buffer = "";

		// read until we find the Length-string which indicates dl-start
		$ctr = 0;
		while (($ctr < 64) && (!@feof($this->_wget))) {

			// read
			$this->_buffer .= @fread($this->_wget, 256);

			// check for error
			if (preg_match("/.*error.*/i", $this->_buffer)) {
				$this->_outputError("error in response.\n");
				// return
				return false;
			}

			// check for Length
			if (preg_match("/.*Length:\s(.+\d)\s(\[|\()/i", $this->_buffer, $matches)) {
				// set size
				$this->_size = str_replace(',','', $matches[1]);
				// set size in stat-file
				$this->_sf->size = $this->_size;
				// return
				return true;
			}

			// wait for 0.25 seconds
			usleep(250000);

			// increment counter
			$ctr++;
		}

		// there were problems
		$this->_outputMessage("problems when processing header...\n");

		// set size from sf
		$this->_outputMessage("try to set size from stat-file...\n");
		if (!empty($this->_sf->size)) {
			$this->_outputMessage("set size from stat-file :".formatBytesTokBMBGBTB($this->_sf->size)."\n");
			$this->_size = $this->_sf->size;
			// return
			return true;
		}

		// give up, then we got no size
		$this->_outputError("failed to get size for download.\n");

		// set size to 0
		$this->_size = 0;

		// set size in stat-file
		$this->_sf->size = $this->_size;

		// return
		return false;
	}

	/**
	 * process buffer
	 *
	 * @return boolean
	 */
	function _processBuffer() {

		// downtotal
		if (preg_match_all("/(\d*)K\s\./i", $this->_buffer, $matches, PREG_SET_ORDER))
			$this->_downtotal = $matches[count($matches) - 1][1] * 1024;

		// percent_done + down_speed + _speed
		if (preg_match_all("/(\d*)%(\s*)(.*)\/s/i", $this->_buffer, $matches, PREG_SET_ORDER)) {
			$matchIdx = count($matches) - 1;
			// percentage
			$this->_percent_done = $matches[$matchIdx][1];
			// speed
			$this->_down_speed = $matches[$matchIdx][3]."/s";
			// we dont want upper-case k
			$this->_down_speed = str_replace("KB/s", "kB/s", $this->_down_speed);
			// size as int + convert MB/s
			$sizeTemp = substr($this->_down_speed, 0, -5);
			if (is_numeric($sizeTemp)) {
				$this->_speed = intval($sizeTemp);
				if (substr($this->_down_speed, -4) == "MB/s") {
					$this->_speed = $this->_speed * 1024;
					$this->_down_speed = $this->_speed." kB/s";
				}
			} else {
				$this->_speed = 0;
				$this->_down_speed = "0.00 kB/s";
			}
		}

		// time left
		$this->_time_left = (($this->_size > 0) && ($this->_speed > 0))
			? convertTime((($this->_size - $this->_downtotal) / 1024) / $this->_speed)
			: '-';

		// download done
		if (preg_match("/.*saved\s\[.*/", $this->_buffer)) {
			$this->_outputMessage("download complete. initializing shutdown...\n");
			// return
			return true;
		}

		// return
		return false;
	}

	/**
	 * process command stack
	 *
	 * @return boolean
	 */
	function _processCommandStack() {

		// check for command-file
		if (@is_file($this->_commandFile)) {
			// print
			$this->_outputMessage("processing command-file ".$this->_commandFile."...\n");
			// read command-file
			$data = @file_get_contents($this->_commandFile);
			// delete command-file
			@unlink($this->_commandFile);
			// process content
	        $commands = @explode("\n", $data);
	        if ((is_array($commands)) && (count($commands > 0))) {
	        	foreach ($commands as $command) {
	        		// exec, early out when reading a quit-command
	        		$command = str_replace("\n", "", $command);
	        		$command = trim($command);
	        		if ($this->_execCommand($command)) {
	        			// return
						return true;
	        		}
	        	}
	        } else {
	        	// no commands found
	        	$this->_outputMessage("No commands found.\n");
	        }
		}

		// return
		return false;
	}

	/**
	 * exec a command
	 *
	 * @param $command
	 * @return boolean
	 */
	function _execCommand($command) {

		// parse command-string
		$len = strlen($command);
		if ($len < 1)
			return false;
		$opcode = $command{0};
		$workload = ($len > 1)
			? substr($command, 1)
			: "";

		// opcode-switch
		switch ($opcode) {
			// q
			case 'q':
				$this->_outputMessage("command: stop-request, initializing shutdown...\n");
				return true;
			// default
			default:
				$this->_outputMessage("op-code unknown: ".$opcode."\n");
				return false;
		}
	}

	/**
	 * stat-file at startup
	 *
	 * @return boolean
	 */
	function _statStartup() {
		// set some values
		$this->_sf->running = 1;
		$this->_sf->percent_done = 0;
		$this->_sf->time_left = "Starting...";
		$this->_sf->down_speed = "0.00 kB/s";
		$this->_sf->up_speed = "0.00 kB/s";
		$this->_sf->transferowner = $this->_owner;
		$this->_sf->seeds = 1;
		$this->_sf->peers = 1;
		$this->_sf->sharing = "";
		$this->_sf->seedlimit = "";
		$this->_sf->uptotal = 0;
		$this->_sf->downtotal = 0;
		// write
		return $this->_sf->write();
	}

	/**
	 * stat-file while running
	 *
	 * @param $percent_done
	 * @param $time_left
	 * @param $down_speed
	 * @param $downtotal
	 * @return boolean
	 */
	function _statRunning($percent_done, $time_left, $down_speed, $downtotal) {
		// set some values
		$this->_sf->percent_done = $percent_done;
		$this->_sf->time_left = $time_left;
		$this->_sf->down_speed = $down_speed;
		$this->_sf->downtotal = $downtotal;
		// write
		return $this->_sf->write();
	}

	/**
	 * stat-file at shutdown
	 *
	 * @param $error
	 * @return boolean
	 */
	function _statShutdown($error = false) {
		// set some values
		$this->_sf->running = 0;
		if ($this->_done) {
			$this->_sf->percent_done = 100;
			$this->_sf->time_left = "Download Succeeded!";
		} else {
			$this->_sf->percent_done = ($this->_size > 0)
				? (((intval((100.0 * $this->_downtotal / $this->_size))) + 100) * (-1))
				: "-100";
			$this->_sf->time_left = "Transfer Stopped";
		}
		if ($error)
			$this->_sf->time_left = "Error";
		$this->_sf->down_speed = "";
		$this->_sf->up_speed = "";
		$this->_sf->transferowner = $this->_owner;
		$this->_sf->seeds = "";
		$this->_sf->peers = "";
		$this->_sf->sharing = "";
		$this->_sf->seedlimit = "";
		$this->_sf->uptotal = 0;
		$this->_sf->downtotal = $this->_downtotal;
		// write
		return $this->_sf->write();
	}

	/**
	 * delete the pid-file
	 */
	function _pidFileDelete() {
		global $cfg;
		if (@file_exists($cfg['transfer_file_path'].$this->_transfer.".pid")) {
			$this->_outputMessage("removing pid-file : ".$cfg['transfer_file_path'].$this->_transfer.".pid\n");
			@unlink($cfg['transfer_file_path'].$this->_transfer.".pid");
		}
	}

	/**
	 * signal-handler
	 *
	 * @param $signal
	 */
	function _sigHandler($signal) {
		switch ($signal) {
			// HUP
			case SIGHUP:
				$this->_outputMessage("got SIGHUP, ignoring...\n");
				break;
			// INT
			case SIGINT:
				$this->_outputMessage("got SIGINT, shutting down...\n");
				$this->_wrapperStop(false);
				break;
			// TERM
			case SIGTERM:
				$this->_outputMessage("got SIGTERM, shutting down...\n");
				$this->_wrapperStop(false);
				break;
			// QUIT
			case SIGQUIT:
				$this->_outputMessage("got SIGQUIT, shutting down...\n");
				$this->_wrapperStop(false);
				break;
		}
	}

    /**
     * output message
     *
     * @param $message
     */
	function _outputMessage($message) {
		@fwrite(STDOUT, @date("[Y/m/d - H:i:s]")." ".$message);
    }

    /**
     * output error
     *
     * @param $message
     */
	function _outputError($message) {
		@fwrite(STDERR, @date("[Y/m/d - H:i:s]")." ".$message);
    }

}

?>