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
 * init
 */
function transfer_init() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// request-var
	$transfer = tfb_getRequestVar('transfer');
	if (empty($transfer))
		@error("missing params", "", "", array('transfer'));
	// validate transfer
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// permission
	if ((!$cfg['isAdmin']) && (!IsOwner($cfg["user"], getOwner($transfer)))) {
		AuditAction($cfg["constants"]["error"], "ACCESS DENIED: ".$transfer);
		@error("Access Denied", "", "", array($transfer));
	}
	// get label
	$transferLabel = (strlen($transfer) >= 39) ? substr($transfer, 0, 35)."..." : $transfer;
	// set transfer vars
	$tmpl->setvar('transfer', $transfer);
	$tmpl->setvar('transferLabel', $transferLabel);
	$tmpl->setvar('transfer_exists', (transferExists($transfer)) ? 1 : 0);
}

/**
 * setCustomizeVars
 */
function transfer_setCustomizeVars() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// customize settings
	if ($cfg['transfer_customize_settings'] == 2)
		$customize_settings = 1;
	elseif ($cfg['transfer_customize_settings'] == 1 && $cfg['isAdmin'])
		$customize_settings = 1;
	else
		$customize_settings = 0;
	$tmpl->setvar('customize_settings', $customize_settings);
	// set supported-vars for transfer
	if ($customize_settings == 0) {
		$tmpl->setvar('upload_support_enabled', 0);
		$tmpl->setvar('download_support_enabled', 0);
		$tmpl->setvar('max_uploads_enabled', 0);
		$tmpl->setvar('superseeder_enabled', 0);
		$tmpl->setvar('die_when_done_enabled', 0);
		$tmpl->setvar('sharekill_enabled', 0);
		$tmpl->setvar('minport_enabled', 0);
		$tmpl->setvar('maxport_enabled', 0);
		$tmpl->setvar('maxcons_enabled', 0);
		$tmpl->setvar('rerequest_enabled', 0);
	} else {
		$tmpl->setvar('upload_support_enabled', $cfg["supportMap"][$ch->client]['max_upload_rate']);
		$tmpl->setvar('download_support_enabled', $cfg["supportMap"][$ch->client]['max_download_rate']);
		$tmpl->setvar('max_uploads_enabled', $cfg["supportMap"][$ch->client]['max_uploads']);
		$tmpl->setvar('superseeder_enabled', $cfg["supportMap"][$ch->client]['superseeder']);
		$tmpl->setvar('die_when_done_enabled', $cfg["supportMap"][$ch->client]['die_when_done']);
		$tmpl->setvar('sharekill_enabled', $cfg["supportMap"][$ch->client]['sharekill']);
		$tmpl->setvar('minport_enabled', $cfg["supportMap"][$ch->client]['minport']);
		$tmpl->setvar('maxport_enabled', $cfg["supportMap"][$ch->client]['maxport']);
		$tmpl->setvar('maxcons_enabled', $cfg["supportMap"][$ch->client]['maxcons']);
		$tmpl->setvar('rerequest_enabled', $cfg["supportMap"][$ch->client]['rerequest']);
	}
}

/**
 * setGenericVarsFromCH
 */
function transfer_setGenericVarsFromCH() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set generic vars for transfer
	$tmpl->setvar('type', $ch->type);
	$tmpl->setvar('client', $ch->client);
	$tmpl->setvar('hash', $ch->hash);
	$tmpl->setvar('datapath', $ch->datapath);
	$tmpl->setvar('savepath', $ch->savepath);
	$tmpl->setvar('running', $ch->running);
}

/**
 * setVarsFromCHSettings
 */
