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
 * @param $return
 * @return string
 */
function tfb_getRequestVar($varName, $return = '') {
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
		/*
		disabled, need to fix deadeye's implementation
		if ($varName == 'transfer' && isHash($return)) {
			$name = getTransferFromHash($return);
			if (!empty($name))
				return $name;
			else
				return $return;
		}
		*/
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
 * @param $return
 * @return string
 */
function tfb_getRequestVarRaw($varName,$return = '') {
	// Note: CANNOT use tfb_strip_quotes directly on $_REQUEST
	// here, because it works in-place, i.e. would break other
	// future uses of tfb_getRequestVarRaw on the same variables.
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
	return (preg_match('/^[0-9a-zA-Z_\.\-]+('.$cfg["file_types_regexp"].')$/D', $transfer) == 1);
}


/**
 * clean accents
 *
 * @param $inName
 * @return string
 */
function tfb_clean_accents($inName) {
	return remove_accents($outName);
}

/**
 * clean file-name, validate extension and make it lower-case
 *
 * @param $inName
 * @return string or false
 */
function tfb_cleanFileName($inName) {
	global $cfg;
	$outName = tfb_clean_accents($inName);
	$outName = preg_replace("/[^0-9a-zA-Z\.\-]+/",'_', $outName);
	$outName = str_replace("_-_", "-", $outName);
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
	$outName = trim(preg_replace("/\[www\.[^\]]+\]/i",'', $transfer));
	$outName = preg_replace("/[^0-9a-zA-Z\.\-]+/",'_', $outName);
	return str_replace($cfg["file_types_array"], "", $outName);
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
  return isset($str) && strlen($str) > 0 ? mb_escapeshellarg($str) : "''";
}

// http://markushedlund.com/dev-tech/php-escapeshellarg-with-unicodeutf-8-support
// By default escapeshellarg will strip any unicode characters.
// The code below is translated from the C source of PHP.
function mb_escapeshellarg($arg) {
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		return '"' . str_replace(array('"', '%'), array('', ''), $arg) . '"';
	} else {
		return "'" . str_replace("'", "'\\''", $arg) . "'";
	}
}

// http://stackoverflow.com/a/10790734/2428861
// Hard-coded accented characters should be avoided in php files
// due to various text editors which might overwrite them.
// The safer way is to use chr() function.
function remove_accents($string) {
    if ( !preg_match('/[\x80-\xff]/', $string) )
        return $string;

    $chars = array(
    // Decompositions for Latin-1 Supplement
    chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
    chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
    chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
    chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
    chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
    chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
    chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
    chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
    chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
    chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
    chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
    chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
    chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
    chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
    chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
    chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
    chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
    chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
    chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
    chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
    chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
    chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
    chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
    chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
    chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
    chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
    chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
    chr(195).chr(191) => 'y',
    // Decompositions for Latin Extended-A
    chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
    chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
    chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
    chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
    chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
    chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
    chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
    chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
    chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
    chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
    chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
    chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
    chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
    chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
    chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
    chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
    chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
    chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
    chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
    chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
    chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
    chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
    chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
    chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
    chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
    chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
    chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
    chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
    chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
    chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
    chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
    chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
    chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
    chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
    chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
    chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
    chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
    chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
    chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
    chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
    chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
    chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
    chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
    chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
    chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
    chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
    chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
    chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
    chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
    chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
    chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
    chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
    chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
    chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
    chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
    chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
    chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
    chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
    chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
    chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
    chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
    chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
    chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
    chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
    );

    $string = strtr($string, $chars);

    return $string;
}
