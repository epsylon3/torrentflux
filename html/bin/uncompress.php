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

// prevent invocation from web
if (empty($argv[0])) die();
if (isset($_REQUEST['argv'])) die();

// dummy
$_SESSION = array('cache' => false);

/******************************************************************************/

// change to docroot if needed
if (!is_file(realpath(getcwd().'/inc/functions/functions.core.php')))
	chdir(realpath(dirname(__FILE__)."/.."));

// check for home
if (!is_file('inc/functions/functions.core.php'))
	exit("Error: this script can only be used in its default-path (DOCROOT/bin/)\n");

// core functions
require_once('inc/functions/functions.core.php');

/**
 * @author    R.D. Damron
 * @name      rar/zip uncompression
 * @usage	  ./uncompress.php "pathtofile" "extractdir" "typeofcompression" "uncompressor-bin" "password"
 */


//convert and set variables
$arg1 = urldecode($argv[1]); //file
$arg2 = urldecode($argv[2]); //dir
$arg3 = $argv[3]; //ext
$arg4 = urldecode($argv[4]); //bin_unxxx
$arg5 = urldecode($argv[5]); //password

$dir = $arg2;
$subdir = 'extracted';
$destdir = $dir."/".$subdir;
$logfile = $dir.'/'.$subdir.'/error.log';

// unrar file
if (strcasecmp('rar', $arg3) == 0) {
	if (!is_dir($destdir)) {
		mkdir($destdir);
	}
	chmod($destdir,01777);
	if (file_exists($logfile)) @unlink($logfile);
	if (is_dir($arg1)) {
		$arg1.="/*.rar";
	}
	$Command = tfb_shellencode($arg4)." x -o+ -p". tfb_shellencode($arg5) ." ". tfb_shellencode($arg1) . " " . tfb_shellencode($destdir);
	$unrarpid = trim(shell_exec("nohup ".$Command." > " . tfb_shellencode($logfile) . " 2>&1 & echo $!"));
	echo 'Uncompressing file...<BR>PID is: ' . $unrarpid . '<BR>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unrarpid)) {
		if (file_exists($logfile)) {
			$lines = file($logfile);
			foreach($lines as $chkline) {
				if (strpos($chkline, 'already exists. Overwrite it ?') !== FALSE){
					kill($unrarpid);
					echo 'File has already been extracted, please delete extracted file if re-extraction is necessary.';
					break 2;
				}
				if (strpos($chkline, 'Cannot find volume') !== FALSE){
					kill($unrarpid);
					echo 'File has a missing volume and can not been extracted.';
					break 2;
				}
				if (strpos($chkline, 'ERROR: Bad archive') !== FALSE){
					kill($unrarpid);
					echo 'File has a bad volume and can not been extracted.';
					break 2;
				}
				if (strpos($chkline, 'CRC failed') !== FALSE){
					kill($unrarpid);
					echo 'File extraction has failed with a CRC error and was not been extracted.';
					break 2;
				}
			}
		}
		usleep(250000); // wait for 0.25 seconds
	}
	if (file_exists($logfile)) {
		$lines = file($logfile);
		foreach($lines as $chkline) {
			if (strpos($chkline, 'All OK') !== FALSE){
				echo 'File has successfully been extracted!';
				@unlink($logfile);
				// exit
				exit();
			}
		}
	}
	// exit
	exit();
}

// unzip
if (strcasecmp('zip', $arg3) == 0) {
	if (!is_dir($destdir)) {
		mkdir($destdir);
	}
	if (is_dir($arg1)) {
		$arg1.="/*.zip";
	}
	chmod($destdir,01777);
	if (file_exists($logfile))
		@unlink($logfile);
	$Command = tfb_shellencode($arg4).' -o ' . tfb_shellencode($arg1) . ' -d ' . tfb_shellencode($destdir);
	$unzippid = trim(shell_exec("nohup ".$Command." 2> " . tfb_shellencode($logfile) . " 1>/dev/null & echo $!"));
	echo 'Uncompressing file...<br/>PID is: ' . $unzippid . '<br/>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unzippid)) {
		usleep(250000); // wait for 0.25 seconds
		/* occupy time to cause popup window load bar to load in conjunction with unzip progress */
	}
	if (filesize($logfile) == 0) {
		echo '<br/><br/>File has successfully been extracted!';
		@unlink($logfile);
	}
	// exit
	exit();
}

/**
 * is_running
 *
 * @param $PID
 * @return
 */
function is_running($PID){
	$ProcessState = shell_exec("ps ".tfb_shellencode($PID));
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
	exec("rm -f ".tfb_shellencode($file));
	return true;
}

?>