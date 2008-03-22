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
 * Delete Message
 *
 * @param $mid
 */
function DeleteMessage($mid) {
	global $cfg, $db;
	$sql = "delete from tf_messages where mid=".$db->qstr($mid)." and to_user=".$db->qstr($cfg["user"]);
	$result = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Mark Message as Read
 *
 * @param  $mid
 */
function MarkMessageRead($mid) {
	global $cfg, $db;
	$sql = "select * from tf_messages where mid = ".$db->qstr($mid);
	$rs = $db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	$rec = array('IsNew'=>0, 'force_read'=>0);
	$sql = $db->GetUpdateSQL($rs, $rec);
	$db->Execute($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
}

/**
 * Save Message
 *
 * @param $to_user
 * @param $from_user
 * @param $message
 * @param $to_all
 * @param $force_read
 */
function SaveMessage($to_user, $from_user, $message, $to_all=0, $force_read=0) {
	global $cfg, $db;
	$message = str_replace(array("'"), "", $message);
	$create_time = time();
	$sTable = 'tf_messages';
	if ($to_all == 1) {
		$message .= "\n\n__________________________________\n*** ".$cfg['_MESSAGETOALL']." ***";
		$sql = 'select user_id from tf_users';
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
		while ($row = $result->FetchRow()) {
			$rec = array(
						'to_user' => strtolower($row['user_id']),
						'from_user' => strtolower($from_user),
						'message' => $message,
						'IsNew' => 1,
						'ip' => $cfg['ip'],
						'time' => $create_time,
						'force_read' => $force_read
						);
			$sql = $db->GetInsertSql($sTable, $rec);
			$result2 = $db->Execute($sql);
			if ($db->ErrorNo() != 0) dbError($sql);
		}
	} else {
		// Only Send to one Person
		$rec = array(
					'to_user' => strtolower($to_user),
					'from_user' => strtolower($from_user),
					'message' => $message,
					'IsNew' => 1,
					'ip' => $cfg['ip'],
					'time' => $create_time,
					'force_read' => $force_read
					);
		$sql = $db->GetInsertSql($sTable, $rec);
		$result = $db->Execute($sql);
		if ($db->ErrorNo() != 0) dbError($sql);
	}
}

/**
 * Get Message data in an array
 *
 * @param $mid
 * @return array
 */
function GetMessage($mid) {
	global $cfg, $db;
	$sql = "select from_user, message, ip, time, isnew, force_read from tf_messages where mid=".$db->qstr($mid)." and to_user=".$db->qstr($cfg["user"]);
	$rtnValue = $db->GetRow($sql);
	if ($db->ErrorNo() != 0) dbError($sql);
	return $rtnValue;
}

?>