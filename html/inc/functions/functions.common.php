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
require_once("inc/functions/functions.common.auth.php");

/*
 * cookie
 */
require_once("inc/functions/functions.common.cookie.php");

/*
 * language
 */
require_once("inc/functions/functions.common.language.php");

/*
 * message
 */
require_once("inc/functions/functions.common.message.php");

/*
 * settings
 */
require_once("inc/functions/functions.common.settings.php");

/*
 * theme
 */
require_once("inc/functions/functions.common.theme.php");

/*
 * tmpl
 */
require_once("inc/functions/functions.common.tmpl.php");

/*
 * transfer
 */
require_once("inc/functions/functions.common.transfer.php");

/*
 * trprofile
 */
require_once("inc/functions/functions.common.trprofile.php");

/*
 * user
 */
require_once("inc/functions/functions.common.user.php");

/**
 * GetActivityCount
 *
 * @param $user
 * @return int
 */
function GetActivityCount($user="") {
	global $cfg, $db;
	$count = 0;
	$for_user = ($user != "") ? "user_id=".$db->qstr($user)." AND " : "";
	$sql = "SELECT count(*) FROM tf_log WHERE ".$for_user."(action=".$db->qstr($cfg["constants"]["file_upload"])." OR action=".$db->qstr($cfg["constants"]["url_upload"]).")";
	$count = $db->GetOne($sql);
	return $count;
}

/**
 * get File
 *
 * @param $var
 * @return boolean
 */
function getFile($var) {
	return ($var < 65535);
}

/**
 * checks main-directories.
 *
 * @return boolean
 */
function checkMainDirectories() {
	global $cfg;
	// main-path
	if (!checkDirectory($cfg["path"]))
		@error("Main-Path does not exist and cannot be created or is not writable", "admin.php?op=serverSettings", "Server-Settings", array("path : ".$cfg["path"]));
	// transfer-path
	if (!checkDirectory($cfg["transfer_file_path"]))
		@error("Transfer-File-Path does not exist and cannot be created or is not writable", "admin.php?op=serverSettings", "Server-Settings", array("transfer_file_path : ".$cfg["transfer_file_path"]));
}

/**
 * Removes HTML from Messages
 *
 * @param $str
 * @param $strip
 * @return string
 */
function check_html ($str, $strip="") {
	/* The core of this code has been lifted from phpslash */
	/* which is licenced under the GPL. */
	if ($strip == "nohtml")
		$AllowableHTML = array('');
	$str = stripslashes($str);
	$str = eregi_replace("<[[:space:]]*([^>]*)[[:space:]]*>",'<\\1>', $str);
	// Delete all spaces from html tags .
	$str = eregi_replace("<a[^>]*href[[:space:]]*=[[:space:]]*\"?[[:space:]]*([^\" >]*)[[:space:]]*\"?[^>]*>",'<a href="\\1">', $str);
	// Delete all attribs from Anchor, except an href, double quoted.
	$str = eregi_replace("<[[:space:]]* img[[:space:]]*([^>]*)[[:space:]]*>", '', $str);
	// Delete all img tags
	$str = eregi_replace("<a[^>]*href[[:space:]]*=[[:space:]]*\"?javascript[[:punct:]]*\"?[^>]*>", '', $str);
	// Delete javascript code from a href tags -- Zhen-Xjell @ http://nukecops.com
	$tmp = "";
	while (ereg("<(/?[[:alpha:]]*)[[:space:]]*([^>]*)>",$str,$reg)) {
		$i = strpos($str,$reg[0]);
		$l = strlen($reg[0]);
		$tag = ($reg[1][0] == "/") ? strtolower(substr($reg[1],1)) : strtolower($reg[1]);
		if ($a = $AllowableHTML[$tag]) {
			if ($reg[1][0] == "/") {
				$tag = "</$tag>";
			} elseif (($a == 1) || ($reg[2] == "")) {
				$tag = "<$tag>";
			} else {
			  # Place here the double quote fix function.
			  $attrb_list=delQuotes($reg[2]);
			  // A VER
			  $attrb_list = ereg_replace("&","&amp;",$attrb_list);
			  $tag = "<$tag" . $attrb_list . ">";
			} # Attribs in tag allowed
		} else {
			$tag = "";
		}
		$tmp .= substr($str,0,$i) . $tag;
		$str = substr($str,$i+$l);
	}
	$str = $tmp . $str;
	// parse for strings starting with http:// and subst em with hyperlinks.
	if ($strip != "nohtml") {
		global $cfg;
		$str = ($cfg["enable_dereferrer"] != 0)
			? preg_replace('/(http:\/\/)(.*)([[:space:]]*)/i', '<a href="index.php?iid=dereferrer&u=${1}${2}" target="_blank">${1}${2}</a>${3}', $str)
			: preg_replace('/(http:\/\/)(.*)([[:space:]]*)/i', '<a href="${1}${2}" target="_blank">${1}${2}</a>${3}', $str);
	}
	return $str;
}

/**
 * sendLine - sends a line to the browser
 */
function sendLine($line = "") {
	echo $line;
	echo str_pad('',4096)."\n";
	@ob_flush();
	@flush();
}

?>