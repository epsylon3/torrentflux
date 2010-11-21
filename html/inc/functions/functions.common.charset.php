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
 * utf8_needed - utf-8 output ? (defined in language)
 *
 * @return bool
 */
function utf8_needed() {
	global $cfg;
	return (strtolower($cfg['_CHARSET']) == "utf-8");
}

/**
 * setCharset - format data to utf-8 if needed (defined in language)
 *
 * @param $src
 * @param $src_charset
 * @return string
 */
function setCharset($src, $src_charset="utf-8") {
	
	if (utf8_needed() && $src_charset != 'utf-8') {
		
		return utf8_encode($src);
		
	} elseif (!utf8_needed() && $src_charset == 'utf-8') {
		
		return utf8_decode($src);
		
	} else
		return $src;
	
}

?>