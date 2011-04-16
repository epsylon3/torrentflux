#!/usr/bin/php
<?php
/*
Transmission-daemon  RPC interface for PHP (CRON)
			  by Epsylon3 on gmail.com, Apr 2011

Require PHP 5 for public/protected members

Temporary cron script used to update .stat files
before full integration in fluxcli.php

*/

# sample cron.d update vuze rpc stat files every minutes
# */1 * * * *     www-data /var/git/torrentflux/clients/transmission-rpc/statrpc_cron.php update

// change to docroot if needed
if (!is_file(realpath(getcwd().'/inc/main.core.php')))
	chdir(realpath(dirname(__FILE__)."/../../html"));

// check for home
if (!is_file('inc/main.core.php'))
	exit("Error: this script can't find main.core.php, please run it in current directory or change chdir in sources.\n");

//get $cfg
require("inc/main.core.php");
global $cfg;

//set username for logs
$cfg["user"] = 'cron';

require_once("inc/functions/functions.rpc.transmission.php");
require_once("inc/classes/Transmission.class.php");

//print_r($cfg);

//commented to keep default (use db cfg)
//$cfg['transmission_rpc_host']='127.0.0.1';
//$cfg['transmission_rpc_port']='9091';
//$cfg['transmission_rpc_user']='transmission';
//$cfg['transmission_rpc_password']='';

$client = 'transmissionrpc';


function updateStatFiles($bShowMissing=false) {
	global $cfg, $db, $client;

	$rpc = Transmission::getInstance($cfg);

	// check if running and get all session variables in cache
	if (!$rpc->session_get()) {
		echo "unable to connect to transmission-daemon\n";
		return;
	}

	$tfs = $rpc->torrent_get_tf();

	if (empty($tfs)) {
		echo "no loaded torrents\n";
		return;
	}

	$sql = "SELECT hash, transfer, sharekill FROM tf_transfers WHERE type='torrent' AND client = 'transmissionrpc'";

	$hashes = array("''");
	foreach ($tfs as $hash => $t) {
		$hashes[] = "'".strtolower($hash)."'";
	}
	$sql .= " AND hash IN (".implode(',',$hashes).")";

	$recordset = $db->Execute($sql);

	$hashes=array();
	$sharekills=array();
	while (list($hash, $transfer, $sharekill) = $recordset->FetchRow()) {
		$hash = strtolower($hash);
		$hashes[$hash] = $transfer;
		$sharekills[$hash] = $sharekill;
	}

	$max_ul = 1024.0 * $cfg['max_upload_rate'];
	$max_dl = 1024.0 * $cfg['max_download_rate'];

	//SHAREKILLS Checks
	$nbUpdate=0;
	foreach ($tfs as $hash => $t) {
		if (!isset($sharekills[$hash]))
			continue;
		if (($t['status']==8 || $t['status']==9) && ($t['sharing']) > $sharekills[$hash]) {
			$transfer = $hashes[$hash];
			$nbUpdate++;
			if (stopTransmissionTransferCron($hash)) {
				AuditAction($cfg["constants"]["debug"], $client.": stop error $transfer.");
			} else {
				AuditAction($cfg["constants"]["stop_transfer"], $this->client."-stat. : sharekill stopped $transfer");
				// flag the transfer as stopped (in db)
				stopTransferSettings($transfer);
			}
		}
	}
	echo " stopped $nbUpdate torrents.\n";

	$nbUpdate=0;
	$missing=array();
	foreach ($tfs as $hash => $t) {

		if (!isset($hashes[$hash])) {
			if ($bShowMissing) $missing[$t['rpcid']] = $t['name'];
			continue;
		}
		
		$transfer = $hashes[$hash];
		
		//file_put_contents($cfg["path"].'.Transmission/'."updateStatFiles4.log",serialize($t));
		$sf = new StatFile($transfer);
		$sf->running = $t['running'];

		if (empty($sf->transferowner)) {
			$uid = getTransferOwnerID($hash);
			if ($uid > 0) {
				$sf->transferowner = GetUsername($uid);
				echo "transfer '$transfer' owner fixed to ".$sf->transferowner." \n";
				$sf->write();
			}
		}

		if ($sf->running) {

			$sharebase = (int) $sharekills[$hash];
			//$sharekill = (int) round(floatval($t['seedRatioLimit']) * 100);
	
			if ($sharebase > 0 && (int) $sf->seedlimit == 0) {
				AuditAction($cfg["constants"]["debug"], $client.": changed empty .stat sharekill ".$sf->seedlimit." to $sharebase (from db), $transfer.");
				$sf->seedlimit = $sharebase;
			}

			$max_ul= max($t['urate'], $max_ul);
			$max_dl= max($t['drate'], $max_dl);
			$max_share = max($sharebase, $sharekill);

			if ($t['eta'] > 0 || $t['eta'] < -1) {
				$sf->time_left = convertTimeText($t['eta']);
			}

			$sf->percent_done = $t['percentDone'];
			$sf->sharing = round($t['sharing'],1);

			if ($t['status'] != 9 && $t['status'] != 5) {
				$sf->peers = $t['peers'];
				$sf->seeds = $t['seeds'];
			}

			if ($t['seeds'] >= 0)
				$sf->seeds = $t['seeds'];

			if ($t['peers'] >= 0)
				$sf->peers = $t['peers'];

			if ((float)$t['speedDown'] >= 0.0)
				$sf->down_speed = formatBytesTokBMBGBTB($t['speedDown'])."/s";
			if ((float)$t['speedUp'] >= 0.0)
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
			$sf->time_left = "0";
			if ($t['eta'] < -1) {
				$sf->time_left = "Done in ".convertTimeText($t['eta']);
			} elseif ($sf->percent_done >= 100 && strpos($sf->time_left, 'Done') === false && strpos($sf->time_left, 'Finished') === false) {
				$sf->percent_done = 100;
				$sf->time_left = "Done!";
			}
			
			if ($sf->sharing == 0)
				$sf->sharing = round($t['sharing'],1);
			
			if (is_file($cfg["transfer_file_path"].'/'.$transfer.".pid"))
				unlink($cfg["transfer_file_path"].'/'.$transfer.".pid");
			
			//if ($sf->percent_done < 100 && $sf->percent_done > 0)
			//	$sf->percent_done = 0 - $sf->percent_done;
		}

		$sf->downtotal = $t['downTotal'];
		$sf->uptotal = $t['upTotal'];
		
		if ($sf->size == 0)
			$sf->size = $t['size'];
		
		if ($sf->seeds = -1);
			$sf->seeds = '';

		if ($sf->write()) {
			$nbUpdate++;
		}
	}
	$nb = count($tfs);
	echo " updated $nbUpdate/$nb stat files.\n";

	//fix globall sharekill to maximum of torrents sharekill, other torrent with lower sharekill will be stopped by this cron
	/*
	if (isset($max_share))  {
		$sharekill = getTransmissionShareKill();
		if ($max_share > $sharekill) {
			//set vuze global sharekill to max sharekill value
			$rpc->session_set( array('seedRatioLimit' => round($max_share / 100, 2)) );
			if ($cfg['debuglevel'] > 0) {
				$msg = $client.": changed vuze global sharekill from $sharekill to $max_share.";
				AuditAction($cfg["constants"]["debug"], $msg);
				echo $msg."\n";
			}
		}
	}
	if ($max_ul > 0) {
		$vzmaxul = getTransmissionSpeedLimitUpload();
		if ($cfg['max_upload_rate'] > 0 && $max_ul > 0) {
			$max_ul = min($max_ul, 1024.0 * $cfg['max_upload_rate']);
		}
		if ($vzmaxul != $max_ul) {
			$max_ul = $max_ul / 1024;
			$rpc->session_set( array('speed-limit-up' => $max_ul) );
			if ($cfg['debuglevel'] > 0) {
				$msg = $client.": tranmission global speed-limit-up from $vzmaxul to $max_ul.";
				AuditAction($cfg["constants"]["debug"], $msg);
				echo $msg."\n";
			}
		}
	}
	*/

	if ($bShowMissing) return $missing;
}
//--------------------------------------------------------------------

