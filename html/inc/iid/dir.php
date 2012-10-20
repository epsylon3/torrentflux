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

/*
munk TODO:
Check each of these items for correct functionality with encoding/decoding
of HTML and URLs, including inc/iid/item.php and any templates associated with
the item:

vlc
*/
// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

// common functions
require_once('inc/functions/functions.common.php');

// dir functions
require_once('inc/functions/functions.dir.php');

// transfer functions (to know running)
require_once('inc/functions/functions.transfer.php');

// config
initRestrictedDirEntries();

// check incoming path
checkIncomingPath();


// to be able to execute shell commands with utf8 accents
if (isset($cfg['_LC_CTYPE'])) {
	setlocale(LC_CTYPE, $cfg['_LC_CTYPE']); //"fr_FR.UTF-8" or "de_DE.UTF-8"
}

// get request-vars
$chmod = UrlHTMLSlashesDecode(tfb_getRequestVar('chmod'));
$del = UrlHTMLSlashesDecode(tfb_getRequestVar('del'));
$down = UrlHTMLSlashesDecode(tfb_getRequestVar('down'));
$tar = UrlHTMLSlashesDecode(tfb_getRequestVar('tar'));
$multidel = UrlHTMLSlashesDecode(tfb_getRequestVar('multidel'));
$dir = UrlHTMLSlashesDecode(tfb_getRequestVar('dir'));
$wget_url = UrlHTMLSlashesDecode(tfb_getRequestVar('wget_url'));


// check dir-var
if (tfb_isValidPath($dir) !== true) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL DIR: ".$cfg["user"]." tried to access ".$dir);
	@error("Invalid Dir", "index.php?iid=dir", "", array($dir));
}

/*******************************************************************************
 * log history
 ******************************************************************************/
function getDownloadLogs($path) {
	global $cfg, $db;
	$srchAction = "File Download";
	
	$sqlWhere  = "file LIKE ".$db->qstr($path."%")." AND ";
	$sqlWhere .= "action LIKE ".$db->qstr($srchAction."%")." AND ";

	$sql = "SELECT user_id, file, max(time) as time FROM tf_log WHERE ".$sqlWhere."action!=".$db->qstr($cfg["constants"]["hit"])." GROUP BY user_id, file ORDER BY time desc";
	$result = $db->SelectLimit($sql, 999, 0);
	if ($db->ErrorNo() != 0) dbError($sql);
	$array = array();
	while ($row = $result->FetchNextObject(false))
		$array[] = $row;
	
	return $array;
}

