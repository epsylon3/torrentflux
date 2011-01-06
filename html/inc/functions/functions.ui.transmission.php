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

// TODO: Rename function name
function transmissionSetVars ($transfer, $tmpl) {

	//require_once('inc/functions/functions.rpc.transmission.php');
	require_once('functions.rpc.transmission.php');
	$options = array("eta","percentDone", "rateDownload", "rateUpload", "downloadedEver", "uploadedEver", "percentDone", "sizeWhenDone");
	$returnArr = getTransmissionTransfer($transfer, $options);

	$tmpl->setvar('transferowner', getTransmissionTransferOwner($transfer));

	$tmpl->setvar('size', @formatBytesTokBMBGBTB($returnArr['sizeWhenDone']));

	// sharing
	$tmpl->setvar('sharing', ($returnArr["downloadedEver"] > 0) ? @number_format((($returnArr["uploadedEver"] / $returnArr["downloadedEver"]) * 100), 2) : "0");

	// totals
	$tmpl->setvar('downTotal', @formatFreeSpace($returnArr["downloadedEver"] / 1048576));
	$tmpl->setvar('upTotal', @formatFreeSpace($returnArr["uploadedEver"] / 1048576));

	// port + cons
	//$tmpl->setvar('size', @formatBytesTokBMBGBTB($transferSize));

	$isRunning = true;
	if ($isRunning) {
		$tmpl->setvar('running', 1);

		// current totals
		$tmpl->setvar('downTotalCurrent', formatFreeSpace($totalsCurrent["downtotal"] / 1048576));
		$tmpl->setvar('upTotalCurrent', formatFreeSpace($totalsCurrent["uptotal"] / 1048576));

		// seeds + peers
		$tmpl->setvar('seeds', $sf->seeds);
		$tmpl->setvar('peers', $sf->peers);

		// port + cons
		$transfer_pid = getTransferPid($transfer);
		$tmpl->setvar('port', netstatPortByPid($transfer_pid));
		$tmpl->setvar('cons', netstatConnectionsByPid($transfer_pid));

		// up speed
		$tmpl->setvar('up_speed', (trim($returnArr['rateUpload']) != "") ? formatBytesTokBMBGBTB( $returnArr['rateUpload'] ) . '/s' : '0.0 kB/s');

		// down speed
		$tmpl->setvar('down_speed', (trim($returnArr['rateDownload']) != "") ? formatBytesTokBMBGBTB( $returnArr['rateDownload'] ) . '/s' : '0.0 kB/s');

		// sharekill
		$tmpl->setvar('sharekill', ($ch->sharekill != 0) ? $ch->sharekill.'%' : '&#8734');
	} else {

		// running
		$tmpl->setvar('running', 0);

		// current totals
		$tmpl->setvar('downTotalCurrent', "");
		$tmpl->setvar('upTotalCurrent', "");

		// seeds + peers
		$tmpl->setvar('seeds', "");
		$tmpl->setvar('peers', "");

		// port + cons
		$tmpl->setvar('port', "");
		$tmpl->setvar('cons', "");

		// up speed
		$tmpl->setvar('up_speed', "");

		// down speed
		$tmpl->setvar('down_speed', "");

		// sharekill
		$tmpl->setvar('sharekill', "");

	}

	if ($returnArr['eta'] < 0)
		$tmpl->setvar('time_left', 'n/a');
	else
		$tmpl->setvar('time_left', convertTime( $returnArr['eta'] ));

	// graph width
	$tmpl->setvar('graph_width1', $returnArr['percentDone']*100);
	$tmpl->setvar('graph_width2', (100 - $returnArr['percentDone']*100));
		
	$tmpl->setvar('percent_done', $returnArr['percentDone']*100);

	// language vars
	global $cfg;
	$tmpl->setvar('_USER', $cfg['_USER']);
	$tmpl->setvar('_SHARING', $cfg['_SHARING']);
	$tmpl->setvar('_ID_CONNECTIONS', $cfg['_ID_CONNECTIONS']);
	$tmpl->setvar('_ID_PORT', $cfg['_ID_PORT']);
	$tmpl->setvar('_DOWNLOADSPEED', $cfg['_DOWNLOADSPEED']);
	$tmpl->setvar('_UPLOADSPEED', $cfg['_UPLOADSPEED']);
	$tmpl->setvar('_PERCENTDONE', $cfg['_PERCENTDONE']);
	$tmpl->setvar('_ESTIMATEDTIME', $cfg['_ESTIMATEDTIME']);

	return $tmpl;
}

?>