global $argv;

// prevent invocation from web
if (empty($argv[0])) die("command line only");
if (isset($_REQUEST['argv'])) die("command line only");

// list vuze torrents (via rpc)
$cmd = isset($argv[1]) ? $argv[1] : '';
switch ($cmd) {
	case 'list':
		$rpc = Transmission::getInstance();
		$torrents = $rpc->torrent_get_tf();
		//$filter = array('running' => 1);
		//$torrents = $rpc->torrent_filter_tf($filter);
		echo print_r($torrents,true);
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	// list seeding torrents (via rpc)
	case 'seed':
		$rpc = Transmission::getInstance();
		$torrents = $rpc->torrent_get_tf();
		$filter = array('running' => 1, 'status' => 8);
		$torrents = $rpc->torrent_filter_tf($filter);
		echo print_r($torrents,true);
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	// list downloading torrents (via rpc)
	case 'down':
		$rpc = Transmission::getInstance();
		$torrents = $rpc->torrent_get_tf();
		$filter = array('running' => 1, 'status' => 4);
		$torrents = $rpc->torrent_filter_tf($filter);
		echo print_r($torrents,true);
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	// get session settings (via rpc)
	case 'session':
		$rpc = Transmission::getInstance($cfg);
		$session = $rpc->session_get();
		print_r($session);
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	// torrent missing in torrentflux
	case 'missing':
		$missing = updateStatFiles($bShowMissing=true);
		if (!empty($missing)) {
			echo "Not in TorrentFlux:\n";
			print_r($missing);
		} else {
			echo "No missing Torrents\n";
		}
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	case 'config':
		print_r($cfg);
		if (!empty($rpc->lastError)) {
			echo $rpc->lastError."\n";
		}
	break;

	case 'version':
		$rpc = Transmission::getInstance($cfg);
		echo "Transmission :".$rpc->version."\n";
	break;

	case 'update':
		if ($cfg['transmission_rpc_enable']==0) {
			//cron disabled
			return;
		}
		echo $client.": updateStatFiles()\n";
		updateStatFiles();
	break;

	default:
		echo "usage : ./statrpc_cron.php [update,config,session,list,down,seed,missing,version]\n";
}

?>
