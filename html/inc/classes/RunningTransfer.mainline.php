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
 * class RunningTransferMainline for mainline-client
 */
class RunningTransferMainline extends RunningTransfer
{

	/**
	 * ctor
	 *
	 * @param $psLine
	 * @return RunningTransferMainline
	 */
    function RunningTransferMainline($psLine) {
    	global $cfg;
        // mainlineBin
        $mainlineBin = $cfg["docroot"]."bin/clients/mainline/tfmainline.py";
        // ps-parse
        if (strlen($psLine) > 0) {
            while (strpos($psLine,"  ") > 0)
                $psLine = str_replace("  ",' ',trim($psLine));
            $arr = split(' ',$psLine);
            $this->processId = $arr[0];
            $arrC = count($arr);
            foreach($arr as $key =>$value) {
                if ($key == 0)
                    $startArgs = false;
                if ($value == $mainlineBin) {
                	$this->transferowner = $arr[5];
                	$this->filePath = substr($arr[$arrC - 1], 0, strrpos($arr[$arrC - 1], "/") + 1);
                	$this->transferFile = str_replace($cfg['transfer_file_path'],'', $arr[$arrC - 1]);
                }
            }
            $this->args = str_replace("--","",$this->args);
            $this->args = substr($this->args,0,strlen($this->args));
        }
    }

}

?>