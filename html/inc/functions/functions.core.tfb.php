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
 * get Request Var
 *
 * @param $varName
 * @return string
 */
function tfb_getRequestVar($varName) {
	$return = "";
	if(array_key_exists($varName, $_REQUEST)){
		// If magic quoting on, strip magic quotes:
		/**
		* TODO:
		* Codebase needs auditing to remove any unneeded stripslashes
		* calls before uncommenting this.  Also using this really means
		* checking any addslashes() calls to see if they're really needed
		* when magic quotes is on.
		if(ini_get('magic_quotes_gpc')){
			tfb_strip_quotes($_REQUEST[$varName]);
		}
		*/
		$return = htmlentities(trim($_REQUEST[$varName]), ENT_QUOTES);
	}
	return $return;
}

/**
 * Get Request Var, with no quoting or escaping (i.e. if
 * active on server, PHP's magic quoting is removed).
 *
 * Be careful what you do with the return value: it must not be output in HTML
 * without going thru htmlspecialchars, in a shell command without going thru
 * tfb_shellencode, in a DB without going thru addslashes or similar, ...
 *
 * @param $varName
 * @return string
 */
function tfb_getRequestVarRaw($varName) {
	// Note: CANNOT use tfb_strip_quotes directly on $_REQUEST
	// here, because it works in-place, i.e. would break other
	// future uses of tfb_getRequestVarRaw on the same variables.
	$return = '';
	if (array_key_exists($varName, $_REQUEST)){
		$return = $_REQUEST[$varName];
		// Seems get_magic_quotes_gpc is deprecated
		// in PHP 6, use ini_get instead.
		if (ini_get('magic_quotes_gpc'))
			tfb_strip_quotes($return);
	}
	return $return;
}

/**
 * check if path is valid
 *
 * @param $path
 * @param $ext
 * @return boolean
 */
function tfb_isValidPath($path, $ext = "") {
	if (preg_match("/\\\/", $path)) return false;
	if (preg_match("/\.\.\//", $path)) return false;
	if ($ext != "") {
		$extLength = strlen($ext);
		if (strlen($path) < $extLength) return false;
		if ((strtolower(substr($path, -($extLength)))) !== strtolower($ext)) return false;
	}
	return true;
}

/**
 * check if transfer is valid
 *
 * @param $transfer
 * @return boolean
 */
function tfb_isValidTransfer($transfer) {
	global $cfg;
	return (preg_match('/^[0-9a-zA-Z._-]+('.$cfg["file_types_regexp"].')$/D', $transfer) == 1);
}

/**
 * clean file-name, validate extension and make it lower-case
 *
 * @param $inName
 * @return string or false
 */
function tfb_cleanFileName($inName) {
	global $cfg;
	$outName = preg_replace("/[^0-9a-zA-Z.-]+/",'_', $inName);
	$stringLength = strlen($outName);
	foreach ($cfg['file_types_array'] as $ftype) {
		$extLength = strlen($ftype);
		$extIndex = 0 - $extLength;
		if (($stringLength > $extLength) && (strtolower(substr($outName, $extIndex)) === ($ftype)))
			return substr($outName, 0, $extIndex).$ftype;
	}
	return false;
}

/**
 * get name of transfer. name cleaned and extension removed.
 *
 * @param $transfer
 * @return string
 */
function tfb_cleanTransferName($transfer) {
	global $cfg;
	return str_replace($cfg["file_types_array"], "", preg_replace("/[^0-9a-zA-Z.-]+/",'_', $transfer));
}

/**
 * split on the "*" coming from Varchar URL
 *
 * @param $url
 * @return string
 */
function tfb_cleanURL($url) {
	$arURL = explode("*", $url);
	return ((is_array($arURL)) && (count($arURL)) > 1) ? $arURL[1] : $url;
}

/**
 *  Avoid magic_quotes_gpc issues
 *  courtesy of iliaa@php.net
 * @param	ref		&$var reference to a $_REQUEST variable
 * @return	null
 */
function tfb_strip_quotes(&$var){
	if (is_array($var)) {
		foreach ($var as $k => $v) {
			if (is_array($v))
				array_walk($var[$k], 'tfb_strip_quotes');
			else
				$var[$k] = stripslashes($v);
		}
	} else {
		$var = stripslashes($var);
	}
}

/**
 * HTML-encode a string.
 *
 * @param $str
 * @return string
 */
function tfb_htmlencode($str) {
	return htmlspecialchars($str, ENT_QUOTES);
}

/**
 * HTML-encode a string, transforming spaces into '&nbsp;'.
 * Should be used on strings that might contain multiple spaces
 * (names, paths & filenames, ...), unless string will be output:
 *   - in an HTML attribute,
 *   - in a <pre> element,
 * since both of those do not ignore multiple spaces (in that
 * case, tfb_htmlencode is enough).
 *
 * @param $str
 * @return string
 */
function tfb_htmlencodekeepspaces($str) {
	return str_replace(' ', '&nbsp;', htmlspecialchars($str, ENT_QUOTES));
}

/**
 * Shell-escape a string. The argument must be one whole (and only one) arg
 * (this function adds quotes around it so that the shell sees it as such).
 *
 * @param $str
 * @return string
 */
function tfb_shellencode($str) {
  $str = (string)$str;
  return isset($str) && strlen($str) > 0 ? escapeshellarg($str) : "''";
}

?>