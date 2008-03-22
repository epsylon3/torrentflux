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
	v 1.06 - Feb 16, 07 - Update parsing.
    v 1.05 - Oct 18, 06 - Seeds and Peers were off.
    v 1.04 - Oct 16, 06 - fix paging
    v 1.03 - Aug 23, 06 - Added Top 100
    v 1.02 - Jun 29, 06 - fix to paging..
    v 1.01 - Apr 11, 06 - bug in parsing paging.
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "thepiratebay.org";
        $this->altURL = "thepiratebay.org";
        $this->mainTitle = "The PirateBay";
        $this->engineName = "PirateBay";

        $this->author = "kboy";
        $this->version = "1.06-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1125.0.html";

        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {
        $this->mainCatalog["000"] = "Top100";
        $this->mainCatalog["100"] = "Audio";
        $this->mainCatalog["200"] = "Video";
        $this->mainCatalog["300"] = "Applications";
        $this->mainCatalog["400"] = "Games";
        $this->mainCatalog["500"] = "Porn";
        $this->mainCatalog["600"] = "Other";
    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
    function getSubCategories($mainGenre)
    {
        $output = array();

        switch ($mainGenre)
        {
            case "100" :
                $output["101"] = "Music";
                $output["102"] = "Audio books";
                $output["103"] = "Sound clips";
                $output["199"] = "Other";
                break;
            case "200" :
                $output["201"] = "Movies";
                $output["202"] = "Movies DVDR";
                $output["203"] = "Music videos";
                $output["204"] = "Movie clips";
                $output["205"] = "TV shows";
                $output["206"] = "Handheld";
                $output["299"] = "Other";
                break;
            case "300" :
                $output["301"] = "Windows";
                $output["302"] = "Mac";
                $output["303"] = "UNIX";
                $output["304"] = "Handheld";
                $output["399"] = "Other OS";
                break;
            case "400" :
                $output["401"] = "PC";
                $output["402"] = "Mac";
                $output["403"] = "PS2";
                $output["404"] = "XBOX";
                $output["405"] = "Gamecube";
                $output["406"] = "Handheld";
                $output["499"] = "Other";
                break;
            case "500" :
                $output["501"] = "Movies";
                $output["502"] = "Movies DVDR";
                $output["503"] = "Pictures";
                $output["504"] = "Games";
                $output["599"] = "Other";
                break;
            case "600" :
                $output["601"] = "E-books";
                $output["602"] = "Comics";
                $output["603"] = "Pictures";
                $output["604"] = "Covers";
                $output["699"] = "Other";
                break;
        }

        return $output;

    }

    //----------------------------------------------------------------
    // Function to Make the Request (overriding base)
    function makeRequest($request)
    {
        return parent::makeRequest($request, false);
    }

    //----------------------------------------------------------------
    // Function to get Latest..
    function getLatest()
    {
        //recent.php
        //top100.php

        if ($_REQUEST["mainGenre"] == "000")
        {
            $request = "/top/all";
        }
        else
        {
            if (array_key_exists("subGenre",$_REQUEST))
            {
                $request = "/brwsearch.php?b=1&c=".$_REQUEST["subGenre"];
            }
            elseif (array_key_exists("mainGenre",$_REQUEST))
            {
                if ($_REQUEST["mainGenre"] == "000")
                {
                    $request = "/top/all";
                } else {
                    $request = "/brwsearch.php?b=1&c=0&d=".$_REQUEST["mainGenre"];
                }
            }
            else
            {
                $request = "/recent.php?orderby=se";
            }
            if (!empty($this->pg))
            {
                $request .= "&page=" . $this->pg;
            }
        }

        if ($this->makeRequest($request))
        {
          return $this->parseResponse();
        }
        else
        {
           return $this->msg;
        }

    }

    //----------------------------------------------------------------
    // Function to perform Search.
    function performSearch($searchTerm)
    {
        if (array_key_exists("subGenre",$_REQUEST))
        {
            $request = "/brwsearch.php?b=1&c=".$_REQUEST["subGenre"];
        }
        elseif (array_key_exists("mainGenre",$_REQUEST))
        {
           $request = "/brwsearch.php?b=1&c=0&d=".$_REQUEST["mainGenre"];
        }
        else
        {
            $request = "/search.php?orderby=".$searchTerm;
        }

        if(strlen($searchTerm) > 0)
        {
            $searchTerm = str_replace(" ", "+", $searchTerm);
            $request .= "&q=".$searchTerm;
        }

        $request .= "&audio=&video=&apps=&games=&porn=&other=&what=search&orderby=";

        if (!empty($this->pg))
        {
            $request .= "&page=" . $this->pg;
        }

        if ($this->makeRequest($request))
        {
            return $this->parseResponse();
        }
        else
        {
            return $this->msg;
        }
    }

    //----------------------------------------------------------------
    // Override the base to show custom table header.
    // Function to setup the table header
    function tableHeader()
    {
        $output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";

        $output .= "<br>\n";
        $output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
        $output .= "  <td>&nbsp;</td>";
        $output .= "  <td><strong>Torrent Name</strong> &nbsp;(";

        $tmpURI = str_replace(array("?hideSeedless=yes","&hideSeedless=yes","?hideSeedless=no","&hideSeedless=no"),"",$_SERVER["REQUEST_URI"]);

        // Check to see if Question mark is there.
        if (strpos($tmpURI,'?'))
        {
            $tmpURI .= "&";
        }
        else
        {
            $tmpURI .= "?";
        }

        if($this->hideSeedless == "yes")
        {
            $output .= "<a href=\"". $tmpURI . "hideSeedless=no\">Show Seedless</a>";
        }
        else
        {
            $output .= "<a href=\"". $tmpURI . "hideSeedless=yes\">Hide Seedless</a>";
        }

        $output .= ")</td>";
        $output .= "  <td><strong>Category</strong></td>";
        $output .= "  <td align=center><strong>&nbsp;&nbsp;Size</strong></td>";
        $output .= "  <td><strong>Date Added</strong></td>";
        $output .= "  <td><strong>Seeds</strong></td>";
        $output .= "  <td><strong>Peers</strong></td>";
        $output .= "</tr>\n";

        return $output;
    }

    //----------------------------------------------------------------
    // Function to parse the response.
    function parseResponse()
    {
        $output = $this->tableHeader();

        $thing = $this->htmlPage;

        // We got a response so display it.
        // Chop the front end off.

		if (is_integer(strpos($thing,"Your search did not match any torrents")))
		{
			$this->msg = "Your search did not match any torrents";

		} else {

			while (is_integer(strpos($thing,"searchResult")))
			{
				$thing = substr($thing,strpos($thing,"searchResult"));
				$thing = substr($thing,strpos($thing,"</thead>"));
				$thing = substr($thing,strpos($thing,"<tr>"));

				$tmpList = substr($thing,0,strpos($thing,"</table>"));

				// ok so now we have the listing.
				$tmpListArr = split("</tr>",$tmpList);

				$bg = $this->cfg["bgLight"];

				foreach($tmpListArr as $key =>$value)
				{
					$buildLine = true;
					if (strpos($value,"static.thepiratebay.org"))
					{

						$ts = new pBay($value);

						// Determine if we should build this output
						if (is_int(array_search($ts->CatName,$this->catFilter)))
						{
							$buildLine = false;
						}

						if ($this->hideSeedless == "yes")
						{
							if($ts->Seeds == "N/A" || $ts->Seeds == "0")
							{
								$buildLine = false;
							}
						}

						if (!empty($ts->torrentFile) && $buildLine) {

							$output .= trim($ts->BuildOutput($bg, $this->searchURL()));

							// ok switch colors.
							if ($bg == $this->cfg["bgLight"])
							{
								$bg = $this->cfg["bgDark"];
							}
							else
							{
								$bg = $this->cfg["bgLight"];
							}
						}

					} elseif (strpos($value,"torrents.thepiratebay.org")) {
						$ts = new pBay($value);

						// Determine if we should build this output
						if (is_int(array_search($ts->CatName,$this->catFilter)))
						{
							$buildLine = false;
						}

						if ($this->hideSeedless == "yes")
						{
							if($ts->Seeds == "N/A" || $ts->Seeds == "0")
							{
								$buildLine = false;
							}
						}

						if (!empty($ts->torrentFile) && $buildLine) {

							$output .= trim($ts->BuildOutput($bg, $this->searchURL()));

							// ok switch colors.
							if ($bg == $this->cfg["bgLight"])
							{
								$bg = $this->cfg["bgDark"];
							}
							else
							{
								$bg = $this->cfg["bgLight"];
							}
						}
					}

				}
			}

			$output .= "</table>";

			// is there paging at the bottom?
			if (strpos($thing, "&page=") != false)
			{
				// Yes, then lets grab it and display it!  ;)
				$thing = substr($thing,strpos($thing,"<tr><td colspan")+strlen("<tr><td colspan"));
				$thing = substr($thing,strpos($thing,">")+1);
				$pages = substr($thing,0,strpos($thing,"</td>"));
				if (strpos($pages,"prev") > 0)
				{
					$tmpStr = substr($pages,0,strpos($pages,"<img"));

					$pages = substr($pages,strpos($pages,"<img"));
					$pages = substr($pages,strpos($pages,">")+1);
					$pages = $tmpStr."Prev".$pages;

					if (strpos($pages,"next") > 0)
					{
						$pages = substr($pages,0,strpos($pages,"<img"))."Next</a>";
					}
				}
				elseif (strpos($pages,"next") > 0)
				{
					$pages = substr($pages,0,strpos($pages,"<img"))."Next</a>";
				}

				if(strpos($this->curRequest,"LATEST"))
				{
					$pages = str_replace("?",$this->searchURL()."&LATEST=1&",$pages);
					$pages = str_replace("/recent.php","",$pages);
				}
				else
				{
					$pages = str_replace("?",$this->searchURL()."&",$pages);
					$pages = str_replace("/search.php",'',$pages);
				}

				$pages = str_replace("page=","pg=",$pages);
				$pages = str_replace("d=","cat=",$pages);
				$pages = str_replace("c=","subGenre=",$pages);
				$pages = str_replace("q=","searchterm=", $pages);
				$pages = str_replace("orderby=","",$pages);

				$pages = str_replace("&&","&",$pages);

				$pages = str_replace("/brwsearch.php","",$pages);

				$output .= "<div align=center>".$pages."</div>";
			}

	    }
        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class pBay
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentSize = "";
    var $torrentStatus = "";
    var $CatName = "";
    var $CatId = "";
    var $MainId = "";
    var $MainCategory = "";
    var $SubId = "";
    var $SubCategory = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    var $dateAdded = "";
    var $dwnldCount = "";

    function pBay( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Chunck up the row into columns.
            $tmpListArr = split("</td>",$htmlLine);

            if(count($tmpListArr) > 6)
            {
                // Cat Id
                $tmpStr = substr($tmpListArr["0"],strpos($tmpListArr["0"],"c=")+2);
                $this->CatId = substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->CatName = $this->cleanLine($tmpListArr["0"]);  // Cat Name

                if (strpos($this->CatName,">"))
                {
                    $this->MainCategory = trim(substr($this->CatName,0,strpos($this->CatName,">")));
                    $this->MainId = substr($this->CatId,0,1) . "00";
                    $this->SubCategory = trim(substr($this->CatName,strpos($this->CatName,">")+1));
                    $this->SubId = $this->CatId;
                }
                else
                {
                    $this->MainCategory = $this->CatName;
                    $this->MainId = $this->CatId;
                }

                $this->torrentName = $this->cleanLine($tmpListArr["1"]);  // TorrentName

                $this->dateAdded = $this->cleanLine($tmpListArr["2"]);  // Date Added

                $tmpStr = "";
                $tmpStr = substr($tmpListArr["3"],strpos($tmpListArr["3"],"href=\"")+strlen("href=\"")); // Download Link
                $this->torrentFile = substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->torrentSize = $this->cleanLine($tmpListArr["4"]);  // Size of File

                $this->Seeds = $this->cleanLine($tmpListArr["5"]);  // Seeds
                $this->Peers = $this->cleanLine($tmpListArr["6"]);  // Peers


                if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }
                if ($this->Seeds == '') $this->Seeds = "N/A";

                $this->torrentDisplayName = $this->torrentName;
                if(strlen($this->torrentDisplayName) > 50)
                {
                    $this->torrentDisplayName = substr($this->torrentDisplayName,0,50)."...";
                }

           }
        }

    }

    function cleanLine($stringIn,$tags='')
    {
        if(empty($tags))
            return trim(str_replace(array("&nbsp;","&nbsp")," ",strip_tags($stringIn)));
        else
            return trim(str_replace(array("&nbsp;","&nbsp")," ",strip_tags($stringIn,$tags)));
    }

    //----------------------------------------------------------------
    // Function to build output for the table.
    function BuildOutput($bg, $searchURL)
    {
        $output = "<tr>\n";
        $output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";

        if (strlen($this->MainCategory) > 1){
            if (strlen($this->SubCategory) > 1){
                $mainGenre = "<a href=\"".$searchURL."&mainGenre=".$this->MainId."\">".$this->MainCategory."</a>";
                $subGenre = "<a href=\"".$searchURL."&subGenre=".$this->SubId."\">".$this->SubCategory."</a>";
                $genre = $mainGenre."-".$subGenre;
            }else{
                $genre = "<a href=\"".$searchURL."&mainGenre=".$this->MainId."\">".$this->MainCategory."</a>";
            }
        }else{
            $genre = "<a href=\"".$searchURL."&subGenre=".$this->SubId."\">".$this->SubCategory."</a>";
        }

        $output .= "    <td bgcolor=\"".$bg."\">". $genre ."</td>\n";

        $output .= "    <td bgcolor=\"".$bg."\" align=right>".$this->torrentSize."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->dateAdded."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>
