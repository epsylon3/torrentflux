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

// iid-check
if (!isset($_REQUEST["iid"])) {
	// this is for official tf compat
	require_once("inc/functions/functions.compat.tf.php");
	compat_tf_indexDispatch();
	// set iid-var
	$_REQUEST["iid"] = "index";
}

// include page
if (preg_match('/^[a-zA-Z]+$/D', $_REQUEST["iid"])) {
	@require_once("inc/iid/".$_REQUEST["iid"].".php");
} else {
	$_iid = tfb_getRequestVar('iid');
	AuditAction($cfg["constants"]["error"], "INVALID PAGE: ".$_iid);
	@error("Invalid Page", "index.php?iid=index", "Home", array($_iid));
}

?>