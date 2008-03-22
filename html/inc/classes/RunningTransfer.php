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
 * base class RunningTransfer
 */
class RunningTransfer
{

	// public fields
    var $transferFile = "";
    var $filePath = "";
    var $transferowner = "";
    var $processId = "";
    var $args = "";

    /**
     * factory
     *
     * @param $psLine ps-line
     * @param $client client
     * @return RunningTransfer
     */
    function getInstance($psLine, $client = '') {
    	// create and return object-instance
        switch ($client) {
            case "tornado":
            	require_once('inc/classes/RunningTransfer.tornado.php');
                return new RunningTransferTornado($psLine);
            case "transmission":
            	require_once('inc/classes/RunningTransfer.transmission.php');
                return new RunningTransferTransmission($psLine);
            case "mainline":
            	require_once('inc/classes/RunningTransfer.mainline.php');
                return new RunningTransferMainline($psLine);
            case "azureus":
            	require_once('inc/classes/RunningTransfer.azureus.php');
                return new RunningTransferAzureus($psLine);
            case "wget":
            	require_once('inc/classes/RunningTransfer.wget.php');
                return new RunningTransferWget($psLine);
			case "nzbperl":
				require_once('inc/classes/RunningTransfer.nzbperl.php');
				return new RunningTransferNzbperl($psLine);
            default:
            	global $cfg;
            	return RunningTransfer::getInstance($psLine, $cfg["btclient"]);
        }
    }

	/**
	 * ctor
	 *
	 * @return RunningTransfer
	 */
    function RunningTransfer() {
        die('base class -- dont do this');
    }

}

?>