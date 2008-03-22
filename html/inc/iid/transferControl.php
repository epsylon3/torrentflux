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

// common functions
require_once('inc/functions/functions.common.php');

// transfer functions
require_once('inc/functions/functions.transfer.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.transferControl.tmpl");

// init transfer
transfer_init();

// request-vars
$pageop = tfb_getRequestVar('pageop');
$client = tfb_getRequestVar('client');

// init ch-instance
$ch = ($client == "")
	? ClientHandler::getInstance(getTransferClient($transfer))
	: ClientHandler::getInstance($client);

// customize-vars
transfer_setCustomizeVars();

// load settings, default if settings could not be loaded (fresh transfer)
if ($ch->settingsLoad($transfer) !== true) {
	$ch->settingsDefault();
	$settings_exist = 0;
} else {
	$settings_exist = 1;
}
$tmpl->setvar('settings_exist', $settings_exist);

// set running-field
$ch->running = isTransferRunning($transfer) ? 1 : 0;
$tmpl->setvar('running', $ch->running);

// sf
$sf = new StatFile($transfer);

// pageop
//
// * control (start, stats)
// * start (form or link)
//
if (empty($pageop)) {
	if ($ch->running == 1) {
		$pageop = "control";
		$sf->running = 1;
	} else {
		switch ($sf->running) {
			case 0:
			case 2:
				$pageop = "start";
				break;
			case 1:
				$ch->running = 1;
			case 3:
				$pageop = "control";
				break;
			default:
				@error("We got a Problem, Stat-File-state unknown.", "", "", array($transfer));
		}
	}
}
$tmpl->setvar('pageop', $pageop);

// op-switch
switch ($pageop) {

	case "control":                                                /* control */

		switch ($sf->running) {

			case 1: // running
				// state
				$tmpl->setvar('state', "running");
				// get pid
				$pid = 0;
		        $running = $ch->runningProcesses();
		        foreach ($running as $rng) {
		            $rt = RunningTransfer::getInstance($rng['pinfo'], $ch->client);
		            if ($rt->transferFile == $transfer) {
		            	$pid = $rt->processId;
						break;
					}
		        }
				if ($pid == 0)
					$pid = getTransferPid($transfer);
				$tmpl->setvar('pid', $pid);
				// break
				break;

			case 3: // queued
				// state
				$tmpl->setvar('state', "queued");
				// break
				break;
		}

		// break
		break;

	case "start":                                                    /* start */

		// client-chooser
		if ($ch->type == "torrent") {
			$tmpl->setvar('enableClientChooser', 1);
			$tmpl->setvar('enableBtclientChooser', $cfg["enable_btclient_chooser"]);
			if ($cfg["enable_btclient_chooser"] != 0)
				tmplSetClientSelectForm($ch->client);
			else
				$tmpl->setvar('btclientDefault', $ch->client);
		} else {
			$tmpl->setvar('enableClientChooser', 0);
		}

		// set vars
		transfer_setProfiledVars();

		// file prio
		if (($cfg["supportMap"][$ch->client]['file_priority'] == 1) && ($cfg["enable_file_priority"] == 1)) {
			require_once("inc/functions/functions.fileprio.php");
			$tmpl->setvar('filePrio', getFilePrioForm($transfer, false));
			$tmpl->setvar('file_priority_enabled', 1);
			$tmpl->setvar('enable_file_priority', 1);
		} else {
			$tmpl->setvar('file_priority_enabled', 0);
			$tmpl->setvar('enable_file_priority', 0);
		}

		// dirtree
		if ($cfg["supportMap"][$ch->client]['savepath'] == 1) {
			$tmpl->setvar('showdirtree', $cfg["showdirtree"]);
			if ($cfg["showdirtree"] == 1)
				tmplSetDirTree($ch->savepath, $cfg["maxdepth"]);
		} else {
			$tmpl->setvar('showdirtree', 0);
		}

		// hash-check
		$tmpl->setvar('skip_hash_check_enabled', $cfg["supportMap"][$ch->client]['skip_hash_check']);
		if ($cfg["supportMap"][$ch->client]['skip_hash_check'] == 1) {
			$dsize = getTorrentDataSize($transfer);
			$tmpl->setvar('is_skip',
				(($dsize > 0) && ($dsize != 4096))
					? $cfg["skiphashcheck"]
					: 0
			);
		} else {
			$tmpl->setvar('is_skip', 0);
		}

		// queue
		$tmpl->setvar('is_queue', (FluxdQmgr::isRunning()) ? 1 : 0);

		// break
		break;

	default:                                                       /* default */
		@error("Invalid pageop", "", "", array($pageop));

}

// title + foot
tmplSetFoot(false);
tmplSetTitleBar($transferLabel." - Control", false);

// lang vars
$tmpl->setvar('_RUNTRANSFER', $cfg['_RUNTRANSFER']);
$tmpl->setvar('_STOPTRANSFER', $cfg['_STOPTRANSFER']);
$tmpl->setvar('_DELQUEUE', $cfg['_DELQUEUE']);

// iid
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>