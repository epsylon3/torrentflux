#!/usr/bin/php
<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
		by Epsylon3 on gmail.com, Nov 2010

Require PHP 5 for public/protected members

*/

# sample cron.d update vuze rpc stat files every minutes
# */1 * * * *     www-data cd /var/www/dedib.ath.cx/bin/clients/vuzerpc ;./vuzerpc_cmd.php update

chdir('../../../');

//get $cfg
require("inc/main.core.php");

require("inc/classes/VuzeRPC.php");

//--------------------------------------------------------
//Test config
global $cfg;

//commented to keep default
//$cfg['vuze_rpc_host']='127.0.0.1';
//$cfg['vuze_rpc_port']='19091';
//$cfg['vuze_rpc_user']='vuze';
//$cfg['vuze_rpc_pass']='mypassword';

function updateStatFiles() {
	global $cfg, $db, $argv;

	$vuze = VuzeRPC::getInstance();

	// do special-pre-start-checks
	if (!VuzeRPC::isRunning()) {
		return;
	}

	// log
	echo $argv[0].": updateStatFiles()\n";
	//AuditAction($cfg["constants"]["debug"], $argv[0].": updateStatFiles()");

	$tfs = $vuze->torrent_get_tf();
	//file_put_contents($cfg["path"].'.vuzerpc/'."updateStatFiles.log",serialize($tfs));

	if (empty($tfs))
		return;

	$hashes = array("''");
	foreach ($tfs as $name => $t)
		$hashes[$t['hashString']] = "'".$t['hashString']."'";

	$sql = "SELECT hash, transfer, sharekill FROM tf_transfers WHERE type='torrent' AND client='azureus' AND hash IN (".implode(',',$hashes).")";
	$recordset = $db->Execute($sql);
	$hashes=array();
	$sharekills=array();
	while (list($hash, $transfer, $sharekill) = $recordset->FetchRow()) {
		$hashes[$hash] = $transfer;
		$sharekills[$hash] = $sharekill;
	}

	//convertTime
	require_once("inc/functions/functions.core.php");

	$nbUpdate=0;
	foreach ($tfs as $name => $t) {
		if (isset($hashes[$t['hashString']])) {

			$nbUpdate++;
			
			$transfer = $hashes[$t['hashString']];
			
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

				$sf->percent_done = max($t['percentDone'],$t['sharing']);

				if ($t['status'] != 9 && $t['status'] != 5) {
					$sf->peers = $t['peers'];
					$sf->seeds = $t['seeds'];
				}

				if ($t['speedDown'] > 0)
					$sf->down_speed = formatBytesTokBMBGBTB($t['speedDown']);
				if ($t['speedUp'] > 0)
					$sf->up_speed = formatBytesTokBMBGBTB($t['speedUp']);

				if ($t['status'] == 8) {
					$sf->percent_done = 100;
					$sf->down_speed = "";
				}
				if ($t['status'] == 9) {
					$sf->percent_done = 100;
					$sf->up_speed = "";
					$sf->down_speed = "";
				}

			} else {
				$sf->down_speed = "";
				$sf->up_speed = "";
				$sf->peers = "";
				if ($sf->percent_done >= 100)
					$sf->time_left = "Download Succeeded!";
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
	
	//SHAREKILLS
	$nbUpdate=0;
	foreach ($tfs as $name => $t) {
		if (isset($sharekills[$t['hashString']])) {
			if (($t['status']==8 || $t['status']==9) && $t['sharing'] > $sharekills[$t['hashString']]) {
				
				$transfer = $hashes[$t['hashString']];
				
				$nbUpdate++;
				
				if (!$vuze->torrent_stop_tf($t['hashString'])) {
					AuditAction($cfg["constants"]["debug"], $argv[0].": stop error $transfer.");
				} else {
					// log
					AuditAction($cfg["constants"]["debug"], $argv[0].": sharekill stopped $transfer");
					// flag the transfer as stopped (in db)
					stopTransferSettings($transfer);
				}
			}
		}
	}
	echo " stopped $nbUpdate torrents.\n";
}

//$v = VuzeRPC::getInstance();
//$v->torrent_get_tf();
//$filter = array('running' => 1);
//$torrents = $v->torrent_filter_tf($filter);
//echo print_r($torrents,true);

updateStatFiles();

?>