function getDownloadFtpLogUsers($srchFile, $logNumber="") {
	global $cfg, $db, $dlLog;
	$userlist=array();
	$userRenamer=array(); 

	//xferlog or xferlog.0 (last month)
	//$ftplog = '/var/log/proftpd/xferlog'.$logNumber;
	$ftplog = "/var/log/pure-ftpd/stats_transfer$logNumber.log";

	if (!is_file($ftplog))
		return array();

	//Search in Log (for old or external log insert, todo)
	$srchFile   = str_replace($cfg["path"],'',$srchFile);

	//Search in cached db log array
	foreach ($dlLog as $row) {
		if ($row->file == $srchFile) {
			$userlist[$row->user_id] = htmlentities(substr($row->user_id, 0, 3), ENT_QUOTES);
		}
	}
	
	if (count($userlist) > 0) return $userlist;
	if (!file_exists($ftplog)) return $userlist;

	$userRenamer["root"]="epsylon3";

	$cmdLog = "cat $ftplog|".$cfg["bin_grep"].' '.tfb_shellencode(str_replace(' ','_',$srchFile)); //.'|'.$cfg["bin_grep"]." -o -E ' r (.*) ftp'"

	$dlInfos=trim( @ shell_exec($cmdLog) );
	if ($dlInfos) {
		$ftpusers = explode("\n", $dlInfos);
		foreach ($ftpusers as $key=>$value) {
/* PROFTPD
			$value=substr($value,4);
			$time=strtotime(substr($value,0,20));
			$value=substr($value,21);
			$lineWords=explode(' ',$value);
			$hostname=$lineWords[1];
			$size=0+($lineWords[2]);
			$username=$lineWords[count($lineWords)-5];
			$complete=$lineWords[count($lineWords)-1]; */

/* pure-ftpd (stats:/var/log/pure-ftpd/stats_transfer.log) */
			$lineWords=explode(' ',$value);
			$time=0+($lineWords[0]);
			$username=$lineWords[2];
			$hostname=$lineWords[3];
			$complete=str_replace("D","c",$lineWords[4]);
			$size=0.0+($lineWords[5]);

//die( "<pre>$size-$complete-$hostname-$username-$time\n$value\n</pre>");

			if ($complete=="c") {

				//rename user ?
				if (array_key_exists($username,$userRenamer)) $username=$userRenamer[$username];	
			
				if (!array_key_exists($username,$userlist)) {
					
					$srchAction = "File Download (FTP)";
					
					$db->Execute("INSERT INTO tf_log (user_id,file,action,ip,ip_resolved,user_agent,time)"
 					   	." VALUES ("
  					  	. $db->qstr($username).","
  					  	. $db->qstr($srchFile).","
  					  	. $db->qstr($srchAction).","
 					   	. $db->qstr('FTP')."," //IP
 					   	. $db->qstr($hostname).","
 					   	. $db->qstr('FTP')."," //user-agent
   					 	. $time
   					 	.")"
					);
					if ($db->ErrorNo() != 0) dbError($sql);
				}

				$userlist[$username]=substr($username,0,3);
			}

		}
	}
	return $userlist;
}

function getDownloadWebLogUsers($srchFile,$is_dir) {
	global $cfg, $db, $dlLog;
	$userlist=array();

	//$srchFile=str_replace('/var/cache/torrentflux/','',$srchFile);
	$srchFile=str_replace($cfg["path"],'',$srchFile);

	foreach ($dlLog as $row) {
		$downloaded = ($row->file == $srchFile);
		if (!$downloaded && $is_dir && $srchFile) {
			$downloaded = (strpos($row->file,$srchFile) !== false);
		}
		if ($downloaded) {
			$userlist[$row->user_id] = htmlentities(substr($row->user_id, 0, 3), ENT_QUOTES);
		}
	}
	
	return $userlist;
}

/*******************************************************************************
 * chmod
 ******************************************************************************/
if ($chmod != "") {
	if (substr($dir,strlen($dir)-1)=='/') $dir=substr($dir,0,strlen($dir)-1);
	// is enabled ?
	if ($cfg["dir_enable_chmod"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use chmod (".$dir.")");
		@error("chmod is disabled", "index.php?iid=index", "");
	}
	// only valid entry with permission
	if ((isValidEntry(basename($dir))) && (hasPermission($dir, $cfg["user"], 'w')))
		chmodRecursive($cfg["path"].$dir,0775);
	else
		AuditAction($cfg["constants"]["error"], "ILLEGAL CHMOD: ".$cfg["user"]." tried to chmod ".$dir);
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($dir));
	exit();
}

/*******************************************************************************
 * delete
 ******************************************************************************/
if ($del != "") {
	if (substr($dir,strlen($dir)-1)=='/') $dir=substr($dir,0,strlen($dir)-1);
	// only valid entry with permission
	if ((isValidEntry(basename($del))) && (hasPermission($del, $cfg["user"], 'w'))) {
		$current = delDirEntry($del);
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL DELETE: ".$cfg["user"]." tried to delete (".$del.")");
		$current = $del;

		if (tfb_isValidPath($del)) {
			$arTemp = explode("/", $del);
			if (count($arTemp) > 1) {
				array_pop($arTemp);
				$current = implode("/", $arTemp);
			}
		}
	}
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($current));
	exit();
}

/*******************************************************************************
 * download
 ******************************************************************************/
