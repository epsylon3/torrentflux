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
 * File Prio Form
 *
 * @param $transfer
 * @param $withForm
 * @return string
 */
function getFilePrioForm($transfer, $withForm = false) {
	global $cfg;
	$prioFileName = $cfg["transfer_file_path"].$transfer.".prio";
	require_once('inc/classes/BDecode.php');
	$retVal = "";
	// theme-switch
	if ((strpos($cfg["theme"], '/')) === false) {
		$retVal .= '<link rel="StyleSheet" href="themes/'.$cfg["theme"].'/css/dtree.css" type="text/css" />';
		$retVal .= '<script type="text/javascript">var dtree_path_images = "themes/'.$cfg["theme"].'/images/dtree/";</script>';
	} else {
		$retVal .= '<link rel="StyleSheet" href="themes/tf_standard_themes/css/dtree.css" type="text/css" />';
		$retVal .= '<script type="text/javascript">var dtree_path_images = "themes/tf_standard_themes/images/dtree/";</script>';
	}
	$retVal .= '<script type="text/javascript" src="js/dtree.js"></script>';
	$ftorrent = $cfg["transfer_file_path"].$transfer;
	$fp = @fopen($ftorrent, "rd");
	$alltorrent = @fread($fp, @filesize($ftorrent));
	@fclose($fp);
	$btmeta = @BDecode($alltorrent);
	$torrent_size = $btmeta["info"]["piece length"] * (strlen($btmeta["info"]["pieces"]) / 20);
	$dirnum = (array_key_exists('files',$btmeta['info'])) ? count($btmeta['info']['files']) : 0;
	if (@is_readable($prioFileName)) {
		$prio = split(',', @file_get_contents($prioFileName));
		$prio = array_splice($prio,1);
	} else {
		$prio = array();
		for ($i=0; $i<$dirnum; $i++)
			$prio[$i] = -1;
	}
	$tree = new dir("/",$dirnum, isset($prio[$dirnum]) ? $prio[$dirnum] : -1);
	if (array_key_exists('files',$btmeta['info'])) {
		foreach( $btmeta['info']['files'] as $filenum => $file) {
			$depth = count($file['path']);
			$branch =& $tree;
			for ($i=0; $i < $depth; $i++) {
				if ($i != $depth - 1) {
					$d =& $branch->findDir($file['path'][$i]);
					if ($d) {
						$branch =& $d;
					} else {
						$dirnum++;
						$d =& $branch->addDir(new dir($file['path'][$i], $dirnum, (isset($prio[$dirnum]) ? $prio[$dirnum] : -1)));
						$branch =& $d;
					}
				} else {
					$branch->addFile(new file($file['path'][$i]." (".$file['length'].")", $filenum,$file['length'], $prio[$filenum]));
				}
			}
		}
	}
	$retVal .= "<table><tr>";
	$retVal .= "<tr><td width=\"110\">Metainfo File:</td><td>".$transfer."</td></tr>";
	$retVal .= "<tr><td>Directory Name:</td><td>".$btmeta['info']['name']."</td></tr>";
	$retVal .= "<tr><td>Announce URL:</td><td>".$btmeta['announce']."</td></tr>";
	if (array_key_exists('comment',$btmeta))
		$retVal .= "<tr><td valign=\"top\">Comment:</td><td>".tfb_htmlencode($btmeta['comment'])."</td></tr>";
	$retVal .= "<tr><td>Created:</td><td>".date("F j, Y, g:i a",$btmeta['creation date'])."</td></tr>";
	$retVal .= "<tr><td>Torrent Size:</td><td>".$torrent_size." (".@formatBytesTokBMBGBTB($torrent_size).")</td></tr>";
	$retVal .= "<tr><td>Chunk size:</td><td>".$btmeta['info']['piece length']." (".@formatBytesTokBMBGBTB($btmeta['info']['piece length']).")</td></tr>";
	if (array_key_exists('files',$btmeta['info'])) {
		$retVal .= "<tr><td>Selected size:</td><td id=\"sel\">0</td></tr>";
		$retVal .= "</table><br>\n";
		if ($withForm) {
			$retVal .= "<form name=\"priority\" action=\"dispatcher.php?action=setFilePriority&riid=_referer_\" method=\"POST\" >";
			$retVal .= "<input type=\"hidden\" name=\"transfer\" value=\"".$transfer."\" >";
		}
		$retVal .= "<script type=\"text/javascript\">\n";
		$retVal .= "var sel = 0;\n";
		$retVal .= "d = new dTree('d');\n";
		$retVal .= $tree->draw(-1);
		$retVal .= "document.write(d);\n";
		$retVal .= "sel = getSizes();\n";
		$retVal .= "drawSel();\n";
		$retVal .= "</script>\n";
		$retVal .= "<input type=\"hidden\" name=\"filecount\" value=\"".count($btmeta['info']['files'])."\">";
		$retVal .= "<input type=\"hidden\" name=\"count\" value=\"".$dirnum."\">";
		$retVal .= "<br>";
		if ($withForm) {
			$retVal .= '<input type="submit" value="Save" >';
			$retVal .= "<br>";
			$retVal .= "</form>";
		}
	} else {
		$retVal .= "</table><br>";
		$retVal .= $btmeta['info']['name'].$torrent_size." (".@formatBytesTokBMBGBTB($torrent_size).")";
	}
	// return
	return $retVal;
}

// =============================================================================
// classes
// =============================================================================

/**
 * dir
 */
class dir {

	var $name;
	var $subdirs;
	var $files;
	var $num;
	var $prio;

	function dir($name,$num,$prio) {
		$this->name = $name;
		$this->num = $num;
		$this->prio = $prio;
		$this->files = array();
		$this->subdirs = array();
	}

	function &addFile($file) {
		$this->files[] =& $file;
		return $file;
	}

	function &addDir($dir) {
		$this->subdirs[] =& $dir;
		return $dir;
	}

	// code changed to support php4
	// thx to Mistar Muffin
	function &findDir($name) {
		foreach (array_keys($this->subdirs) as $v) {
			$dir =& $this->subdirs[$v];
			if($dir->name == $name)
				return $dir;
		}
		$retVal = false;
		return $retVal;
	}

	function draw($parent) {
		$draw = ("d.add(".$this->num.",".$parent.",\"".$this->name."\",".$this->prio.",0);\n");
		foreach($this->subdirs as $v)
			$draw .= $v->draw($this->num);
		foreach($this->files as $v) {
			if(is_object($v))
			  $draw .= ("d.add(".$v->num.",".$this->num.",\"".$v->name."\",".$v->prio.",".$v->size.");\n");
		}
		return $draw;
	}

}

/**
 * file
 */
class file {

	var $name;
	var $prio;
	var $size;
	var $num;

	function file($name,$num,$size,$prio) {
		$this->name = $name;
		$this->num	= $num;
		$this->size = $size;
		$this->prio = $prio;
	}

}

?>