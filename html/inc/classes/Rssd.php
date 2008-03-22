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
define('RSSD_STATE_NULL', 0);                                            // null
define('RSSD_STATE_OK', 1);                                                // ok
define('RSSD_STATE_ERROR', -1);                                         // error

// modes
define('RSSD_MODE_CLI', 1);                                               // cli
define('RSSD_MODE_WEB', 2);                                               // web

// require SimpleHTTP
require_once("inc/classes/SimpleHTTP.php");

// require lastRSS
require_once("inc/classes/lastRSS.php");

/**
 * Rssd
 */
class Rssd
{
	// public fields
	var $name = "Rssd";

    // state
    var $state = RSSD_STATE_NULL;

    // messages-array
    var $messages = array();

    // private fields

    // mode
    var $_mode = 0;

	// job-vars
	var $_fileFilters = "";
	var $_fileHistory = "";
	var $_dirSave = "";
	var $_urlRSS = "";

	// filters
	var $_filters = array();

	// history
	var $_history = array();
	var $_historyNew = array();

	// data
	var $_data = array();

	// saved files
	var $_filesSaved = array();

	// lastRSS-instance
	var $_lastRSS;

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return Rssd
     */
    function getInstance() {
		global $instanceRssd;
		// initialize if needed
		if (!isset($instanceRssd))
			Rssd::initialize();
		return $instanceRssd;
    }

    /**
     * initialize Rssd.
     */
    function initialize() {
    	global $instanceRssd;
    	// create instance
    	if (!isset($instanceRssd))
    		$instanceRssd = new Rssd();
    }

	/**
	 * getState
	 *
	 * @return state
	 */
    function getState() {
		global $instanceRssd;
		return (isset($instanceRssd))
			? $instanceRssd->state
			: RSSD_STATE_NULL;
    }

    /**
     * getMessages
     *
     * @return array
     */
    function getMessages() {
		global $instanceRssd;
		return (isset($instanceRssd))
			? $instanceRssd->messages
			: array();
    }

