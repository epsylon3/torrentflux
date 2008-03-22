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

/*
 * auth
 */
require_once("inc/functions/functions.core.auth.php");

/*
 * db
 */
require_once("inc/functions/functions.core.db.php");

/*
 * netstat
 */
require_once("inc/functions/functions.core.netstat.php");

/**
 * POSIX-wrapper for PHPs lacking posix-support (--disable-posix)
 */
if (!function_exists("posix_kill"))
	require_once("inc/functions/functions.core.posix.php");

/*
 * tfb
 */
require_once("inc/functions/functions.core.tfb.php");

/*
 * theme
 */
require_once("inc/functions/functions.core.theme.php");

/*
 * tmpl
 */
require_once("inc/functions/functions.core.tmpl.php");

/*
 * transfer
 */
require_once("inc/functions/functions.core.transfer.php");

/*
 * user
 */
require_once("inc/functions/functions.core.user.php");

/**
 * Returns true if user has message from admin with force_read
 *
 * @return boolean
 */
function IsForceReadMsg() {
	global $cfg, $db;
	return ($db->GetOne("SELECT count(*) FROM tf_messages WHERE to_user=".$db->qstr($cfg["user"])." AND force_read=1") >= 1);
}

/**
 * Get Links in an array
 *
 * @return array
 */
function GetLinks() {
	global $db;
	$link_array = array();
	$link_array = $db->GetAssoc("SELECT lid, url, sitename, sort_order FROM tf_links ORDER BY sort_order");
	return $link_array;
}

/**
 * Returns sum of max numbers of connections of all running transfers.
 *
 * @return int with max cons
 */
function getSumMaxCons() {
	global $db;
	return $db->GetOne("SELECT SUM(maxcons) AS maxcons FROM tf_transfers WHERE running = '1'");
}

/**
 * Returns sum of max upload-speed of all running transfers.
 *
 * @return int with max upload-speed
 */
function getSumMaxUpRate() {
	global $db;
	return $db->GetOne("SELECT SUM(rate) AS rate FROM tf_transfers WHERE running = '1'");
}

/**
 * Returns sum of max download-speed of all running transfers.
 *
 * @return int with max download-speed
 */
function getSumMaxDownRate() {
	global $db;
	return $db->GetOne("SELECT SUM(drate) AS drate FROM tf_transfers WHERE running = '1'");
}

/**
 * get server stats
 * note : this can only be used after a call to update transfer-values in cfg-
 *        array (eg by getTransferListArray)
 *
 * @return array
 *
 * "speedDown"            0
 * "speedUp"              1
 * "speedTotal"           2
 * "cons"                 3
 * "freeSpace"            4
 * "loadavg"              5
 * "running"              6
 * "queued"               7
 * "speedDownPercent"     8
 * "speedUpPercent"       9
 * "driveSpacePercent"   10
 *
 */
function getServerStats() {
	global $cfg;
	$serverStats = array();
	// speedDown
    $speedDown = "n/a";
	$speedDown = @number_format($cfg["total_download"], 2);
	array_push($serverStats, $speedDown);
	// speedUp
    $speedUp = "n/a";
	$speedUp =  @number_format($cfg["total_upload"], 2);
	array_push($serverStats, $speedUp);
	// speedTotal
    $speedTotal = "n/a";
	$speedTotal = @number_format($cfg["total_download"] + $cfg["total_upload"], 2);
	array_push($serverStats, $speedTotal);
	// cons
    $cons = "n/a";
	$cons = @netstatConnectionsSum();
	array_push($serverStats, $cons);
	// freeSpace
    $freeSpace = "n/a";
	$freeSpace = @formatFreeSpace($cfg["free_space"]);
	array_push($serverStats, $freeSpace);
	// loadavg
	$loadavg = "n/a";
	$loadavg = @getLoadAverageString();
	array_push($serverStats, $loadavg);
	// running
	$running = "n/a";
	$running = @getRunningTransferCount();
	array_push($serverStats, $running);
	// queued
	$queued = FluxdQmgr::countQueuedTransfers();
	array_push($serverStats, $queued);
	// speedDownPercent
	$percentDownload = 0;
	$maxDownload = $cfg["bandwidth_down"] / 8;
	$percentDownload = ($maxDownload > 0)
		? @number_format(($cfg["total_download"] / $maxDownload) * 100, 0)
		: 0;
	array_push($serverStats, $percentDownload);
	// speedUpPercent
	$percentUpload = 0;
	$maxUpload = $cfg["bandwidth_up"] / 8;
	$percentUpload = ($maxUpload > 0)
		? @number_format(($cfg["total_upload"] / $maxUpload) * 100, 0)
		: 0;
	array_push($serverStats, $percentUpload);
	// driveSpacePercent
    $driveSpacePercent = 0;
	$driveSpacePercent = @getDriveSpace($cfg["path"]);
	array_push($serverStats, $driveSpacePercent);
	// return
	return $serverStats;
}

