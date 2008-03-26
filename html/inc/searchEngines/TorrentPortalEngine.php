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
    v 1.01 - change in parsing routine
    v 1.02 - Mar 19, 06 - another change in the parsing. and updated paging
    v 1.03 - Updated to check for a count on the array
*/

class SearchEngine extends SearchEngineBase
{
    function SearchEngine($cfg)
    {
        $this->mainURL = "torrentportal.com";
        $this->altURL = "tp.searching.com";
        $this->mainTitle = "TorrentPortal";
        $this->engineName = "TorrentPortal";

        $this->author = "kboy";
        $this->version = "1.03-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php?topic=875.0.html";

        $this->Initialize($cfg);
    }

    //----------------------------------------------------------------
    // Function to Get Main Categories
    function populateMainCategories()
    {
        $this->mainCatalog["0"] = "(all types)";
        $this->mainCatalog["1"] = "Games";
        $this->mainCatalog["2"] = "Movies";
        $this->mainCatalog["3"] = "TV";
        $this->mainCatalog["4"] = "Videos";
        $this->mainCatalog["5"] = "Apps";
        $this->mainCatalog["6"] = "Anime";
        $this->mainCatalog["7"] = "Audio";
        $this->mainCatalog["8"] = "Comics";
        $this->mainCatalog["9"] = "Unsorted";
        $this->mainCatalog["10"] = "Porn";
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
        $cat = tfb_getRequestVar('subGenre');
        $count = tfb_getRequestVar('count');

        if (empty($cat)) $cat = tfb_getRequestVar('cat');

        if(empty($cat) && empty($this->pg))
        {
            $request = "/new-torrents.php";
        }
        else
        {
            $request = "/torrents.php";
        }

        if(!empty($cat))
        {
            if(strpos($request,"?"))
            {
                $request .= "&cat=".$cat;
            }
            else
            {
                $request .= "?cat=".$cat;
            }
        }

        if(!empty($count))
        {
            if(strpos($request,"?"))
            {
                $request .= "&count=".$count;
            }
            else
            {
                $request .= "?count=".$count;
            }
        }

        if (!empty($this->pg))
        {
            if(strpos($request,"?"))
            {
                $request .= "&page=" . $this->pg;
            }
            else
            {
                $request .= "?page=" . $this->pg;
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

        $searchTerm = str_replace(" ", "+", $searchTerm);
        $request = "/torrents-search.php?search=".$searchTerm;

        if(!empty($cat))
        {
            $request .= "&cat=".$cat;
        }

        $count = tfb_getRequestVar('count');
        if(!empty($count))
        {
            $request .= "&count=".$count;
        }

        $onlyname = tfb_getRequestVar('onlyname');
        if (empty($onlyname)) $onlyname = "no";
        $request .= "&onlyname=".$onlyname;

        $incldead = tfb_getRequestVar('incldead');
        if (empty($incldead)) $incldead = "0";
        $request .= "&incldead=".$incldead;


        $request .= "&submit=";

        if (!empty($this->pg))
        {
            $request .= "&page=" . $this->pg;
        }

        if ($this->makeRequest($request,true))
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
        $output .= "  <td><strong>Seeds</strong></td>";
        $output .= "  <td><strong>Peers</strong></td>";
        $output .= "  <td><strong>Health</strong></td>";
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
        while (is_integer(strpos($thing,">Health")))
        {
            $thing = substr($thing,strpos($thing,">Health"));
            $thing = substr($thing,strpos($thing,"<tr"));
            $tmpList = substr($thing,0,strpos($thing,"</table>"));

            // ok so now we have the listing.
            $tmpListArr = split("<tr>",$tmpList);

            $bg = $this->cfg["bgLight"];

            foreach($tmpListArr as $key =>$value)
            {
                $buildLine = true;
                if (strpos($value,"/download/"))
                {
                    $ts = new tPort($value);

                    // Determine if we should build this output
                    if (is_int(array_search($ts->MainCategory,$this->catFilter)))
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
            // set thing to end of this table.
            $thing = substr($thing,strpos($thing,"</table>"));
        }

        $output .= "</table>";

        // is there paging at the bottom?
        /*
        <p align="center"><b>1&nbsp;-&nbsp;25</b> | <a href="?search=test&amp;count=87&amp;page=1"><b>26&nbsp;-&nbsp;50</b></a> | <a href="?search=test&amp;count=87&amp;page=2"><b>51&nbsp;-&nbsp;75</b></a> | <a href="?search=test&amp;count=87&amp;page=3"><b>76&nbsp;-&nbsp;87</b></a><br /><b>&lt;&lt;&nbsp;Prev</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="?search=test&amp;count=87&amp;page=1"><b>Next&nbsp;&gt;&gt;</b></a></p>
        */
        if (strpos($thing, "page=") != false)
        {
            // Yes, then lets grab it and display it!  ;)
            $thing = substr($thing,strpos($thing,"<p align=\"center\">")+strlen("<p align=\"center\">"));
            $pages = substr($thing,0,strpos($thing,"</p>"));
            //$output .= $pages;

            if(strpos($this->curRequest,"LATEST"))
            {
                if(strpos($pages,"cat="))
                {
                    $pages = str_replace("page=","pg=",str_replace("?",$this->searchURL()."&LATEST=1&",$pages));
                }
                else
                {
                    $pages = str_replace("?page=",$this->searchURL()."&LATEST=1&pg=",$pages);
                }
            }
            else
            {
               if(strpos($pages,"\"?"))
               {
                   $pages = str_replace("?",$this->searchURL()."&",$pages);
               }

               if(strpos($pages,"?search="))
               {
                   $pages = str_replace("?search=",$this->searchURL()."&searchterm=",$pages);
               }
               if(strpos($pages,"search="))
               {
                   $pages = str_replace("search=","searchterm=",$pages);
               }
            }

           if(strpos($pages,"torrents.php?"))
           {
               $pages = str_replace("torrents.php?",$this->searchURL()."&",$pages);
           }

           if(strpos($pages,"torrents-search.php?"))
           {
               $pages = str_replace("torrents-search.php?",$this->searchURL()."&",$pages);
           }

            $pages = str_replace("page=","pg=",$pages);

            $output .= "<div align=center>".$pages."</div>";
        }

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class tPort
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentSize = "";
    var $torrentStatus = "";
    var $MainId = "";
    var $MainCategory = "";
    var $fileCount = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    var $torrentRating = "";

    function tPort( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Cleanup any bugs in the HTML
            $htmlLine = eregi_replace("</td>\n</td>",'</td>',$htmlLine);

            // Chunck up the row into columns.
            $tmpListArr = split("<td ",$htmlLine);

            if(count($tmpListArr) > 8)
            {
                $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"href=\"")+strlen("href=\"")); // Download Link
                $this->torrentFile = "http://www.torrentportal.com".substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->MainCategory = $this->cleanLine("<td ".$tmpListArr["3"]."</td>");  // MainCategory

                $tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"cat=")+strlen("cat=")); // Main Id
                $this->MainId = substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->torrentName = str_replace('[+]','',$this->cleanLine("<td ".$tmpListArr["4"]."</td>"));  // TorrentName
                $this->torrentRating = $this->cleanLine("<td ".$tmpListArr["5"]."</td>");  // Rating

                $this->torrentSize = $this->cleanLine("<td ".$tmpListArr["6"]."</td>");  // Size of File
                $this->Seeds = str_replace('[U]','',$this->cleanLine("<td ".$tmpListArr["7"]."</td>"));  // Seeds
                $this->Peers = $this->cleanLine("<td ".$tmpListArr["8"]."</td>");  // Leech

                $tmpStr = substr($tmpListArr["9"],strpos($tmpListArr["9"],"Health ")+strlen("Health "));  // Health
                $tmpStr = substr($tmpStr,0,strpos($tmpStr,"\""));
                $tmpArr = split("/",$tmpStr);
                if (count($tmpArr) > 1 && $tmpArr["1"] > 0 )
                {
                    $this->torrentStatus = ($tmpArr["0"] / $tmpArr["1"]) * 100 . "%";
                }
                else
                {
                    $this->torrentStatus = "0%";
                }

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
    function BuildOutput($bg, $searchURL = '')
    {
        $output = "<tr>\n";
		$output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";

        if (strlen($this->MainCategory) > 1){
            $genre = "<a href=\"".$searchURL."&mainGenre=".$this->MainId."\">".$this->MainCategory."</a>";
        }else{
            $genre = "";
        }

        $output .= "    <td bgcolor=\"".$bg."\">". $genre ."</td>\n";

        $output .= "    <td bgcolor=\"".$bg."\" align=right>".$this->torrentSize."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->torrentStatus."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>
