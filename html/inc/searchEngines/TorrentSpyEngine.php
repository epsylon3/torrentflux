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
    v 1.01  - Changed Main Categories. (removed TV, changed Movies to Video)
    v 1.02  - Mar 19, 06 - Updated pageing.
    v 1.03  - May 16, 06 - They changed there URL for Searching back to their domain.
    v 1.04  - Sep 21, 06 - Fix for Bug found by batmark
    v 1.04a - Nov 18, 07 - Quick fix for 'download.asp?id=' URLs not working anymore.
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "torrentspy.com";
        $this->altURL = "www.torrentspy.com";
        $this->mainTitle = "TorrentSpy";
        $this->engineName = "TorrentSpy";

        $this->author = "kboy";
        $this->version = "1.04-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,874.0.html";
        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {

        $this->mainCatalog["11"] = "Adult";
        $this->mainCatalog["6"] = "Anime";
        $this->mainCatalog["1"] = "Applications";
        $this->mainCatalog["2"] = "Games";
        $this->mainCatalog["13"] = "Handheld";
        $this->mainCatalog["7"] = "Hentai";
        $this->mainCatalog["8"] = "Linux";
        $this->mainCatalog["9"] = "Macintosh";
        $this->mainCatalog["10"] = "Misc";
        $this->mainCatalog["3"] = "Music";
        $this->mainCatalog["14"] = "Non-English";
        $this->mainCatalog["12"] = "Unsorted/Other";
        $this->mainCatalog["4"] = "Videos";

    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
    function getSubCategories($mainGenre)
    {
        $output = array();

        if (strpos($mainGenre,'/'))
        {
            $request = '/directory/' . $mainGenre;
        }
        else
        {
            $request = '/directory.asp?mode=main&id=' . $mainGenre;
        }

        $mainGenreName = $this->GetMainCatName($mainGenre);

        if ($this->makeRequest($request))
        {
            $thing = $this->htmlPage;

            while (is_integer(strpos($thing,"href=\"/directory/")))
            {

                $thing = substr($thing,strpos($thing,"href=\"/directory/")+strlen("href=\"/directory/"));
                $tmpStr = str_replace(array("%2f","+")," ",substr($thing,0,strpos($thing,"\"")));
                $tmpStr = str_replace("%2d","-",$tmpStr);
                $subid = $tmpStr;
                $thing = substr($thing,strpos($thing,">")+strlen(">"));
                $subname = trim(substr($thing,0,strpos($thing,"<")));

                if($subname != $mainGenreName)
                {
                    $output[$subid] = $subname;
                }

            }
       }

        return $output;
    }

    //----------------------------------------------------------------
    // Function to Make the Request (overriding base)
    function makeRequest($request)
    {
        if (strpos($request,"search.asp"))
        {
            return parent::makeRequest($request, true);
        }
        else
        {
            return parent::makeRequest($request, false);
        }
    }

    //----------------------------------------------------------------
    // Function to get Latest..
    function getLatest()
    {
        $request = '/latest.asp';

        // Added mode to support yesterday request.
        if (array_key_exists("mode",$_REQUEST))
        {
            $request .='?mode='.$_REQUEST["mode"];
            if (array_key_exists("id",$_REQUEST))
            {
                $request .= '&id=' . $_REQUEST["id"];
            }
            if (!empty($this->pg))
            {
                $request .= '&pg=' . $this->pg;
            }
        }
        elseif (array_key_exists("subGenre",$_REQUEST))
        {
            if (strpos($_REQUEST["subGenre"],"/")>0)
            {
                $request ='/directory.asp?mode=sub&id=' . substr($_REQUEST["subGenre"],0,strpos($_REQUEST["subGenre"],"/"));
            }
            else
            {
                $request ='/directory.asp?mode=sub&id=' . $_REQUEST["subGenre"];
            }

            if (!empty($this->pg))
            {
                $request .= '&pg=' . $this->pg;
            }
        }
        elseif (!empty($this->pg))
        {
            $request .= '?pg=' . $this->pg;
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
        if (array_key_exists("directory",$_REQUEST))
        {
            $request = "/directory/" . str_replace("%2F","/",urlencode($_REQUEST["directory"]));
            if (!empty($this->pg))
            {
                $request .= "?pg=" . $this->pg;
            }
        }
        elseif (array_key_exists("getMain",$_REQUEST))
        {
            $request = '/directory';
        }
        elseif (array_key_exists("mainGenre",$_REQUEST))
        {
            if (strpos($_REQUEST["mainGenre"],'/'))
            {
                $request .= '/' . $_REQUEST["mainGenre"];
            }
            else
            {
                if (!empty($this->pg))
                {
                    $request = '/directory.asp?mode=main&id=' . $_REQUEST["mainGenre"] . '&pg=' . $this->pg;
                }
                else
                {
                    $request = '/directory.asp?mode=main&id=' . $_REQUEST["mainGenre"];
                }
            }
        }
        elseif (array_key_exists("subGenre",$_REQUEST))
        {
            if (strpos($_REQUEST["subGenre"],'/'))
            {
                $request = "/directory/" . $_REQUEST["subGenre"];
            }
            else
            {
                if ($_REQUEST["subGenre"] != "")
                {
                    if (!empty($this->pg))
                    {
                        $request = '/directory.asp?mode=sub&id=' . $_REQUEST["subGenre"] . '&pg=' . $this->pg;
                    }
                    else
                    {
                        $request = '/directory.asp?mode=sub&id=' . $_REQUEST["subGenre"];
                    }
                }
            }
        }
        else
        {
            $searchTerm = str_replace(" ", "+", $searchTerm);

            if ( $this->pg != '' && array_key_exists("db",$_REQUEST))
            {
                $request = '/search.asp?h=&query=' . $searchTerm . '&pg=' . $this->pg . '&db=' . $_REQUEST["db"] . '&submit.x=24&submit.y=10';
            }
            else
            {
                $request = '/search.asp?h=&query=' . $searchTerm . '&submit.x=24&submit.y=10';
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
    // Function to parse the response.
    function parseResponse()
    {
        $output = $this->tableHeader();

        $thing = $this->htmlPage;

        if (strpos($thing, "experiencing high load") > 0)
        {
            $output .= "<b>We're experiencing high load at this time. Please use the directory until further notice.</b>";
        }else{
            // We got a response so display it.
            // Chop the front end off.
            while (is_integer(strpos($thing,">Health<")))
            {
                $thing = substr($thing,strpos($thing,">Health<"));
                $thing = substr($thing,strpos($thing,"<tr"));
                $tmpList = substr($thing,0,strpos($thing,"</table>"));
                // ok so now we have the listing.
                $tmpListArr = split("</tr>",$tmpList);

                $langFile = $this->cfg['_FILE'];

                $bg = $this->cfg["bgLight"];

                foreach($tmpListArr as $key =>$value)
                {
                    $buildLine = true;
                    if (strpos($value,"/torrent/"))
                    {
                        $ts = new tSpy($value);

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

                            $output .= trim($ts->BuildOutput($bg,$langFile,$this->searchURL()));

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
        }

        $output .= "</table>";

        // is there paging at the bottom?
        if (strpos($thing, "<p class=\"pagenav\">Pages (") !== false)
        {
            // Yes, then lets grab it and display it!  ;)
            $thing = substr($thing,strpos($thing,"<p class=\"pagenav\">Pages (")+strlen("<p class=\"pagenav\">"));
            $pages = substr($thing,0,strpos($thing,"</p>"));
            $page1 = substr($pages,0,strpos($pages,"<img"));
            $page2 = substr($pages,strlen($page1));
            $page2 = substr($page2,strpos($page2,'>')+1);
            $pages = $page1.$page2;

            if (strpos($pages,"directory"))
            {
                $pages = str_replace("?","&",$pages);
                $pages = str_replace("/directory/", $this->searchURL()."&directory=", $pages);
            }
            elseif (strpos($pages, "search.asp?"))
            {
                $pages = str_replace("search.asp?", $this->searchURL()."&", $pages);
            }
            elseif  (strpos($pages, "/search/"))
            {
                $pages = str_replace("&amp;","&",$pages);
                $pages = str_replace("?","&",$pages);
                $pages = str_replace("/search/", $this->searchURL()."&searchterm=", $pages);
            }
            elseif (strpos($pages,"latest.asp?"))
            {
                $pages = str_replace("latest.asp?mode=", $this->searchURL()."&LATEST=1&mode=", $pages);
                if (!array_key_exists("mode",$_REQUEST))
                {
                    $pages .= "<br><a href=\"".$this->searchURL()."&LATEST=1&mode=yesterday\" title=\"Yesterday's Latest\">Yesterday's Latest</a>";
                }
                elseif (! $_REQUEST["mode"] == "yesterday")
                {
                    $pages .= "<br><a href=\"".$this->searchURL()."&LATEST=1&mode=yesterday\" title=\"Yesterday's Latest\">Yesterday's Latest</a>";
                }
                else
                {
                    $pages .= "<br><b>Yesterday's Latest</b>";
                }
            }

            $output .= "<br><div align=center>".$pages."</div><br>";
        }
        elseif(array_key_exists("LATEST",$_REQUEST))
        {
            $pages = '';
            if (!array_key_exists("mode",$_REQUEST))
            {
                $pages .= "<br><a href=\"".$this->searchURL()."&LATEST=1&mode=yesterday\" title=\"Yesterday's Latest\">Yesterday's Latest</a>";
            }
            elseif (! $_REQUEST["mode"] == "yesterday")
            {
                $pages .= "<br><a href=\"".$this->searchURL()."&LATEST=1&mode=yesterday\" title=\"Yesterday's Latest\">Yesterday's Latest</a>";
            }
            else
            {
                $pages .= "<br><b>Yesterday's Latest</b>";
            }

            $output .= "<br><div align=center>".$pages."</div><br>";
        }

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class tSpy
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentStatus = "";
    var $MainId = "";
    var $MainCategory = "";
    var $SubId = "";
    var $SubCategory = "";
    var $torrentSize = "";
    var $fileCount = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    function tSpy( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Chunck up the row into columns.
            $tmpListArr = split("</td>",$htmlLine);

            if(count($tmpListArr) > 5)
            {
                //$tmpListArr["0"];  // Torrent Name, Download Link, Status

                $this->torrentDisplayName = $this->cleanLine($tmpListArr["0"]);  // TorrentName

                $tmpStr = substr($tmpListArr["0"],strpos($tmpListArr["0"],"/torrent/")+strlen("/torrent/")); // Download Link
                $this->torrentFile = "http://www.torrentspy.com/torrent/" . substr($tmpStr,0,strpos($tmpStr,"/")) . "/";

                $tmpStatus = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                $tmpStatus = substr($tmpStatus,0,strpos($tmpStatus,">"));

                if (strpos($tmpStatus,"password"))
                {
                    $this->torrentStatus = "P";
                }elseif (strpos($tmpStatus,"register"))
                {
                    $this->torrentStatus = "R";
                }

                $tmpStr = substr($tmpStr,strpos($tmpStr,">")+strlen(">"));

                if(strpos($tmpStr,"/torrent/") > 0)
                {
                    $tmpStr = substr($tmpStr,strpos($tmpStr,"/torrent/")+strlen("/torrent/"));
                }

                $tmpStr = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                $this->torrentName = substr($tmpStr,0,strpos($tmpStr,"\""));

                $tmpStr = $tmpListArr["1"]; // Categories
                if(strpos($tmpStr,"mode=")){
                    $tmpStr = substr($tmpStr,strpos($tmpStr,"mode=main&id=")+strlen("mode=main&id="));
                    $this->MainId = substr($tmpStr,0,strpos($tmpStr,"\""));
                }else{
                    $tmpStr = substr($tmpStr,strpos($tmpStr,"/directory/")+strlen("/directory/"));
                    $this->MainId = substr($tmpStr,0,strpos($tmpStr,"/"));
                }
                $tmpStr = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                $this->MainCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

                if(strpos($tmpStr,"mode=")){
                    $tmpStr = substr($tmpStr,strpos($tmpStr,"mode=sub&id=")+strlen("mode=sub&id="));
                    $this->SubId = substr($tmpStr,0,strpos($tmpStr,"\""));
                }else{
                    $tmpStr = substr($tmpStr,strpos($tmpStr,"/directory/")+strlen("/directory/"));
                    $this->SubId = substr($tmpStr,0,strpos($tmpStr,"/"));
                }
                $tmpStr = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                $this->SubCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->torrentSize = $this->cleanLine($tmpListArr["2"]);  // Size of File
                $this->fileCount = $this->cleanLine($tmpListArr["3"]);  // File Count
                $this->Seeds = $this->cleanLine($tmpListArr["4"]);  // Seeds
                $this->Peers = $this->cleanLine($tmpListArr["5"]);  // Peers
                //$tmpListArr["6"] = $this->cleanLine($tmpListArr["6"]);  // Health

                if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }
                if ($this->Seeds == '') $this->Seeds = "N/A";

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
    function BuildOutput($bg,$langFILE, $searchURL = '')
    {
        $output = "<tr>\n";
        $output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a>";
        switch ($this->torrentStatus)
        {
            case "R":
                $output .= " <span title='Registration needed'><b>R</b></span>";
                break;
            case "P":
                $output .= " <span title='Password needed'><b>P</b></span>";
                break;
        }
        $output .= "</td>\n";

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
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>