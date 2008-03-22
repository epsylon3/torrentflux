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
 * NZBFile
 */
class NZBFile
{
    // public fields

    // file
    var $theFile = "";

    // props
    var $files = array();
    var $size = 0;
    var $filecount = 0;

    // content
    var $content = "";

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * factory
     *
     * @param $nzbname
     * @return NZBFile
     */
    function getInstance($nzbname = "") {
        return new NZBFile($nzbname);
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     *
     * @param $nzbname
     * @param $user
     * @return NZBFile
     */
    function NZBFile($nzbname = "") {
    	$this->initialize($nzbname);
    }

	// =========================================================================
	// public methods
	// =========================================================================

    /**
     * initialize
     *
     * @param $nzbname
     */
    function initialize($nzbname) {
    	global $cfg;
    	// vars
    	$this->theFile = $cfg["transfer_file_path"].$nzbname;
    	$this->files = array();
    	$this->size = 0;
    	$this->filecount = 0;
        // load file
		$this->content = @file_get_contents($this->theFile);
		$tList = explode("\n", $this->content);
		if ((isset($tList)) && (is_array($tList))) {
			$fname = "";
			$fsize = 0;
			foreach ($tList as $tLine) {
				// file-start
				if (strpos($tLine, "<file") !== false) {
					$fname = preg_replace('/<file.*subject="(.*)">/i', '${1}', $tLine);
					$fsize = 0;
				}
				// segment
				if (strpos($tLine, "<segment bytes") !== false) {
					$bytes = preg_replace('/<segment bytes="(\d+)".*/i', '${1}', $tLine);
					$fsize += floatval($bytes);
				}
				// file end
				if (strpos($tLine, "</file>") !== false) {
					array_push($this->files, array(
						'name' => $fname,
						'size' => $fsize
						)
					);
					// size
					$this->size += $fsize;
				}
			}
			// count
			$this->filecount = count($this->files);
		}
    }

    /**
     * write Method
     *
     * @return boolean
     */
    function write() {
		// write file
		if ($handle = @fopen($this->theFile, "w")) {
	        $resultSuccess = (@fwrite($handle, $this->content) !== false);
			@fclose($handle);
			return $resultSuccess;
		}
		return false;
    }

}

?>