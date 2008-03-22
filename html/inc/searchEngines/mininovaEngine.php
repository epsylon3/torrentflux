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
        $this->mainURL = "www.mininova.org";
        $this->altURL = "www.mininova.org";
        $this->mainTitle = "mininova";
        $this->engineName = "mininova";

        $this->author = "sloan";
        $this->version = "1.01-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1501.0.html";

        $this->Initialize($cfg);

    }
    function populateMainCategories()
    {
        $this->mainCatalog[0] = "(all types)";
        $this->mainCatalog[1] = "Anime";
        $this->mainCatalog[2] = "Books";
        $this->mainCatalog[3] = "Games";
        $this->mainCatalog[4] = "Movies";
                $this->mainCatalog[5] = "Music";
        $this->mainCatalog[6] = "Other";
        $this->mainCatalog[7] = "Pictures";
        $this->mainCatalog[8] = "Software";
                $this->mainCatalog[9] = "TV Shows";
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
        // This is what mininova is looking for in a request.
        // http://www.mininova.org/search/?search=test

        // create the request string.
        $searchTerm = str_replace(" ", "+", $searchTerm);
        $request = "/search/$searchTerm/seeds";

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

		$thing = substr($thing,strpos($thing,">Leechers<"));
		$thing = substr($thing,strpos($thing,"<tr"));
		$tmpList = substr($thing,0,strpos($thing,"</table>"));

		// ok so now we have the listing.
		$tmpListArr = split("</tr>",$tmpList);

		$bg = $this->cfg["bgLight"];

		foreach($tmpListArr as $key =>$value)
		{

			//echo $value;
			$buildLine = true;
			if (strpos($value,'<a href="/get'))
			{
				$ts = new mininova($value,$latest);

				// Determine if we should build this output
				if (is_int(array_search($ts->CatName,$this->catFilter)))
				{
					$buildLine = false;
				}
print_r("<!--");
print_r($this->catFilter);
print_r($ts->CatName);
print_r("-->");
				if ($this->hideSeedless == "yes")
				{
					if($ts->Seeds == "---")
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


        $output .= "</table>";



        return $output;
    }
}

// This is a worker class that takes in a row in a table and parses it.
class mininova
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

    function mininova( $htmlLine , $latest = true)
    {

        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;

            // Chunck up the row into columns.
            $tmpListArr = split("</td>",$htmlLine);
            array_pop($tmpListArr);
//print_r($tmpListArr);
            //Age   Type    Torrent Names   MB  S   L
            if(count($tmpListArr) > 4)
            {
                if ($latest)
                {
                    // Latest Request //

                    $this->CatName = $this->cleanLine($tmpListArr["1"]); // Type
                    $tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"/tor/")+5);
                    $this->torrentFile = "http://www.mininova.org/get/".substr($tmpStr,0,strpos($tmpStr,'">'));
                    $this->torrentName = $this->cleanLine($tmpListArr["2"]); // Name

                    $this->torrentSize = $this->cleanLine($tmpListArr["3"]); // MB
                    $this->Seeds = $this->cleanLine($tmpListArr["4"]); // Seeds
                    $this->Peers = $this->cleanLine($tmpListArr["5"]); // Leechers

                }
                else
                {
                    // Search Request //


                    $this->CatName = $this->cleanLine($tmpListArr["1"]); // Type
                    $tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"/tor/")+5);
                    $this->torrentFile = "http://www.mininova.org/get/".substr($tmpStr,0,strpos($tmpStr,'">'));
                    $this->torrentName = $this->cleanLine( substr($tmpListArr[2], strpos($tmpListArr["2"], "<a href=\"/tor")) ); // Name
                    $this->torrentSize = $this->cleanLine($tmpListArr["3"]); // MB
                    $this->Seeds = $this->cleanLine($tmpListArr["4"]); // Seeds
                    $this->Peers = $this->cleanLine($tmpListArr["5"]); // Leechers

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