	/**
	 * process a feed
	 *
	 * @param $tdir
	 * @param $filter
	 * @param $hist
	 * @param $url
	 * @return boolean
	 */
    function processFeed($sdir, $filter, $hist, $url) {
		global $instanceRssd;
		// initialize if needed
		if (!isset($instanceRssd))
			Rssd::initialize();
		// call instance-method
		return $instanceRssd->instance_processFeed($sdir, $filter, $hist, $url);
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the factory-methods !
     *
     * @return Rssd
     */
    function Rssd() {
    	global $argv;
        // cli/web
		$this->_mode = (empty($argv[0]))
			? RSSD_MODE_WEB
			: RSSD_MODE_CLI;
        // init lastRSS-instance
		$this->_lastRSS = new lastRSS();
		$this->_lastRSS->cache_dir = '';
		$this->_lastRSS->stripHTML = false;
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * process a feed
	 *
	 * @param $sdir
	 * @param $filter
	 * @param $hist
	 * @param $url
	 * @return boolean
	 */
    function instance_processFeed($sdir, $filter, $hist, $url) {
    	// (re)set state
    	$this->state = RSSD_STATE_NULL;
   		// validate
   		if (!checkDirectory($sdir, 0777)) {
            $this->state = RSSD_STATE_ERROR;
            $msg = "Save-Dir ".$sdir." not valid.";
            array_push($this->messages , $msg);
			$this->_outputError($msg."\n");
            return false;
   		}
   		if (!is_file($filter)) {
            $this->state = RSSD_STATE_ERROR;
            $msg = "Filter-File ".$filter." not valid.";
            array_push($this->messages , $msg);
			$this->_outputError($msg."\n");
            return false;
   		}
		// output
		$this->_outputMessage("Processing feed ".$url." ...\n");
    	// set vars
    	$this->_dirSave = checkDirPathString($sdir);
    	$this->_fileFilters = $filter;
    	$this->_fileHistory = $hist;
    	$this->_urlRSS = $url;
    	$this->_filters = array();
    	$this->_history = array();
    	$this->_historyNew = array();
    	$this->_data = array();
    	$this->_filesSaved = array();
		// load _filters
		if (!$this->_loadFilters())
			return false;
		// load history
		if (!$this->_loadHistory())
			return false;
		// load data
		if (!$this->_loadData())
			return false;
		// something to do ?
		if ($this->_data['items_count'] <= 0) { // no
			// state
			$this->state = RSSD_STATE_OK;
			return true;
		}
		// process data
		if (!$this->_processData())
			return false;
		// update history
		if (!$this->_updateHistory())
			return false;
		// state
		$this->state = RSSD_STATE_OK;
		// output
		$this->_outputMessage("feed processed. downloaded and saved ".count($this->_filesSaved)." torrents.\n");
		// return
		return true;
    }

	// =========================================================================
	// private methods
	// =========================================================================

    /**
     * load filters
     *
     * @return boolean
     */
	function _loadFilters() {
		$fifi = file($this->_fileFilters);
		$this->_filters = array_map('rtrim', $fifi);
		return true;
    }

    /**
     * load history
     *
     * @return boolean
     */
	function _loadHistory() {
		if (is_file($this->_fileHistory)) {
			$fihi = file($this->_fileHistory);
			$this->_history = array_map('rtrim', $fihi);
		} else {
			$this->_history = array();
		}
		return true;
    }

    /**
     * load data
     *
     * @return boolean
     */
	function _loadData() {
		$this->_data = $this->_lastRSS->Get($this->_urlRSS);
		if ($this->_data !== false) {
			return (empty($this->_data) === false);
		} else {
            $msg = "Problem getting feed-data from ".$this->_urlRSS;
            array_push($this->messages , $msg);
			$this->_outputError($msg."\n");
            return false;
		}
    }

    /**
     * process data
     *
     * @return boolean
     */
	function _processData() {
		$itemCount = count($this->_data["items"]);
		// filter-loop
		foreach ($this->_filters as $filter) {
			// output filtername
			$this->_outputMessage("***** ".$filter." *****\n");
			// item-loop
			for ($i = 0; $i < $itemCount; $i++) {
				// skip feed items without a link or title:
				if (!isset($this->_data["items"][$i]["link"]) || empty($this->_data["items"][$i]["link"]))
					continue;
				if (!isset($this->_data["items"][$i]["title"]) || empty($this->_data["items"][$i]["title"]))
					continue;
				// local vars
				$link = html_entity_decode($this->_data["items"][$i]["link"]);
				$title = $this->_data["items"][$i]["title"];
				// check if we have a match
				if (preg_match('/'.$filter.'/i', $title)) {
					// if not in history, process it
					if (!in_array($title, $this->_history)) {
						// output
						$this->_outputMessage("new match for filter '".$filter."' : ".$title."\n");
						// download and save
						if ($this->_saveTorrent($link, $title) === true) {
							// add to history
							array_push($this->_history, $title);
							array_push($this->_historyNew, $title);
						}
					}
				}
			}
		}
		// return
		return true;
    }

    /**
     * update history
     *
     * @return boolean
     */
	function _updateHistory() {
		global $cfg;
		if (count($this->_historyNew) > 0) {
			// write file
			$handle = false;
			$handle = @fopen($this->_fileHistory, "a");
			if (!$handle) {
				$this->state = RSSD_STATE_ERROR;
	            $msg = "cannot open history ".$this->_fileHistory." for writing.";
	            array_push($this->messages , $msg);
	            AuditAction($cfg["constants"]["error"], "Rssd _updateHistory-Error : ".$msg);
				$this->_outputError($msg."\n");
				return false;
			}
	        $result = @fwrite($handle, implode("\n", $this->_historyNew)."\n");
			@fclose($handle);
			if ($result === false) {
				$this->state = RSSD_STATE_ERROR;
	            $msg = "cannot write content to history ".$this->_fileHistory.".";
	            array_push($this->messages , $msg);
	            AuditAction($cfg["constants"]["error"], "Rssd _updateHistory-Error : ".$msg);
				$this->_outputError($msg."\n");
				return false;
			}
		}
		// return
		return true;
    }

    /**
     * download and save a torrent-file
     *
     * @return boolean
     */
	function _saveTorrent($url, $title) {
		global $cfg;
		$content = SimpleHTTP::getTorrent($url);
		if (SimpleHTTP::getState() == SIMPLEHTTP_STATE_OK) {
			// filename
			$filename = SimpleHTTP::getFilename();
			if ($filename != "") {
				$filename = ((strpos($filename, ".torrent") !== false))
					? tfb_cleanFileName($filename)
					: tfb_cleanFileName($filename.".torrent");
			}
			if (($filename == "") || ($filename === false) || (transferExists($filename))) {
				$filename = tfb_cleanFileName($title.".torrent");
				if (($filename === false) || (transferExists($filename))) {
					$filename = tfb_cleanFileName($url.".torrent");
					if (($filename === false) || (transferExists($filename))) {
						$filename = tfb_cleanFileName(md5($url.strval(@microtime())).".torrent");
						if (($filename === false) || (transferExists($filename))) {
							// Error
							$msg = "failed to get a valid transfer-filename for ".$url;
							array_push($this->messages , $msg);
							AuditAction($cfg["constants"]["error"], "Rssd downloadMetafile-Error : ".$msg);
							$this->_outputError($msg."\n");
							return false;
						}
					}
				}
			}
			// file
			$file = $this->_dirSave.$filename;
			// check if file already exists
			if (@is_file($file)) {
				// Error
	            $msg = "the file ".$file." already exists in ".$this->_dirSave;
	            array_push($this->messages , $msg);
	            AuditAction($cfg["constants"]["error"], "Rssd downloadMetafile-Error : ".$msg);
				$this->_outputError($msg."\n");
				return false;
			}
			// write file
			$handle = false;
			$handle = @fopen($file, "w");
			if (!$handle) {
	            $msg = "cannot open ".$file." for writing.";
	            array_push($this->messages , $msg);
				AuditAction($cfg["constants"]["error"], "Rssd downloadMetafile-Error : ".$msg);
				$this->_outputError($msg."\n");
				return false;
			}
	        $result = @fwrite($handle, $content);
			@fclose($handle);
			if ($result === false) {
	            $msg = "cannot write content to ".$file.".";
	            array_push($this->messages , $msg);
				AuditAction($cfg["constants"]["error"], "Rssd downloadMetafile-Error : ".$msg);
				$this->_outputError($msg."\n");
				return false;
			}
			// add to file-array
			array_push($this->_filesSaved, array(
				'url' => $url,
				'title' => $title,
				'filename' => $filename,
				'file' => $file
				)
			);
			// output
			$this->_outputMessage("torrent saved : \n url: ".$url."\n file: ".$file."\n");
			// return
			return true;
		} else {
			// last op was not ok
			$msgs = SimpleHTTP::getMessages();
			$this->_outputError("could not download torrent with title ".$title." from url ".$url." : \n".implode("\n", $msgs));
			return false;
		}
    }

    /**
     * output message
     *
     * @param $message
     */
	function _outputMessage($message) {
        // only in cli-mode
		if ($this->_mode == RSSD_MODE_CLI)
			printMessage($this->name, $message);
    }

    /**
     * output error
     *
     * @param $message
     */
	function _outputError($message) {
        // only in cli-mode
		if ($this->_mode == RSSD_MODE_CLI)
			printError($this->name, $message);
    }

}

?>