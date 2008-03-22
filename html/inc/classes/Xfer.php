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

/*******************************************************************************
 *  Original Code:
 *  TorrentFlux xfer Statistics hack
 *  blackwidow - matt@mattjanssen.net
 ******************************************************************************/
/*
	TorrentFlux xfer Statistics hack is free code; you can redistribute it
	and/or modify it under the terms of the GNU General Public License as
	published by the Free Software Foundation; either version 2 of the License,
	or (at your option) any later version.
*/

/**
 * class Xfer
 */
class Xfer
{
	// public fields

    // stats-arrays
    var $xfer = array();
    var $xfer_total = array();

    // newday-flag
    var $xfer_newday = 0;

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return Xfer
     */
    function getInstance() {
		global $instanceXfer;
		// initialize if needed
		if (!isset($instanceXfer))
			Xfer::initialize();
		return $instanceXfer;
    }

    /**
     * initialize Xfer.
     */
    function initialize() {
    	global $instanceXfer;
    	// create instance
    	if (!isset($instanceXfer))
    		$instanceXfer = new Xfer();
    }

    /**
     * init Xfer (login-task).
     */
    function init() {
    	global $db;
		// if xfer is empty, insert a zero record for today
		$xferRecord = $db->GetRow("SELECT 1 FROM tf_xfer");
		if (empty($xferRecord))
			$db->Execute("INSERT INTO tf_xfer (user_id,date) values ('',".$db->DBDate(time()).")");
    }

	/**
	 * gets new-day
	 *
	 * @return int
	 */
	function getNewday() {
    	global $instanceXfer;
    	return (isset($instanceXfer))
    		? $instanceXfer->xfer_newday
    		: 0;
	}

	/**
	 * sets new-day
	 */
	function setNewday() {
    	global $instanceXfer, $db;
 		// initialize if needed
		if (!isset($instanceXfer))
			Xfer::initialize();
		// set new-day
		$instanceXfer->xfer_newday = 0;
		$instanceXfer->xfer_newday = !$db->GetOne('SELECT 1 FROM tf_xfer WHERE date = '.$db->DBDate(time()));
	}

    /**
     * get Stats
     *
     * @return array
     */
    function getStats() {
    	global $instanceXfer;
    	return (isset($instanceXfer))
    		? $instanceXfer->xfer
    		: array();
    }

    /**
     * get Stats Total
     *
     * @return array
     */
    function getStatsTotal() {
    	global $instanceXfer;
    	return (isset($instanceXfer))
    		? $instanceXfer->xfer_total
    		: array();
    }

