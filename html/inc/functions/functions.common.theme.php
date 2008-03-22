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
 * Get Themes data in an array
 *
 * @return array
 */
function GetThemes() {
	$arThemes = array();
	$dir = "themes/";
	$handle = opendir($dir);
	while($entry = readdir($handle)) {
		if (is_dir($dir.$entry) && ($entry != "." && $entry != ".." && $entry != ".svn" && $entry != "CVS" && $entry != "tf_standard_themes"))
			array_push($arThemes, $entry);
	}
	closedir($handle);
	sort($arThemes);
	return $arThemes;
}

/**
 * Get Themes data in an array
 *
 * @return array
 */
function GetThemesStandard() {
	$arThemes = array();
	$dir = "themes/tf_standard_themes/";
	$handle = opendir($dir);
	while($entry = readdir($handle)) {
		if (is_dir($dir.$entry) && ($entry != "." && $entry != ".." && $entry != ".svn" && $entry != "CVS" && $entry != "css" && $entry != "tmpl" && $entry != "scripts" && $entry != "images"))
			array_push($arThemes, $entry);
	}
	closedir($handle);
	sort($arThemes);
	return $arThemes;
}

?>