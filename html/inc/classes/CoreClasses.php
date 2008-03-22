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

// ClientHandler
require_once("inc/classes/ClientHandler.php");

// CommandHandler
require_once("inc/classes/CommandHandler.php");

// RunningTransfer
require_once("inc/classes/RunningTransfer.php");

// StatFile
require_once("inc/classes/StatFile.php");

// Fluxd
require_once("inc/classes/Fluxd.php");

// FluxdServiceMod
require_once("inc/classes/FluxdServiceMod.php");

// Xfer
require_once("inc/classes/Xfer.php");

/**
 * class ProcessInfo
 */
class ProcessInfo {
	var $pid = "";
	var $ppid = "";
	var $cmdline = "";
	function ProcessInfo($psLine) {
		$psLine = trim($psLine);
		if (strlen($psLine) > 12) {
			$this->pid = trim(substr($psLine, 0, 5));
			$this->ppid = trim(substr($psLine, 5, 6));
			$this->cmdline = trim(substr($psLine, 12));
		}
	}
}

/**
 * class ProcessInfo : Stores the image and title of for the health of a file.
 */
class HealthData {
	var $image = "";
	var $title = "";
}

?>