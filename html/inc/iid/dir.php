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

// config
initRestrictedDirEntries();

// check incoming path
checkIncomingPath();

// get request-vars
$chmod = UrlHTMLSlashesDecode(tfb_getRequestVar('chmod'));
$del = UrlHTMLSlashesDecode(tfb_getRequestVar('del'));
$down = UrlHTMLSlashesDecode(tfb_getRequestVar('down'));
$tar = UrlHTMLSlashesDecode(tfb_getRequestVar('tar'));
$multidel = UrlHTMLSlashesDecode(tfb_getRequestVar('multidel'));
$dir = UrlHTMLSlashesDecode(tfb_getRequestVar('dir'));

// check dir-var
if (tfb_isValidPath($dir) !== true) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL DIR: ".$cfg["user"]." tried to access ".$dir);
	@error("Invalid Dir", "index.php?iid=dir", "", array($dir));
}

/*******************************************************************************
 * chmod
 ******************************************************************************/
if ($chmod != "") {
	// is enabled ?
	if ($cfg["dir_enable_chmod"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use chmod (".$dir.")");
		@error("chmod is disabled", "index.php?iid=index", "");
	}
	// only valid entry with permission
	if ((isValidEntry(basename($dir))) && (hasPermission($dir, $cfg["user"], 'w')))
		chmodRecursive($cfg["path"].$dir);
	else
		AuditAction($cfg["constants"]["error"], "ILLEGAL CHMOD: ".$cfg["user"]." tried to chmod ".$dir);
	@header("Location: index.php?iid=dir&dir=".UrlHTMLSlashesEncode($dir));
	exit();
}

/*******************************************************************************
 * delete
 ******************************************************************************/
if ($del != "") {
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
 * dir-page
 ******************************************************************************/

// check dir-var
if (isset($dir)) {
	if ($dir != "")
		$dir = $dir."/";
} else {
	$dir = "";
}

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
if (($dir != "") && (isValidEntry($dir) !== true)) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL DIR: ".$cfg["user"]." tried to access ".$dir);
	@error("Invalid Dir", "index.php?iid=dir", "", array($dir));
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
$handle = opendir($dirName);
while (false !== ($entry = readdir($handle))) {
	if (empty($dir)) { // parent dir
		if ((isValidEntry($entry)) && (hasPermission($entry, $cfg["user"], 'r')))
			array_push($entrys, $entry);
	} else { // sub-dir
		if (hasPermission($dir, $cfg["user"], 'r')) {
			if (isValidEntry($entry))
				array_push($entrys, $entry);
		}
	}
}
closedir($handle);
natsort($entrys);

// process entries and fill dir- + file-array
$list = array();

foreach ($entrys as $entry) {
	// acl-write-check
	if (empty($dir)) /* parent dir */
		$aclWrite = (hasPermission($entry, $cfg["user"], 'w')) ? 1 : 0;
	else /* sub-dir */
		$aclWrite = (hasPermission($dir, $cfg["user"], 'w')) ? 1 : 0;
		
	// symbolic links
	if(!is_link($dirName.$entry))
	{
		$islink = 0;
	}
	else
	{
		if(!($slink = readlink($dirName.$entry)))
			$slink = "";
		$islink = 1;
	}
	// dirstats
	if ($cfg['enable_dirstats'] == 1) 
	{
		if($islink == 0) // it's not a symbolic link
		{
			$size = (is_dir($dirName.$entry))? formatBytesTokBMBGBTB(dirsize($dirName.$entry)):formatBytesTokBMBGBTB(filesize($dirName.$entry));
			$timeStamp = filemtime($dirName.$entry);
			$date = date("m-d-Y h:i a", $timeStamp);
		}
		else // it's a symbolic link
		{
			$size = 0;
			$date = "";
		}
	} 
	else 
	{
		$size = 0;
		$date = "";
	}	
	if (is_dir($dirName.$entry)) // dir
	{ 
		// sfv
		if (($cfg['enable_sfvcheck'] == 1) && (false !== ($sfv = findSFV($dirName.$entry)))) 
		{
			$show_sfv = 1;
			$sfvdir = $sfv['dir'];
			$sfvsfv = $sfv['sfv'];
		} 
		else 
		{
			$show_sfv = 0;
			$sfvdir = "";
			$sfvsfv = "";
		}
		$isdir = 1;
		$show_nfo = 0;
		$show_rar = 0;
	} 
	else if (!@is_dir($dirName.$entry)) // file
	{ 
		// image
		$image = "themes/".$cfg['theme']."/images/time.gif";
		$imageOption = "themes/".$cfg['theme']."/images/files/".getExtension($entry).".png";
		if (file_exists("./".$imageOption))
			$image = $imageOption;
		// nfo
		$show_nfo = ($cfg["enable_view_nfo"] == 1) ? isNfo($entry) : 0;
		// rar
		$show_rar = (($cfg["enable_rar"] == 1) && ($aclWrite == 1)) ? isRar($entry) : 0;
		// add entry to file-array
		$isdir = 0;
		$show_sfv = 0;
		$sfvdir = "";
		$sfvsfv = "";
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
	if(function_exists('mb_detect_encoding') && function_exists('utf8_decode') && mb_detect_encoding(" ".$entry." ",'UTF-8,ISO-8859-1') == 'UTF-8')
		$entry = utf8_decode($entry);
	
	// add entry to dir-array
	array_push($list, array(
		'is_dir'      => $isdir,
		'is_link'     => $islink,
		'aclWrite'    => $aclWrite,
		'permission'  => $permission,
		'entry'       => $entry,
		'real_entry'  => $slink,
		'urlencode1'  => UrlHTMLSlashesEncode($dir.$entry),
		'urlencode2'  => UrlHTMLSlashesEncode($dir),
		'urlencode3'  => UrlHTMLSlashesEncode($entry),
		'addslashes1' => addslashes($entry),
		'size'        => $size,
		'date'        => $date,
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
$tmpl->setvar('dir', $dir);
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
}
else
	$tmpl->setvar('showparentURL', FALSE);
// chmod, parent-dir cannot be chmodded
if ($dir == "")
	$tmpl->setvar('show_chmod', 0);
else
	$tmpl->setvar('show_chmod', (($cfg["dir_enable_chmod"] == 1) && (hasPermission($dir, $cfg['user'], 'w'))) ? 1 : 0);
//
$tmpl->setvar('enable_rename', $cfg["enable_rename"]);
$tmpl->setvar('enable_move', $cfg["enable_move"]);
$tmpl->setvar('enable_sfvcheck',  $cfg['enable_sfvcheck']);
$tmpl->setvar('enable_vlc',  $cfg['enable_vlc']);
$tmpl->setvar('enable_rar', $cfg["enable_rar"]);
$tmpl->setvar('enable_view_nfo', $cfg["enable_view_nfo"]);
$tmpl->setvar('enable_file_download', $cfg["enable_file_download"]);
$tmpl->setvar('package_type', $cfg["package_type"]);
$tmpl->setvar('enable_maketorrent', $cfg["enable_maketorrent"]);
$tmpl->setvar('bgDark', $cfg['bgDark']);
$tmpl->setvar('bgLight', $cfg['bgLight']);
//
$tmpl->setvar('_DELETE', $cfg['_DELETE']);
$tmpl->setvar('_DIR_REN_LINK', $cfg['_DIR_REN_LINK']);
$tmpl->setvar('_DIR_MOVE_LINK', $cfg['_DIR_MOVE_LINK']);
$tmpl->setvar('_ABOUTTODELETE', $cfg['_ABOUTTODELETE']);
$tmpl->setvar('_BACKTOPARRENT', $cfg['_BACKTOPARRENT']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
//
tmplSetTitleBar($cfg["pagetitle"].' - '.$cfg['_DIRECTORYLIST']);
tmplSetDriveSpaceBar();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>