/**
 * print message
 *
 * @param $msg
 */
function printMessage($mod, $msg) {
	@fwrite(STDOUT, @date("[Y/m/d - H:i:s]")."[".$mod."] ".$msg);
}

/**
 * print error
 *
 * @param $msg
 */
function printError($mod, $msg) {
	@fwrite(STDERR, @date("[Y/m/d - H:i:s]")."[".$mod."] ".$msg);
}

/**
 * Audit Action
 *
 * @param $action
 * @param $file
 */
function AuditAction($action, $file = "") {
    global $cfg, $db;
    // add entry to the log
    $db->Execute("INSERT INTO tf_log (user_id,file,action,ip,ip_resolved,user_agent,time)"
    	." VALUES ("
    	. $db->qstr($cfg["user"]).","
    	. $db->qstr($file).","
    	. $db->qstr(($action != "") ? $action : "unset").","
    	. $db->qstr($cfg['ip']).","
    	. $db->qstr($cfg['ip_resolved']).","
    	. $db->qstr($cfg['user_agent']).","
    	. $db->qstr(time())
    	.")"
    );
}

/**
 * global error-function
 *
 * @param $msg
 * @param $link
 * @param $linklabel
 * @param $msgs
 */
function error($msg, $link = "", $linklabel = "", $msgs = array()) {
	global $cfg, $argv;
	// web/cli/tfbf
	if ((empty($argv[0])) &&
		(!("tfbf" == @substr($cfg['user_agent'], 0, 4)))) { // web
		// theme
		$theme = CheckandSetUserTheme();
		// template
		require_once("themes/".$theme."/index.php");
		require_once("inc/lib/vlib/vlibTemplate.php");
		$_tmpl = tmplGetInstance($theme, "page.error.tmpl");
		// message
		$_tmpl->setvar('message', htmlentities($msg, ENT_QUOTES));
		// messages
		if (!empty($msgs)) {
			$msgAry = array_map("htmlentities", $msgs);
			$_tmpl->setvar('messages', implode("\n", $msgAry));
		}
		// link + linklabel
		if (!empty($link)) {
			$_tmpl->setvar('link', $link);
			$_tmpl->setvar('linklabel', (!empty($linklabel)) ? htmlentities($linklabel, ENT_QUOTES) : "Ok");
		}
		// parse template
		$_tmpl->pparse();
		// get out here
		exit();
 	} else { // cli/tfbf
    	// message
    	$exitMsg = "Error: ".$msg."\n";
    	// messages
    	if (!empty($msgs))
    		$exitMsg .= implode("\n", $msgs)."\n";
    	// get out here
    	exit($exitMsg);
    }
}

/**
 * checks if a path-string has a trailing slash. concat if it hasn't
 *
 * @param $dirPath
 * @return string with dirPath
 */
function checkDirPathString($dirPath) {
	if (((strlen($dirPath) > 0)) && (substr($dirPath, -1 ) != "/"))
		$dirPath .= "/";
	return $dirPath;
}

/**
 * checks a dir. recursive process to emulate "mkdir -p" if dir not present
 *
 * @param $dir the name of the dir
 * @param $mode the mode of the dir if created. default is 0755
 * @return boolean if dir exists/could be created
 */
function checkDirectory($dir, $mode = 0755, $depth = 0) {
	if ($depth > 32)
		return false;
	if ((@is_dir($dir) && @is_writable($dir)) || @mkdir($dir, $mode))
		return true;
	if ($dir == '/')
		return false;
	if (!@checkDirectory(dirname($dir), $mode, ++$depth))
		return false;
	return @mkdir($dir, $mode);
}

/**
 * isFile
 *
 * @param $file
 * @return boolean
 */
function isFile($file) {
    $rtnValue = False;
    if (@is_file($file)) {
        $rtnValue = True;
    } else {
        if ($file == @trim(shell_exec("ls 2>/dev/null ".tfb_shellencode($file))))
            $rtnValue = True;
    }
    return $rtnValue;
}

/**
 * Returns file size... overcomes PHP limit of 2.0GB
 *
 * @param $file
 * @return int
 */
function file_size($file) {
	$size = @filesize($file);
	if ($size == 0)
		return exec("ls -l ".tfb_shellencode($file)." 2>/dev/null | awk '{print $5}'");
	return $size;
}

/**
 * avddelete
 *
 * @param $file
 */
function avddelete($file) {
	@chmod($file,0777);
	if (@is_dir($file)) {
		$handle = @opendir($file);
		while($filename = readdir($handle)) {
			if ($filename != "." && $filename != "..")
				avddelete($file."/".$filename);
		}
		closedir($handle);
		@rmdir($file);
	} else {
		@unlink($file);
	}
}

/**
 * getLoadAverageString
 *
 * @return string with load-average
 */
