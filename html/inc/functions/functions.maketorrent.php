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

/**
 * create torrent with BitTornado
 *
 * @return string $onLoad
 */
function createTorrentTornado() {
	global $cfg, $path, $tfile, $announce, $ancelist, $comment, $piece, $alert, $private, $dht;
	// sanity-check
	if ((empty($announce)) || ($announce == "http://"))
		return;
	$onLoad = "";
	// Clean up old files
	if (@file_exists($cfg["transfer_file_path"].$tfile))
		@unlink($cfg["transfer_file_path"].$tfile );
	// This is the command to execute
	$command = "nohup ".$cfg["pythonCmd"]." -OO";
	$command .= " ".tfb_shellencode($cfg["docroot"]."bin/clients/tornado/btmakemetafile.py");
	$command .= " ".tfb_shellencode($announce);
	$command .= " ".tfb_shellencode($cfg["path"].$path);
	// Is there comments to add?
	if (!empty($comment))
		$command .= " --comment ".tfb_shellencode($comment);
	// Set the piece size
	if (!empty($piece))
		$command .= " --piece_size_pow2 ".tfb_shellencode($piece);
	if (!empty($ancelist)) {
		$check = "/".str_replace("/", "\/", quotemeta($announce)) . "/i";
		// if they didn't add the primary tracker in, we will add it for them
		if (preg_match( $check, $ancelist, $result))
			$command .= " --announce_list ".tfb_shellencode($ancelist);
		else
			$command .= " --announce_list ".tfb_shellencode($announce.",".$ancelist);
	}
	// Set the target torrent field
	$command .= " --target ".tfb_shellencode($cfg["transfer_file_path"].$tfile);
	// Set to never timeout for large torrents
	@set_time_limit(0);
	// Let's see how long this takes...
	$time_start = microtime(true);
	// Execute the command
	exec($command);
	// We want to check to make sure the file was successful
	$success = false;
	$raw = @file_get_contents($cfg["transfer_file_path"].$tfile );
	if (preg_match( "/6:pieces([^:]+):/i", $raw, $results)) {
		// This means it is a valid torrent
		$success = true;
		// Make an entry for the owner
		AuditAction($cfg["constants"]["file_upload"], $tfile);
		// Check to see if one of the flags were set
		if ($private || $dht) {
			// Add private/dht Flags
			// e7:privatei1e
			// e17:dht_backup_enablei1e
			// e20:dht_backup_requestedi1e
			if(preg_match( "/6:pieces([^:]+):/i", $raw, $results)) {
				$pos = strpos( $raw, "6:pieces" ) + 9 + strlen( $results[1] ) + $results[1];
				$fp = @fopen( $cfg["transfer_file_path"] . $tfile, "r+" );
				@fseek( $fp, $pos, SEEK_SET );
				if ($private)
					@fwrite($fp,"7:privatei1eee");
				else
					@fwrite($fp,"e7:privatei0e17:dht_backup_enablei1e20:dht_backup_requestedi1eee");
				@fclose( $fp );
			}
		}
	} else {
		// Something went wrong, clean up
		if (@file_exists($cfg["transfer_file_path"].$tfile))
			@unlink($cfg["transfer_file_path"].$tfile);
	}
	// We are done! how long did we take?
	$time_end = microtime(true);
	$diff = duration($time_end - $time_start);
	// make path URL friendly to support non-standard characters
	$downpath = urlencode($tfile);
	// Depending if we were successful, display the required information
	$onLoad = ($success)
		? "completed('".$downpath."',".$alert.",'".$diff."');"
		: "failed('".$downpath."',".$alert.");";
	return $onLoad;
}

/**
 * create torrent with Mainline
 *
 * @return string $onLoad
 */
