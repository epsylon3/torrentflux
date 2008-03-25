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
 * checks if $user has the $permission on $object
 *
 * @param $object
 * @param $user
 * @param $permission
 */
function hasPermission($object, $user, $permission) {
	global $cfg;
	// if homedirs disabled return true
	if ($cfg["enable_home_dirs"] == 0)
		return true;
	// check permission
	switch ($permission) {
		case 'r':
			// public read enabled return true
			if ($cfg["dir_public_read"] == 1)
				return true;
			break;
		case 'w':
			// public write enabled return true
			if ($cfg["dir_public_write"] == 1)
				return true;
			break;
		default:
			return false;
	}
	// check if object in users home-dir
	if (preg_match("/^".$user."/", $object))
		return true;
	// only admin has right
	return $cfg['isAdmin'];
}

/**
 * inits restricted entries array.
 */
function initRestrictedDirEntries() {
	global $cfg, $restrictedFileEntries;
	$restrictedFileEntries = ((isset($cfg["dir_restricted"])) && (strlen($cfg["dir_restricted"]) > 0))
		? split(":", trim($cfg["dir_restricted"]))
		: array();
}

/**
 * Checks for the location of the incoming directory
 * If it does not exist, then it creates it.
 */
function checkIncomingPath() {
	global $cfg;
	switch ($cfg["enable_home_dirs"]) {
	    case 1:
	    default:
			// is there a user dir?
			checkDirectory($cfg["path"].$cfg["user"], 0777);
	        break;
	    case 0:
			// is there a incoming dir?
			checkDirectory($cfg["path"].$cfg["path_incoming"], 0777);
	        break;
	}
}

/**
 * deletes a dir-entry. recursive process via avddelete
 *
 * @param $del entry to delete
 * @return string with current
 */
function delDirEntry($del) {
	global $cfg;

	$current = "";

	if (tfb_isValidPath($del)) {
		avddelete($cfg["path"].$del);
		$arTemp = explode("/", $del);
		if (count($arTemp) > 1) {
			array_pop($arTemp);
			$current = implode("/", $arTemp);
		}
		AuditAction($cfg["constants"]["fm_delete"], $del);
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL DELETE: ".$cfg["user"]." tried to delete ".$del);
	}
	return $current;
}

/**
 * downloads a file.
 *
 * @param $down
 * @return string with current
 */