function transfer_setVarsFromCHSettings() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set generic vars for transfer
	transfer_setGenericVarsFromCH();
	// set vars for transfer
	$tmpl->setvar('max_upload_rate', $ch->rate);
	$tmpl->setvar('max_download_rate', $ch->drate);
	$tmpl->setvar('max_uploads', $ch->maxuploads);
	$tmpl->setvar('superseeder', $ch->superseeder);
	$tmpl->setvar('die_when_done', $ch->runtime);
	$tmpl->setvar('sharekill', $ch->sharekill);
	$tmpl->setvar('minport', $ch->minport);
	$tmpl->setvar('maxport', $ch->maxport);
	$tmpl->setvar('maxcons', $ch->maxcons);
	$tmpl->setvar('rerequest', $ch->rerequest);
}

/**
 * setVarsFromProfileSettings
 *
 * @param $profile
 */
function transfer_setVarsFromProfileSettings($profile) {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set generic vars for transfer
	transfer_setGenericVarsFromCH();
	//load custom settings
	$settings = GetProfileSettings($profile);
	// set vars for transfer
	$tmpl->setvar('max_upload_rate', $settings["rate"]);
	$tmpl->setvar('max_download_rate', $settings["drate"]);
	$tmpl->setvar('max_uploads', $settings["maxuploads"]);
	$tmpl->setvar('superseeder', $settings['superseeder']);
	$tmpl->setvar('die_when_done', $settings["runtime"]);
	$tmpl->setvar('sharekill', $settings["sharekill"]);
	$tmpl->setvar('minport', $settings["minport"]);
	$tmpl->setvar('maxport', $settings["maxport"]);
	$tmpl->setvar('maxcons', $settings["maxcons"]);
	$tmpl->setvar('rerequest', $settings["rerequest"]);
	$tmpl->setvar('savepath', getTransferSavepath($transfer, $profile));
}

/**
 * setFileVars
 */
function transfer_setFileVars() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set vars for transfer
	$transferFilesList = array();
	switch ($ch->type) {
		case "torrent":
			require_once("inc/classes/BDecode.php");
			$tFile = $cfg["transfer_file_path"].$transfer;
			if ($fd = @fopen($tFile, "rd")) {
				$alltorrent = @fread($fd, @filesize($tFile));
				$btmeta = @BDecode($alltorrent);
				@fclose($fd);
			}
			$transferSizeSum = 0;
			if ((isset($btmeta)) && (is_array($btmeta)) && (isset($btmeta['info']))) {
				if (array_key_exists('files', $btmeta['info'])) {
					foreach ($btmeta['info']['files'] as $filenum => $file) {
						$name = (is_array($file['path'])) ? (implode("/", ($file['path']))) : $file['path'];
						$size = ((isset($file['length'])) && (is_numeric($file['length']))) ? $file['length'] : 0;
						$transferSizeSum += $size;
						array_push($transferFilesList, array(
							'name' => $name,
							'size' => ($size != 0) ? formatBytesTokBMBGBTB($size) : 0
							)
						);
					}
				} else {
					$size = $btmeta["info"]["piece length"] * (strlen($btmeta["info"]["pieces"]) / 20);
					$transferSizeSum += $size;
					array_push($transferFilesList, array(
						'name' => $btmeta["info"]["name"],
						'size' => formatBytesTokBMBGBTB($size)
						)
					);
				}
			}
			if (empty($transferFilesList)) {
				$tmpl->setvar('transferFilesString', "Empty");
				$tmpl->setvar('transferFileCount', count($btmeta['info']['files']));
			} else {
				$tmpl->setloop('transferFilesList', $transferFilesList);
				$tmpl->setvar('transferFileCount', count($transferFilesList));
			}
			$tmpl->setvar('transferSizeSum', ($transferSizeSum > 0) ? formatBytesTokBMBGBTB($transferSizeSum) : 0);
			return;
		case "wget":
			$ch = ClientHandler::getInstance('wget');
			$ch->setVarsFromFile($transfer);
			$transferSizeSum = 0;
			if (!empty($ch->url)) {
				require_once("inc/classes/SimpleHTTP.php");
				$size = SimpleHTTP::getRemoteSize($ch->url);
				$transferSizeSum += $size;
				array_push($transferFilesList, array(
					'name' => $ch->url,
					'size' => formatBytesTokBMBGBTB($size)
					)
				);
			}
			if (empty($transferFilesList)) {
				$tmpl->setvar('transferFilesString', "Empty");
				$tmpl->setvar('transferFileCount', 0);
			} else {
				$tmpl->setloop('transferFilesList', $transferFilesList);
				$tmpl->setvar('transferFileCount', count($transferFilesList));
			}
			$tmpl->setvar('transferSizeSum', ($transferSizeSum > 0) ? formatBytesTokBMBGBTB($transferSizeSum) : 0);
			return;
		case "nzb":
			require_once("inc/classes/NZBFile.php");
			$nzb = new NZBFile($transfer);
			$transferSizeSum = 0;
			if (empty($nzb->files)) {
				$tmpl->setvar('transferFilesString', "Empty");
				$tmpl->setvar('transferFileCount', 0);
			} else {
				foreach ($nzb->files as $file) {
					$transferSizeSum += $file['size'];
					array_push($transferFilesList, array(
						'name' => $file['name'],
						'size' => formatBytesTokBMBGBTB($file['size'])
						)
					);
				}
				$tmpl->setloop('transferFilesList', $transferFilesList);
				$tmpl->setvar('transferFileCount', $nzb->filecount);
			}
			$tmpl->setvar('transferSizeSum', ($transferSizeSum > 0) ? formatBytesTokBMBGBTB($transferSizeSum) : 0);
			return;
	}
}

