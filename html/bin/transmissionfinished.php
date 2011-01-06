#!/usr/bin/env php
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

// Get variables set by Transmission
$tAppVer = getenv('TR_APP_VERSION');
$tLocalTime = getenv('TR_TIME_LOCALTIME');
$tDir = getenv('TR_TORRENT_DIR');
$tHash = getenv('TR_TORRENT_HASH');
$tId = getenv('TR_TORRENT_ID');
$tName = getenv('TR_TORRENT_NAME');

$debug = true;
$debugtofile = true;
$debuglog = "/var/log/tfng.log";
$logfile = "error.log"; // TODO: get this out of the code... it is used for log of unzip unrar operations

/******************************************************************************/

function debug($message) {
	global $debug, $debuglog, $debugtofile;
	if ($debug) print($message);
	
	if ($debugtofile) {
		$fh = fopen($debuglog, 'a') or die("Can't open file");
		fwrite($fh, date("Ymd G:i:s").' '.$message);
		fclose($fh);
	}
}

debug( "Get Transfer with hash $tHash\n" );
chdir(dirname(__FILE__).'/../');
debug( "The current working directory: ".getcwd()."\n" );

require_once('inc/functions/functions.common.php'); // Necessary for message functions
require_once('inc/functions/functions.rpc.transmission.php');
require_once('inc/main.core.php');

$options = array('files','downloadDir','name');
$returnArr = getTransmissionTransfer($tHash, $options);
SaveMessage("administrator", "administrator", htmlentities("Torrent ".$returnArr['name']." has finished"), 0, 0);

if ( ! $returnArr ) {
	debug("No result. Exiting...\n");
	exit();
} else {
	print_r( $returnArr );
}

$supportedCompression = array('zip','rar','gz','bzip','tbz','tgz','tar');
foreach ( $returnArr['files'] as $fileentry) {
	$filename = $fileentry['name'];
	debug( "The filename is $filename\n" );
	$ext = end( explode('.', $filename) );
	debug( "The extension is $ext\n" );

	if (in_array($ext, $supportedCompression)) {
		debug( "$filename is a supported archive\n" );
		$owner = getTransmissionTransferOwner($tHash);
		debug( "The owner is $owner\n" );
		$message = "Torrent $tName was finished and unzipping of file $filename has started.";

		//SaveMessage($to_user, $cfg["user"], htmlentities($message), (empty($to_all_r)) ? 0 : 1, (!empty($force_read_r) && $cfg['isAdmin']) ? 1 : 0);
		SaveMessage($owner, $owner, htmlentities($message), 0, 0);
		
		// start uncompressing the file
		if ( $ext === "rar" )
			unrarFile($returnArr['downloadDir'], $filename, $returnArr['name']);
		if ( $ext === "zip" )
			unzipFile($returnArr['downloadDir'], $filename, $returnArr['name']);
		if ( $ext === "gz" )
			print("Not yet implemented");
	}
}
debug("\nEnd of script");
exit();

// unrar file
function unrarFile($downloadDir, $filename, $name, $password = "-") {
	global $logfile, $cfg;
	$unrar = $cfg['bin_unrar'];
	$log = $downloadDir.$logfile;

	if (file_exists($log))
		@unlink($log);
	if ($downloadDir.'/'.$filename === $downloadDir.'/'.$name)
		$destdir = $downloadDir.'/'.str_replace('.rar', '', $name);
	else
		$destdir = $downloadDir.'/'.$name;
	$Command = tfb_shellencode($unrar)." x -o+ -p". tfb_shellencode($password) ." ". tfb_shellencode($downloadDir.'/'.$filename) . " " . tfb_shellencode($destdir);

	$unrarpid = trim(shell_exec("nohup ".$Command." > " . tfb_shellencode($log) . " 2>&1 & echo $!"));
	echo 'Uncompressing file...<BR>PID is: ' . $unrarpid . '<BR>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unrarpid)) {
		if (file_exists($log)) {
			$lines = file($log);
			foreach($lines as $chkline) {
				if (strpos($chkline, 'already exists. Overwrite it ?') !== FALSE){
					kill($unrarpid);
					debug('File has already been extracted, please delete extracted file if re-extraction is necessary.');
					break 2;
				}
				if (strpos($chkline, 'Cannot find volume') !== FALSE){
					kill($unrarpid);
					debug('File has a missing volume and can not been extracted.');
					break 2;
				}
				if (strpos($chkline, 'ERROR: Bad archive') !== FALSE){
					kill($unrarpid);
					debug('File has a bad volume and can not been extracted.');
					break 2;
				}
				if (strpos($chkline, 'CRC failed') !== FALSE){
					kill($unrarpid);
					debug('File extraction has failed with a CRC error and was not been extracted.');
					break 2;
				}
			}
		}
		usleep(250000); // wait for 0.25 seconds
	}
	if (file_exists($log)) {
		$lines = file($log);
		foreach($lines as $chkline) {
			if (strpos($chkline, 'All OK') !== FALSE){
				debug('File has successfully been extracted!');
				@unlink($log);
				return;
			}
		}
	}
}

// unzip
function unzipFile ($downloadDir, $filename, $name) {
	global $logfile, $cfg;
	$log = $downloadDir.$logfile;
	$unzip = $cfg['bin_unzip'];

	if (file_exists($log))
		@unlink($log);
	if ($downloadDir.'/'.$filename === $downloadDir.'/'.$name)
		$destdir = $downloadDir.'/'.str_replace('.zip', '/', $name);
	else
		$destdir = $downloadDir.'/'.$name;
	$Command = tfb_shellencode($unzip).' -o ' . tfb_shellencode($downloadDir.'/'.$filename) . ' -d ' . tfb_shellencode($destdir);
	debug($Command."\n");
	$unzippid = trim(shell_exec("nohup ".$Command." > ".tfb_shellencode($log)." 2>&1 & echo $!"));
	debug($unzippid."\n");
	echo 'Uncompressing file...<BR>PID is: ' . $unzippid . '<BR>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unzippid)) {
		usleep(250000); // wait for 0.25 seconds
		/* occupy time to cause popup window load bar to load in conjunction with unzip progress */
	}
}

/**
 * is_running
 *
 * @param $PID
 * @return
 */
function is_running($PID){
    $ProcessState = exec("ps ".tfb_shellencode($PID));
    return (count($ProcessState) >= 2);
}

/**
 * kill
 *
 * @param $PID
 * @return
 */
function kill($PID){
    exec("kill -KILL ".tfb_shellencode($PID));
    return true;
}

/**
 * del
 *
 * @param $file
 * @return
 */
function del($file){
    exec("rm -rf ".tfb_shellencode($file));
    return true;
}

?>