function createTorrentMainline() {
	global $cfg, $path, $tfile, $comment, $piece, $use_tracker, $tracker_name, $alert;
	$onLoad = "";
	// Clean up old files
	if (@file_exists($cfg["transfer_file_path"].$tfile))
		@unlink($cfg["transfer_file_path"].$tfile );
	// build command-string
	$command = "cd ".tfb_shellencode($cfg["transfer_file_path"]).";";
	$command .= " HOME=".tfb_shellencode($cfg["path"]);
	$command .= "; export HOME;";
	$command .= "nohup ".$cfg["pythonCmd"]." -OO ";
	$command .= tfb_shellencode($cfg["docroot"]."bin/clients/mainline/maketorrent-console.py");
	$command .= " --no_verbose";
	$command .= " --no_debug";
	// $command .= " --language en";
	// Is there comments to add?
	if (!empty($comment))
		$command .= " --comment ".tfb_shellencode($comment);
	// Set the piece size
	if (!empty($piece))
		$command .= " --piece_size_pow2 ".tfb_shellencode($piece);
	// trackerless / tracker
	/*
	if ((isset($use_tracker)) && ($use_tracker == 1))
		$command .= " --use_tracker";
	else
		$command .= " --no_use_tracker";
	*/
	$command .= " --use_tracker";
	// tracker-name
	//if ((!empty($tracker_name)) && ($tracker_name != "http://"))
	$command .= " --tracker_name ".tfb_shellencode($tracker_name);
	// Set the target torrent field
	$command .= " --target ".tfb_shellencode($cfg["transfer_file_path"].$tfile);
	// tracker (i don't know...)
	$command .= " ".tfb_shellencode($tracker_name);
	// input
	$command .= " ".tfb_shellencode($cfg["path"].$path);
	// Set to never timeout for large torrents
	@set_time_limit(0);
	// Let's see how long this takes...
	$time_start = microtime(true);
	// Execute the command
	exec($command);
	// We want to check to make sure the file was successful
	$success = false;
	$raw = @file_get_contents($cfg["transfer_file_path"].$tfile );
	if (preg_match( "/6:pieces([^:]+):/i", $raw, $results)) {
		// This means it is a valid torrent
		$success = true;
		// Make an entry for the owner
		AuditAction($cfg["constants"]["file_upload"], $tfile);
	} else {
		// Something went wrong, clean up
		if (@file_exists($cfg["transfer_file_path"].$tfile))
			@unlink($cfg["transfer_file_path"].$tfile);
	}
	// We are done! how long did we take?
	$time_end = microtime(true);
	$diff = duration($time_end - $time_start);
	// make path URL friendly to support non-standard characters
	$downpath = urlencode($tfile);
	// Depending if we were successful, display the required information
	$onLoad = ($success)
		? "completed('".$downpath."',".$alert.",'".$diff."');"
		: "failed('".$downpath."',".$alert.");";
	return $onLoad;
}

/**
 * Strip the folders from the path
 *
 * @param $path
 * @return string
 */
function StripFolders($path) {
	$pos = strrpos($path, "/");
	$pos = ($pos === false) ? 0 : $pos + 1;
	$path = substr($path, $pos);
	return $path;
}

/**
 * Convert a timestamp to a duration string
 *
 * @param $timestamp
 * @return string
 */
function duration($timestamp) {
	$years = floor($timestamp / (60 * 60 * 24 * 365));
	$timestamp %= 60 * 60 * 24 * 365;
	$weeks = floor($timestamp / (60 * 60 * 24 * 7));
	$timestamp %= 60 * 60 * 24 * 7;
	$days = floor($timestamp / (60 * 60 * 24));
	$timestamp %= 60 * 60 * 24;
	$hrs = floor($timestamp / (60 * 60));
	$timestamp %= 60 * 60;
	$mins = floor($timestamp / 60);
	$secs = $timestamp % 60;
	$str = "";
	if ($years >= 1)
		$str .= "{$years} years ";
	if ($weeks >= 1)
		$str .= "{$weeks} weeks ";
	if ($days >= 1)
		$str .= "{$days} days ";
	if ($hrs >= 1)
		$str .= "{$hrs} hours ";
	if ($mins >= 1)
		$str .= "{$mins} minutes ";
	if ($secs >= 1)
		$str.="{$secs} seconds ";
	return $str;
}

?>