if ($down != "") {
	// is enabled ?
	if ($cfg["enable_file_download"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use download (".$down.")");
		@error("download is disabled", "index.php?iid=index", "");
	}
	// only valid entry with permission
	if ((isValidEntry(basename($down))) && (hasPermission($down, $cfg["user"], 'r'))) {
		@ ini_set("zlib.output_compression","Off");
		$current = downloadFile($down);
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL DOWNLOAD: ".$cfg["user"]." tried to download ".$down);
		$current = $down;

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
		}
	}
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($current));
	exit();
}

/*******************************************************************************
 * download as archive
 ******************************************************************************/
if ($tar != "") {
	// is enabled ?
	if ($cfg["enable_file_download"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use download (".$tar.")");
		@error("download is disabled", "index.php?iid=index", "");
	}
	// only valid entry with permission
	if ((isValidEntry(basename($tar))) && (hasPermission($tar, $cfg["user"], 'r'))) {
		@ ini_set("zlib.output_compression","Off");
		$current = downloadArchive($tar);
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL TAR DOWNLOAD: ".$cfg["user"]." tried to download ".$tar);
		$current = $tar;

		if (tfb_isValidPath($tar)) {
			$arTemp = explode("/", $tar);
			if (count($arTemp) > 1) {
				array_pop($arTemp);
				$current = implode("/", $arTemp);
			}
		}
	}
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($current));
	exit();
}

/*******************************************************************************
 * wget
 ******************************************************************************/
function _dir_cleanFileName($inName) {
	global $cfg;
	$arURL = explode("/", $inName);
	$inName = urldecode($arURL[count($arURL)-1]); // get the file name
	$outName = preg_replace("/[^0-9a-zA-Z.-]+/",'_', $inName);
	return $outName;
}

function _dir_WgetFile($url,$target_dir) {
	require_once('inc/functions/functions.core.tfb.php');
	global $cfg;
	$filename = "";
	$downloadMessages = array();
	if (!empty($url)) {
		$arURL = explode("/", $url);
		$filename = urldecode($arURL[count($arURL)-1]); // get the file name
		$filename = str_replace(array("'",","), "", $filename);
		$filename = stripslashes($filename);
		
		// Check to see if url has something like ?passkey=12345
		// If so remove it.
		if (($point = strrpos($filename, "?")) !== false )
			$filename = substr($filename, 0, $point);
		$ret = strrpos($filename, ".");

		$url = str_replace(" ", "%20", $url);
		// This is to support Sites that pass an id along with the url for downloads.
		$tmpId = tfb_getRequestVar("id");
		if(!empty($tmpId))
			$url .= "&id=".$tmpId;

		// retrieve the file
		require_once("inc/classes/SimpleHTTP.php");

		$content = SimpleHTTP::getData($url);
		if ((SimpleHTTP::getState() == SIMPLEHTTP_STATE_OK) && (strlen($content) > 0)) {
			$fileNameBackup = $filename;
			$filename = SimpleHTTP::getFilename();
			if ($filename != "") {
				$filename = _dir_cleanFileName($filename);
			}
			if (($filename == "") || ($filename === false)) {
				$filename = _dir_cleanFileName($fileNameBackup);
				if ($filename === false || $filename=="") {
					$filename = _dir_cleanFileName(SimpleHTTP::getRealUrl($url));
					if ($filename === false || $filename=="") {
						$filename = _dir_cleanFileName(md5($url.strval(@microtime())));
						if ($filename === false || $filename=="") {
							// Error
							array_push($downloadMessages , "failed to get a valid filename for ".$url);
						}
					}
				}
			}
			if (empty($downloadMessages)) { // no messages
				// check if content contains html
				if ($cfg['debuglevel'] > 0) {
					if (strpos($content, "<br />") !== false)
						AuditAction($cfg["constants"]["debug"], "download-content contained html : ".htmlentities(addslashes($url), ENT_QUOTES));
				}
				if (is_file($target_dir.$filename)) {
					// Error
					array_push($downloadMessages, "the file ".$filename." already exists on the server.");
				} else {
					// write to file
					$handle = false;
					$handle = @fopen($target_dir.$filename, "w");
					if (!$handle) {
						array_push($downloadMessages, "cannot open ".$target_dir.$filename." for writing.");
					} else {
						$result = @fwrite($handle, $content);
						@fclose($handle);
						if ($result === false)
							array_push($downloadMessages, "cannot write content to ".$filename.".");
					}
				}
			}
		} else {
			$msgs = SimpleHTTP::getMessages();
			if (count($msgs) > 0)
				$downloadMessages = array_merge($downloadMessages, $msgs);
		}
		if (empty($downloadMessages)) { // no messages
			AuditAction($cfg["constants"]["url_upload"], $filename);
		}
	} else {
		array_push($downloadMessages, "Invalid Url : ".$url);
	}
	if (count($downloadMessages) > 0) {
		AuditAction($cfg["constants"]["error"], $cfg["constants"]["url_upload"]." :: ".$filename);
		@error("There were Problems", "", "", $downloadMessages);
	}
}


