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
 * html_entity_decode for for PHP < 4.3
 *
 * @param $string
 * @param $opt
 * @return
 */
if (!function_exists('html_entity_decode')) {
	function html_entity_decode($string, $opt = ENT_COMPAT) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		if ($opt & 1) {
			// Translating single quotes
			// Add single quote to translation table;
			// doesn't appear to be there by default
			$trans_tbl["&apos;"] = "'";
		}
		if (!($opt & 2)) {
			// Not translating double quotes
			// Remove double quote from translation table
			unset($trans_tbl["&quot;"]);
		}
		return strtr ($string, $trans_tbl);
	}
}

/**
 * Scrub the description to take out the ugly long URLs
 *
 * @param $desc
 * @param $title
 * @return
 */
function ScrubDescription($desc, $title) {
	$rtnValue = "";
	$parts = explode("</a>", $desc);
	$replace = ereg_replace('">.*$', '">'.$title."</a>", $parts[0]);
	if (strpos($parts[1], "Search:") !== false)
		$parts[1] = $parts[1]."</a>\n";
	for ($inx = 2; $inx < count($parts); $inx++) {
		if (strpos($parts[$inx], "Info: <a ") !== false) {
			// We have an Info: and URL to clean
			$parts[$inx] = ereg_replace('">.*$', '" target="_blank">Read More...</a>', $parts[$inx]);
		}
	}
	$rtnValue = $replace;
	for ($inx = 1; $inx < count($parts); $inx++)
		$rtnValue .= $parts[$inx];
	return $rtnValue;
}

/**
 * get rss links
 *
 * @return array
 */
function GetRSSLinks() {
	global $cfg, $db;
	$link_array = array();
	$sql = "SELECT rid, url FROM tf_rss ORDER BY rid";
	$link_array = $db->GetAssoc($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	return $link_array;
}

?>