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

// core functions
require_once('inc/functions/functions.core.php');

// common functions
require_once('inc/functions/functions.common.php');

// image functions
require_once('inc/functions/functions.image.php');

// Image class
require_once('inc/classes/Image.php');

// image-op-switch
$imageOp = (isset($_REQUEST['i'])) ? tfb_getRequestVar('i') : "noop";
switch ($imageOp) {

	case "login":
		// check for valid referer
		Image::checkReferer();
		// main.external
		require_once('inc/main.external.php');
		// output image
		image_login();

	case "test":
		// check for valid referer
		Image::checkReferer();
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_test();

	case "pieTransferTotals":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_pieTransferTotals();

	case "pieTransferPeers":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_pieTransferPeers();

	case "pieTransferScrape":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_pieTransferScrape();

	case "pieServerBandwidth":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_pieServerBandwidth();

	case "pieServerDrivespace":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_pieServerDrivespace();

	case "mrtg":
		// main.internal
		require_once('inc/main.internal.php');
		// output image
		image_mrtg();

	case "spacer":
		// check for valid referer
		Image::checkReferer();
		// output image
		image_spacer();

	case "notsup":
		// check for valid referer
		Image::checkReferer();
		// output image
		image_notsup();

	case "noop":
	default:
		// check for valid referer
		Image::checkReferer();
		// output image
		image_noop();

}

?>