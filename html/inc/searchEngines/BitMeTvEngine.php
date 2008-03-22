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
        $this->mainURL = "www.bitmetv.org";
        $this->altURL = "www.bitmetv.org";
        $this->mainTitle = "BitMeTv";
        $this->engineName = "BitMeTv";

        $this->author = "AnglaChel";
        $this->version = "1.0-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1582.0.html";

        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {
        $this->mainCatalog["211"] = "70s Shows";
        $this->mainCatalog["110"] = "80s Shows";
        $this->mainCatalog["86"] = "Anime";
        $this->mainCatalog["199"] = "Billiards-Snooker-Pool";
        $this->mainCatalog["116"] = "British- UK Comedy";
        $this->mainCatalog["231"] = "British - UK Drama";
        $this->mainCatalog["215"] = "British Mystery";
        $this->mainCatalog["90"] = "Cartoons";
        $this->mainCatalog["101"] = "Documentaries";
        $this->mainCatalog["195"] = "Fantasy-Supernatural";
        $this->mainCatalog["225"] = "News";
        $this->mainCatalog["238"] = "OZTV";
        $this->mainCatalog["134"] = "Poker";
        $this->mainCatalog["99"] = "PSP TV Episodes";
        $this->mainCatalog["102"] = "Reality TV - Competitive";
        $this->mainCatalog["196"] = "Reality TV - Un-Scripted";
        $this->mainCatalog["95"] = "Sci Fi";
        $this->mainCatalog["87"] = "Stand-UP Comedy";
        $this->mainCatalog["210"] = "Subtitles";
        $this->mainCatalog["197"] = "Talk Shows";
        $this->mainCatalog["228"] = "Trailers";
        $this->mainCatalog["209"] = "Wrestling";
        $this->mainCatalog["70"] = "Other TV Episodes";
        $this->mainCatalog["1"] = "24";
        $this->mainCatalog["84"] = "3rd Rock from the Sun";
        $this->mainCatalog["104"] = "The 4400";
        $this->mainCatalog["151"] = "7 Days";
        $this->mainCatalog["212"] = "According to Jim";
        $this->mainCatalog["5"] = "Alias";
        $this->mainCatalog["187"] = "Ally McBeal";
        $this->mainCatalog["108"] = "The Amazing Race";
        $this->mainCatalog["152"] = "American Chopper";
        $this->mainCatalog["121"] = "American Dad";
        $this->mainCatalog["214"] = "American Idol";
        $this->mainCatalog["6"] = "Andromeda";
        $this->mainCatalog["9"] = "Angel";
        $this->mainCatalog["132"] = "Arrested Development";
        $this->mainCatalog["85"] = "Babylon 5";
        $this->mainCatalog["12"] = "Battlestar Galactica";
        $this->mainCatalog["230"] = "Battlestar Galactica Classic";
        $this->mainCatalog["198"] = "Bernie Mac";
        $this->mainCatalog["81"] = "Biggest Loser";
        $this->mainCatalog["137"] = "Birds of Prey";
        $this->mainCatalog["130"] = "Black Books";
        $this->mainCatalog["188"] = "Blakes 7";
        $this->mainCatalog["153"] = "Bones";
        $this->mainCatalog["128"] = "Boston Legal";
        $this->mainCatalog["23"] = "Buffy";
        $this->mainCatalog["154"] = "Carnivale";
        $this->mainCatalog["155"] = "Chappelles Show";
        $this->mainCatalog["103"] = "Charlie Jade";
        $this->mainCatalog["22"] = "Charmed";
        $this->mainCatalog["216"] = "The Chasers War on Everything";
        $this->mainCatalog["156"] = "Colbert Report";
        $this->mainCatalog["139"] = "Cold Case";
        $this->mainCatalog["138"] = "Commander In Chief";
        $this->mainCatalog["157"] = "Conan O Brien";
        $this->mainCatalog["217"] = "Conviction";
        $this->mainCatalog["218"] = "Cops";
        $this->mainCatalog["158"] = "Corner Gas";
        $this->mainCatalog["200"] = "Criminal Minds";
        $this->mainCatalog["201"] = "Crossing Jordan";
        $this->mainCatalog["19"] = "CSI";
        $this->mainCatalog["232"] = "CSI Miami";
        $this->mainCatalog["233"] = "CSI NY";
        $this->mainCatalog["131"] = "Curb your Enthusiasim";
        $this->mainCatalog["109"] = "Da ALi G Show";
        $this->mainCatalog["71"] = "Dark Angel";
        $this->mainCatalog["159"] = "Dark Skies";
        $this->mainCatalog["202"] = "Dawsons Creek";
        $this->mainCatalog["24"] = "Dead Like Me";
        $this->mainCatalog["160"] = "Dead Zone";
        $this->mainCatalog["105"] = "Deadwood";
        $this->mainCatalog["75"] = "Desperate Housewives";
        $this->mainCatalog["88"] = "Dr Who";
        $this->mainCatalog["219"] = "Dr Who Classics";
        $this->mainCatalog["113"] = "Dragon Ball";
        $this->mainCatalog["135"] = "E-ring";
        $this->mainCatalog["26"] = "E.R.";
        $this->mainCatalog["161"] = "Earth 2";
        $this->mainCatalog["107"] = "Entourage";
        $this->mainCatalog["27"] = "Everwood";
        $this->mainCatalog["144"] = "Everybody Hates Chris";
        $this->mainCatalog["28"] = "Family Guy";
        $this->mainCatalog["29"] = "Farscape";
        $this->mainCatalog["162"] = "Firefly";
        $this->mainCatalog["163"] = "First Wave";
        $this->mainCatalog["220"] = "Four Kings";
        $this->mainCatalog["164"] = "Frasier";
        $this->mainCatalog["93"] = "Free Ride";
        $this->mainCatalog["83"] = "Fresh Prince";
        $this->mainCatalog["30"] = "Friends";
        $this->mainCatalog["234"] = "Full House";
        $this->mainCatalog["77"] = "Futurama";
        $this->mainCatalog["189"] = "Ghost Whisper";
        $this->mainCatalog["31"] = "Gilmore Girls";
        $this->mainCatalog["127"] = "Greys Anatomy";
        $this->mainCatalog["221"] = "Heist";
        $this->mainCatalog["96"] = "Hells Kitchen";
        $this->mainCatalog["165"] = "Hex";
        $this->mainCatalog["46"] = "Hogans Heroes";
        $this->mainCatalog["92"] = "House";
        $this->mainCatalog["190"] = "How I Met Your Mother";
        $this->mainCatalog["222"] = "How its Made";
        $this->mainCatalog["3"] = "Howard Stern";
        $this->mainCatalog["235"] = "In Justice";
        $this->mainCatalog["32"] = "In Living Color";
        $this->mainCatalog["36"] = "Invasion";
        $this->mainCatalog["39"] = "Invisible Man";
        $this->mainCatalog["33"] = "Iron Chef";
        $this->mainCatalog["147"] = "JAG";
        $this->mainCatalog["40"] = "Jake 2.0";
        $this->mainCatalog["72"] = "Joey";
        $this->mainCatalog["69"] = "Killer Instinct";
        $this->mainCatalog["35"] = "King of Queens";
        $this->mainCatalog["34"] = "King of the Hill";
        $this->mainCatalog["117"] = "Las Vegas";
        $this->mainCatalog["37"] = "Law and Order";
        $this->mainCatalog["223"] = "Law and Order CI";
        $this->mainCatalog["224"] = "Law and Order SVU";
        $this->mainCatalog["243"] = "Law and Order TBJ";
        $this->mainCatalog["194"] = "Little House on the Prairie";
        $this->mainCatalog["203"] = "Living with Fran";
        $this->mainCatalog["76"] = "Lost";
        $this->mainCatalog["38"] = "MacGyver";
        $this->mainCatalog["82"] = "Malcolm in the Middle";
        $this->mainCatalog["100"] = "Married with Children";
        $this->mainCatalog["236"] = "Mayo";
        $this->mainCatalog["142"] = "Medium";
        $this->mainCatalog["20"] = "Millenium";
        $this->mainCatalog["21"] = "Mind of Mencia";
        $this->mainCatalog["191"] = "Modern Marvels";
        $this->mainCatalog["204"] = "Monk";
        $this->mainCatalog["122"] = "My Name Is Earl";
        $this->mainCatalog["106"] = "Mythbusters";
        $this->mainCatalog["140"] = "NCIS";
        $this->mainCatalog["146"] = "Night Stalker";
        $this->mainCatalog["119"] = "NipTuck";
        $this->mainCatalog["80"] = "Numb3rs";
        $this->mainCatalog["237"] = "NYPD Blue";
        $this->mainCatalog["118"] = "One Tree Hill";
        $this->mainCatalog["41"] = "Outer Limits";
        $this->mainCatalog["112"] = "Over There";
        $this->mainCatalog["42"] = "OZ";
        $this->mainCatalog["25"] = "Penn and Teller Bullshit";
        $this->mainCatalog["239"] = "Perfect Strangers";
        $this->mainCatalog["43"] = "Pimp My Ride";
        $this->mainCatalog["240"] = "Pinks";
        $this->mainCatalog["241"] = "Popular";
        $this->mainCatalog["114"] = "Prison Break";
        $this->mainCatalog["192"] = "Profiler";
        $this->mainCatalog["74"] = "Punkd";
        $this->mainCatalog["44"] = "Quantum Leap";
        $this->mainCatalog["18"] = "Real Time with Bill Maher";
        $this->mainCatalog["78"] = "Red Dwarf";
        $this->mainCatalog["244"] = "Regenesis";
        $this->mainCatalog["205"] = "livingwithfran.gif";
        $this->mainCatalog["98"] = "Rescue Me";
        $this->mainCatalog["17"] = "Reunion";
        $this->mainCatalog["79"] = "Robot Chicken";
        $this->mainCatalog["115"] = "Rome";
        $this->mainCatalog["45"] = "Roswell";
        $this->mainCatalog["193"] = "Saturday Night Live";
        $this->mainCatalog["47"] = "Scrubs";
        $this->mainCatalog["16"] = "Sealab 2021";
        $this->mainCatalog["15"] = "Seaquest";
        $this->mainCatalog["48"] = "Seinfeld";
        $this->mainCatalog["49"] = "Sex and the City";
        $this->mainCatalog["51"] = "Simpsons";
        $this->mainCatalog["50"] = "Six Feet Under";
        $this->mainCatalog["52"] = "Sliders";
        $this->mainCatalog["53"] = "Smallville";
        $this->mainCatalog["54"] = "Sopranos";
        $this->mainCatalog["55"] = "South Park";
        $this->mainCatalog["14"] = "Space 1999";
        $this->mainCatalog["185"] = "Space Above and Beyond";
        $this->mainCatalog["123"] = "Space Ghost";
        $this->mainCatalog["120"] = "Spooks";
        $this->mainCatalog["13"] = "Star Hunter";
        $this->mainCatalog["58"] = "Star Trek Deep Space Nine";
        $this->mainCatalog["56"] = "Star Trek Enterprise";
        $this->mainCatalog["60"] = "Star Trek Original Series";
        $this->mainCatalog["59"] = "Star Trek The Next Generation";
        $this->mainCatalog["57"] = "Star Trek Voyager";
        $this->mainCatalog["61"] = "Stargate Atlantis";
        $this->mainCatalog["7"] = "Stargate SG-1";
        $this->mainCatalog["11"] = "Stella";
        $this->mainCatalog["126"] = "Supernatural";
        $this->mainCatalog["133"] = "Surface";
        $this->mainCatalog["141"] = "Survivor";
        $this->mainCatalog["91"] = "Tales from the Crypt";
        $this->mainCatalog["62"] = "Tech TV";
        $this->mainCatalog["63"] = "That 70s Show";
        $this->mainCatalog["149"] = "The Apprentice";
        $this->mainCatalog["73"] = "Daily show";
        $this->mainCatalog["97"] = "The Inside";
        $this->mainCatalog["143"] = "the L word";
        $this->mainCatalog["64"] = "The O.C.";
        $this->mainCatalog["206"] = "The Office";
        $this->mainCatalog["65"] = "The Shield";
        $this->mainCatalog["66"] = "The Simple Life";
        $this->mainCatalog["226"] = "The Unit";
        $this->mainCatalog["67"] = "The West Wing";
        $this->mainCatalog["186"] = "The Wire";
        $this->mainCatalog["227"] = "Thief";
        $this->mainCatalog["148"] = "Third Watch";
        $this->mainCatalog["125"] = "Threshold";
        $this->mainCatalog["10"] = "Tonite Show";
        $this->mainCatalog["207"] = "TopGear";
        $this->mainCatalog["8"] = "Trailer Park Boys";
        $this->mainCatalog["94"] = "Tripping the Rift";
        $this->mainCatalog["4"] = "Tru Calling";
        $this->mainCatalog["213"] = "Twilight Zone";
        $this->mainCatalog["208"] = "Two and a half men";
        $this->mainCatalog["129"] = "Veronica Mars";
        $this->mainCatalog["89"] = "Viva La Bam";
        $this->mainCatalog["124"] = "Voltron";
        $this->mainCatalog["136"] = "Wanted";
        $this->mainCatalog["229"] = "War at Home";
        $this->mainCatalog["111"] = "Weeds";
        $this->mainCatalog["2"] = "Whose Line is it Anyway";
        $this->mainCatalog["242"] = "Wildfire";
        $this->mainCatalog["150"] = "Will and Grace";
        $this->mainCatalog["145"] = "Without a Trace";
        $this->mainCatalog["68"] = "X-Files";

    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
/*    function getSubCategories($mainGenre)
    {
        return $output;

    }*/

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
        $output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";

        $output .= "<br>\n";

        $output .= "<tr bgcolor=\"".$this->cfg["bgLight"]."\">";
        if ($needWait)
        {
            $output .= "  <td colspan=8 align=center>";
        }
        else
        {
            $output .= "  <td colspan=7 align=center>";
        }



        if (is_integer(strpos($this->htmlPage,"Welcome back, ")))
        {
          $userinfo = substr($this->htmlPage,strpos($this->htmlPage,"Welcome back, ")+strlen("Welcome back, "));
          $userinfo = substr($userinfo,strpos($userinfo,"<br/>")+strlen("<br/>"));
          $userinfo = substr($userinfo,0,strpos($userinfo,"</span>"));
          //$userinfo = substr($userinfo,strpos($userinfo,"<br>")+strlen("<br>"));
          //$userinfo = str_replace("<font class=\"font_10px\">","",$userinfo);
          //$userinfo = str_replace("</font>","",$userinfo);
          //$userinfo = str_replace("<br>","",$userinfo);
                //$output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
          $output .= $userinfo;
        }
        $output .= "</td></tr>";

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
        $output .= "  <td><strong>Snatched</strong></td>";
        $output .= "</tr>\n";

        return $output;
    }

    //----------------------------------------------------------------
    // Function to parse the response.
        function parseResponse($latest = true)
    {


        $thing = $this->htmlPage;

        if(strpos($thing,"Not logged in!") > 0)
        {
            $tmpStr = substr($thing,strpos($thing,"takelogin"));
            $tmpStr = substr($tmpStr,strpos($tmpStr, ">")+1);
            $tmpStr2 = "<form method=\"post\" action=\"http://".$this->mainURL."/takelogin.php\">";
            $tmpStr = substr($tmpStr,0,strpos($tmpStr,"</form>")+strlen("</form>"));
            $output = $tmpStr2.str_replace("src=\"","src=\"http://".$this->mainURL."/",$tmpStr)."</table>";

        }
        else
        {

            $output = $this->tableHeader();

            if(strpos($thing,"Error:") > 0)
            {
                $tmpStr = substr($thing,strpos($thing,"Error:")+strlen("Error:"));
                $tmpStr = substr($tmpStr,0,strpos($tmpStr,"</p>"));
                $this->msg = strip_tags($tmpStr);
                return $output . "<center>".$this->msg."</center><br>";
            }

            // We got a response so display it.
            // Chop the front end off.
            $thing = substr($thing,strpos($thing,"Leechers</a></td>"));

            $thing = substr($thing,strpos($thing,"<tr>")+strlen("<tr>"));

            //$tmpList = substr($thing,0,strpos($thing,"</table>"));
            // ok so now we have the listing.
            $tmpListArr = split("</tr>",$thing);

            $bg = $this->cfg["bgLight"];
            //var_export($tmpListArr);
            foreach($tmpListArr as $key =>$value)
            {

                $buildLine = true;
                if (strpos($value,"id="))
                {
                    $ts = new BitMeTv($value);

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
                    $tmpVal = substr($value,strpos($value,"browse.php?"),strpos($value,">")-1);

                    $pgNum = substr($tmpVal,strpos($tmpVal,"page=")+strlen("page="));
                    $pagesout .= str_replace($tmpVal,"XXXURLXXX".$pgNum,$value);
                }

                $cat = tfb_getRequestVar('mainGenre');

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
               // $pages = strip_tags($pages,"<a><b>");
                $output .= "<div align=center>".substr($pages,1)."</div>";
            }
        }
        return $output;
    }
}


