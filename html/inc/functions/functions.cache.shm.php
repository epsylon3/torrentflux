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

// size of shared memory to allocate
define("_WEBAPP_CACHE_SHM_SIZE_CONFIG", 16384); // (ok : 16384)
define("_WEBAPP_CACHE_SHM_SIZE_TRANSFERS", 8192); // (depends on transfer-count)

// shm-ids
define("_WEBAPP_CACHE_SHM_ID_CONFIG", 0x8457); // ftok(__FILE__, 'b')
define("_WEBAPP_CACHE_SHM_ID_TRANSFERS", 0x7544); // ftok('index.php', 'b')

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
	return isset($_SESSION['SHM_ID_CONFIG']);
}

/**
 * init cfg from cache
 *
 * @param $username
 */
function cacheInit($username) {
	global $cfg;
	// attach
	if (!($mkey = shm_attach(_WEBAPP_CACHE_SHM_ID_CONFIG)))
	    error("shmem_attach failed", "", "");
	// get var from shared mem
	$cfg = shm_get_var($mkey, 1);
}

/**
 * set the cache
 *
 * @param $username
 */
function cacheSet($username) {
	global $cfg;
	// add to cache
	_cacheShmSet('SHM_ID_CONFIG', _WEBAPP_CACHE_SHM_ID_CONFIG, _WEBAPP_CACHE_SHM_SIZE_CONFIG, $cfg);
}

/**
 * flush the cache
 *
 * @param $username
 */
function cacheFlush($username = "") {
	_cacheShmFlush('SHM_ID_CONFIG', _WEBAPP_CACHE_SHM_ID_CONFIG);
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
	return isset($_SESSION['SHM_ID_TRANSFERS']);
}

/**
 * init transfers from cache
 */
function cacheTransfersInit() {
	global $transfers;
	// attach
	if (!($mkey = shm_attach(_WEBAPP_CACHE_SHM_ID_TRANSFERS)))
	    error("shmem_attach failed", "", "");
	// get var from shared mem
	$transfers = shm_get_var($mkey, 1);
}

/**
 * set the cache
 */
function cacheTransfersSet() {
	global $transfers;
	initGlobalTransfersArray();
	// add to cache
	_cacheShmSet('SHM_ID_TRANSFERS', _WEBAPP_CACHE_SHM_ID_TRANSFERS, _WEBAPP_CACHE_SHM_SIZE_TRANSFERS, $transfers);
}

/**
 * flush the cache
 */
function cacheTransfersFlush() {
	_cacheShmFlush('SHM_ID_TRANSFERS', _WEBAPP_CACHE_SHM_ID_TRANSFERS);
}


/*******************************************************************************
 * common shm-functions
 ******************************************************************************/

/**
 * generic shm set
 *
 * @param $shmname
 * @param $shmid
 * @param $shmsize
 * @param &$var
 */
function _cacheShmSet($shmname, $shmid, $shmsize, &$var) {
	// attach
	if (!($mkey = shm_attach($shmid, $shmsize, 0666)))
	    error("shmem_attach failed", "", "");
	// save id in session-var
	$_SESSION[$shmname] = $mkey;
	// get sem
	if (!($skey = sem_get($shmid, 1, 0666)))
	    error("sem_get failed", "", "");
	// acquire sem
	if (!sem_acquire($skey))
	    error("sem_acquire failed", "", "");
	// put var to shared mem
	if (!shm_put_var($mkey, 1, $var))
		error("Fail to put var to Shared memory ".$mkey.".", "", "");
	// release sem
	if (!sem_release($skey))
		error("sem_release failed", "", "");
}

/**
 * generic shm flush
 *
 * @param $shmname
 * @param $shmid
 */
function _cacheShmFlush($shmname, $shmid) {
	// keys
	if (!($skey = sem_get($shmid, 1)))
	    error("sem_get failed", "", "");
	if (!($mkey = shm_attach($shmid)))
	    error("shmem_attach failed", "", "");
	// remove var
	@shm_remove_var($shmid, 1);
	// Release semaphore
	@sem_release($skey);
	// remove shared memory segment from SysV
	@shm_remove($mkey);
	// detach
	@shm_detach($mkey);
	// Remove semaphore
	@sem_remove($skey);
	// session-id
	unset($_SESSION[$shmname]);
}

?>