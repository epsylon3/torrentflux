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
 * class RunningTransferNzbperl for nzbperl-client
 */
class RunningTransferNzbperl extends RunningTransfer
{

	/**
	 * ctor
	 *
	 * @param $psLine
	 * @return RunningTransferNzbperl
	 */
    function RunningTransferNzbperl($psLine) {
    	global $cfg;
        // ps-parse
        if (strlen($psLine) > 0) {
            while (strpos($psLine,"  ") > 0)
                $psLine = str_replace("  ",' ',trim($psLine));
            $arr = split(' ',$psLine);
            $count = count($arr);
            $this->processId = $arr[0];
            $this->args = "";
            $this->filePath = $arr[($count - 4)];
            $this->transferowner = $arr[($count - 2)];
            $this->transferFile = str_replace($cfg['transfer_file_path'],'', $arr[($count - 1)]);
        }
    }

}

?>