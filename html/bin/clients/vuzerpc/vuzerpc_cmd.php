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

$_SESSION['user'] = 'cron';
	
//get $cfg
require("inc/main.core.php");

require("inc/classes/VuzeRPC.php");

global $cfg;

$cfg["uid"] = 'cron';

//commented to keep default
//$cfg['vuze_rpc_host']='127.0.0.1';
//$cfg['vuze_rpc_port']='19091';
//$cfg['vuze_rpc_user']='vuze';
//$cfg['vuze_rpc_pass']='mypassword';

function updateStatFiles() {
	global $cfg, $db;

	$client = 'vuzerpc_cmd.php';

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
				$sf->down_speed = "";
				$sf->up_speed = "";
				$sf->peers = "";
				if ($sf->percent_done >= 100 && strpos($sf->time_left, 'Finished') === false)
					$sf->time_left = "Finished!";
				if ($sf->percent_done < 100 && $sf->percent_done > 0)
					$sf->percent_done = 0 - $sf->percent_done;
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
					AuditAction($cfg["constants"]["debug"], $client.": stop error $transfer.");
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
}
//--------------------------------------------------------------------

global $argv;

if ($argv[1] == 'list') {
	$v = VuzeRPC::getInstance();
	$torrents = $v->torrent_get_tf();
	//$filter = array('running' => 1);
	//$torrents = $v->torrent_filter_tf($filter);
	echo print_r($torrents,true);
}

if (empty($argv[1]) or $argv[1] == 'update')
	updateStatFiles();

?>