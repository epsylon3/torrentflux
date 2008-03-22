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
    v 1.04 - Oct 18, 06 - fix paging
    v 1.03 - updated by batmark
    v 1.02 - update to add torrentbox.com to download param.
    v 1.01 - TorrentBox changed the download.php to dl.php.
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "torrentbox.com";
        $this->altURL = "torrentbox.com";
        $this->mainTitle = "TorrentBox";
        $this->engineName = "TorrentBox";

        $this->author = "kboy";
        $this->version = "1.04-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,876.0.html";

        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {
        $this->mainCatalog["0"] = "(all types)";
        $this->mainCatalog["9"] = "Anime";
        $this->mainCatalog["90"] = "Apps";
        $this->mainCatalog["11"] = "Books";
        $this->mainCatalog["10"] = "Comics";
        $this->mainCatalog["91"] = "Games";
        $this->mainCatalog["6"] = "Misc";
        $this->mainCatalog["92"] = "Movies";
        $this->mainCatalog["93"] = "Music";
        $this->mainCatalog["8"] = "Pics";
        $this->mainCatalog["3"] = "TV";
        $this->mainCatalog["13"] = "Videos";
    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
    function getSubCategories($mainGenre)
    {
        $output = array();

        switch ($mainGenre)
        {
            case "90" :
                $output["51"] = "Linux";
                $output["52"] = "Mac";
                $output["50"] = "Windows";
                $output["5"] = "Misc";
                break;
            case "91" :
                $output["4"] = "Misc";
                $output["41"] = "PC";
                $output["42"] = "PS2";
                $output["43"] = "PSX";
                $output["44"] = "ROMS";
                $output["40"] = "Xbox";
                break;
            case "92" :
                $output["14"] = "DVD-R";
                $output["100"] = "Action";
                $output["101"] = "Adventure";
                $output["102"] = "Animation";
                $output["103"] = "Comedy";
                $output["104"] = "Drama";
                $output["105"] = "Documentary";
                $output["106"] = "Horror";
                $output["107"] = "Sci-Fi";
                $output["1"] = "Misc";
                break;
            case "93" :
                $output["15"] = "Alternative";
                $output["16"] = "Blues";
                $output["17"] = "Electronic";
                $output["18"] = "Pop";
                $output["19"] = "Rap";
                $output["20"] = "Rock";
                $output["21"] = "Reggae";
                $output["22"] = "Jazz";
                $output["23"] = "Dance";
                $output["24"] = "Christian";
                $output["25"] = "Spanish";
                $output["2"] = "Misc";
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
        $cat = tfb_getRequestVar('subGenre');
        if (empty($cat)) $cat = tfb_getRequestVar('cat');

        $request = "/torrents-browse.php";

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

        // We got a response so display it.
        // Chop the front end off.
        while (is_integer(strpos($thing,">Uploader<")))
        {
            $thing = substr($thing,strpos($thing,">Uploader<"));
            $thing = substr($thing,strpos($thing,"<tr"));
            $tmpList = substr($thing,0,strpos($thing,"</table>"));

            // ok so now we have the listing.
            $tmpListArr = split("</tr>",$tmpList);

            $bg = $this->cfg["bgLight"];

           foreach($tmpListArr as $key =>$value)
            {
                $buildLine = true;
                if (strpos($value,".torrent"))
                {
                    $ts = new tBox($value);

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

                        $output .= trim($ts->BuildOutput($bg));

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

        //http://www.torrentbox.com/torrents-browse.php?cat=4&page=2
        // is there paging at the bottom?
        if (strpos($thing, "<span class=\"pager\">") != false)
        {
            // Yes, then lets grab it and display it!  ;)
            $thing = substr($thing,strpos($thing,"<span class=\"pager\">")+strlen("<span class=\"pager\">"));
            $pages = substr($thing,0,strpos($thing,"</span>"));

            $pages = str_replace("http://www.torrentbox.com","",$pages);
            $pages = str_replace("page=","pg=",$pages);
            $pages = str_replace("search=","searchterm=",$pages);

            if(strpos($this->curRequest,"LATEST"))
            {
                $pages = str_replace("/torrents-browse.php?",$this->searchURL()."&LATEST=1&",$pages);
            }
            else
            {
                $pages = str_replace("/torrents-browse.php?",$this->searchURL()."&",$pages);
            }
            $output .= "<div align=center>".$pages."</div>";
        }

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class tBox
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentSize = "";
    var $torrentStatus = "";
    var $CatId = "";
    var $CatName = "";
    var $fileCount = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    var $dateAdded = "";
    var $dwnldCount = "";

    function tBox( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Fix messed up end td's once in a while.
            $htmlLine = eregi_replace("<(.)*1ff8(.)*/td>",'</td>',$htmlLine);
            $htmlLine = eregi_replace("1ff8",'',$htmlLine);

            // Chunck up the row into columns.
            $tmpListArr = split("</td>",$htmlLine);

            if(count($tmpListArr) > 8)
            {
                $this->CatName = $this->cleanLine($tmpListArr["0"]);  // Cat Name
                if (strpos($this->CatName,">"))
                {
                    $this->CatName = trim(substr($this->CatName,strpos($this->CatName,">")+1));
                }
                $this->torrentName = $this->cleanLine($tmpListArr["1"]);  // TorrentName

        $tmpStr = $tmpListArr["2"];
                $start = strpos($tmpStr, "href");
        $tmpStr = substr($tmpStr,$start + 6, strlen($tmpStr)-$start-6);
        $end = strpos($tmpStr, ".torrent");
        $tmpStr = substr($tmpStr, 0, $end + 8); //the DL link
        $this->torrentFile = "http://www.torrentbox.com".$tmpStr;

                $this->dateAdded = $this->cleanLine($tmpListArr["4"]);  // Date Added
                $this->torrentSize = $this->cleanLine($tmpListArr["5"]);  // Size of File
                $this->dwnldCount = $this->cleanLine($tmpListArr["6"]);  // Download Count
                $this->Seeds = $this->cleanLine($tmpListArr["7"]);  // Seeds
                $this->Peers = $this->cleanLine($tmpListArr["8"]);  // Peers
                //$tmpListArr["9"] = $this->cleanLine($tmpListArr["9"]);  // Person who Uploaded it.

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
    function BuildOutput($bg)
    {
        $output = "<tr>\n";
        $output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";
        $output .= "    <td bgcolor=\"".$bg."\">". $this->CatName ."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=right>".$this->torrentSize."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>