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
    v 1.02 - Oct 18, 06. fix to parsing thx kalep
    v 1.01 - Sep 22, 06. Added Stats and Fixed filenames
    v 1.00 - Sep 22, 06.
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "Filemp3.org";
        $this->altURL = "www.Filemp3.org";
        $this->mainTitle = "Filemp3";
        $this->engineName = "Filemp3";

        $this->author = "kboy";
        $this->version = "1.02-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1967.0.html";

        $this->Initialize($cfg);

    }


    //----------------------------------------------------------------
    // Function to Get Main Categories
    function populateMainCategories()
    {
        $this->mainCatalog["0"]  = "(all types)";
        $this->mainCatalog["1"] = " Alternative";
        $this->mainCatalog["2"] = " Dance";
        $this->mainCatalog["3"] = " Hip-Hop";
        $this->mainCatalog["4"] = " Metal";
        $this->mainCatalog["5"] = " Pop";
        $this->mainCatalog["6"] = " Punk";
        $this->mainCatalog["7"] = " Rap";
        $this->mainCatalog["8"] = " R 'n' B";
        $this->mainCatalog["9"] = " Rock";
        $this->mainCatalog["10"] = "Trance";
        $this->mainCatalog["11"] = "Other";
        $this->mainCatalog["12"] = "Music Vids";
        $this->mainCatalog["13"] = "Old Music";
        $this->mainCatalog["14"] = "Country";
        $this->mainCatalog["15"] = "House";
        $this->mainCatalog["16"] = "Reggae";
        $this->mainCatalog["17"] = "Electronic";
        $this->mainCatalog["18"] = "Techno";
        $this->mainCatalog["19"] = "Drum 'n' Bass";
        $this->mainCatalog["20"] = "OST";
        $this->mainCatalog["21"] = "Psychedelic";
        $this->mainCatalog["22"] = "Hardcore";
        $this->mainCatalog["23"] = "Jazz";
        $this->mainCatalog["24"] = "Indie";
        $this->mainCatalog["25"] = "Funk";
        $this->mainCatalog["26"] = "Lo Fi";
        $this->mainCatalog["27"] = "Ambient";
        $this->mainCatalog["28"] = "Instrumental";
        $this->mainCatalog["29"] = "Blues";
    }

    //----------------------------------------------------------------
    // Function to get Latest..
    function getLatest()
    {

        $cat = tfb_getRequestVar('mainGenre');

        if (empty($cat)) $cat = tfb_getRequestVar('cat');

        $request = "/browse.php";

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

        if ($this->makeRequest($request,true))
        {
            if (strlen($this->htmlPage) > 0 )
            {
              return $this->parseResponse();
            }
            else
            {
              return 'Unable to Browse at this time.';
            }
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

        // create the request string.
        $searchTerm = str_replace(" ", "+", $searchTerm);
        $request = "/browse.php?search=".$searchTerm;

        if(!empty($cat))
        {
            $request .= "&cat=".$cat;
        }

        $incldead = tfb_getRequestVar('incldead');
        if (empty($incldead)) $incldead = "0";
        $request .= "&incldead=".$incldead;

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
        $tmpStr = $this->htmlPage;

        if (strpos($tmpStr,">Wait</") > 0)
        {
            $needWait = true;
        }
        else
        {
            $needWait = false;
        }

        $output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";

        $output .= "<br>\n";

        $output .= "<tr bgcolor=\"".$this->cfg["bgLight"]."\">";
        if ($needWait)
        {
            $output .= "  <td colspan=9 align=center>";
        }
        else
        {
            $output .= "  <td colspan=8 align=center>";
        }

        $tmpStr = substr($tmpStr,strpos($tmpStr,"userdetails"));
        $tmpStr = substr($tmpStr,strpos($tmpStr,"<font"));
        $output .= "<font size=5px> Current Stats : ".strip_tags(substr("<td>".$tmpStr,0,strpos($tmpStr,"</td>")))."</font>";
        $output .= "</td>";

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
        if ($needWait)
        {
            $output .= "  <td><strong>Wait</strong></td>";
        }
        $output .= "  <td><strong>File Life</strong></td>";
        $output .= "  <td><strong>Snatched</strong></td>";
        $output .= "</tr>\n";

        return $output;
    }


    //----------------------------------------------------------------
    // Function to parse the response.
    function parseResponse($latest = true)
    {
        $output = $this->tableHeader();

        $thing = $this->htmlPage;

        if(strpos($thing,"Error:") > 0)
        {
            $tmpStr = substr($thing,strpos($thing,"Error:")+strlen("Error:"));
            $tmpStr = substr($tmpStr,0,strpos($tmpStr,"</p>"));
            $this->msg = strip_tags($tmpStr);
            return $output . "<center>".$this->msg."</center><br>";
        }

        // We got a response so display it.
        // Chop the front end off.
        $thing = substr($thing,strpos($thing,">Hits<"));

        $thing = substr($thing,strpos($thing,"</tr>")+strlen("</tr>"));
        $tmpList = substr($thing,0,strpos($thing,"</table>"));
        // ok so now we have the listing.
        $tmpListArr = split("</tr>",$tmpList);

        $bg = $this->cfg["bgLight"];

        foreach($tmpListArr as $key =>$value)
        {
            //echo $value;
            $buildLine = true;
            if (strpos($value,"id="))
            {
                $ts = new fileMP3($value, $this->mainURL);

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

                    $output .= trim($ts->BuildOutput($bg,$this->searchURL()));

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

        $output .= "</table>";

        // is there paging at the bottom?
        if (strpos($thing, "page=") != false)
        {
            // Yes, then lets grab it and display it!  ;)

            $pages = substr($thing,strpos($thing,"<p"));
            $pages = substr($pages,strpos($pages,">"));
            $pages = substr($pages,0,strpos($pages,"</p>"));

            $pages = str_replace("&nbsp; ",'',$pages);

            $tmpPageArr = split("</a>",$pages);
            array_pop($tmpPageArr);

            $pagesout = '';
            foreach($tmpPageArr as $key => $value)
            {
                $value .= "</a> &nbsp;";
                //$tmpVal = substr($value,strpos($value,"browse.php?"));
                $tmpVal = substr($value,strpos($value,"browse.php?"),strpos($value,"\">")-2);

                $pgNum = substr($tmpVal,strpos($tmpVal,"page=")+strlen("page="));
                $pagesout .= str_replace($tmpVal,"XXXURLXXX".$pgNum,$value);
            }

            $pagesout = str_replace("se.php?page=","",$pagesout);

            $cat = tfb_getRequestVar('mainGenre');

            if (empty($cat)) $cat = tfb_getRequestVar('cat');

            if(strpos($this->curRequest,"LATEST"))
            {
                if (!empty($cat))
                {
                    $pages = str_replace("XXXURLXXX",$this->searchURL()."&LATEST=1&cat=".$cat."&pg=",$pagesout);
                }
                else
                {
                    $pages = str_replace("XXXURLXXX",$this->searchURL()."&LATEST=1&pg=",$pagesout);
                }
            }
            else
            {
                if(!empty($cat))
                {
                    $pages = str_replace("XXXURLXXX",$this->searchURL()."&searchterm=".$_REQUEST["searchterm"]."&cat=".$cat."&pg=",$pagesout);

                }
                else
                {
                    $pages = str_replace("XXXURLXXX",$this->searchURL()."&searchterm=".$_REQUEST["searchterm"]."&pg=",$pagesout);
                }
            }

            $output .= "<div align=center>".substr($pages,1)."</div>";

        }

        return $output;
    }
}


// This is a worker class that takes in a row in a table and parses it.
class fileMP3
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentSize = "";
    var $torrentStatus = "";
    var $MainId = "";
    var $MainCategory = "";
    var $fileLife = "";
    var $Seeds = "";
    var $Peers = "";

    var $needsWait = false;
    var $waitTime = "";

    var $Data = "";

    var $torrentRating = "";

    function fileMP3( $htmlLine, $dwnURL )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Cleanup any bugs in the HTML
            $htmlLine = eregi_replace("</td>\n</td>",'</td>',$htmlLine);

            // Chunck up the row into columns.
            $tmpListArr = split("<td ",$htmlLine);

            if(count($tmpListArr) > 9)
            {

                $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"alt=\"")+strlen("alt=\"")); // MainCategory
                $this->MainCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

                $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"cat=")+strlen("cat=")); // Main Id
                $this->MainId = trim(substr($tmpStr,0,strpos($tmpStr,"\"")));

                $this->torrentName = $this->cleanLine("<td ".$tmpListArr["2"]."</td>");  // TorrentName

                $tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"id=")+strlen("id=")); // File Id
                $tmpStr = substr($tmpStr,0,strpos($tmpStr,"&"));

                $this->torrentFile = "http://".$dwnURL."/download.php/".$tmpStr."/";
                if($this->MainId == "9")
                {
                    $this->torrentFile .= $this->torrentName.".torrent";
                }
                else
                {
                    $this->torrentFile .= str_replace(" ","_",$this->torrentName).".torrent";
                }

                $this->needsWait = true;
                $this->waitTime = $this->cleanLine("<td ".$tmpListArr["3"]."</td>");  // Wait Time

                $this->fileLife = $this->cleanLine("<td ".$tmpListArr["5"]."</td>");  // File Life
                $this->fileLife = str_replace("hours","hrs",$this->fileLife);

                $this->torrentSize = $this->cleanLine("<td ".$tmpListArr["6"]."</td>");  // Size of File

                $this->torrentStatus = $this->cleanLine(str_replace("<br>"," ","<td ".$tmpListArr["7"]."</td>"));  // Snatched

                $this->Seeds = $this->cleanLine("<td ".$tmpListArr["8"]."</td>");  // Seeds
                $this->Peers = $this->cleanLine("<td ".$tmpListArr["9"]."</td>");  // Leech

                if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }
                if ($this->Seeds == '') $this->Seeds = "N/A";

           } else {

                $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"alt=\"")+strlen("alt=\"")); // MainCategory
                $this->MainCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

                $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"cat=")+strlen("cat=")); // Main Id
                $this->MainId = trim(substr($tmpStr,0,strpos($tmpStr,"\"")));

                $this->torrentName = $this->cleanLine("<td ".$tmpListArr["2"]."</td>");  // TorrentName

                $tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"id=")+strlen("id=")); // File Id
                $tmpStr = substr($tmpStr,0,strpos($tmpStr,"&"));

                $this->torrentFile = "http://".$dwnURL."/download.php/".$tmpStr."/";
                if($this->MainId == "9")
                {
                    $this->torrentFile .= $this->torrentName.".torrent";
                }
                else
                {
                    $this->torrentFile .= str_replace(" ","_",$this->torrentName).".torrent";
                }

                $this->needsWait = false;

                $this->fileLife = $this->cleanLine("<td ".$tmpListArr["4"]."</td>");  // File Life
                $this->fileLife = str_replace("hours","hrs",$this->fileLife);

                $this->torrentSize = $this->cleanLine("<td ".$tmpListArr["5"]."</td>");  // Size of File

                $this->torrentStatus = $this->cleanLine(str_replace("<br>"," ","<td ".$tmpListArr["6"]."</td>"));  // Snatched

                $this->Seeds = $this->cleanLine("<td ".$tmpListArr["7"]."</td>");  // Seeds
                $this->Peers = $this->cleanLine("<td ".$tmpListArr["8"]."</td>");  // Leech

                if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }
                if ($this->Seeds == '') $this->Seeds = "N/A";

           }

            $this->torrentDisplayName = $this->torrentName;
            if(strpos($this->torrentName,"\n"))
            {
                $this->torrentDisplayName = substr($this->torrentName,0,strpos($this->torrentName,"\n"));
                $this->torrentName = str_replace("\n"," [",$this->torrentName)."]";
            }
            if(strlen($this->torrentDisplayName) > 50)
            {
                $this->torrentDisplayName = substr($this->torrentDisplayName,0,50)."...";
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
        if ($this->needsWait)
        {
            $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->waitTime."</td>\n";
        }
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->fileLife."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->torrentStatus."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>