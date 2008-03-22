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
 * posix_kill
 *
 * @param $pid
 * @param $sig
 * @return string
 */
function posix_kill($pid, $sig) {
	return exec("kill -s ".$sig." ".$pid);
}

/**
 * posix_geteuid
 *
 * @author osearth@gmail.c0m
 *
 * @return string
 */
function posix_geteuid() {
	return exec("id -u");
}

/**
 * posix_getpwuid
 *
 * @author osearth@gmail.c0m
 *
 * @param $uid
 * @return array
 */
function posix_getpwuid($uid) {
	if (!$uid) return FALSE;
	$file = file("/etc/passwd");
	foreach ($file as $f) {
		$l = explode(":",$f);
		if ($l[2] == $uid) {
			$out[name] = $l[0];
			$out[passwd] = $l[1];
			$out[uid] = $l[2];
			$out[gid] = $l[3];
			$out[gecos] = $l[4];
			$out[dir] = $l[5];
			$out[shell] = $l[6];
			return $out;
		}
	}
}

?>