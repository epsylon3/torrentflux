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

// main.internal
require_once("inc/main.internal.php");

// common functions
require_once('inc/functions/functions.common.php');

// admin functions
require_once('inc/functions/functions.admin.php');

// access-check
if ((!isset($cfg['isAdmin'])) || (!$cfg['isAdmin'])) {
	 // the user probably hit this page direct
	AuditAction($cfg["constants"]["access_denied"], "ILLEGAL ACCESS: No Admin");
	@header("location: index.php?iid=index");
}

// op-arg
$op = (isset($_REQUEST['op'])) ? tfb_getRequestVar('op') : "default";

// check arg
if (!preg_match('/^[a-zA-Z]+$/D', $op)) {
	AuditAction($cfg["constants"]["error"], "INVALID ADMIN-OP : ".$op);
	@error("Invalid Admin-Op", "admin.php", "Admin", array($op));
}

// op-switch
switch ($op) {

	case "updateServerSettings":
		admin_updateServerSettings();

	case "updateTransferSettings":
		admin_updateTransferSettings();

	case "updateWebappSettings":
		admin_updateWebappSettings();

	case "updateIndexSettings":
		admin_updateIndexSettings();

	case "updateControlSettings":
		admin_updateControlSettings();

	case "updateDirSettings":
		admin_updateDirSettings();

	case "updateStatsSettings":
		admin_updateStatsSettings();

	case "updateXferSettings":
		admin_updateXferSettings();

	case "updateFluxdSettings":
		admin_updateFluxdSettings();

	case "controlFluxd":
		admin_controlFluxd();

	case "controlFluAzu":
		admin_controlFluAzu();

	case "updateFluAzuSettings":
		admin_updateFluAzuSettings();

	case "updateAzureusSettings":
		admin_updateAzureusSettings();

	case "updateSearchSettings":
		admin_updateSearchSettings();

	case "addLink":
		admin_addLink();

	case "editLink":
		admin_editLink();

	case "moveLink":
		admin_moveLink();

	case "deleteLink":
		admin_deleteLink();

	case "addRSS":
		admin_addRSS();

	case "deleteRSS":
		admin_deleteRSS();

	case "deleteUser":
		admin_deleteUser();

	case "setUserState":
		admin_setUserState();

	default:
		// set iid-var
		$_REQUEST["iid"] = "admin";
		// include page
		@require_once("inc/iid/admin/".$op.".php");
}

?>