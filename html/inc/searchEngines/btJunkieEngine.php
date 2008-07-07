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
    v 1.01b - Jul 05 2008 - fix for search results
    v 1.01 - Oct 06, 06 fix to search results.
    v 1.00 - Aug 23, 06
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "btjunkie.org";
        $this->altURL = "www.btjunkie.org";
        $this->mainTitle = "btjunkie";
        $this->engineName = "btJunkie";

        $this->author = "kboy";
        $this->version = "1.01b-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,874.0.html";
        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {

        $this->mainCatalog["1"] = "Audio";
        $this->mainCatalog["7"] = "Anime";
        $this->mainCatalog["2"] = "Games";
        $this->mainCatalog["3"] = "Software";
        $this->mainCatalog["4"] = "TV";
        $this->mainCatalog["5"] = "Unsorted";
        $this->mainCatalog["6"] = "Video";
        $this->mainCatalog["8"] = "XXX";

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
        $cat = tfb_getRequestVar('mainGenre');
        if (empty($cat)) $cat = tfb_getRequestVar('c');

        if(!empty($cat))
        {
            $request = "/browse";
            if(strpos($request,"?"))
            {
                $request .= "&c=".$cat;
            }
            else
            {
                $request .= "?c=".$cat;
            }
        }
        else
        {
            $request = "/?do=latest";
        }

        if (!empty($this->pg))
        {
            if(strpos($request,"?"))
            {
                $request .= "&p=" . $this->pg;
            }
            else
            {
                $request .= "?p=" . $this->pg;
            }
        }

        $request .= "&o=72";  // Sort Newest to Oldest
        //$request .= "&o=52";  // Sort Most Seeded

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
        $request = "/search?q=".$searchTerm;

        if(!empty($cat))
        {
            $request .= "&c=".$cat;
        }

        if (!empty($this->pg))
        {
            $request .= "&p=" . $this->pg;
        }

        //$request .= "&o=72";  // Sort Newest to Oldest
        $request .= "&o=52";  // Sort Most Seeded

        $request .= "&m=0"; // Search Exact
        //$request .= "&m=1"; // Search Contains

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

        // Chop the front end off.
        while (is_integer(strpos($thing,">Torrent Name<")))
        {
            $thing = substr($thing,strpos($thing,">Health<"));
            $thing = substr($thing,strpos($thing,"<tr"));
            if (is_integer(strpos($thing,"do=copyrights")))
            {
                $tmpList = substr($thing,0,strpos($thing,"do=copyrights"));
            }
            else
            {
                $tmpList = $thing; //$tmpList = substr($thing,0,strpos($thing,"JavaScript"));
            }
            // ok so now we have the listing.
            $tmpListArr = split("</tr>",$tmpList);

            $langFile = $this->cfg["_FILE"];

            $bg = $this->cfg["bgLight"];

            foreach($tmpListArr as $key =>$value)
            {
                $buildLine = true;
                if (strpos($value,"/download.torrent"))//if (strpos($value,"/torrent?do"))
                {
                    $ts = new btJunk($value);

                    // Determine if we should build this output
                    if (is_int(array_search($ts->MainId,$this->catFilter)))
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
            $thing = substr($thing,strpos($thing,"do=copyrights"));
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

            $output .= "<br><div align=center>".$pages."</div><br>";
        }

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class btJunk
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $MainId = "";
    var $MainCategory = "";
    var $torrentSize = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    function btJunk( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Chunck up the row into columns.
            $tmpListArr = split("</th>",$htmlLine);

/*
(
    [0] => <tr bgcolor="#FFFFFF"><th width="60%" align="left">
        <a href="/torrent?do=download&id=3780687290ec6d04d99417b85ef14bca45f030c90781"><img src="/images/down.gif" alt="Download Torrent" border="0"></a>
        <a href="/?do=listfiles&id=3780687290ec6d04d99417b85ef14bca45f030c90781" onclick="return listfiles(this,750,50,'2px solid',0,0,'img3780687290ec6d04d99417b85ef14bca45f030c90781');">
        <img name="img3780687290ec6d04d99417b85ef14bca45f030c90781" src="/images/expand.gif" alt="File Listing" border="0"></a>&nbsp;
        <a href="/torrent?do=stat&id=3780687290ec6d04d99417b85ef14bca45f030c90781" class="BlckUnd"><b>Paris Hilton - Paris (withcovers) a DHZ Inc Release</b></a>
)

*/

            if(count($tmpListArr) > 5)
            {
                //$tmpListArr["0"];  // Torrent Name, Download Link, Status

                $this->torrentDisplayName = $this->cleanLine($tmpListArr["0"]);  // TorrentName

                $tmpStr = substr($tmpListArr[0],strpos($tmpListArr[0],"/torrent/"),strpos($tmpListArr[0],"><img")-17);//$tmpStr = substr($tmpListArr["0"],strpos($tmpListArr["0"],"torrent?"),strpos($tmpListArr["0"],"><img")-6);

                $this->torrentFile = "http://dl.btjunkie.org" . substr($tmpStr,0,strpos($tmpStr,"\""));

                if (strpos($this->torrentFile,"do=stat"))
                {
                    $this->torrentFile = str_replace("do=stat","do=download",$this->torrentFile);
                }
                $this->torrentName = $this->torrentDisplayName;

                $this->MainCategory = $this->cleanLine($tmpListArr["3"]);
                $this->MainId = substr($tmpStr,0,strpos($tmpStr,"\""));

                $this->torrentSize = $this->cleanLine($tmpListArr["4"]);  // Size of File

                $this->Seeds = $this->cleanLine($tmpListArr["5"]);  // Seeds
                $this->Peers = $this->cleanLine($tmpListArr["6"]);  // Peers

                if ($this->Seeds == '') $this->Seeds = "N/A";
                if ($this->Seeds == 'X') $this->Seeds = "N/A";

                if ($this->Peers == '') $this->Peers = "N/A";
                if ($this->Peers == 'X') $this->Peers = "N/A";


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
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";
        $output .= "</td>\n";

        if (strlen($this->MainCategory) > 1){
            $genre = "<a href=\"".$searchURL."&mainGenre=".$this->MainId."\">".$this->MainCategory."</a>";
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