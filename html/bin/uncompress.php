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

$logfile = 'error.log';

//convert and set variables
$arg1 = urldecode($argv[1]);
$arg2 = urldecode($argv[2]);
$arg3 = $argv[3];
$arg4 = $argv[4];
$arg5 = $argv[5];

// unrar file
if (strcasecmp('rar', $arg3) == 0){
	if (file_exists($arg2.$logfile))
		@unlink($arg2.$logfile);
    $Command = tfb_shellencode($arg4)." x -o+ -p". tfb_shellencode($arg5) ." ". tfb_shellencode($arg1) . " " . tfb_shellencode($arg2);
	$unrarpid = trim(shell_exec("nohup ".$Command." > " . tfb_shellencode($arg2.$logfile) . " 2>&1 & echo $!"));
	echo 'Uncompressing file...<BR>PID is: ' . $unrarpid . '<BR>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unrarpid)) {
		if (file_exists($arg2.$logfile)) {
			$lines = file($arg2.$logfile);
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
	if (file_exists($arg2.$logfile)) {
		$lines = file($arg2.$logfile);
		foreach($lines as $chkline) {
			if (strpos($chkline, 'All OK') !== FALSE){
				echo 'File has successfully been extracted!';
				@unlink($arg2.$logfile);
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
	if (file_exists($arg2.$logfile))
		@unlink($arg2.$logfile);
    $Command = tfb_shellencode($arg4).' -o ' . tfb_shellencode($arg1) . ' -d ' . tfb_shellencode($arg2);
	$unzippid = trim(shell_exec("nohup ".$Command." > " . tfb_shellencode($arg2.$logfile) . " 2>&1 & echo $!"));
	echo 'Uncompressing file...<BR>PID is: ' . $unzippid . '<BR>';
	usleep(250000); // wait for 0.25 seconds
	while (is_running($unzippid)) {
		usleep(250000); // wait for 0.25 seconds
		/* occupy time to cause popup window load bar to load in conjunction with unzip progress */
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