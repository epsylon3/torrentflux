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

// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

// default-type
define('_DEFAULT_TYPE', 'server');

// default-targets
define('_DEFAULT_TARGET_SERVER', 'all');
define('_DEFAULT_TARGET_MRTG', 'traffic');

// input-dir mrtg
define('_MRTG_DIR_INPUT', $cfg["path"].'.mrtg');

// image-defines
define('_IMAGE_URL', "image.php");
define('_IMAGE_PREFIX_MRTG', "?i=mrtg&f=");

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.images.tmpl");

// request-vars
$type = (isset($_REQUEST['type'])) ? tfb_getRequestVar('type') : _DEFAULT_TYPE;
$target = tfb_getRequestVar('target');

// types
$type_list = array();
array_push($type_list, array(
	'name' => "server",
	'selected' => ($type == "server") ? 1 : 0
	)
);
array_push($type_list, array(
	'name' => "mrtg",
	'selected' => ($type == "mrtg") ? 1 : 0
	)
);
$tmpl->setloop('type_list', $type_list);

// type-switch
switch ($type) {

	// server
	case "server":

		// target
		if ($target == "")
			$target = _DEFAULT_TARGET_SERVER;

		// targets
		$target_list = array();
		array_push($target_list, array(
			'name' => "all",
			'selected' => ($target == "all") ? 1 : 0
			)
		);
		array_push($target_list, array(
			'name' => "bandwidth",
			'selected' => ($target == "bandwidth") ? 1 : 0
			)
		);
		array_push($target_list, array(
			'name' => "drivespace",
			'selected' => ($target == "drivespace") ? 1 : 0
			)
		);
		$tmpl->setloop('target_list', $target_list);

		// target-content

		// create template-instance
		$_tmpl = tmplGetInstance($cfg["theme"], "component.images.server.tmpl");

		// set vars
		$image_list = array();
		if (($target == "bandwidth") || ($target == "all"))
			array_push($image_list, array(
				'title' => "Bandwidth",
				'src' => "image.php?i=pieServerBandwidth"
				)
			);
		if (($target == "drivespace") || ($target == "all"))
			array_push($image_list, array(
				'title' => "Drivespace",
				'src' => "image.php?i=pieServerDrivespace"
				)
			);
		if (!empty($image_list))
			$_tmpl->setloop('image_list', $image_list);
		$_tmpl->setvar('type', $type);
		$_tmpl->setvar('target', $target);

		// grab + set the content of template
		$tmpl->setvar('content', $_tmpl->grab());

		break;

	// mrtg
	case "mrtg":

		// target
		if ($target == "")
			$target = _DEFAULT_TARGET_MRTG;

		// targets
		$target_list = array();
		if ((@is_dir(_MRTG_DIR_INPUT)) && ($dirHandle = @opendir(_MRTG_DIR_INPUT))) {
			while (false !== ($file = @readdir($dirHandle))) {
				if ((strlen($file) > 4) && (substr($file, -4) == ".inc")) {
		      		$targetName = (substr($file, 0, -4));
					array_push($target_list, array(
						'name' => $targetName,
						'selected' => ($target == $targetName) ? 1 : 0
						)
					);
				}
			}
			@closedir($dirHandle);
		}

		// stop here if no targets found
		if (empty($target_list)) {
			$tmpl->setvar('content', "<br><p><strong>No Targets found.</strong></p>");
			break;
		}

		// set target-list
		$tmpl->setloop('target_list', $target_list);

		// target-content
		$targetFile = _MRTG_DIR_INPUT."/".$target.".inc";
		// check target
		if (!((tfb_isValidPath($targetFile) === true)
			&& (preg_match('/^[0-9a-zA-Z_]+$/D', $target))
			&& (@is_file($targetFile))
			)) {
			AuditAction($cfg["constants"]["error"], "ILLEGAL MRTG-TARGET: ".$cfg["user"]." tried to access ".$target);
			@error("Invalid Target", "", "", array($target));
		}
		$content = @file_get_contents($targetFile);
		// we are only interested in the "real" content
		$tempAry = explode("_CONTENT_BEGIN_", $content);
		if (is_array($tempAry)) {
			$tempVar = array_pop($tempAry);
			$tempAry = explode("_CONTENT_END_", $tempVar);
			if (is_array($tempAry)) {
				$content = array_shift($tempAry);
				// rewrite image-links
				$content = preg_replace('/(.*")(.*)(png".*)/i', '${1}'._IMAGE_URL._IMAGE_PREFIX_MRTG.'${2}${3}', $content);
				// set var
				$tmpl->setvar('content', $content);
			}
		}

		break;

	// default
	default:
		$tmpl->setvar('content', "Invalid Type");
		break;
}

// set vars
$tmpl->setvar('type', $type);
$tmpl->setvar('target', $target);

// more vars
tmplSetTitleBar($cfg["pagetitle"].' - '.$cfg['_ID_IMAGES']);
tmplSetFoot();
$tmpl->setvar('enable_multiupload', $cfg["enable_multiupload"]);
$tmpl->setvar('_MULTIPLE_UPLOAD', $cfg['_MULTIPLE_UPLOAD']);
$tmpl->setvar('_ID_IMAGES', $cfg['_ID_IMAGES']);
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>