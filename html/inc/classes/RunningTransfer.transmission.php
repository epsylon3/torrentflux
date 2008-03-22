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
 * class RunningTransferTransmission for transmission-client
 */
class RunningTransferTransmission extends RunningTransfer
{

	/**
	 * ctor
	 *
	 * @param $psLine
	 * @return RunningTransferTransmission
	 */
    function RunningTransferTransmission($psLine) {
    	global $cfg;
        // ps-parse
        if (strlen($psLine) > 0) {
            while (strpos($psLine,"  ") > 0)
                $psLine = str_replace("  ",' ',trim($psLine));
            $arr = split(' ',$psLine);
            $this->processId = $arr[0];
            $this->transferFile = str_replace($cfg['transfer_file_path'],'',$arr[(count($arr) - 1)]);
            $this->filePath = substr($this->transferFile,0,strrpos($this->transferFile,"/")+1);
            foreach ($arr as $key =>$value) {
                if ($value == '-O')
                    $this->transferowner = $arr[$key+1];
            }
            $this->args = str_replace("-","",$this->args);
            $this->args = substr($this->args,0,strlen($this->args));
        }
    }

}

?>