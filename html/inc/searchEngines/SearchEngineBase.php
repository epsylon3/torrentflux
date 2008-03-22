<?php

/* $Id$ */

/*************************************************************
*  TorrentFlux PHP Torrent Manager
*  www.torrentflux.com
**************************************************************/
/*
    This file is part of TorrentFlux.

    TorrentFlux is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    TorrentFlux is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with TorrentFlux; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
   This is the base search engine class that is inherited from to
   create specialized search engines.

   Each new Search must use the following nameing standards.
   ???Engine.php where ??? is the search engine name.

   !! All changes and customizations should be done in those files and not in this file. !!

*/

// Created By Kboy
class SearchEngineBase
{
    var $engineName = '';       // The Engine Name Must be the same as the File name
                                // minus Engine.

    var $mainTitle = '';        // The displayed Main Title for the engine.
    var $mainURL = '';          // The Primary URL used in searching or paging.
    var $altURL = '';           // The alternate URL used in searching or paging.

    var $author = '';           // the author of this engine
    var $version = '';
    var $updateURL = 'http://www.torrentflux.com/forum/index.php/board,14.0.html';

    // ------------------------------------------------------------------------------
    // You should only need to set the above variables in each of the custom classes.
    // ------------------------------------------------------------------------------

    var $cfg = array();         // The config array that holds the config settings of
                                // TorrentFlux at time of initialization
                                // This may contain the mainCatalog and catFilter
                                // as assigned by the admin tool.
                                // If it doesn't then we ask the individual engines for there
                                // mainCatalog and catFilter.


    var $mainCatalogName = '';  // The name of the Main Catalog
    var $mainCatalog = array(); // An array of Main Catalog entries.
    var $subCatalog = array();  // An array of Sub Catalog entries.

    var $catFilterName = '';    // Name of Filter used to retrieve from DB.
    var $catFilter = array();   // An array of categories to Filter out from DB.

    var $curRequest ='';        // The actual Request sent to the Search Engine
    var $hideSeedless = false;  // Boolean to determine if we should hide or show seedless torrents
    var $searchTerm = '';       // Search term passed into the engine

    var $htmlPage = '';         // HTML created by the engine for displaying
    var $msg = '';              // Message to be displayed
    var $pg = '';               // Page Variable used in Paging

    var $fp = '';               // Pointer to a socket connection

    var $initialized = false;   // Boolean to determine if the search engine initialized ok.

    /**
     * Constructor
     */
    function SearchEngineBase() {
        die('Virtual Class -- cannot instantiate');
    }

    //----------------------------------------------------------------
    // Initialize the Search Engine setting up the Catalog and Filters.
    // and Testing the connection.
    function Initialize($cfg) {
		$rtnValue = false;
		$this->cfg = unserialize($cfg);
		$this->pg = tfb_getRequestVar('pg');
		if (empty($this->altURL))
			$this->altURL = $this->mainURL;
		if (empty($this->cfg)) {
			$this->msg = "Config not passed";
			$this->initialized = false;
			return;
		}
		$this->catFilterName = $this->engineName."GenreFilter";
		$this->mainCatalogName = $this->engineName."_catalog";
		if (array_key_exists('hideSeedless',$_SESSION))
			$this->hideSeedless = $_SESSION['hideSeedless'];
		$this->catFilter = (array_key_exists($this->catFilterName,$this->cfg))
			? $this->cfg[$this->catFilterName]
			: array();
		if (array_key_exists($this->mainCatalogName,$this->cfg))
			$this->mainCatalog = $this->cfg[$this->mainCatalogName];
		else
			$this->populateMainCategories();
		if ($this->getConnection())
			$rtnValue = true;
		$this->closeConnection();
		// in PHP 5 use
		//$this->curRequest = http_build_query($_REQUEST);
		$this->curRequest = $this->http_query_builder($_REQUEST);
		$this->initialized = $rtnValue;
    }