if ($wget_url != "") {
	if ($dir != "" && substr($dir,strlen($dir)-1,1)!="/") {
		$dir = $dir."/";	
	}
	if ($dir != "") {
		_dir_WgetFile($wget_url,$cfg["path"].$dir);
	}
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($dir));
	exit();
}

/*******************************************************************************
 * multi-delete
 ******************************************************************************/
if ($multidel != "") {
	foreach($_POST['file'] as $key => $element) {
		$element = urldecode($element);
		// only valid entry with permission
		if ((isValidEntry(basename($element))) && (hasPermission($element, $cfg["user"], 'w')))
			delDirEntry($element);
		else
			AuditAction($cfg["constants"]["error"], "ILLEGAL DELETE: ".$cfg["user"]." tried to delete ".$element);
	}
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($dir));
	exit();
}


/*******************************************************************************
 * dir-page
 ******************************************************************************/
$tDirPs=array();
$tRunning=array();
$tSeeding=array();

// check dir-var
if (isset($dir)) {
	if ($dir != "" && substr($dir,strlen($dir)-1,1)!="/") {
		$dir = $dir."/";
	}
	if ($dir != "") {
		//get list of processes of known running transfers
		$handle = opendir($cfg['transfer_file_path']);
		$tDirPs = array();
		while (false !== ($entry = readdir($handle))) {
			if (substr($entry,-3) == 'pid')
				$tDirPs[] = $entry;
		}
	}
} else {
	$dir = "";
}

foreach($tDirPs as $value) {
	$value=$cfg['transfer_file_path'].'/'.preg_replace('#(.*\.torrent)\.pid#','\1',$value);
	$stats=explode("\n",@file_get_contents($value.".stat"));
	$value=preg_replace('#.*\.transfers/([^ ]+\.torrent)#','\1',$value);
	$path=getTransferDatapath($value);
	if ((int) @$stats[1] < 100) {
		$tRunning[$path]=$value;
	} else {
		$tSeeding[$path]=$value;
	}
}
unset($tDirPs);


// dir-name
$dirName = $cfg["path"].$dir;

// dir-check
if (!(@is_dir($dirName))) {
	// our dir is no dir but a file. use parent-directory.
	if (preg_match("/^(.+)\/.+$/", $dir, $matches) == 1)
		header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($matches[1]));
	else
		header("Location: index.php?iid=dir");
	exit();
}

// check if valid entry
if (($dir != "") && (isValidEntry($dir) !== true)) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL DIR: ".$cfg["user"]." tried to access ".$dir);
	@error("Invalid Dir", "index.php?iid=dir", "", array($dir));
}

// check for permission to read
if (($dir != "") && (hasPermission($dir, $cfg["user"], 'r') !== true)) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL DIR: ".$cfg["user"]." tried to access ".$dir);
	@error("No Permission for Dir", "index.php?iid=dir", "", array($dir));
}

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.dir.tmpl");
	
