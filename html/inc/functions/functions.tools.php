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
 * get list with files + checksums
 *
 * @param $talk
 * @return array
 */
function getFileChecksums($talk = false) {
	global $cfg, $fileList;
	$fileList = array();
	$fileList['files'] = array();
	$fileList['types'] = array(".php", ".dist", ".pl", ".pm", ".tmpl", ".html", ".js", ".css", ".xml", ".xsd", ".py");
	_getFileChecksums(substr($cfg['docroot'], 0 , -1), $talk);
	return $fileList['files'];
}

/**
 * get list with files + checksums worker
 *
 * @param $dir
 * @param $talk
 */
function _getFileChecksums($dir, $talk = false) {
	global $cfg, $fileList;
	if (!is_dir($dir))
		return false;
	$dirHandle = opendir($dir);
	while ($file = readdir($dirHandle)) {
		$fullpath = $dir.'/'.$file;
		if (is_dir($fullpath)) {
			if ($file{0} != '.')
				_getFileChecksums($fullpath, $talk);
		} else {
			$stringLength = strlen($file);
			foreach ($fileList['types'] as $ftype) {
				$extLength = strlen($ftype);
				if (($stringLength > $extLength) && (strtolower(substr($file, -($extLength))) === ($ftype))) {
					$file = str_replace($cfg["docroot"], '', $fullpath);
					$fileList['files'][$file] = md5_file($fullpath);
					if ($talk)
						sendLine('.');
				}
			}
		}
	}
	closedir($dirHandle);
}

/**
 * print file-list
 *
 * @param $basedir
 * @param $type 1 = list, 2 = checksums
 * @param $mode 1 = text, 2 = html
 */
function printFileList($basedir, $type = 1, $mode = 2) {
	global $fileList;
	if (((strlen($basedir) > 0)) && (substr($basedir, -1 ) != "/"))
		$basedir .= "/";
	$dir = substr($basedir, 0 , -1);
	if (!is_dir($dir))
		return false;
	define('_URL_SVNLOG','http://svn.berlios.de/wsvn/tf-b4rt/trunk/?rev=');
	define('_URL_SVNLOG_SUFFIX','&sc=1');
	define('_URL_SVNFILE','http://svn.berlios.de/wsvn/tf-b4rt/trunk/html/');
	define('_URL_SVNFILE_SUFFIX','?op=log&rev=0&sc=0&isdir=0');
	$fileList = array();
	$fileList['files'] = array();
	$fileList['types'] = array(".php", ".dist", ".pl", ".pm", ".tmpl", ".html", ".js", ".css", ".xml", ".xsd", ".py");
	$fileList['count'] = 0;
	$fileList['size'] = 0;
	$fileList['revision'] = 1;
	_printFileList($basedir, $dir, $type, $mode);
	// footer in html
	if (($type == 1) && ($mode == 2)) {
		sendLine('<br><strong>Processed '.$fileList['count'].' files. ('.formatHumanSize($fileList['size']).')</strong>');
		sendLine('<br><strong>Highest Revision-Number : ');
		sendLine('<a href="'._URL_SVNLOG.$fileList['revision']._URL_SVNLOG_SUFFIX.'" target="_blank">'.$fileList['revision'].'</a>');
		sendLine('</strong>');
	}
}

/**
 * print file list worker
 *
 * @param $basedir
 * @param $dir
 * @param $type 1 = list, 2 = checksums
 * @param $mode 1 = text, 2 = html
 * @return revision-list as html-snip
 */
function _printFileList($basedir, $dir, $type = 1, $mode = 2) {
	global $fileList;
	if (!is_dir($dir))
		return false;
	$dirHandle = opendir($dir);
	while ($file = readdir($dirHandle)) {
		$fullpath = $dir.'/'.$file;
		if (is_dir($fullpath)) {
			if ($file{0} != '.')
				_printFileList($basedir, $fullpath, $type, $mode);
		} else {
			$stringLength = strlen($file);
			foreach ($fileList['types'] as $ftype) {
				$extLength = strlen($ftype);
				if (($stringLength > $extLength) && (strtolower(substr($file, -($extLength))) === ($ftype))) {
					// count
					$fileList['count'] += 1;
					// file
					$_file = str_replace($basedir, '', $fullpath);
					switch ($type) {
						default:
						case 1:
							// vars
							$_size = filesize($fullpath);
							$_rev = getSVNRevisionFromId($fullpath);
							// size
							$fileList['size'] += $_size;
							// rev
							if ($_rev != 'NoID') {
								$intrev = intval($_rev);
								if ($intrev > $fileList['revision'])
									$fileList['revision'] = $intrev;
							}
							// print
							switch ($mode) {
								default:
								case 1:
									echo $_file.';'.$_size.';'.$_rev."\n";
									break;
								case 2:
									$line  = '<a href="'._URL_SVNFILE.$_file._URL_SVNFILE_SUFFIX.'" target="_blank">'.$_file.'</a> | ';
									$line .= formatHumanSize($_size).' | ';
									$line .= ($_rev != 'NoID')
										? '<a href="'._URL_SVNLOG.$_rev._URL_SVNLOG_SUFFIX.'" target="_blank">'.$_rev.'</a>'
										: 'NoID';
									$line .= '<br>';
									sendLine($line);
									break;
							}
							break;
						case 2:
							// vars
							$_md5 = md5_file($fullpath);
							// print
							switch ($mode) {
								default:
								case 1:
									echo $_file.';'.$_md5."\n";
									break;
								case 2:
									sendLine($_file." ".$_md5."<br>");
									break;
							}
							break;
					}
				}
			}
		}
	}
	closedir($dirHandle);
}

/**
 * get svn-revision from id-tag of a file
 *
 * @param $filename
 * @return string
 */
function getSVNRevisionFromId($filename) {
	$data = file_get_contents($filename);
	$len = strlen($data);
	for ($i = 0; $i < $len; $i++) {
		if ($data{$i} == '$') {
            if (($data{$i+1} == 'I') && ($data{$i+2} == 'd')) {
            	$revision = "";
            	$j = $i + 3;
                while ($j < $len) {
                	if ($data{$j} == '$') {
                		$rev = explode(" ", $revision);
                		return trim($rev[2]);
                	} else {
                		$revision .= $data{$j};
                	}
                	$j++;
                }
            }
        }
	}
	return 'NoID';
}

/**
 * get data of a url
 *
 * @param $url the url
 * @return data
 */
function getDataFromUrl($url) {
	ini_set("allow_url_fopen", "1");
	ini_set("user_agent", "torrentflux-b4rt/". _VERSION);
	if ($urlHandle = @fopen($url, 'r')) {
		stream_set_timeout($urlHandle, 15);
		$info = stream_get_meta_data($urlHandle);
		$data = null;
		while ((!feof($urlHandle)) && (!$info['timed_out'])) {
			$data .= @fgets($urlHandle, 4096);
			$info = stream_get_meta_data($urlHandle);
		}
		@fclose ($urlHandle);
		return $data;
	}
}

?>