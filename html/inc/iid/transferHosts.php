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

// transfer functions
require_once('inc/functions/functions.transfer.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.transferHosts.tmpl");

// init transfer
transfer_init();

// stat
$sf = new StatFile($transfer);

// set vars
if ($sf->running == 1) {
	$transfer_pid = getTransferPid($transfer);
	$transfer_cons = netstatConnectionsByPid($transfer_pid);
	$transfer_hosts = netstatHostsByPid($transfer_pid);
} else {
	$transfer_cons = 0;
}
$tmpl->setvar('cons_hosts', ((isset($transfer_cons)) && ($transfer_cons != "")) ? $transfer_cons : 0);
if ((isset($transfer_hosts)) && ($transfer_hosts != "")) {
	$tmpl->setvar('transfer_hosts_aval', 1);
	$tmpl->setvar('_ID_HOST', $cfg['_ID_HOST']);
	$tmpl->setvar('_ID_PORT', $cfg['_ID_PORT']);
	$hostAry = array_keys($transfer_hosts);
	$list_host = array();
	foreach ($hostAry as $host) {
		$host = @trim($host);
		$port = @trim($transfer_hosts[$host]);
		if ($cfg["transferHosts"] == 1)
			$host = @gethostbyaddr($host);
		if ($host != "") {
			$tmpl->setvar('hosts', 1);
			array_push($list_host, array(
				'host' => $host,
				'port' => $port
				)
			);
		}
	}
	$tmpl->setloop('list_host', $list_host);
}

//refresh
//$tmpl->setvar('meta_refresh', '15;URL=index.php?iid=transferHosts&transfer='.$transfer);

// title + foot
tmplSetTitleBar($transferLabel." - ".$cfg['_ID_HOSTS'], false);
tmplSetFoot(false);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>