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
 * class RunningTransferTornado for tornado-client
 */
class RunningTransferTornado extends RunningTransfer
{

	/**
	 * ctor
	 *
	 * @param $psLine
	 * @return RunningTransferTornado
	 */
    function RunningTransferTornado($psLine) {
    	global $cfg;
        // tornadoBin
        $tornadoBin = $cfg["docroot"]."bin/clients/tornado/tftornado.py";
        // ps-parse
        if (strlen($psLine) > 0) {
            while (strpos($psLine,"  ") > 0)
                $psLine = str_replace("  ",' ',trim($psLine));
            $arr = split(' ',$psLine);
            $this->processId = $arr[0];
            foreach($arr as $key =>$value) {
                if ($key == 0)
                    $startArgs = false;
                if ($value == $tornadoBin) {
                    $offset = 1;
                    if(!@strpos($arr[$key+$offset],"/",1) > 0)
                        $offset += 1;
                    if(!@strpos($arr[$key+$offset],"/",1) > 0)
                        $offset += 1;
                    $this->filePath = substr($arr[$key+$offset],0,strrpos($arr[$key+$offset],"/")+1);
                    $this->transferowner = $arr[$key+$offset];
                    $this->transferFile = str_replace($cfg['transfer_file_path'],'',$arr[$key+$offset+1]);
                }
                if ($value == '--display_interval')
                    $startArgs = true;
                if ($startArgs) {
                    if (!empty($value)) {
                        if (strpos($value, "-", 1) > 0) {
                            if (array_key_exists($key + 1, $arr)) {
                            	$this->args .= (strpos($value,"priority") > 0)
                            		? "\n file ".$value." set"
                            		: $value.":".$arr[$key+1].",";
                            } else {
                                $this->args .= "";
                            }
                        }
                    }
                }
                if ($value == '--responsefile')
                    $this->transferFile = str_replace($cfg['transfer_file_path'],'',$arr[$key+1]);
            }
            $this->args = str_replace("--","",$this->args);
            $this->args = substr($this->args,0,strlen($this->args));
        }
    }

}

?>