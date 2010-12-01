#!/usr/bin/php
<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
		by Epsylon3 on gmail.com, Nov 2010

Require PHP 5 for public/protected members

*/

# sample cron.d update vuze rpc stat files every minutes
# */1 * * * *     www-data cd /var/git/torrentflux/clients/vuzerpc ;./vuzerpc_cron.php update

chdir('../../html');

//get $cfg
require("inc/main.core.php");

require("inc/classes/VuzeRPC.php");

global $cfg;
$cfg["user"] = 'cron';

//print_r($cfg);

//commented to keep default (use db cfg)
//$cfg['vuze_rpc_host']='127.0.0.1';
//$cfg['vuze_rpc_port']='19091';
//$cfg['vuze_rpc_user']='vuze';
//$cfg['vuze_rpc_password']='mypassword';

function updateStatFiles() {
	global $cfg, $db;

	//convertTime
	require_once("inc/functions/functions.core.php");

	$client = 'vuzerpc';

	$vuze = VuzeRPC::getInstance();

	// do special-pre-start-checks
	if (!VuzeRPC::isRunning()) {
		return;
	}

	// log
	echo $client.": updateStatFiles()\n";
	//AuditAction($cfg["constants"]["debug"], $client.": updateStatFiles()");

	$tfs = $vuze->torrent_get_tf();
	//file_put_contents($cfg["path"].'.vuzerpc/'."updateStatFiles.log",serialize($tfs));

	if (empty($tfs))
		return;

	$hashes = array("''");
	foreach ($tfs as $hash => $t) {
		$hashes[] = "'".strtolower($hash)."'";
	}

	$sql = "SELECT hash, transfer, sharekill FROM tf_transfers WHERE type='torrent' AND client='azureus' AND hash IN (".implode(',',$hashes).")";
	$recordset = $db->Execute($sql);
	$hashes=array();
	$sharekills=array();
	while (list($hash, $transfer, $sharekill) = $recordset->FetchRow()) {
		$hash = strtoupper($hash);
		$hashes[$hash] = $transfer;
		$sharekills[$hash] = $sharekill;
	}

	//SHAREKILLS
	$nbUpdate=0;
	foreach ($tfs as $hash => $t) {
		if (isset($sharekills[$hash])) {
			if (($t['status']==8 || $t['status']==9) && $t['sharing'] > $sharekills[$hash]) {
				
				$transfer = $hashes[$hash];
				
				$nbUpdate++;
				
				if (!$vuze->torrent_stop_tf($hash)) {
					AuditAction($cfg["constants"]["admin"], $client.": stop error $transfer.");
				} else {
					// log
					AuditAction($cfg["constants"]["stop_transfer"], $client.": sharekill stopped $transfer");
					// flag the transfer as stopped (in db)
					stopTransferSettings($transfer);
				}
			}
		}
	}
	echo " stopped $nbUpdate torrents.\n";
	
	$nbUpdate=0;
	foreach ($tfs as $hash => $t) {
		if (isset($hashes[$hash])) {

			$nbUpdate++;
			
			$transfer = $hashes[$hash];
			
			//file_put_contents($cfg["path"].'.vuzerpc/'."updateStatFiles4.log",serialize($t));
			$sf = new StatFile($transfer);
			$sf->running = $t['running'];

			if ($t['eta'] < -1) {
				$t['eta'] = "Finished in ".convertTime(abs($t['eta']));
			} elseif ($t['eta'] > 0) {
				$t['eta'] = convertTime($t['eta']);
			} elseif ($t['eta'] == -1) {
				$t['eta'] = "";
			}
			$sf->time_left = $t['eta'];

			if ($sf->running) {

				$sf->percent_done = $t['percentDone'];

				if ($t['status'] != 9 && $t['status'] != 5) {
					$sf->peers = $t['peers'];
					$sf->seeds = $t['seeds'];
				}

				if ((float)$t['speedDown'] > 0.0)
					$sf->down_speed = formatBytesTokBMBGBTB($t['speedDown'])."/s";
				if ((float)$t['speedUp'] > 0.0)
					$sf->up_speed = formatBytesTokBMBGBTB($t['speedUp'])."/s";

				if ($t['status'] == 8) {
					$sf->percent_done = 100 + $t['sharing'];
					$sf->down_speed = "&nbsp;";
					if (trim($sf->up_speed) == '')
						$sf->up_speed = "&nbsp;";
				}
				if ($t['status'] == 9) {
					$sf->percent_done = 100 + $t['sharing'];
					$sf->up_speed = "&nbsp;";
					$sf->down_speed = "&nbsp;";
				}

			} else {
				//Stopped or finished...
				
				$sf->down_speed = "";
				$sf->up_speed = "";
				$sf->peers = "";
				if ($sf->percent_done >= 100 && strpos($sf->time_left, 'Finished') === false) {
					$sf->time_left = "Finished!";
					$sf->percent_done = 100;
				}
				//if ($sf->percent_done < 100 && $sf->percent_done > 0)
				//	$sf->percent_done = 0 - $sf->percent_done;
			}
			
			$sf->downtotal = $t['downTotal'];
			$sf->uptotal = $t['upTotal'];
			
			if (!$sf->size)
				$sf->size = $t['size'];
			
			if ($sf->seeds = -1);
				$sf->seeds = '';
			$sf->write();
		}
	}
	$nb = count($tfs);
	echo " updated $nbUpdate/$nb stat files.\n";
	
//	echo $vuze->lastError."\n";
}
//--------------------------------------------------------------------

global $argv;

if (isset($argv[1]) && $argv[1] == 'list') {
	$v = VuzeRPC::getInstance();
	$torrents = $v->torrent_get_tf();
	//$filter = array('running' => 1);
	//$torrents = $v->torrent_filter_tf($filter);
	echo print_r($torrents,true);
}

if (empty($argv[1]) or $argv[1] == 'update') {
	updateStatFiles();
}
?>