// dirstats
if ($cfg['enable_dirstats'] == 1) {
	$tmpl->setvar('enable_dirstats', 1);
	$du = dirsize($dirName);
	$tmpl->setvar('duTotal', formatBytesTokBMBGBTB($du));
	$tmpl->setvar('_TDDU', $cfg['_TDDU']);
} 
else 
{
	$tmpl->setvar('enable_dirstats', 0);
}

// read in entries
$entrys = array();
$entrysDirs = array();
$entrysFiles = array();

$handle = opendir($dirName);
while (false !== ($entry = readdir($handle))) {
	if (empty($dir)) { // parent dir
		if ((isValidEntry($entry)) && (hasPermission($entry, $cfg["user"], 'r')))
			array_push($entrys, $entry);
	} else { // sub-dir
		if (hasPermission($dir, $cfg["user"], 'r')) {
			if (isValidEntry($entry)) {
				if (is_dir($dirName.$entry)) {
					array_push($entrysDirs, $entry);
				} else {
					array_push($entrysFiles, $entry);
				}
			}

		}
	}
}
closedir($handle);
 
natcasesort($entrysDirs);
natcasesort($entrysFiles); 
$entrys = array_merge ($entrysFiles, $entrysDirs, $entrys);

// process entries and fill dir- + file-array
$list = array();

// files downloaded (from log)
$dlLog = getDownloadLogs($dir);