/**
 * setDetailsVars
 *
 * @param $withForm
 */
function transfer_setDetailsVars() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set vars for transfer
	$tmpl->setvar('clientType', $ch->type);
	switch ($ch->type) {
		case "torrent":
			$tmpl->setvar('transferMetaInfo', getTorrentMetaInfo($transfer));
			return;
		case "wget":
			$ch->setVarsFromFile($transfer);
			$tmpl->setvar('transferUrl', $ch->url);
			return;
		case "nzb":
			$tmpl->setvar('transferMetaInfo', @htmlentities(file_get_contents($cfg["transfer_file_path"].$transfer), ENT_QUOTES));
			return;
	}
}

/**
 * setProfiledVars
 */
function transfer_setProfiledVars() {
	global $cfg, $tmpl, $transfer, $transferLabel, $ch;
	// set vars for transfer
	if ($cfg['transfer_profiles'] <= 0) {
		$with_profiles = 0;
	} else {
		if ($cfg['transfer_profiles'] >= 2)
			$with_profiles = 1;
		else
			$with_profiles = ($cfg['isAdmin']) ? 1 : 0;
	}
	if ($with_profiles == 0) {
		// set vars for transfer from ch
		transfer_setVarsFromCHSettings();
		$tmpl->setvar('useLastSettings', 1);
	} else {
		$profile = tfb_getRequestVar('profile');
		if (($profile != "") && ($profile != "last_used")) {
			// set vars for transfer from profile
			transfer_setVarsFromProfileSettings($profile);
			$tmpl->setvar('useLastSettings', 0);
			$tmpl->setvar('profile', $profile);
		} else {
			// set vars for transfer from ch
			transfer_setVarsFromCHSettings();
			$tmpl->setvar('useLastSettings', 1);
		}
		// load profile lists
		$profiles = ($cfg['transfer_profiles'] >= 3 || $cfg['isAdmin'])
			? GetProfiles($cfg["uid"], $profile)
			: array();
		$public_profiles = ($cfg['transfer_profiles'] >= 2 || $cfg['isAdmin'])
			? GetPublicProfiles($profile)
			: array();
		if ((count($profiles) + count($public_profiles)) > 0) {
			$tmpl->setloop('profiles', $profiles);
			$tmpl->setloop('public_profiles', $public_profiles);
		} else {
			$with_profiles = 0;
		}
	}
	$tmpl->setvar('with_profiles', $with_profiles);
}

?>