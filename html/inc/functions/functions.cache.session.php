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
 * config
 ******************************************************************************/

/**
 * check if cache set
 *
 * @param $username
 * @return boolean
 */
function cacheIsSet($username) {
	return isset($_SESSION['cache'][$username]);
}

/**
 * init cfg from cache
 *
 * @param $username
 */
function cacheInit($username) {
	global $cfg;
	$cfg = $_SESSION['cache'][$username];
}

/**
 * set the cache
 *
 * @param $username
 */
function cacheSet($username) {
	global $cfg;
	$_SESSION['cache'][$username] = $cfg;
}

/**
 * flush the cache
 *
 * @param $username
 */
function cacheFlush($username = "") {
	if (empty($username))
		unset($_SESSION['cache']);
	else
		unset($_SESSION['cache'][$username]);
}

/*******************************************************************************
 * transfers
 ******************************************************************************/

/**
 * check if cache set
 *
 * @return boolean
 */
function cacheTransfersIsSet() {
	return isset($_SESSION['cache_transfers']);
}

/**
 * init transfers from cache
 */
function cacheTransfersInit() {
	global $transfers;
	$transfers = $_SESSION['cache_transfers'];
}

/**
 * set the cache
 */
function cacheTransfersSet() {
	global $transfers;
	initGlobalTransfersArray();
	// add to cache
	$_SESSION['cache_transfers'] = $transfers;
}

/**
 * flush the cache
 */
function cacheTransfersFlush() {
	unset($_SESSION['cache_transfers']);
}

?>