foreach ($entrys as $entry) {
	$slink="";
	$dlInfos="";
	
	// acl-write-check
	if (empty($dir)) /* parent dir */
		$aclWrite = (hasPermission($entry, $cfg["user"], 'w')) ? 1 : 0;
	else /* sub-dir */
		$aclWrite = (hasPermission($dir, $cfg["user"], 'w')) ? 1 : 0;

	// symbolic links
	if(!is_link($dirName.$entry))
	{
		$islink = 0;
		$slink = $entry;
	}
	else
	{
		if(!($slink = readlink($dirName.$entry)))
			$slink = "";
		$islink = 1;
	}
	
	// dirstats
	$date = "";
	$size = 0.0;
	$isrecent = 0;
	$show_sfv = 0;
	$sfvdir = "";
	$sfvsfv = "";
	$userlist = array();

	$realentry=$entry_iso=$entry;
	if(function_exists('mb_detect_encoding') && function_exists('utf8_decode') && mb_detect_encoding(" ".$entry." ",'UTF-8,ISO-8859-1') == 'UTF-8')
		$entry_iso = utf8_decode($entry);

	if ($cfg['enable_dirstats'] == 1)
	{
		$path = $dirName.$entry;
		$stat = stat($path);
		$ssz = 0.0;
		
		if($islink == 0) // it's not a symbolic link
		{
			$ssz += is_dir($path)? dirsize($path) : sprintf("%.0f", $stat['size']);
			if (($ssz < 0 || $stat['blocks'] > 2000000) && !isWinOS()) $ssz = @trim(1024.0 * shell_exec('du -ksL '.tfb_shellencode($dirName.$entry)));
		}
		elseif (!isWinOS()) // it's a symbolic link
		{
			$ssz += @trim(1024.0 * shell_exec('du -ksL '.tfb_shellencode($slink)));
			$date = "";
		}
		$size = formatBytesTokBMBGBTB( sprintf("%.0f", $ssz) );
		if (strstr($size,"G")) {
			$size="<b>$size</b>";
		}
		
		$timeStamp = $stat['mtime'];
		$date = date($cfg['_DATETIMEFORMAT'],$timeStamp);
		if ($timeStamp + (86400*2) > time()) {
			$isrecent = 1;
		}
	}
	if (is_dir($dirName.$entry)) // dir
	{ 
		// sfv
		if ($cfg['enable_sfvcheck'] == 1 && (false !== ($sfv = findSFV($dirName.$entry))) ) 
		{
			$show_sfv = 1;
			$sfvdir = $sfv['dir'];
			$sfvsfv = $sfv['sfv'];
		}
		$isdir = 1;
		$show_nfo = 0;
		$show_rar = 0;
		/* disabled here, unzip only for current dir
		$show_rar = ($cfg["enable_rar"] == 1) && ($aclWrite == 1) && (false !== ($zip = findArchives($dirName.$entry)) );
		if ($show_rar) {
			$zip = array_pop($zip);
			$show_rar = isRar($zip);
		}
		*/
		
		$image = "";
		
		if ($dir != '') {
			$userlist = getDownloadWebLogUsers($dirName.$entry,1);
		}
	}
	else if (!@is_dir($dirName.$entry)) // file
	{

		if ((0+$size) > 0 && $cfg['enable_dirstats'] == 1) {

			//WEB DL Users (Who Downloaded it ?)
			$userlist = getDownloadWebLogUsers($dirName.$entry,0);
			//FTP DL Users (Who Downloaded it ?)
			$userlist = array_merge($userlist, getDownloadFtpLogUsers($dirName.$entry) );
			//FTP DL Users (Who Downloaded it last month?)
			$userlist = array_merge($userlist, getDownloadFtpLogUsers($dirName.$entry, ".0") );
		}
		
		// nfo
		$show_nfo = ($cfg["enable_view_nfo"] == 1) ? isNfo($entry) : 0;
		// rar
		$show_rar = (($cfg["enable_rar"] == 1) && ($aclWrite == 1)) ? isRar($entry) : 0;
		// add entry to file-array
		$isdir = 0;

		// image
		$image = "themes/".$cfg['theme']."/images/time.gif";
		$imageOption = "themes/".$cfg['theme']."/images/files/".getExtension($entry);
		
		if (file_exists("./".$imageOption.".png"))
			$image = $imageOption.".png";
		else if (file_exists("./".$imageOption.".gif"))
			$image = $imageOption.".gif";
	}
	
	// get Permission and format it userfriendly
	if(($fperm = fileperms($dirName.$entry)) !== FALSE)
	{
		$permission_oct = substr(decoct($fperm),-3);
		$permission = (is_dir($dirName.$entry))? "d":"-";
		for($i=0;$i<=2;$i++)
		{
			$permission_bin = decbin($permission_oct[$i]);
			$permission .= ($permission_bin[0] == 1)? "r":"-";
			$permission .= ($permission_bin[1] == 1)? "w":"-";
			$permission .= ($permission_bin[2] == 1)? "x":"-";
		}
		$permission .= " (0".$permission_oct.")";
	}

	if (array_key_exists($realentry,$tRunning)) {
		$size='<span style="color:red;">'.$size.'</span>';
	} elseif (array_key_exists($realentry,$tSeeding)) {
		$size='<span style="color:navy;">'.$size.'</span>';
	}
	
	$dlFullInfo = implode(', ',array_keys($userlist));
	if ($dlFullInfo) {
		$dlImg = 'dlinfog.png';
		if (array_key_exists($cfg["user"], $userlist))
			$dlImg = 'dlinfo.png';
		if (file_exists('./themes/'.$cfg['theme'].'/images/dir/'.$dlImg))
			$dlFullInfo = '<img src="themes/'.$cfg['theme'].'/images/dir/'.$dlImg.'" title="Downloaded by '.$dlFullInfo.'">';
		else
			$dlFullInfo = '<img src="themes/'.$cfg['theme'].'/images/download_owner.gif" title="Downloaded by '.$dlFullInfo.'">';
	}
	if ($cfg['_CHARSET']!='utf-8')
		$entry = $entry_iso;
	
	// add entry to dir-array
	array_push($list, array(
		'is_dir'      => $isdir,
		'is_link'     => $islink,
		'is_recent'   => $isrecent,
		'aclWrite'    => $aclWrite,
		'permission'  => $permission,
		'entry'       => $entry,
		'real_entry'  => $realentry,
		'urlencode1'  => UrlHTMLSlashesEncode($dir.$entry),
		'urlencode2'  => UrlHTMLSlashesEncode($dir),
		'urlencode3'  => UrlHTMLSlashesEncode($entry),
		'addslashes1' => addslashes($entry),
		'size'        => $size,
		'date'        => "<nobr>$date</nobr>",
		'dlinfo'      => $dlFullInfo,
		'image'       => $image,
		'show_sfv'    => $show_sfv,
		'sfvdir'      => UrlHTMLSlashesEncode($sfvdir),
		'sfvsfv'      => UrlHTMLSlashesEncode($sfvsfv),
		'show_nfo'    => $show_nfo,
		'show_rar'    => $show_rar
		)
	);
}

