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

// default-type
define('_DEFAULT_TYPE', 'all');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.serverStats.tmpl");

// request-vars
$type = (isset($_REQUEST['type'])) ? tfb_getRequestVar('type') : _DEFAULT_TYPE;

// types
$type_list = array();
array_push($type_list, array(
	'name' => "all",
	'selected' => ($type == "all") ? 1 : 0
	)
);
array_push($type_list, array(
	'name' => "drivespace",
	'selected' => ($type == "drivespace") ? 1 : 0
	)
);
array_push($type_list, array(
	'name' => "who",
	'selected' => ($type == "who") ? 1 : 0
	)
);
if ($cfg['isAdmin'] == 1)
	array_push($type_list, array(
		'name' => "ps",
		'selected' => ($type == "ps") ? 1 : 0
		)
	);
if ($cfg['isAdmin'] == 1)
	array_push($type_list, array(
		'name' => "netstat",
		'selected' => ($type == "netstat") ? 1 : 0
		)
	);
if (($cfg['enable_xfer'] == 1) && (($cfg['enable_public_xfer'] == 1 ) || $cfg['isAdmin']))
	array_push($type_list, array(
		'name' => "xfer",
		'selected' => ($type == "xfer") ? 1 : 0
		)
	);
$tmpl->setloop('type_list', $type_list);