    /**
     * get Stats Formatted
     *
     * @return array
     */
    function getStatsFormatted() {
		global $cfg, $instanceXfer;
		return array(
			/* global */
			(isset($instanceXfer->xfer_total['total']['total']))
				? @formatFreeSpace($instanceXfer->xfer_total['total']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer_total['month']['total']))
				? @formatFreeSpace($instanceXfer->xfer_total['month']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer_total['month']['total']))
				? @formatFreeSpace($instanceXfer->xfer_total['week']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer_total['month']['total']))
				? @formatFreeSpace($instanceXfer->xfer_total['day']['total'] / 1048576)
				: 0,
			/* user */
			(isset($instanceXfer->xfer[$cfg["user"]]['total']['total']))
				? @formatFreeSpace($instanceXfer->xfer[$cfg["user"]]['total']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer[$cfg["user"]]['total']['total']))
				? @formatFreeSpace($instanceXfer->xfer[$cfg["user"]]['month']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer[$cfg["user"]]['total']['total']))
				? @formatFreeSpace($instanceXfer->xfer[$cfg["user"]]['week']['total'] / 1048576)
				: 0,
			(isset($instanceXfer->xfer[$cfg["user"]]['total']['total']))
				? @formatFreeSpace($instanceXfer->xfer[$cfg["user"]]['day']['total'] / 1048576)
				: 0
		);
    }

	/**
	 * reset Xfer-Stats
	 *
	 * @return true or function exits with error
	 */
	function resetStats() {
		global $db;
		// exec delete
		$db->Execute("DELETE FROM tf_xfer");
		// set transfers-cache
		cacheTransfersSet();
		//return
		return ($db->ErrorNo() == 0)
			? true
			: $db->ErrorMsg();
	}

	/**
	 * get Xfer-Usage
	 *
	 * @param $username
	 * @return number
	 */
	function getUsage($username = "") {
		global $db;
		// sql-state
		$sql = "SELECT SUM(download) AS download, SUM(upload) AS upload FROM tf_xfer";
		if ($username != "")
			$sql .= " WHERE user_id LIKE ".$db->qstr($username);
		// exec state
		$result = $db->Execute($sql);
		// error-check
		if ($db->ErrorNo() != 0) dbError($sql);
		// get usage
		$row = $result->FetchRow();
		$rtnValue = "0";
		if (!empty($row))
			$rtnValue = @formatFreeSpace($row["download"] / 1048576 + $row["upload"] / 1048576);
		// return
		return $rtnValue;
	}

	/**
	 * get Xfer-Usage by date
	 *
	 * @param $username
	 * @param $dateBegin
	 * @param $dateEnd
	 * @return array
	 */
	function getUsageByDate($username = "", $dateBegin = "", $dateEnd = "") {
		global $db;
		// sql-state
		$sql = "SELECT SUM(download) AS download, SUM(upload) AS upload, date FROM tf_xfer";
		if ($username != "")
			$sql .= " WHERE user_id LIKE ".$db->qstr($username);
		if (($dateBegin != "") && ($dateEnd != ""))
			$sql .= " AND date >= ".$db->qstr($dateBegin)." AND date < ".$db->qstr($dateEnd);
		$sql .= " GROUP BY date ORDER BY date";
		// exec state
		$rtnValue = $db->GetAll($sql);
		// error-check
		if ($db->ErrorNo() != 0) dbError($sql);
		// return
		return $rtnValue;
	}

	/**
	 * xfer update 1
	 * add upload/download stats to the xfer array
	 *
	 * @param $entry
	 * @param $transferowner
	 * @param $client
	 * @param $hash
	 * @param $uptotal
	 * @param $downtotal
	 */
	function update1($entry, $transferowner, $client, $hash, $uptotal, $downtotal) {
		global $instanceXfer;
 		// initialize if needed
		if (!isset($instanceXfer))
			Xfer::initialize();
		// call instance-method
		$instanceXfer->instance_update1($entry, $transferowner, $client, $hash, $uptotal, $downtotal);
	}

	/**
	 * xfer update 2
	 */
	function update2() {
		global $instanceXfer;
 		// initialize if needed
		if (!isset($instanceXfer))
			Xfer::initialize();
		// call instance-method
		$instanceXfer->instance_update2();
	}

	/**
	 * Inserts or updates SQL upload/download for user
	 *
	 * @param $user
	 * @param $down
	 * @param $up
	 */
	function save($user, $down, $up) {
		global $db;
		// just to be safe..
		if (empty($down)) $down = "0";
		if (empty($up)) $up = "0";
		$sql = ($db->GetRow("SELECT 1 FROM tf_xfer WHERE user_id = ".$db->qstr($user)." AND date = ".$db->DBDate(time())))
			? "UPDATE tf_xfer SET download = download+".$down.", upload = upload+".$up." WHERE user_id = ".$db->qstr($user)." AND date = ".$db->DBDate(time())
			: "INSERT INTO tf_xfer (user_id,date,download,upload) values (".$db->qstr($user).",".$db->DBDate(time()).",".$down.",".$up.")";
		$db->Execute($sql);
	}

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function Xfer() {
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * xfer update 1
	 * add upload/download stats to the xfer array
	 *
	 * @param $entry
	 * @param $transferowner
	 * @param $client
	 * @param $hash
	 * @param $uptotal
	 * @param $downtotal
	 */
	function instance_update1($entry, $transferowner, $client, $hash, $uptotal, $downtotal) {
		global $cfg, $db;
		$ch = ClientHandler::getInstance($client);
		$transferTotalsCurrent = $ch->getTransferCurrentOP($entry, $hash, $uptotal, $downtotal);
		$this->_sumUsage($transferowner, $transferTotalsCurrent["downtotal"], $transferTotalsCurrent["uptotal"], 'total');
		$this->_sumUsage($transferowner, $transferTotalsCurrent["downtotal"], $transferTotalsCurrent["uptotal"], 'month');
		$this->_sumUsage($transferowner, $transferTotalsCurrent["downtotal"], $transferTotalsCurrent["uptotal"], 'week');
		$this->_sumUsage($transferowner, $transferTotalsCurrent["downtotal"], $transferTotalsCurrent["uptotal"], 'day');
		//XFER: if new day add upload/download totals to last date on record and subtract from today in SQL
		if ($this->xfer_newday != 0) {
			$this->xfer_newday = 2;
			$lastDate = $db->GetOne('SELECT date FROM tf_xfer ORDER BY date DESC');
			$sql = ($db->GetOne("SELECT 1 FROM tf_xfer WHERE user_id = ".$db->qstr($transferowner)." AND date = ".$db->qstr($lastDate)))
				? "UPDATE tf_xfer SET download = download+".@($transferTotalsCurrent["downtotal"] + 0).", upload = upload+".@($transferTotalsCurrent["uptotal"] + 0)." WHERE user_id = ".$db->qstr($transferowner)." AND date = ".$db->qstr($lastDate)
				: "INSERT INTO tf_xfer (user_id,date,download,upload) values (".$db->qstr($transferowner).",".$db->qstr($lastDate).",".@($transferTotalsCurrent["downtotal"] + 0).",".@($transferTotalsCurrent["uptotal"] + 0).")";
			$db->Execute($sql);
			$sql = ($db->GetOne("SELECT 1 FROM tf_xfer WHERE user_id = ".$db->qstr($transferowner)." AND date = ".$db->DBDate(time())))
				? "UPDATE tf_xfer SET download = download-".@($transferTotalsCurrent["downtotal"] + 0).", upload = upload-".@($transferTotalsCurrent["uptotal"] + 0)." WHERE user_id = ".$db->qstr($transferowner)." AND date = ".$db->DBDate(time())
				: "INSERT INTO tf_xfer (user_id,date,download,upload) values (".$db->qstr($transferowner).",".$db->DBDate(time()).",".@($transferTotalsCurrent["downtotal"] + 0).",".@($transferTotalsCurrent["uptotal"] + 0).")";
			$db->Execute($sql);
		}
	}

	/**
	 * xfer update 2
	 */
	function instance_update2() {
		global $cfg, $db;
		//XFER: if a new day but no .stat files where found put blank entry into the
		// DB for today to indicate accounting has been done for the new day
		if ($this->xfer_newday == 1)
			$db->Execute("INSERT INTO tf_xfer (user_id,date) values ('',".$db->DBDate(time()).")");
		$this->_getUsage('0001-01-01', 'total');
		$month_start = (date('j')>=$cfg['month_start']) ? date('Y-m-').$cfg['month_start'] : date('Y-m-',strtotime('-1 Month')).$cfg['month_start'];
		$this->_getUsage($month_start, 'month');
		$week_start = date('Y-m-d', strtotime('last '.$cfg['week_start']));
		$this->_getUsage($week_start, 'week');
		$day_start = date('Y-m-d');
		$this->_getUsage($day_start, 'day');
	}

    // =========================================================================
	// private methods
	// =========================================================================

	/**
	 * Gets upload/download usage for all users starting at timestamp from SQL
	 *
	 * @param $start
	 * @param $period
	 */
	function _getUsage($start, $period) {
		global $db;
		$sql = "SELECT user_id, SUM(download) AS download, SUM(upload) AS upload FROM tf_xfer WHERE date >= ".$db->qstr($start)." AND user_id != '' GROUP BY user_id";
		$rtnValue = $db->GetAll($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		foreach ($rtnValue as $row)
			$this->_sumUsage($row[0], $row[1], $row[2], $period);
	}

	/**
	 *  Adds download/upload into correct usage_array (total, month, etc)
	 *
	 * @param $user
	 * @param $download
	 * @param $upload
	 * @param $period
	 */
	function _sumUsage($user, $download, $upload, $period) {
		@ $this->xfer[$user][$period]['download'] += $download;
		@ $this->xfer[$user][$period]['upload'] += $upload;
		@ $this->xfer[$user][$period]['total'] += $download + $upload;
		@ $this->xfer_total[$period]['download'] += $download;
		@ $this->xfer_total[$period]['upload'] += $upload;
		@ $this->xfer_total[$period]['total'] += $download + $upload;
	}

}

?>