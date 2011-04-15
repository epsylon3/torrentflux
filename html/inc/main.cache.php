<?php
/*******************************************************************************
 Set tabs to 4.

 $Id: main.cache.php $

 @package torrentflux
 @license LICENSE http://www.gnu.org/copyleft/gpl.html

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

*******************************************************************************/

/*
  webapp-cache :
  don't change this unless you know what you are doing.
  don't use shm on multi-user-installs. it is not supported.
*/

// no cache
// require_once('inc/functions/functions.cache.off.php');

// session-based cache
require_once('inc/functions/functions.cache.session.php');

// shared-mem-based cache (NOT for multi-user-installs !)
// require_once('inc/functions/functions.cache.shm.php');

/******************************************************************************/

?>