// This is a worker class that takes in a row in a table and parses it.
class BitMeTv
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

    var $needsWait = false;
    var $waitTime = "";

    var $Data = "";

    var $torrentRating = "";

    function BitMeTv( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {

            $this->Data = $htmlLine;


            // Cleanup any bugs in the HTML
            $htmlLine = eregi_replace("</td>\n</td>",'</td>',$htmlLine);

            // Chunck up the row into columns.
            $tmpListArr = split("<td ",$htmlLine);

            $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"alt=\"")+strlen("alt=\"")); // MainCategory
            $this->MainCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

            $tmpStr = substr($tmpListArr["1"],strpos($tmpListArr["1"],"cat=")+strlen("cat=")); // Main Id
            $this->MainId = substr($tmpStr,0,strpos($tmpStr,"\""));

            //$this->torrentName = $this->cleanLine("<td ".$tmpListArr["2"]."</td>");  // TorrentName

            $this->torrentName = substr($tmpListArr["2"],strpos($tmpListArr["2"],"<b>")+strlen("<b>"),strpos($tmpListArr["2"],"</b>"));

            $tmpStr = substr($tmpListArr["3"],strpos($tmpListArr["3"],"href=\"download.php")+strlen("href=\""));
            $this->torrentFile = "http://www.bitmetv.org/".substr($tmpStr,0,strpos($tmpStr,"\""));

            //$tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"id=")+strlen("id=")); // File Id
            //$tmpStr = substr($tmpStr,0,strpos($tmpStr,"&"));

            //$this->torrentFile = "http://oink.me.uk/downloadpk/".$tmpStr."/".str_replace(" ","_",$this->torrentName).".torrent";

            $this->fileCount = $this->cleanLine("<td ".$tmpListArr["4"]."</td>");  // File Count

            $this->torrentSize = $this->cleanLine("<td ".$tmpListArr["8"]."</td>");  // Size of File

            $this->torrentStatus = $this->cleanLine(str_replace("<br>"," ","<td ".$tmpListArr["9"]."</td>"));  // Snatched

            $this->Seeds = $this->cleanLine("<td ".$tmpListArr["10"]."</td>");  // Seeds
            $this->Peers = $this->cleanLine("<td ".$tmpListArr["11"]."</td>");  // Leech

            $this->torrentDisplayName = $this->torrentName;
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
        $output .= "    <td bgcolor=\"".$bg."\" align=center>".$this->torrentStatus."</td>\n";
        $output .= "</tr>\n";

        return $output;

    }
}

?>