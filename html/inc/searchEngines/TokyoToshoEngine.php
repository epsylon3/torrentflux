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

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "tokyotosho.com";
        $this->altURL = "tokyotosho.info";
        $this->mainTitle = "TokyoTosho";
        $this->engineName = "TokyoTosho";

        $this->author = "nls";
        $this->version = "1.00-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1581.0.html";

        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {
        $this->mainCatalog["0"] =  "(all types)";
        $this->mainCatalog["1"] =  "Anime";
        $this->mainCatalog["10"] = "Non-English Sub";
        $this->mainCatalog["8"] =  "Drama";
        $this->mainCatalog["3"] =  "Manga";
        $this->mainCatalog["2"] =  "Music";
        $this->mainCatalog["9"] =  "Music-Video";
        $this->mainCatalog["7"] =  "Raw";
        $this->mainCatalog["4"] =  "Hentai";
        $this->mainCatalog["5"] =  "Other";
    }

    //----------------------------------------------------------------
    // Function to Make the Request (overriding base)
    function makeRequest($request)
    {
        return parent::makeRequest($request, false);
    }

    //----------------------------------------------------------------
    // Function to get Latest
    function getLatest()
    {
        return $this->performSearch("");
    }

    //----------------------------------------------------------------
    // Function to perform Search.
    function performSearch($searchTerm)
    {
        if ($searchTerm == "") {
            $request = "/";
        } else {
            $searchTerm = str_replace(" ", "+", $searchTerm);
            $request = "/search.php?terms=" . $searchTerm;
        }
        $cat = tfb_getRequestVar("cat");
        if(empty($cat)) $cat = tfb_getRequestVar("mainGenre");

        if(!empty($cat))
        {
            if(strpos($request, "?")) {
                $request .= "&cat=" . $cat;
            } else {
                $request .= "?cat=" . $cat;
            }
        }

        if (!empty($this->pg))
        {
            if(strpos($request, "?")) {
                $request .= "&page=" . $this->pg;
            } else {
                $request .= "?page=" . $this->pg;
            }
        }

        if ($this->makeRequest($request)) {
            return $this->parseResponse();
        } else {
            return $this->msg;
        }
    }

    //----------------------------------------------------------------
    // Function to parse the response.
    function parseResponse()
    {
        $output = $this->tableHeader();
        $s = $this->htmlPage;
        $bg = $this->cfg["bgLight"];

        $s = substr($s, strpos($s, "<table class=\"listing\">"));
        if ($s === false) {
            return "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0><tr><td align=\"center\"><strong><br/>Nothing to display.<br/></strong></td></tr></table>";
        }
        $s = substr($s, strpos($s, "<tr"), strpos($s, "</table>") - strpos($s, "<tr"));
        $tmpListArr = split("<td rowspan=\"2\">", $s);

        foreach($tmpListArr as $line)
        {
            $buildLine = true;

            if (strpos($line, "cat=") !== false) {

                $ts = new ToTo($line);

                // we always have category description, but when searching,
                // there is no category id, so we try to find it...
                if (!is_int($ts->catId))
                    $ts->catId = array_search($ts->catName, $this->mainCatalog);

                if (is_int(array_search($ts->catId, $this->catFilter))) {
                    $buildLine = false;
                }

                if (!empty($ts->torrentFile) && $buildLine) {

                    $output .= trim($ts->BuildOutput($bg));

                    if ($bg == $this->cfg["bgLight"]) {
                        $bg = $this->cfg["bgDark"];
                    } else {
                        $bg = $this->cfg["bgLight"];
                    }
                }
            }
        }

        $output .= "</table>";

        // http://tokyotosho.com/?cat=4&page=2
        // is there paging?

        $s = $this->htmlPage;
        if (strpos($s, "<td class=\"nav\"") !== false) {

            $s = substr($s, strpos($s, "<td class=\"nav\""), 500);
            $cat = 0;
            $output .= "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0><tr><td align=\"center\">";

            if (preg_match("@.*page=([0-9]*)&amp;cat=([0-9]*)\">.*Previous 50.*@U", $s, $parts)) {
              $output .= "<a href=\"" . $this->searchURL() . "&pg=" . $parts[1] . "&cat=" . $parts[2] . "\">&lt;&lt; Previous 50</a> :: ";
            }

            $output .= "<a href=\"" . $this->searchURL() . "\">50 Most Recent</a>";

            if (preg_match("@.*page=([0-9]*)&amp;cat=([0-9]*)\">Next 50 .*@U", $s, $parts)) {
              $output .= " :: <a href=\"" . $this->searchURL() . "&pg=" . $parts[1] . "&cat=" . $parts[2] . "\">Next 50 &gt;&gt;</a>";
            }

            $output .= "</td></tr></table>";
        }

        return $output;
    }

    // Function to setup the table header - overridden for toto (no seeds, no peers)
    function tableHeader()
    {
        $output  = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";
        $output .= "<br>\n";
        $output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
        $output .= "  <td>&nbsp;</td>";
        $output .= "  <td><strong>Torrent Name</strong></td>";
        $output .= "  <td><strong>Category</strong></td>";
//        $output .= "  <td align=center><strong>Date</strong></td>";
        $output .= "  <td align=right><strong>&nbsp;&nbsp;Size</strong></td>";
        $output .= "</tr>\n";

        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class ToTo
{
    var $catId = "";
    var $catName = "";
    var $torrentFile = "";
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentSize = "";
    var $dateAdded = "";
    var $comment = "";

    function ToTo($line) {

        if (strlen($line) > 0) {

            if (0 == preg_match(
                    "@.*<a href=\"\?cat=([^\"]*)\"><img class=\"icon\" alt=\"([^\"]*)\"" .
                    ".*<td class=\"desc-top\"><a rel=\"nofollow\" href=\"([^\"]*)\">([^\"]*)</a></td>" .
                    ".*Size: ([^ ]*) \| Date: ([^\|<]*).*@", $line, $parts))
            {
                // search results
                preg_match(
                    "@.*<a rel=\"nofollow\" href=\".*?cat=([^\"]*)\"><img alt=\"([^\"]*)\"" .
                    ".*<td class=\"desc-top\"><a href=\"([^\"]*)\">([^\"]*)</a></td>" .
                    ".*Size: ([^ ]*) \| Date: ([^\|<]*).*@", $line, $parts);
            }

            $this->catId = trim($parts[1]);
            $this->catName = trim($parts[2]);
            $this->torrentFile = $parts[3];
            $this->torrentName = $parts[4];
            $this->torrentSize = trim($parts[5]);
            $this->dateAdded = trim($parts[6]);
            $this->torrentDisplayName = $this->torrentName;
            if(strlen($this->torrentDisplayName) > 103) {
                $this->torrentDisplayName = substr($this->torrentDisplayName, 0, 100) . "...";
            }

            if (preg_match("@.*Comment: (.*)</td>@U", $line, $parts)) $this->comment = trim($parts[1]);
        }
    }

    function BuildOutput($bg) {
        $output = "<tr>\n";
        $output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";
        $output .= "    <td bgcolor=\"" . $bg . "\">" . $this->catName . "&nbsp;</td>\n";
//        $output .= "    <td bgcolor=\"" . $bg . "\" align=center>" . $this->dateAdded . "</td>\n";
        $output .= "    <td bgcolor=\"" . $bg . "\" align=right>" . $this->torrentSize . "</td>\n";
        $output .= "</tr>\n";
        return $output;
    }
}

?>