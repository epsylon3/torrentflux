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
 * Vlc
 */
class Vlc
{
	// public fields

	// lists
	var $lists = array(
		'vidc' => array(
			'DIV3',
			'DIV4',
			'WMV1',
			'WMV2',
			'RV10',
			'mp1v',
			'mp4v'
		),
		'vbit' => array(
			'192',
			'256',
			'384',
			'512',
			'768',
			'1024',
			'1280',
			'1536',
			'1792',
			'2048'
		),
		'audc' => array(
			'mp3',
			'mp4a',
			'mpga',
			'vorb',
			'flac'
		),
		'abit' => array(
			'64',
			'96',
			'128',
			'192',
			'256',
			'384'
		)
	);

	// addr
	var $addr = "";

	// ports
	var $port_default = 0;
    var $port = 0;

    // private fields

	// command
	var $_command;

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * initialize Vlc.
     */
    function initialize() {
    	global $instanceVlc;
    	// create instance
    	if (!isset($instanceVlc))
    		$instanceVlc = new Vlc();
    }

	/**
	 * getPort
	 *
	 * @return port
	 */
    function getPort() {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// return
		return $instanceVlc->port;
    }

	/**
	 * getAddr
	 *
	 * @return port
	 */
    function getAddr() {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// return
		return $instanceVlc->addr;
    }

    /**
     * getList
     *
     * @param $type
     * @return array
     */
    function getList($type) {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// return
		return (isset($instanceVlc->lists[$type]))
			? $instanceVlc->lists[$type]
			: array();
    }

	/**
	 * start a stream
	 *
	 * @param $file
	 * @param $vidc
	 * @param $vbit
	 * @param $audc
	 * @param $abit
	 */
    function start($file, $vidc, $vbit, $audc, $abit) {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// call instance-method
		$instanceVlc->instance_start($file, $vidc, $vbit, $audc, $abit);
    }

	/**
	 * stop a (/all) stream(s)
	 */
    function stop($port = 0) {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// call instance-method
		$instanceVlc->instance_stop($port);
    }

	/**
	 * get current running stream(s)
	 *
	 * @return array
	 */
    function getRunning($port = 0) {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// call instance-method
		return $instanceVlc->instance_getRunning($port);
    }

	/**
	 * check if a stream is running on host/port
	 *
	 * @param $port
	 * @return boolean
	 */
    function isStreamRunning($port) {
		global $instanceVlc;
		// initialize if needed
		if (!isset($instanceVlc))
			Vlc::initialize();
		// call instance-method
		return $instanceVlc->instance_isStreamRunning($port);
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the factory-methods !
     *
     * @return Vlc
     */
    function Vlc() {
    	global $cfg;
    	$this->addr = $_SERVER['SERVER_ADDR'];
    	$this->port_default = $cfg['vlc_port'];
    	$this->port = $this->port_default;
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * start a stream
	 *
	 * @param $file
	 * @param $vidc
	 * @param $vbit
	 * @param $audc
	 * @param $abit
	 */
    function instance_start($file, $vidc, $vbit, $audc, $abit) {
    	global $cfg;
		// build command
		$this->_command = "nohup";
		$this->_command .= " ".$cfg['bin_vlc'];
		$this->_command .= " --rc-fake-tty";
		$this->_command .= " --sout ".tfb_shellencode("#transcode{vcodec=".$vidc.",vb=".$vbit.",scale=1,acodec=".$audc.",ab=".$abit.",channels=2}:std{access=mmsh,mux=asfh,dst=".$this->addr.":".$this->port."}");
		$this->_command .= " ".tfb_shellencode($file);
		$this->_command .= " > /dev/null &";
		// DEBUG : log the command
		if ($cfg['debuglevel'] > 1)
			AuditAction($cfg["constants"]["debug"], "vlcStart : ".$this->_command);
		// exec command
		exec($this->_command);
    }

	/**
	 * stop a (/all) stream(s)
	 */
    function instance_stop($port = 0) {
    	if ($port == 0) { /* all */
    		@shell_exec("killall -9 vlc > /dev/null");
    	}
    }

	/**
	 * get current running stream(s)
	 *
	 * @return array
	 */
    function instance_getRunning($port = 0) {
		global $cfg;
		$retVal = array();
		if ($port > 0) { /* single */
			$vlcPS = trim(shell_exec("ps x -o pid='' -o ppid='' -o command='' -ww | ".$cfg['bin_grep']." ". $cfg['bin_vlc'] ." | ".$cfg['bin_grep']." ".$port." | ".$cfg['bin_grep']." -v grep"));
			if (strlen($vlcPS > 0)) {
				$tempArray = explode("\n", $vlcPS);
				if ((count($tempArray)) > 0) {
					$streamProcess = array_pop($tempArray);
					$processArray = explode(" ", $streamProcess);
					if ((count($processArray)) > 0) {
						$fileString = array_pop($processArray);
						$fileArray = explode("/", $fileString);
						if ((count($fileArray)) > 0) {
							$tempo = array_pop($fileArray);
							array_push($retVal, $tempo);
						}
					}
				}
			}
		}
		return $retVal;
    }

	/**
	 * check if a stream is running on addr/port
	 *
	 * @param $port
	 * @return boolean
	 */
    function instance_isStreamRunning($port) {
		$fp = false;
		$fp = @fsockopen($this->addr, $port, $errno, $errstr, 1);
		if ($fp === false)
			return false;
		@fclose($fp);
		return true;
    }

}

?>