// type-switch
switch ($type) {

	// all
	case "all":
		// set vars
		$tmpl->setvar('all_df', shell_exec("df -h ".tfb_shellencode($cfg["path"])));
		$tmpl->setvar('all_du', shell_exec("du -sh ".tfb_shellencode($cfg["path"]."*")));
		$tmpl->setvar('all_w', shell_exec("w"));
		$tmpl->setvar('all_free', shell_exec("free -mo"));
		// language
		$tmpl->setvar('_DRIVESPACE', $cfg['_DRIVESPACE']);
		$tmpl->setvar('_SERVERSTATS', $cfg['_SERVERSTATS']);
		// drivespace-bar
		tmplSetDriveSpaceBar();
		break;

	// drivespace
	case "drivespace":
		// set vars
		$tmpl->setvar('drivespace_df', shell_exec("df -h ".tfb_shellencode($cfg["path"])));
		$tmpl->setvar('drivespace_du', shell_exec("du -sh ".tfb_shellencode($cfg["path"]."*")));
		// language
		tmplSetTitleBar($cfg['_DRIVESPACE']);
		// drivespace-bar
		tmplSetDriveSpaceBar();
		break;

	// who
	case "who":
		// set vars
		$tmpl->setvar('who_w', shell_exec("w"));
		$tmpl->setvar('who_free', shell_exec("free -mo"));
		// drivespace-bar
		tmplSetDriveSpaceBar();
		break;

	// ps
	case "ps":
		// set vars
		if ($cfg['isAdmin']) {
			// array with all clients
			$clients = array('tornado', 'transmission', 'mainline', 'wget', 'nzbperl', 'azureus');
			// get informations
			$process_list = array();
			foreach ($clients as $client) {
				$ch = ClientHandler::getInstance($client);
				array_push($process_list, array(
					'client' => $client,
					'RunningProcessInfo' => $ch->runningProcessInfo(),
					'pinfo' => shell_exec("ps auxww | ".$cfg['bin_grep']." ".tfb_shellencode($ch->binClient)." | ".$cfg['bin_grep']." -v grep")
					)
				);
			}
			$tmpl->setloop('process_list', $process_list);
		}
		// drivespace-bar
		tmplSetDriveSpaceBar();
		break;

	// netstat
	case "netstat":
		// set vars
		if ($cfg['isAdmin']) {
			// set vars
			$tmpl->setvar('netstatConnectionsSum', netstatConnectionsSum());
			$tmpl->setvar('netstatPortList', netstatPortList());
			$tmpl->setvar('netstatHostList', netstatHostList());
		}
		// language
		$tmpl->setvar('_ID_HOSTS', $cfg['_ID_HOSTS']);
		$tmpl->setvar('_ID_PORTS', $cfg['_ID_PORTS']);
		$tmpl->setvar('_ID_CONNECTIONS', $cfg['_ID_CONNECTIONS']);
		// drivespace-bar
		tmplSetDriveSpaceBar();
		break;

	// xfer
	case "xfer":
		// is enabled ?
		if ($cfg["enable_xfer"] != 1) {
			AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use xfer");
			@error("xfer is disabled", "index.php?iid=serverStats", "");
		}
		// set vars
		$tmpl->setvar('is_xfer', 1);
		// getTransferListArray to update xfer-stats
		// set xfer-realtime
		$cfg['xfer_realtime'] = 1;
		// set xfer-newday
		Xfer::setNewday();
		// transferlist-array to update stats
		getTransferListArray();
		// xfer-totals
		$xfer_total = Xfer::getStatsTotal();
		if ($cfg['xfer_day'])
			$tmpl->setvar('xfer_day', tmplGetXferBar($cfg['xfer_day'],$xfer_total['day']['total'],$cfg['_XFERTHRU'].' Today:'));
		if ($cfg['xfer_week'])
			$tmpl->setvar('xfer_week', tmplGetXferBar($cfg['xfer_week'],$xfer_total['week']['total'],$cfg['_XFERTHRU'].' '.$cfg['week_start'].':'));
		$monthStart = strtotime(date('Y-m-').$cfg['month_start']);
		$monthText = (date('j') < $cfg['month_start']) ? date('M j',strtotime('-1 Day',$monthStart)) : date('M j',strtotime('+1 Month -1 Day',$monthStart));
		if ($cfg['xfer_month'])
			$tmpl->setvar('xfer_month', tmplGetXferBar($cfg['xfer_month'],$xfer_total['month']['total'],$cfg['_XFERTHRU'].' '.$monthText.':'));
		if ($cfg['xfer_total'])
			$tmpl->setvar('xfer_total', tmplGetXferBar($cfg['xfer_total'],$xfer_total['total']['total'],$cfg['_TOTALXFER'].':'));
		if (($cfg['enable_public_xfer'] == 1 ) || $cfg['isAdmin']) {
			$tmpl->setvar('show_xfer', 1);
			$sql = 'SELECT user_id FROM tf_users ORDER BY user_id';
			$rtnValue = $db->GetCol($sql);
			if ($db->ErrorNo() != 0) dbError($sql);
			$xfer = Xfer::getStats();
			$user_list = array();
			foreach ($rtnValue as $user_id) {
				array_push($user_list, array(
					'user_id' => $user_id,
					'total' => formatFreeSpace(@ $xfer["$user_id"]['total']['total'] / (1048576)),
					'month' => formatFreeSpace(@ $xfer["$user_id"]['month']['total'] / (1048576)),
					'week' => formatFreeSpace(@ $xfer["$user_id"]['week']['total'] / (1048576)),
					'day' => formatFreeSpace(@ $xfer["$user_id"]['day']['total'] / (1048576))
					)
				);
			}
			$tmpl->setloop('user_list', $user_list);
			$tmpl->setvar('total_total', formatFreeSpace(@ $xfer_total['total']['total'] / (1048576)));
			$tmpl->setvar('total_month', formatFreeSpace(@ $xfer_total['month']['total'] / (1048576)));
			$tmpl->setvar('total_week', formatFreeSpace(@ $xfer_total['week']['total'] / (1048576)));
			$tmpl->setvar('total_day', formatFreeSpace(@ $xfer_total['day']['total'] / (1048576)));
			//
			$username = tfb_getRequestVar('user');
			$tmpl->setvar('user', $username);
			$_month = tfb_getRequestVar('month');
			if (isset($_REQUEST['month'])) {
				$mstart = $_month.'-'.$cfg['month_start'];
				$mend = date('Y-m-d', strtotime('+1 Month', strtotime($mstart)));
			} else {
				$mstart = 0;
				$mend = 0;
			}
			$_week = tfb_getRequestVar('week');
			if (isset($_REQUEST['week'])) {
				$wstart = $_week;
				$wend = date('Y-m-d', strtotime('+1 Week', strtotime($wstart)));
			} else {
				$wstart = $mstart;
				$wend = $mend;
			}
			// month stats
			$xferStats = Xfer::getUsageByDate($username);
			$start = '';
			$download = 0;
			$upload = 0;
			$month_list = array();
			foreach ($xferStats as $row) {
				$rtime = strtotime($row[2]);
				$newstart = $cfg['month_start'].' ';
				$newstart .= (date('j',$rtime) < $cfg['month_start']) ? date('M Y',strtotime('-1 Month',$rtime)) : date('M Y',$rtime);
				if ($start != $newstart) {
					if ($upload + $download != 0) {
						array_push($month_list, array(
							'user_id' => $username,
							'month' => date('Y-m',strtotime($start)),
							'start' => $start,
							'downloadstr' => formatFreeSpace($download / (1048576)),
							'uploadstr' => formatFreeSpace($upload / (1048576)),
							'totalstr' => formatFreeSpace(($download + $upload) / (1048576))
							)
						);
					}
					$download = $row[0];
					$upload = $row[1];
					$start = $newstart;
				} else {
					$download += $row[0];
					$upload += $row[1];
				}
			}
			if ($upload + $download != 0) {
				array_push($month_list, array(
					'user_id' => $username,
					'month' => date('Y-m',strtotime($start)),
					'start' => $start,
					'downloadstr' => formatFreeSpace($download / (1048576)),
					'uploadstr' => formatFreeSpace($upload / (1048576)),
					'totalstr' => formatFreeSpace(($download + $upload) / (1048576))
					)
				);
			}
			$tmpl->setloop('month_list', $month_list);
			// weekly stats
			$xferStats = ($mstart)
				? Xfer::getUsageByDate($username, $mstart, $mend)
				: Xfer::getUsageByDate($username);
			$start = '';
			$download = 0;
			$upload = 0;
			$week_list = array();
			foreach ($xferStats as $row) {
				$rtime = strtotime($row[2]);
				$newstart = date('d M Y', strtotime('+1 Day last '.$cfg['week_start'], $rtime));
				if ($start != $newstart) {
					if ($upload + $download != 0) {
						array_push($week_list, array(
							'user_id' => $username,
							'month' => $_month,
							'week' => date('Y-m-d',strtotime($start)),
							'start' => $start,
							'downloadstr' => formatFreeSpace($download / (1048576)),
							'uploadstr' => formatFreeSpace($upload / (1048576)),
							'totalstr' => formatFreeSpace(($download+$upload) / (1048576))
							)
						);
					}
					$download = $row[0];
					$upload = $row[1];
					$start = $newstart;
				} else {
					$download += $row[0];
					$upload += $row[1];
				}
			}
			if ($upload + $download != 0) {
				array_push($week_list, array(
					'user_id' => $username,
					'month' => $_month,
					'week' => date('Y-m-d',strtotime($start)),
					'start' => $start,
					'downloadstr' => formatFreeSpace($download / (1048576)),
					'uploadstr' => formatFreeSpace($upload / (1048576)),
					'totalstr' => formatFreeSpace(($download+$upload) / (1048576))
					)
				);
			}
			$tmpl->setloop('week_list', $week_list);
			// daily stats
			$xferStats = ($wstart)
				? Xfer::getUsageByDate($username, $wstart, $wend)
				: Xfer::getUsageByDate($username);
			$start = '';
			$download = 0;
			$upload = 0;
			$day_list = array();
			foreach ($xferStats as $row) {
				$rtime = strtotime($row[2]);
				$newstart = $row[2];
				if ($row[2] == date('Y-m-d')) {
					if ($user_id == '%') {
						$row[0] = $xfer_total['day']['download'];
						$row[1] = $xfer_total['day']['upload'];
					} else {
						$row[0] = $xfer[$username]['day']['download'];
						$row[1] = $xfer[$username]['day']['upload'];
					}
				}
				if ($upload + $download != 0) {
					array_push($day_list, array(
						'start' => $start,
						'downloadstr' => formatFreeSpace($download / (1048576)),
						'uploadstr' => formatFreeSpace($upload / (1048576)),
						'totalstr' => formatFreeSpace(($download+$upload) / (1048576))
						)
					);
				}
				$download = $row[0];
				$upload = $row[1];
				$start = $newstart;
			}
			if ($upload + $download != 0) {
				array_push($day_list, array(
					'start' => $start,
					'downloadstr' => formatFreeSpace($download / (1048576)),
					'uploadstr' => formatFreeSpace($upload / (1048576)),
					'totalstr' => formatFreeSpace(($download+$upload) / (1048576))
					)
				);
			}
			$tmpl->setloop('day_list', $day_list);
			//
			$tmpl->setvar('_TOTAL', $cfg["_TOTAL"]);
			$tmpl->setvar('_SERVERXFERSTATS', $cfg['_SERVERXFERSTATS']);
			$tmpl->setvar('_USERDETAILS', $cfg['_USERDETAILS']);
			$tmpl->setvar('_USER', $cfg["_USER"]);
			$tmpl->setvar('_TOTALXFER', $cfg["_TOTALXFER"]);
			$tmpl->setvar('_MONTHXFER', $cfg["_MONTHXFER"]);
			$tmpl->setvar('_WEEKXFER', $cfg["_WEEKXFER"]);
			$tmpl->setvar('_DAYXFER', $cfg["_DAYXFER"]);
			$tmpl->setvar('_DOWNLOAD', $cfg['_DOWNLOAD']);
			$tmpl->setvar('_UPLOAD', $cfg['_UPLOAD']);
		}
		//
		$tmpl->setvar('table_admin_border', $cfg["table_admin_border"]);
		break;

	// default
	default:
		@error("Invalid Type", "index.php?iid=serverStats", "", array($type));
		break;
}

// set vars
$tmpl->setvar('type', $type);

// more vars
tmplSetTitleBar($cfg["pagetitle"].' - Server Stats');
tmplSetFoot();
$tmpl->setvar('enable_multiupload', $cfg["enable_multiupload"]);
$tmpl->setvar('_MULTIPLE_UPLOAD', $cfg['_MULTIPLE_UPLOAD']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>