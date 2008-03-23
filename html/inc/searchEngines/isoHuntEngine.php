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
	v 1.05 - Oct 07, 06 - updated parseing
    v 1.04 - Sep 20, 06 - updated by batmark
    v 1.03 - Aug 23, 06 - fix ISOHunt fixed search results to display externals.
    v 1.02 - Aug 23, 06 - fix ISOHunt changed there site alittle.
    v 1.01 - Jun 30, 06 - fix to Search..
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "isohunt.com";
        $this->altURL = "isohunt.com";
        $this->mainTitle = "isoHunt";
        $this->engineName = "isoHunt";

        $this->author = "kboy";
        $this->version = "1.05-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,878.0.html";

        $this->Initialize($cfg);

    }

    //----------------------------------------------------------------
    // Function to Make the Request (overriding base)
    function makeRequest($request)
    {
        return parent::makeRequest($request, true);
    }

    //----------------------------------------------------------------
    // Function to get Latest..
    function getLatest()
    {
        $request = "/latest.php?mode=bt";

        if (!empty($this->pg))
        {
            $request .= "&pg=" . $this->pg;
        }

        if ($this->makeRequest($request))
        {
          return $this->parseResponse(true);
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
        // This is what isohunt is looking for in a request.
        // http://isohunt.com/torrents.php?ihq=test&ext=&op=and

        // create the request string.
        $searchTerm = str_replace(" ", "+", $searchTerm);
        $request = "/torrents?ihq=".$searchTerm;
        //$request .= "&ext=&op=and";
        if (!empty($this->pg))
        {
            $request .= "&ihs1=18&iho1=d&iht=-1&ihp=" . $this->pg;
        }

        // make the request if successful call the parse routine.
        if ($this->makeRequest($request))
        {
            return $this->parseResponse(false);
        }
        else
        {
            return $this->msg;
        }

    }

    //----------------------------------------------------------------
    // Function to parse the response.
    function parseResponse($latest = true)
    {
        $output = $this->tableHeader();

        $thing = $this->htmlPage;

        // Strip out those Nasty Iframes.
        $thing = eregi_replace("<table[[:space:]]width=[[:punct:]]100%[[:punct:]][[:space:]]cellspacing=[[:punct:]]0[[:punct:]][[:space:]]cellpadding=[[:punct:]]0[[:punct:]][[:space:]]border=[[:punct:]]0[[:punct:]]><tr><td[[:space:]]width=[[:punct:]]10[[:punct:]]></td><td[[:space:]]style=[[:punct:]]border[[:punct:]]3px[[:space:]]solid[[:space:]]#003366[[:punct:]]><iframe[[:space:]]frameborder=[[:punct:]]0[[:punct:]][[:space:]]width=[[:punct:]]100%[[:punct:]][[:space:]]id=[[:punct:]]([a-zA-Z0-9])*[[:punct:]]></iframe></td></tr></table>",'',$thing);

        // We got a response so display it.
        // Chop the front end off.
        if ($latest)
        {
            $start = strpos($thing, "New torrents on isoHunt");
        } else {
            $start = strpos($thing, "isoHunt Rank");
        }

        $thing = substr($thing, $start, strlen($thing) - $start);

        if ($latest)
        {
            $end = strrpos($thing, "adclick");
        } else {
            $end = strrpos($thing, "»");
        }

        $tmpList = $thing;
        //echo $tmpList;

        if (strpos($tmpList,"/download/") || strpos($tmpList,"torrent_details"))
            {
                // ok so now we have the listing.
                $tmpListArr = split("</tr>",$tmpList);

                array_pop($tmpListArr);
                $bg = $this->cfg["bgLight"];


                foreach($tmpListArr as $key =>$value)
                {
                    //echo $value;
                    $buildLine = true;
                    if (strpos($value,"/download/") || strpos($value,"torrent_details"))
                    {
                        $ts = new isoHunt($value,$latest);

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
            if (!is_integer(strpos($thing,"name=ihLogin")))
            {
                $thing = "";
            }
        }

        $output .= "</table>";

        // is there paging at the bottom?
        if (strpos($this->htmlPage, "<table class='pager'>") != false)
        {
            // Yes, then lets grab it and display it!  ;)

            $thing = substr($this->htmlPage,strpos($this->htmlPage,"<table class='pager'>")+strlen("<table class='pager'>"));
            $pages = substr($thing,0,strpos($thing,"</table>"));

            $pages = str_replace("&nbsp; ",'',strip_tags($pages,"<a><b>"));

            $tmpPageArr = split("</a>",$pages);
            array_pop($tmpPageArr);

            $pagesout = '';
            foreach($tmpPageArr as $key => $value)
            {
                $value .= "</a> &nbsp;";
                $tmpVal = substr($value,strpos($value,"/torrents/"),strpos($value,"\>")-1);
                $pgNum = substr($tmpVal,strpos($tmpVal,"ihp=")+strlen("ihp="));
                $pagesout .= str_replace($tmpVal,"XXXURLXXX".$pgNum,$value);
            }
            if(strpos($this->curRequest,"LATEST"))
            {
                $pages = str_replace("XXXURLXXX",$this->searchURL()."&LATEST=1&pg=",$pagesout);
            }
            else
            {
                $pages = str_replace("XXXURLXXX",$this->searchURL()."&searchterm=".$_REQUEST["searchterm"]."&pg=",$pagesout);
            }
            $pages = strip_tags($pages,"<a><b>");
            $output .= "<div align=center>".$pages."</div>";
        }

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class isoHunt
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

    function isoHunt( $htmlLine , $latest = true)
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Fix messed up end td's once in a while.
            $htmlLine = eregi_replace("<(.)*1ff8(.)*/td>",'</td>',$htmlLine);

            // Chunck up the row into columns.
            $tmpListArr = split("</td>",$htmlLine);

            array_pop($tmpListArr);//echo '<br><br>|a|0='.$tmpListArr[0].'<br>| '.'|1='.$tmpListArr[1].'<br>| '.'|2='.$tmpListArr[2].'<br>| '.'|3='.$tmpListArr[3].'<br>| '.'|4='.$tmpListArr[4].'<br>| '.'|5='.$tmpListArr[5].'<br>| ';

            //Age   Type    Torrent Names   MB  F   S   L   D
            if(count($tmpListArr) > 5)
            {
                if ($latest)
                {
                    // Latest Request //
                    if(strpos($tmpListArr["3"],"[DL]"))
                    {

                        //$tmpListArr["1"] = $this->cleanLine($tmpListArr["1"]);  // Age
                        $this->CatName = $this->cleanLine($tmpListArr["2"]); // Type

                        $tmpStr = $tmpListArr["3"];  // TorrentName and Download Link
                        // move to the [DL] area. and remove [REL] line
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"[DL]")+strlen("[DL]"), strpos($tmpStr,"[REL]"));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"href=\"")+strlen("href=\"")); // Download Link
                        $this->torrentFile = "http://isohunt.com".substr($tmpStr,0,strpos($tmpStr,"\""));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                        $tmpStr = substr($tmpStr,0,strpos($tmpStr,"\""));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,'\''));
                        $this->torrentName = str_replace("'",'',$tmpStr);

                        $this->torrentSize = $this->cleanLine($tmpListArr["4"]); // MB
                        //$this->fileCount = $this->cleanLine($tmpListArr["5"]); // Files
                        $this->Seeds = $this->cleanLine($tmpListArr["6"]); // Seeds
                        $this->Peers = $this->cleanLine($tmpListArr["7"]); // Peers / Leechers
                        $this->dwnldCount = $this->cleanLine($tmpListArr["8"]); // Download Count
                    }
                    else
                    {
                        $this->CatName = $this->cleanLine($tmpListArr["1"]); // Type

                        $tmpStr = $tmpListArr["2"];  // TorrentName and Download Link
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"_details/")+9);
                        $this->torrentFile = "http://isohunt.com/download/".substr($tmpStr,0,strpos($tmpStr,"'"));

                        $this->torrentName = substr($this->cleanLine($tmpListArr["2"]),1,strlen($tmpListArr["2"]));

                        $this->torrentSize = $this->cleanLine($tmpListArr["3"]); // MB
                        $this->fileCount = $this->cleanLine($tmpListArr["4"]); // Files
                        $this->Seeds = $this->cleanLine($tmpListArr["5"]); // Seeds
                        $this->Peers = $this->cleanLine($tmpListArr["6"]); // Peers / Leechers
                        $this->dwnldCount = $this->cleanLine($tmpListArr["7"]); // Download Count
                    }
                }
                else
                {
                    // Search Request //

                    if(strpos($tmpListArr["2"],"[DL]"))
                    {
                        $this->CatName = $this->cleanLine($tmpListArr["0"]); // Type

                        //$tmpListArr["1"] = $this->cleanLine($tmpListArr["1"]);  // Age

                        $tmpStr = $tmpListArr["2"];  // TorrentName and Download Link
                        // move to the [DL] area. and remove [REL] line
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"[DL]")+strlen("[DL]"), strpos($tmpStr,"[REL]"));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"href=\"")+strlen("href=\"")); // Download Link
                        $this->torrentFile = "http://isohunt.com".substr($tmpStr,0,strpos($tmpStr,"\""));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"title=\"")+strlen("title=\""));
                        $tmpStr = substr($tmpStr,0,strpos($tmpStr,"\""));
                        $tmpStr = substr($tmpStr,strpos($tmpStr,'\''));
                        $this->torrentName = str_replace(array("'","Download .torrent here: ","Download torrent: "),'',$tmpStr);

                    }
                    else
                    {

                        $tmpStr = $tmpListArr["2"];  // Download ID and Type
                        $this->CatName = $this->cleanLine($tmpListArr["0"]); // Download ID and Type
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"torrent_details/")+strlen("torrent_details/"));

                        $this->torrentFile = "http://isohunt.com/download/".substr($tmpStr,0,strpos($tmpStr,"?tab"));

                        //$tmpListArr["1"] = $this->cleanLine($tmpListArr["1"]);  // Age
                        $tmpStr = $tmpListArr["2"];
                        $tmpStr = substr($tmpStr,strpos($tmpStr,"a id=link")-1,strlen($tmpStr));
                        $this->torrentName = substr($this->cleanLine($tmpStr),0,strlen($tmpStr));

                    }

                    $this->torrentSize = $this->cleanLine($tmpListArr["3"]); // MB
                    $this->Seeds = $this->cleanLine($tmpListArr["4"]); // Seeds
                    $this->Peers = $this->cleanLine($tmpListArr["5"]); // Peers / Leechers

                    $this->torrentDisplayName = $this->torrentName;

                }

                if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }

                if ($this->Seeds == '') $this->Seeds = "N/A";

                $this->torrentDisplayName = str_replace(".torrent",'',$this->torrentName);
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

    function dumpArray($arrIn)
    {
        foreach($arrIn as $key => $value)
        {
            echo "\nkey(".$key.")"."value(".$value.")";
        }
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