function downloadFile($down) {
	global $cfg;
	$current = "";
	// we need to strip slashes twice in some circumstances
	// Ex.	If we are trying to download test/tester's file/test.txt
	// $down will be "test/tester\\\'s file/test.txt"
	// one strip will give us "test/tester\'s file/test.txt
	// the second strip will give us the correct
	//	"test/tester's file/test.txt"
	$down = stripslashes(stripslashes($down));
	if (tfb_isValidPath($down)) {
		$path = $cfg["path"].$down;
		$p = explode(".", $path);
		$pc = count($p);
		$f = explode("/", $path);
		$file = array_pop($f);
		$arTemp = explode("/", $down);
		if (count($arTemp) > 1) {
			array_pop($arTemp);
			$current = implode("/", $arTemp);
		}
		if (file_exists($path)) {
			// size
			$filesize = file_size($path);
			// filenames in IE containing dots will screw up the filename
			$headerName = (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
				? preg_replace('/\./', '%2e', $file, substr_count($file, '.') - 1)
				: $file;
			// partial or full ?
			if (isset($_SERVER['HTTP_RANGE'])) {
				// Partial download
				$bufsize = 32768;
				if (preg_match("/^bytes=(\\d+)-(\\d*)$/D", $_SERVER['HTTP_RANGE'], $matches)) {
					$from = $matches[1];
					$to = $matches[2];
					if (empty($to))
						$to = $filesize - 1;
					$content_size = $to - $from + 1;
					@header("HTTP/1.1 206 Partial Content");
					@header("Content-Range: $from - $to / $filesize");
					@header("Content-Length: $content_size");
					@header("Content-Type: application/force-download");
					@header("Content-Disposition: attachment; filename=\"".$headerName."\"");
					@header("Content-Transfer-Encoding: binary");
					// write the session to close so you can continue to browse on the site.
					@session_write_close();
					$fh = fopen($path, "rb");
					fseek($fh, $from);
					$cur_pos = ftell($fh);
					while ($cur_pos !== FALSE && ftell($fh) + $bufsize < $to + 1) {
						$buffer = fread($fh, $bufsize);
						echo $buffer;
						$cur_pos = ftell($fh);
					}
					$buffer = fread($fh, $to + 1 - $cur_pos);
					echo $buffer;
					fclose($fh);
				} else {
					AuditAction($cfg["constants"]["error"], "Partial download : ".$cfg["user"]." tried to download ".$down);
					@header("HTTP/1.1 500 Internal Server Error");
					exit();
				}
			} else {
				// standard download
				@header("Content-type: application/octet-stream\n");
				@header("Content-disposition: attachment; filename=\"".$headerName."\"\n");
				@header("Content-transfer-encoding: binary\n");
				@header("Content-length: " . $filesize . "\n");
				// write the session to close so you can continue to browse on the site.
				@session_write_close();
				$fp = popen("cat ".tfb_shellencode($path), "r");
				fpassthru($fp);
				pclose($fp);
			}
			// log
			AuditAction($cfg["constants"]["fm_download"], $down);
			exit();
		} else {
			AuditAction($cfg["constants"]["error"], "File Not found for download: ".$cfg["user"]." tried to download ".$down);
		}
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL DOWNLOAD: ".$cfg["user"]." tried to download ".$down);
	}
	return $current;
}

/**
 * downloads as archive.
 *
 * @param $down
 * @return string with current
 */
function downloadArchive($down) {
	global $cfg;
	$current = "";

	if (tfb_isValidPath($down)) {
		// This prevents the script from getting killed off when running lengthy tar jobs.
		@ini_set("max_execution_time", 3600);
		$down = $cfg["path"].$down;
		$arTemp = explode("/", $down);
		if (count($arTemp) > 1) {
			array_pop($arTemp);
			$current = implode("/", $arTemp);
		}
		// Find out if we're really trying to access a file within the
		// proper directory structure. Sadly, this way requires that $cfg["path"]
		// is a REAL path, not a symlinked one. Also check if $cfg["path"] is part
		// of the REAL path.
		if (is_dir($down)) {
			$sendname = basename($down);
			switch ($cfg["package_type"]) {
				Case "tar":
					$command = "tar cf - \"".addslashes($sendname)."\"";
					break;
				Case "zip":
					$command = "zip -0r - \"".addslashes($sendname)."\"";
					break;
				default:
					$cfg["package_type"] = "tar";
					$command = "tar cf - \"".addslashes($sendname)."\"";
					break;
			}
			// filenames in IE containing dots will screw up the filename
			$headerName = (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
				? preg_replace('/\./', '%2e', $sendname, substr_count($sendname, '.') - 1)
				: $sendname;
			@header("Cache-Control: no-cache");
			@header("Pragma: no-cache");
			@header("Content-Description: File Transfer");
			@header("Content-Type: application/force-download");
			@header('Content-Disposition: attachment; filename="'.$headerName.'.'.$cfg["package_type"].'"');
			// write the session to close so you can continue to browse on the site.
			@session_write_close();
			// Make it a bit easier for tar/zip.
			chdir(dirname($down));
			passthru($command);
			AuditAction($cfg["constants"]["fm_download"], $sendname.".".$cfg["package_type"]);
			exit();
		} else {
			AuditAction($cfg["constants"]["error"], "Illegal download: ".$cfg["user"]." tried to download ".$down);
		}
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL TAR DOWNLOAD: ".$cfg["user"]." tried to download ".$down);
	}
	return $current;
}

/**
 * This function returns the extension of a given file.
 * Where the extension is the part after the last dot.
 * When no dot is found the noExtensionFile string is
 * returned. This should point to a 'unknown-type' image
 * time by default. This string is also returned when the
 * file starts with an dot.
 *
 * @param $fileName
 * @return
 */
function getExtension($fileName) {
	$noExtensionFile="unknown"; // The return when no extension is found
	// Prepare the loop to find an extension
	$length = -1*(strlen($fileName)); // The maximum negative value for $i
	$i=-1; //The counter which counts back to $length
	// Find the last dot in an string
	while (substr($fileName,$i,1) != "." && $i > $length) {$i -= 1; }
	// Get the extension (with dot)
	$ext = substr($fileName,$i);
	// Decide what to return.
	$ext = (substr($ext,0,1)==".") ? substr($ext,((-1 * strlen($ext))+1)) : $noExtensionFile;
	// Return the extension
	return strtolower($ext);
}

/**
 * checks if file/dir is valid.
 *
 * @param $fileEntry
 * @return true/false
 */
function isValidEntry($entry) {
	global $restrictedFileEntries;
	// is set
	if (!(isset($entry)))
		return false;
	// check if empty
	if ((strlen($entry)) < 1)
		return false;
	// check if dot-entry
	if (substr($entry, 0, 1) == ".")
		return false;
	if (strpos($entry, "/.") !== false)
		return false;
	// check if weirdo macos-entry
	if (substr($entry, 0, 1) == ":")
		return false;
	// check if in restricted array
	if (in_array($entry, $restrictedFileEntries))
		return false;
	// entry ok
	return true;
}

/**
 * checks if file is nfo.
 *
 * @param $entry
 * @return 0|1
 */
function isNfo($entry) {
	$subst = strtolower(substr($entry, -4));
	if ($subst == ".nfo")
		return 1;
	if ($subst == ".txt")
		return 1;
	if ($subst == ".log")
		return 1;
	return 0;
}

/**
 * checks if file is rar.
 *
 * @param $entry
 * @return 0|1|2 ; 0 = no match, 1 = rar-file, 2 = zip-file
 */
function isRar($entry) {
	if ((strpos($entry, '.rar') !== FALSE AND strpos($entry, '.Part') === FALSE) OR (strpos($entry, '.part01.rar') !== FALSE ) OR (strpos($entry, '.part1.rar') !== FALSE ))
		return 1;
	if (strpos($entry, '.zip') !== FALSE)
		return 2;
	return 0;
}

/**
 * SFV Check hack
 *
 * @param $dirName
 * @return
 */
function findSFV($dirName) {
	$sfv = false;
	$d = dir($dirName);
	while (false !== ($entry = $d->read())) {
   		if($entry != '.' && $entry != '..' && !empty($entry)) {
			if((isFile($dirName.'/'.$entry)) && (strtolower(substr($entry, -4, 4)) == '.sfv')) {
				$sfv['dir'] = $dirName;
				$sfv['sfv'] = $dirName.'/'.$entry;
			}
	   	}
	}
	$d->close();
	return $sfv;
}

/**
 * recursive chmod
 *
 * @param $path
 * @param $mode
 * @return boolean
 */
function chmodRecursive($path, $mode = 0777) {
	if ((! @is_dir($path)) && (isValidEntry(basename($path))))
		return @chmod($path, $mode);
	$dirHandle = opendir($path);
	while ($file = readdir($dirHandle)) {
		if (isValidEntry(basename($file))) {
			$fullpath = $path.'/'.$file;
			if (!@is_dir($fullpath)) {
				if (!@chmod($fullpath, $mode))
					return false;
			} else {
				if (!chmodRecursive($fullpath, $mode))
					return false;
			}
		}
	}
	closedir($dirHandle);
	return ((isValidEntry(basename($path))) && (@chmod($path, $mode)));
}


/**
*  Encode a string for safe transport across GET transfers, adding
*  slashes if magic quoting is off
*
* @param	string	$input to apply encoding to
* @return	string	$return string with encoded string
*/
function UrlHTMLSlashesEncode($input){
	$return=htmlentities(rawurlencode($input), ENT_QUOTES);

	// Add slashes if magic quotes off:
	if(get_magic_quotes_gpc() === 0){
		$return=addslashes($return);
	}
	return($return);
}

/**
*  Decode a string encoded with UrlHTMLSlashesEncode()
*
* @param	string	$input string to decode
* @return	string	$return string with decoded string
*/
function UrlHTMLSlashesDecode($input){
	return(stripslashes(html_entity_decode(rawurldecode($input), ENT_QUOTES)));
}

/**
*  Get the size in bytes of a directory()
*
* @param	string	$path 
* @return	string	$size bytes
*/
function dirsize($path)
{
	global $cfg;
	if(!is_dir($path)) return -1;
	switch ($cfg["_OS"]) {
			case 1: // linux
					$size = shell_exec("du -sb ".tfb_shellencode($path));
					$size = (float) preg_replace("/(.+)[\t\s]*.*/","$1", $size);
					return $size;
			case 2: // bsd
					$size = shell_exec("du -sk ".tfb_shellencode($path));
					$size = (float) preg_replace("/(.+)[\t\s]*.*/","$1", $size);
					$size = $size*1024;
					return $size;
	}
	return -1;
}

?>