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

// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

// logout user
$sql = "DELETE FROM tf_log WHERE user_id=".$db->qstr($cfg["user"])." and action=".$db->qstr($cfg["constants"]["hit"]);
$db->Execute($sql);
if ($db->ErrorNo() != 0) dbError($sql);

// destroy session
@session_destroy();

// final logout-step
if ($cfg["auth_type"] == 2) { /* Basic-Auth */
    @header('WWW-Authenticate: Basic realm="'. $cfg["auth_basic_realm"] .'"');
    @header('HTTP/1.0 401 Unauthorized');
    @ob_end_clean();
} else {
    @header('location: login.php');
}
exit();

?>