    //------------------------------------------------------------------
    // This is for backward compatibility.
    function http_query_builder( $formdata, $numeric_prefix = null, $key = null ) {
		$res = array();
		foreach ((array)$formdata as $k=>$v) {
			$tmp_key = urlencode(is_int($k) ? $numeric_prefix.$k : $k);
			if ($key) $tmp_key = $key.'['.$tmp_key.']';
			$res[] = (is_array($v) || is_object($v))
				? $this->http_query_builder($v, null, $tmp_key)
				: $tmp_key."=".urlencode($v);
		}
		$separator = ini_get('arg_separator.output');
		return implode($separator, $res);
    }

    //----------------------------------------------------------------
    // Function to populate the mainCatalog
    function populateMainCategories() {
        return;
    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
    function getSubCategories($mainGenre) {
        return array();
    }

    //----------------------------------------------------------------
    // Function to test Connection.
    function getConnection() {
        // Try to connect
        if (!$this->fp = @fsockopen ($this->mainURL, 80, $errno, $errstr, 30)) {
            // Error Connecting
            $this->msg = "Error connecting to ".$this->mainURL."!";
            return false;
        }
        return true;
    }

    //----------------------------------------------------------------
    // Function to Close Connection.
    function closeConnection() {
        if($this->fp)
            fclose($this->fp);
    }

    //----------------------------------------------------------------
    // Function to return the URL needed by tf
    function searchURL() {
        return "index.php?iid=torrentSearch&searchEngine=".$this->engineName;
    }

    //----------------------------------------------------------------
    // Function to Make the GetRequest
    function makeRequest($request, $useAlt = false) {
    	$refererURI = (isset($_SESSION['lastOutBoundURI']))
    		? $_SESSION['lastOutBoundURI']
    		: "http://".$this->mainURL;
    	$request = 	($useAlt)
    		? "http://".$this->altURL. $request
    		: "http://".$this->mainURL. $request;
		// require SimpleHTTP
		require_once("inc/classes/SimpleHTTP.php");
		// get data
        $this->htmlPage = SimpleHTTP::getData($request, $refererURI);
        // return
        return (SimpleHTTP::getState() == SIMPLEHTTP_STATE_OK);
    }

    //----------------------------------------------------------------
    // Function to Get Main Categories
    function getMainCategories($filtered = true) {
        $output = array();
        foreach ($this->mainCatalog as $mainId => $mainName) {
            if ($filtered) {
                // see if this is filtered out.
                if (!(@in_array($mainId, $this->catFilter)))
                    $output[$mainId] = $mainName;
            } else {
                $output[$mainId] = $mainName;
            }
        }
        return $output;
    }

    //----------------------------------------------------------------
    // Function to Get Main Category Name
    function GetMainCatName($mainGenre) {
        $mainGenreName = '';
        foreach ($this->getMainCategories() as $mainId => $mainName) {
            if ($mainId == $mainGenre)
                $mainGenreName = $mainName;
        }
        return $mainGenreName;
    }

    //----------------------------------------------------------------
    // Function to setup the table header
    function tableHeader() {
        $output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";
        $output .= "<br>\n";
        $output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
        $output .= "  <td>&nbsp;</td>";
        $output .= "  <td><strong>Torrent Name</strong> &nbsp;(";
        $tmpURI = str_replace(array("?hideSeedless=yes","&hideSeedless=yes","?hideSeedless=no","&hideSeedless=no"),"",$_SERVER["REQUEST_URI"]);
        // Check to see if Question mark is there.
        $tmpURI .= (strpos($tmpURI,'?'))
        	? "&"
        	: "?";
        $output .= ($this->hideSeedless == "yes")
        	? "<a href=\"". $tmpURI . "hideSeedless=no\">Show Seedless</a>"
        	: "<a href=\"". $tmpURI . "hideSeedless=yes\">Hide Seedless</a>";
        $output .= ")</td>";
        $output .= "  <td><strong>Category</strong></td>";
        $output .= "  <td align=center><strong>&nbsp;&nbsp;Size</strong></td>";
        $output .= "  <td><strong>Seeds</strong></td>";
        $output .= "  <td><strong>Peers</strong></td>";
        $output .= "</tr>\n";
        return $output;
    }

}

?>