function getLoadAverageString() {
	global $cfg;
	switch ($cfg["_OS"]) {
		case 1: // linux
			$loadavg = @explode(" ", @file_get_contents($cfg["loadavg_path"]));
			return ((is_array($loadavg)) && (count($loadavg) > 2))
				? $loadavg[2]
				: 'n/a';
		case 2: // bsd
			return preg_replace("/.*load averages:(.*)/", "$1", exec("uptime"));
		default:
			return 'n/a';
	}
}

/**
 * Returns the drive space used as a percentage i.e 85 or 95
 *
 * @param $drive
 * @return int
 */
function getDriveSpace($drive) {
	if (@is_dir($drive)) {
		$dt = disk_total_space($drive);
		$df = disk_free_space($drive);
		return round((($dt - $df) / $dt) * 100);
	}
	return 0;
}

/**
 * Function to convert bit-array to (unsigned) byte
 *
 * @param $dataArray
 * @return byte
 */
function convertArrayToByte($dataArray) {
	if (count($dataArray) > 8) return false;
	foreach ($dataArray as $key => $value)
		$dataArray[$key] = ($value) ? 1 : 0;
	$binString = strrev(implode('', $dataArray));
	$bitByte = bindec($binString);
	return $bitByte;
}

/**
 * Function to convert (unsigned) byte to bit-array
 *
 * @param $dataByte
 * @return array
 */
function convertByteToArray($dataByte) {
	if (($dataByte > 255) || ($dataByte < 0)) return false;
	$binString = strrev(str_pad(decbin($dataByte), 8, "0", STR_PAD_LEFT));
	$bitArray = explode(":", chunk_split($binString, 1, ":"));
	return $bitArray;
}

/**
 * Function to convert bit-array to (unsigned) integer
 *
 * @param $dataArray
 * @return int
 */
function convertArrayToInteger($dataArray) {
	if (count($dataArray) > 31) return false;
	foreach ($dataArray as $key => $value)
		$dataArray[$key] = ($value) ? 1 : 0;
	$binString = strrev(implode('', $dataArray));
	$bitInteger = bindec($binString);
	return $bitInteger;
}

/**
 * Function to convert (unsigned) integer to bit-array
 *
 * @param $dataInt
 * @return array
 */
function convertIntegerToArray($dataInt) {
	if (($dataInt > 2147483647) || ($dataInt < 0)) return false;
	$binString = strrev(str_pad(decbin($dataInt), 31, "0", STR_PAD_LEFT));
	$bitArray = explode(":", chunk_split($binString, 1, ":"));
	return $bitArray;
}

/**
 * convertTime
 *
 * @param $seconds
 * @return common time-delta-string
 */
function convertTime($seconds) {
	// sanity-check
	if ($seconds < 0) return '?';
	// one week is enough
	if ($seconds >= 604800) return '-';
	// format time-delta
	$periods = array (/* 31556926, 2629743, 604800,*/ 86400, 3600, 60, 1);
	$seconds = floatval($seconds);
	$values = array();
	$leading = true;
	foreach ($periods as $period) {
		$count = floor($seconds / $period);
		if ($leading) {
			if ($count == 0)
				continue;
			$leading = false;
		}
		array_push($values, ($count < 10) ? "0".$count : $count);
		$seconds = $seconds % $period;
	}
	return (empty($values)) ? "?" : implode(':', $values);
}

/**
 * Returns a string in format of TB, GB, MB, or kB depending on the size
 *
 * @param $inBytes
 * @return string
 */
function formatBytesTokBMBGBTB($inBytes) {
	if(!is_numeric($inBytes)) return "";
	if ($inBytes > 1099511627776)
		return round($inBytes / 1099511627776, 2) . " TB";
	elseif ($inBytes > 1073741824)
		return round($inBytes / 1073741824, 2) . " GB";
	elseif ($inBytes > 1048576)
		return round($inBytes / 1048576, 1) . " MB";
	elseif ($inBytes > 1024)
		return round($inBytes / 1024, 1) . " kB";
	else
		return $inBytes . " B";
}

/**
 * Convert free space to TB, GB or MB depending on size
 *
 * @param $freeSpace
 * @return string
 */
function formatFreeSpace($freeSpace) {
	if ($freeSpace > 1048576)
		return number_format($freeSpace / 1048576, 2)." TB";
	elseif ($freeSpace > 1024)
		return number_format($freeSpace / 1024, 2)." GB";
	else
		return number_format($freeSpace, 2)." MB";
}

/**
 * GetSpeedValue
 *
 * @param $inValue
 * @return number
 */
function GetSpeedValue($inValue) {
	$arTemp = split(" ", trim($inValue));
	return (is_numeric($arTemp[0])) ? $arTemp[0] : 0;
}

/**
 * Estimated time left to seed
 *
 * @param $inValue
 * @return string
 */
function GetSpeedInBytes($inValue) {
	$arTemp = split(" ", trim($inValue));
	return ($arTemp[1] == "kB/s") ? $arTemp[0] * 1024 : $arTemp[0];
}

?>