// set template-loop
$tmpl->setloop('list', $list);

// define some things

// dir
if($dirName != "/")
	$tmpl->setvar('parentdir', preg_replace("/.*\/(.+?)\//",'$1',$dirName));
else
	$tmpl->setvar('parentdir', "/ (root)");
// parent url
if($dir != "")
{
	if (preg_match("/^(.+)\/.+$/", $dir, $matches) == 1)
		$tmpl->setvar('parentURL', "index.php?iid=dir&dir=" . UrlHTMLSlashesEncode($matches[1]));
	else
		$tmpl->setvar('parentURL', "index.php?iid=dir");
	$tmpl->setvar('showparentURL', TRUE);
	
	//unzip all archives of current dir
	$show_rar = ($cfg["enable_rar"] == 1) && ($aclWrite == 1) && (false !== ($zip = findArchives($dirName)) );
	if ($show_rar) {
		$zip = array_pop($zip);
		$show_rar = isRar($zip);
	}
}
else
	$tmpl->setvar('showparentURL', FALSE);

// chmod, parent-dir cannot be chmodded
if ($dir == "")
	$tmpl->setvar('show_chmod', 0);
else
	$tmpl->setvar('show_chmod', (($cfg["dir_enable_chmod"] == 1) && (hasPermission($dir, $cfg['user'], 'w'))) ? 1 : 0);

$tmpl->setvar('enable_rename', $cfg["enable_rename"]);
$tmpl->setvar('enable_move', $cfg["enable_move"]);
$tmpl->setvar('enable_sfvcheck',  $cfg['enable_sfvcheck']);
$tmpl->setvar('enable_vlc',  $cfg['enable_vlc']);
$tmpl->setvar('enable_rar', $cfg["enable_rar"]);
$tmpl->setvar('show_rar', $show_rar);
$tmpl->setvar('enable_view_nfo', $cfg["enable_view_nfo"]);
$tmpl->setvar('enable_file_download', $cfg["enable_file_download"]);
$tmpl->setvar('package_type', $cfg["package_type"]);
$tmpl->setvar('enable_maketorrent', $cfg["enable_maketorrent"]);
$tmpl->setvar('bgDark', $cfg['bgDark']);
$tmpl->setvar('bgLight', $cfg['bgLight']);

//lang
$tmpl->setvar('_DELETE', $cfg['_DELETE']);
$tmpl->setvar('_DIR_REN_LINK', $cfg['_DIR_REN_LINK']);
$tmpl->setvar('_DIR_MOVE_LINK', $cfg['_DIR_MOVE_LINK']);
$tmpl->setvar('_ABOUTTODELETE', $cfg['_ABOUTTODELETE']);
$tmpl->setvar('_BACKTOPARRENT', $cfg['_BACKTOPARRENT']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
$tmpl->setvar('_WGET', $wget_url);

//directory to display
$tmpl->setvar('dir_raw', $dir);
//directory for links
$tmpl->setvar('dir', str_replace('%2F','/',UrlHTMLSlashesEncode($dir)) );

tmplSetTitleBar($cfg["pagetitle"].' - '.$cfg['_DIRECTORYLIST']);
tmplSetDriveSpaceBar();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>