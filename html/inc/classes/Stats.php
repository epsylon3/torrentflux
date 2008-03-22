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
 * Stats
 */
class Stats
{
    // private fields

	// ids of server-details
	var $_serverIds = array(
		"speedDown",          /*  0 */
		"speedUp",            /*  1 */
		"speedTotal",         /*  2 */
		"cons",               /*  3 */
		"freeSpace",          /*  4 */
		"loadavg",            /*  5 */
		"running",            /*  6 */
		"queued",             /*  7 */
		"speedDownPercent",   /*  8 */
		"speedUpPercent",     /*  9 */
		"driveSpacePercent"   /* 10 */
	);
	var $_serverIdCount = 11;

	// ids of transfer-details
	var $_transferIds = array(
		"running",            /*  0 */
		"speedDown",          /*  1 */
		"speedUp",            /*  2 */
		"downCurrent",        /*  3 */
		"upCurrent",          /*  4 */
		"downTotal",          /*  5 */
		"upTotal",            /*  6 */
		"percentDone",        /*  7 */
		"sharing",            /*  8 */
		"eta",                /*  9 */
		"seeds",              /* 10 */
		"peers",              /* 11 */
		"cons"                /* 12 */
	);
	var $_transferIdCount = 13;

	// ids of xfer-details
	var $_xferIds = array(
		"xferGlobalTotal",    /* 0 */
		"xferGlobalMonth",    /* 1 */
		"xferGlobalWeek",     /* 2 */
		"xferGlobalDay",      /* 3 */
		"xferUserTotal",      /* 4 */
		"xferUserMonth",      /* 5 */
		"xferUserWeek",       /* 6 */
		"xferUserDay"         /* 7 */
	);
	var $_xferIdCount = 8;

	// ids of user-details
	var $_userIds = array(
		"state"               /* 0 */
	);
	var $_userIdCount = 1;

    // stats-fields
   	var $_serverLabels = array();
   	var $_xferLabels = array();
   	var $_transferList = array();
   	var $_transferHeads = array();
   	var $_serverStats = array();
   	var $_xferStats = array();
   	var $_transferDetails = array();
   	var $_userList = array();
   	var $_userCount = 0;
   	var $_transferID = "";

    // options
    var $_type = "";
    var $_format = "";
    var $_header = 0;
    var $_compressed = 0;
    var $_attachment = 0;

