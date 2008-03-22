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
include_once("GoogleFunctions.php");
include_once("inc/classes/BDecode.php");

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "google.com";
        $this->altURL = "www.google.com";
        $this->mainTitle = "Google";
        $this->engineName = "Google";

        $this->author = "kboy";
        $this->version = "1.00-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1690.0.html";
        $this->Initialize($cfg);
    }

    //----------------------------------------------------------------
    // Function to get Latest..
    function getLatest()
    {
        $this->msg = "Get Latest Not available on this Engine";
        return $this->msg;
    }

    //----------------------------------------------------------------
    // Function to perform Search.
    function performSearch($searchTerm)
    {
        //http://www.google.com/search?as_q=test+d8&num=3&as_qdr=m3&as_filetype=torrent
        // as_q -> advanced search query term  (NOTE: we must add +d8 to scrub down to just true torrent files)
        // num -> is the number of results per page.
        // lr -> language (lang_en) for english
        // as_qdr -> advanced search query days returned. (m3) is updated in the past 1 months
        //    m3 ... and y (year) are valid values.
        // as_filetype -> to search for torrents :-)


        $searchTerm = str_replace(" ", "+", $searchTerm);

        $request = '/search?as_q=' . $searchTerm . '+d8&num=100&lr=lang_en&as_qdr=m3&as_filetype=torrent';

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

        $output .= "\n<!-- Begin Response --> \n";

        $thing = $this->htmlPage;

        // We got a response so display it.
        // Chop the front end off.
        while (is_integer(strpos($thing,"Results")))
        {

            $thing = substr($thing,strpos($thing,"Results"));
            $thing = substr($thing,strpos($thing,"</table>")+strlen("</table>"));
            if (is_integer(strpos($thing,"Sponsored Links"))) {
                $thing = substr($thing,strpos($thing,"Sponsored Links"));
                $thing = substr($thing,strpos($thing,"</table>")+strlen("</table>"));
            }

            //$thing = substr($thing,strpos($thing,"<div>")-strlen("<div>");
            $tmpList = substr($thing,0,strpos($thing,"</div>"));
            if (is_integer(strpos($tmpList,"In order to show you the most relevant results"))) {
                $tmpList = substr($thing,0,strpos($thing,"In order to show you the most relevant results")) . "</div>";
            }

            // ok so now we have the listing.
            //$tmpListArr = split("</p>",$tmpList);

            $allowedTags = '<a><b><i><br>';

            // ok so now we have the listing.
            $tmpListArr = split("</a>",strip_tags($tmpList,$allowedTags));

            $langFile = $this->cfg['_FILE'];

            $bg = $this->cfg["bgLight"];

            foreach($tmpListArr as $key =>$value)
            {
                if (strpos($value,"Similar&nbsp;pages"))
                {
                }
                elseif (strpos($value, "ile Format:"))
                {
                }
                elseif (strpos($value, "Cached"))
                {
                }
                elseif (strpos($value, "More results from"))
                {
                }
                elseif (strpos($value, "Translate this page"))
                {
                }
                else
                {

                    $goo = new gOOGLE($value);

                    if (!empty($goo->torrentFile)) {

                        $output .= trim($goo->BuildOutput($bg,$langFile,$this->searchURL()));

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

        $output .= "\n<!-- End Response --> \n";
        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class gOOGLE
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentStatus = "";
    var $torrentSize = "";
    var $fileCount = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    function gOOGLE( $htmlLine )
    {
        $tmpVal = substr($htmlLine,strpos($htmlLine,"<a"));
        $tmpVal = trim($tmpVal);
        if (strlen($tmpVal) > 0)
        {

            $this->Data = $htmlLine;

            $tmpVal2 = substr($tmpVal,strpos($tmpVal,"href=\"")+strlen("href=\""));
            $this->torrentFile = substr($tmpVal2,0,strpos($tmpVal2,"\""));

            $html = FetchHTMLNoWaitNoFollow( $this->torrentFile );

            // Make sure we have a torrent file
            if( strpos( $html, "d8:" ) === false )
            {
                // We don't have a Torrent File... it is something else
                $this->torrentFile = "";
            }
            else
            {

                $array = BDecode($html);
                $this->torrentSize = formatBytesTokBMBGBTB($array["info"]["piece length"] * (strlen($array["info"]["pieces"]) / 20));
                $this->torrentName = $array['info']['name'];
                $this->fileCount = count($array['info']['files']);
                $this->torrentDisplayName = $array['info']['name'];

                if(array_key_exists('comment',$array))
                {
                    $this->torrentDisplayName .= " [". $array['comment']."]";
                }
            }

/*
            $this->Seeds = $this->cleanLine($tmpListArr["4"]);  // Seeds
            $this->Peers = $this->cleanLine($tmpListArr["5"]);  // Peers
*/
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
        $output .= "    <td bgcolor=\"".$bg."\">&nbsp;</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=right>".$this->torrentSize."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>