    // content
    var $_indent = "";
    var $_content = "";

	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * process a request
	 *
	 * @param $params
	 */
    function processRequest($params) {
    	// create new instance
    	$instanceStats = new Stats($params);
		// call instance-method
		$instanceStats->instance_processRequest();
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the public static methods !
     *
	 * @param $params
     * @return Stats
     */
    function Stats($params) {
    	global $cfg;

		// type
		$this->_type = (isset($params["t"]))
			? htmlentities(trim($params["t"]), ENT_QUOTES)
			: $cfg['stats_default_type'];

		// format
		$this->_format = (isset($params["f"]))
			? htmlentities(trim($params["f"]), ENT_QUOTES)
			: $cfg['stats_default_format'];

		// header
		$this->_header = (isset($params["h"]))
			? htmlentities(trim($params["h"]), ENT_QUOTES)
			: $cfg['stats_default_header'];

		// compressed
		$this->_compressed = (isset($params["c"]))
			? htmlentities(trim($params["c"]), ENT_QUOTES)
			: $cfg['stats_default_compress'];

		// attachment
		$this->_attachment = (isset($params["a"]))
			? htmlentities(trim($params["a"]), ENT_QUOTES)
			: $cfg['stats_default_attach'];

		// transfer-id
		$this->_transferID = (isset($params["i"]))
			? htmlentities(trim($params["i"]), ENT_QUOTES)
			: "";

		// usage ?
		if (isset($params["usage"])) {
			$this->_type = "usage";
		} else {
			if (($cfg['stats_show_usage'] == 1) && (count($_GET) == 0))
				$this->_type = "usage";
		}

    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * process a request
	 */
    function instance_processRequest() {
    	global $cfg, $db;

		// type-switch
		switch ($this->_type) {
		    case "all":
		    	if (!(($this->_format == "txt") && ($this->_header == 0)))
		    		$this->_transferHeads = getTransferListHeadArray();
		    	$this->_indent = " ";
		    	// xfer-init
		    	if ($cfg['xfer_realtime'] == 0) {
					$cfg['xfer_realtime'] = 1;
					// set xfer-newday
					Xfer::setNewday();
		    	}
		    	$this->_transferList = getTransferListArray();
		    	$this->_initServerStats();
		    	$this->_initXferStats();
		    	$this->_initUserStats();
		    	break;
		    case "server":
		    	$this->_indent = "";
		    	$this->_transferList = getTransferListArray();
		    	$this->_initServerStats();
		    	break;
		    case "xfer":
		    	$this->_indent = "";
		    	// xfer-init
		    	if ($cfg['xfer_realtime'] == 0) {
					$cfg['xfer_realtime'] = 1;
					// set xfer-newday
					Xfer::setNewday();
		    	}
		    	$this->_transferList = getTransferListArray();
		    	$this->_initXferStats();
		    	break;
		    case "transfers":
		    	$this->_indent = "";
		    	$this->_transferList = getTransferListArray();
		    	if (!(($this->_format == "txt") && ($this->_header == 0)))
		    		$this->_transferHeads = getTransferListHeadArray();
		    	break;
		    case "transfer":
				// transfer-id
				if (empty($this->_transferID))
					@error("missing params", "stats.php", "", array('i'));
				// validate transfer
				if (tfb_isValidTransfer($this->_transferID) !== true) {
					AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$this->_transferID);
					@error("Invalid Transfer", "", "", array($this->_transferID));
				}
		    	$this->_indent = "";
		    	$this->_transferDetails = getTransferDetails($this->_transferID, false);
		    	break;
		    case "users":
		    	$this->_indent = "";
		    	$this->_initUserStats();
		    	break;
		    case "usage":
		    	$this->_sendUsage();
		}

		// action
		switch ($this->_format) {
			case "xml":
				$this->_sendXML();
			case "rss":
				$this->_sendRSS();
			case "txt":
				$this->_sendTXT();
		}
    }

	// =========================================================================
	// private methods
	// =========================================================================

	/**
	 * sends current content
	 *
	 * @param $contentType
	 * @param $fileName
	 * @param $sendCompressed
	 * @param $sendAsAttachment
	 */
	function _sendContent($contentType, $fileName, $sendCompressed, $sendAsAttachment) {
		global $cfg;
	    // send content
	    @header("Cache-Control: no-cache");
	    @header("Pragma: no-cache");
	    if ($sendCompressed != 0) {
	    	$contentCompressed = gzdeflate($this->_content, $cfg['stats_deflate_level']);
			@header("Content-Type: application/octet-stream");
			if ($sendAsAttachment != 0) {
				@header("Content-Length: " .(string)(strlen($contentCompressed)) );
				@header('Content-Disposition: attachment; filename="'.$fileName.'"');
			}
			@header("Content-Transfer-Encoding: binary\n");
			echo $contentCompressed;
	    } else {
		    @header("Content-Type: ".$contentType);
		    if ($sendAsAttachment != 0) {
		        @header("Content-Length: ".(string)strlen($this->_content));
		        @header('Content-Disposition: attachment; filename="'.$fileName.'"');
		    }
		    echo $this->_content;
	    }
	    exit();
	}

	/**
	 * This method sends stats as xml.
	 * xml-schema defined in tfbstats.xsd/tfbserver.xsd/tfbxfer.xsd/tfbtransfers.xsd/tfbtransfer.xsd/tfbusers.xsd
	 */
	function _sendXML() {
	    // build content
		$this->_content = '<?xml version="1.0" encoding="utf-8"?>'."\n";
		switch ($this->_type) {
			case "all":
				$this->_content .= '<tfbstats>'."\n";
				break;
		}
		// server stats
		switch ($this->_type) {
		    case "all":
		    case "server":
		    	$this->_content .= $this->_indent.'<server>'."\n";
				for ($i = 0; $i < $this->_serverIdCount; $i++)
					$this->_content .= $this->_indent.' <serverStat name="'.$this->_serverIds[$i].'">'.$this->_serverStats[$i].'</serverStat>'."\n";
				$this->_content .= $this->_indent.'</server>'."\n";
		}
		// xfer stats
		switch ($this->_type) {
		    case "all":
		    case "xfer":
		    	$this->_content .= $this->_indent.'<xfer>'."\n";
				for ($i = 0; $i < $this->_xferIdCount; $i++)
					$this->_content .= $this->_indent.' <xferStat name="'.$this->_xferIds[$i].'">'.$this->_xferStats[$i].'</xferStat>'."\n";
				$this->_content .= $this->_indent.'</xfer>'."\n";
		}
	    // user-list
		switch ($this->_type) {
		    case "all":
		    case "users":
			    $this->_content .= $this->_indent.'<users>'."\n";
				foreach ($this->_userList as $userAry) {
					$this->_content .= $this->_indent.' <user name="'.$userAry[0].'">'."\n";
					for ($i = 0; $i < $this->_userIdCount; $i++)
						$this->_content .= $this->_indent.'  <userProp name="'.$this->_userIds[$i].'">'.$userAry[$i + 1].'</userProp>'."\n";
					$this->_content .= $this->_indent.' </user>'."\n";
				}
			    $this->_content .= $this->_indent.'</users>'."\n";
		}
	    // transfer-list
		switch ($this->_type) {
		    case "all":
		    case "transfers":
			    $this->_content .= $this->_indent.'<transfers>'."\n";
				foreach ($this->_transferList as $transferAry) {
					$this->_content .= $this->_indent.' <transfer name="'.$transferAry[0].'">'."\n";
					$size = count($transferAry);
					for ($i = 1; $i < $size; $i++)
						$this->_content .= $this->_indent.'  <transferStat name="'.$this->_transferHeads[$i-1].'">'.$transferAry[$i].'</transferStat>'."\n";
					$this->_content .= $this->_indent.' </transfer>'."\n";
				}
			    $this->_content .= $this->_indent.'</transfers>'."\n";
		}
		// transfer-details
		switch ($this->_type) {
		    case "transfer":
				$this->_content .= $this->_indent.'<transfer name="'.$this->_transferID.'">'."\n";
				for ($i = 0; $i < $this->_transferIdCount; $i++)
					$this->_content .= $this->_indent.' <transferStat name="'.$this->_transferIds[$i].'">'.$this->_transferDetails[$this->_transferIds[$i]].'</transferStat>'."\n";
				$this->_content .= $this->_indent.'</transfer>'."\n";
		}
	    // end document
		switch ($this->_type) {
			case "all":
				$this->_content .= '</tfbstats>'."\n";
				break;
		}
	    // send content
	    $this->_sendContent("text/xml", "stats.xml", $this->_compressed, $this->_attachment);
	}

	/**
	 * This method sends stats as rss 0.91.
	 */
	function _sendRSS() {
	    // build content
	    $this->_content = "<?xml version='1.0' ?>\n\n";
	    $this->_content .= "<rss version=\"0.91\">\n";
	    $this->_content .= " <channel>\n";
	    $this->_content .= "  <title>torrentflux Stats</title>\n";
	    // server stats
		switch ($this->_type) {
		    case "all":
		    case "server":
			    $this->_content .= "   <item>\n";
			    $this->_content .= "    <title>Server Stats</title>\n";
			    $this->_content .= "    <description>";
				for ($i = 0; $i < $this->_serverIdCount; $i++) {
					$this->_content .= $this->_serverLabels[$i].": ".$this->_serverStats[$i];
					if ($i < ($this->_serverIdCount - 1))
						$this->_content .= " || ";
				}
			    $this->_content .= "    </description>\n";
			    $this->_content .= "   </item>\n";
		}
		// xfer stats
		switch ($this->_type) {
		    case "all":
		    case "xfer":
			    $this->_content .= "   <item>\n";
			    $this->_content .= "    <title>Xfer Stats</title>\n";
			    $this->_content .= "    <description>";
				for ($i = 0; $i < $this->_xferIdCount; $i++) {
					$this->_content .= $this->_xferLabels[$i].": ".$this->_xferStats[$i];
					if ($i < ($this->_xferIdCount - 1))
						$this->_content .= " || ";
				}
			    $this->_content .= "    </description>\n";
			    $this->_content .= "   </item>\n";
		}
	    // user-list
		switch ($this->_type) {
		    case "all":
		    case "users":
			    $this->_content .= "   <item>\n";
			    $this->_content .= "    <title>Users</title>\n";
			    $this->_content .= "    <description>";
				for ($i = 0; $i < $this->_userCount; $i++) {
					$this->_content .= $this->_userList[$i][0].": ";
					for ($j = 1; $j <= $this->_userIdCount; $j++) {
						$this->_content .= $this->_userList[$i][$j];
						if ($j < ($this->_userIdCount - 1))
							$this->_content .= ", ";
					}
					if ($i < ($this->_userCount - 1))
						$this->_content .= " || ";
				}
			    $this->_content .= "    </description>\n";
			    $this->_content .= "   </item>\n";
		}
		// transfer-list
		switch ($this->_type) {
		    case "all":
		    case "transfers":
				foreach ($this->_transferList as $transferAry) {
					$this->_content .= "   <item>\n";
					$this->_content .= "    <title>Transfer: ".$transferAry[0]."</title>\n";
					$this->_content .= "    <description>";
					$size = count($transferAry);
					for ($i = 1; $i < $size; $i++) {
						$this->_content .= $this->_transferHeads[$i-1].': '.$transferAry[$i];
						if ($i < ($size - 1))
							$this->_content .= " || ";
					}
					$this->_content .= "    </description>\n";
					$this->_content .= "   </item>\n";
				}
		}
		// transfer-details
		switch ($this->_type) {
		    case "transfer":
				$this->_content .= "   <item>\n";
				$this->_content .= "    <title>Transfer: ".$this->_transferID."</title>\n";
				$this->_content .= "    <description>";
				for ($i = 0; $i < $this->_transferIdCount; $i++) {
					$this->_content .= $this->_transferIds[$i].': '.$this->_transferDetails[$this->_transferIds[$i]];
					if ($i < ($this->_transferIdCount - 1))
						$this->_content .= " || ";
				}
				$this->_content .= "    </description>\n";
				$this->_content .= "   </item>\n";
		}
	    // end document
	    $this->_content .= " </channel>\n";
	    $this->_content .= "</rss>";
	    // send content
	    $this->_sendContent("text/xml", "stats.xml", $this->_compressed, $this->_attachment);
	}

	/**
	 * This method sends stats as txt.
	 */
	function _sendTXT() {
	    global $cfg;
	    // build content
	    $this->_content = "";
		// server stats
		switch ($this->_type) {
		    case "all":
		    case "server":
		    	if ($this->_header == 1) {
					for ($j = 0; $j < $this->_serverIdCount; $j++) {
						$this->_content .= $this->_serverLabels[$j];
						if ($j < ($this->_serverIdCount - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
					$this->_content .= "\n";
		    	}
				for ($i = 0; $i < $this->_serverIdCount; $i++) {
					$this->_content .= $this->_serverStats[$i];
					if ($i < ($this->_serverIdCount - 1))
						$this->_content .= $cfg['stats_txt_delim'];
				}
				$this->_content .= "\n";
		}
		// xfer stats
		switch ($this->_type) {
		    case "all":
		    case "xfer":
		    	if ($this->_header == 1) {
					for ($j = 0; $j < $this->_xferIdCount; $j++) {
						$this->_content .= $this->_xferLabels[$j];
						if ($j < ($this->_xferIdCount - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
					$this->_content .= "\n";
		    	}
				for ($i = 0; $i < $this->_xferIdCount; $i++) {
					$this->_content .= $this->_xferStats[$i];
					if ($i < ($this->_xferIdCount - 1))
						$this->_content .= $cfg['stats_txt_delim'];
				}
				$this->_content .= "\n";
		}
	    // user-list
		switch ($this->_type) {
		    case "all":
		    case "users":
		    	if ($this->_header == 1) {
			    	$this->_content .= "name" . $cfg['stats_txt_delim'];
					for ($j = 0; $j < $this->_userIdCount; $j++) {
						$this->_content .= $this->_userIds[$j];
						if ($j < ($this->_userIdCount - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
			    	$this->_content .= "\n";
		    	}
				for ($i = 0; $i < $this->_userCount; $i++) {
					$this->_content .= $this->_userList[$i][0].$cfg['stats_txt_delim'];
					for ($j = 1; $j <= $this->_userIdCount; $j++) {
						$this->_content .= $this->_userList[$i][$j];
						if ($j < ($this->_userIdCount - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
					$this->_content .= "\n";
				}
		}
	    // transfer-list
		switch ($this->_type) {
		    case "all":
		    case "transfers":
		    	if ($this->_header == 1) {
			    	$this->_content .= "Name" . $cfg['stats_txt_delim'];
			    	$sizeHead = count($this->_transferHeads);
					for ($j = 0; $j < $sizeHead; $j++) {
						$this->_content .= $this->_transferHeads[$j];
						if ($j < ($sizeHead - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
			    	$this->_content .= "\n";
		    	}
				foreach ($this->_transferList as $transferAry) {
					$size = count($transferAry);
					for ($i = 0; $i < $size; $i++) {
						$this->_content .= $transferAry[$i];
						if ($i < ($size - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
					$this->_content .= "\n";
				}
		}
		// transfer-details
		switch ($this->_type) {
		    case "transfer":
		    	if ($this->_header == 1) {
					for ($j = 0; $j < $this->_transferIdCount; $j++) {
						$this->_content .= $this->_transferIds[$j];
						if ($j < ($this->_transferIdCount - 1))
							$this->_content .= $cfg['stats_txt_delim'];
					}
			    	$this->_content .= "\n";
		    	}
				for ($i = 0; $i < $this->_transferIdCount; $i++) {
					$this->_content .= $this->_transferDetails[$this->_transferIds[$i]];
					if ($i < ($this->_transferIdCount - 1))
						$this->_content .= $cfg['stats_txt_delim'];
				}
				$this->_content .= "\n";
		}
	    // send content
	    $this->_sendContent("text/plain", "stats.txt", $this->_compressed, $this->_attachment);
	}

	/**
	 * init server stats
	 * note : this can only be used after a call to update transfer-values in cfg-
	 *        array (eg by getTransferListArray)
	 */
	function _initServerStats() {
		// init labels
		$this->_serverLabels = array(
			"Speed Down",
			"Speed Up",
			"Speed Total",
			"Connections",
			"Free Space",
			"Load",
			"Running",
			"Queued",
			"Speed Down (Percent)",
			"Speed Up (Percent)",
			"Drive Space (Percent)"
		);
		$this->_serverStats = getServerStats();
	}

	/**
	 * init xfer stats
	 * note : this can only be used after a call to update transfer-values in cfg-
	 *        array (eg by getTransferListArray)
	 */
	function _initXferStats() {
		global $cfg;
		// init labels
		$this->_xferLabels = array(
			'Server : '.$cfg['_TOTALXFER'],
			'Server : '.$cfg['_MONTHXFER'],
			'Server : '.$cfg['_WEEKXFER'],
			'Server : '.$cfg['_DAYXFER'],
			'User : '.$cfg['_TOTALXFER'],
			'User : '.$cfg['_MONTHXFER'],
			'User : '.$cfg['_WEEKXFER'],
			'User : '.$cfg['_DAYXFER']
		);
		$this->_xferStats = Xfer::getStatsFormatted();
	}

	/**
	 * init user stats
	 */
	function _initUserStats() {
		global $cfg;
		$this->_userList = array();
		$this->_userCount = count($cfg['users']);
		for ($i = 0; $i < $this->_userCount; $i++) {
			$userAry = array();
			// name
			array_push($userAry, $cfg['users'][$i]);
			// state
			if (IsOnline($cfg['users'][$i]))
				array_push($userAry, "online");
			else
				array_push($userAry, "offline");
			// add user to list
			array_push($this->_userList, $userAry);
		}
	}

    /**
     * sends usage
     */
    function _sendUsage() {
    	global $cfg;
    	// content
		$url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
		$this->_content = '

Params :

"t" : type : optional, default is "'.$cfg['stats_default_type'].'"
      "all"        : server + xfer + transfers + users
      "server"     : server-stats
      "xfer"       : xfer-stats
      "users"      : users-stats
      "transfers"  : transfer-stats
      "transfer"   : transfer-stats of a single transfer. needs extra-param "i" with the name of the transfer
"f" : format : optional, default is "'.$cfg['stats_default_format'].'"
      "xml"        : new xml-formats, see xml-schemas in dir "xml"
      "rss"        : rss 0.91
      "txt"        : csv-formatted text
"h" : header : optional, only used in txt-format, default is "'.$cfg['stats_default_header'].'"
      "0"          : send header
      "1"          : dont send header.
"a" : send as attachment : optional, default is "'.$cfg['stats_default_attach'].'"
      "0"          : dont send as attachment
      "1"          : send as attachment
"c" : send compressed (deflate) : optional, default is "'.$cfg['stats_default_compress'].'"
      "0"          : dont send compressed
      "1"          : send compressed (deflate)

Examples :

* '.$url.'?t=all&f=xml              :  all stats sent as xml
* '.$url.'?t=server&f=xml&a=1       :  server stats as xml sent as attachment
* '.$url.'?t=transfers&f=xml&c=1    :  transfer stats as xml sent compressed
* '.$url.'?t=all&f=rss              :  all stats sent as rss
* '.$url.'?t=all&f=txt&h=0          :  all stats sent as txt without headers
* '.$url.'?t=xfer&f=txt&a=1&c=1     :  xfer stats as text sent as compressed attachment

* '.$url.'?t=transfer&i=foo.torrent        :  transfer-stats of foo sent in default-format
* '.$url.'?t=transfer&i=bar.torrent&f=xml  :  transfer-stats of bar sent as xml

* '.$url.'?t=all&f=xml&username=admin&iamhim=seceret                            :  all stats sent as xml. use auth-credentials "admin/seceret"
* '.$url.'?t=all&f=rss&username=admin&md5pass=dc5c74cfa3ba35eb87cf597a60fa756c  :  all stats sent as rss. use auth-credentials "admin/dc5c74cfa3ba35eb87cf597a60fa756c"
	';
	    // send content
		$this->_sendContent("text/plain", "usage.txt", 0, 0);
    }


}

?>