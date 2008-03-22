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
	v 1.06 - Dec 12. 06 - Change path on main pages.
	v 1.05 - May 20. 06 - Change to paging URL, addtion of user stats as per Qromes Request (should now appear above column headers)
	v 1.04 - Apr 26. 06 - Fixed filtering lists - Possible bug in admin.php (line 1997 - option value [NO FILTER] needs setting to -1 instead of "")
	v 1.03 - Apr 21, 06 - Modified first row on table to reflect correct date
	v 1.02 - Apr 18, 06 - Added Update URL
	v 1.01 - Apr 17, 06 - bug in filtering.
*/

class SearchEngine extends SearchEngineBase
{

    function SearchEngine($cfg)
    {
        $this->mainURL = "www.demonoid.com";
        $this->altURL = "www.demonoid.com";
        $this->mainTitle = "Demonoid";
        $this->engineName = "Demonoid";

        $this->author = "moldavite";
        $this->version = "1.06-tfb";
        $this->updateURL = "http://www.torrentflux.com/forum/index.php/topic,1210.0.html";

        $this->Initialize($cfg);
    }

    function populateMainCategories()
    {
        $this->mainCatalog["0"] = "(all types)";
        $this->mainCatalog["9"] = "Anime";
        $this->mainCatalog["5"] = "Applications";
        $this->mainCatalog["17"] = "Audio Books";
        $this->mainCatalog["11"] = "Books";
        $this->mainCatalog["10"] = "Comics";
		$this->mainCatalog["4"] = "Games";
        $this->mainCatalog["6"] = "Misc";
        $this->mainCatalog["1"] = "Movies";
		$this->mainCatalog["2"] = "Music";
        $this->mainCatalog["13"] = "Music Videos";
        $this->mainCatalog["8"] = "Pictures";
		$this->mainCatalog["3"] = "TV";
    }

    //----------------------------------------------------------------
    // Function to Get Sub Categories
    function getSubCategories($mainGenre)
    {
        $output = array();

        switch ($mainGenre)
        {
            case "1" :
			    $output["1:ALL"] = "All";
                $output["1:6"] = "Action";
                $output["1:7"] = "Adventure";
                $output["1:8"] = "Animation";
                $output["1:9"] = "Biography";
                $output["1:10"] = "Comedy";
                $output["1:180"] = "Concerts";
                $output["1:181"] = "Crime";
                $output["1:11"] = "Documentary";
                $output["1:12"] = "Drama";
                $output["1:13"] = "Family";
                $output["1:14"] = "Fantasy";
                $output["1:15"] = "Film-Noir";
                $output["1:17"] = "Horror";
                $output["1:18"] = "Musical";
                $output["1:19"] = "Mystery";
                $output["1:65"] = "Other";
                $output["1:20"] = "Romance";
                $output["1:21"] = "Sci-Fi";
                $output["1:22"] = "Short film";
                $output["1:23"] = "Sports";
                $output["1:24"] = "Thriller";
                $output["1:182"] = "Trailers";
                $output["1:25"] = "War";
                $output["1:26"] = "Western";
                break;
            case "2" :
				$output["2:All"] = "All";
                $output["2:183"] = "Alternative";
                $output["2:34"] = "Blues";
                $output["2:185"] = "Christian";
                $output["2:27"] = "Classical";
                $output["2:186"] = "Comedy";
                $output["2:44"] = "Contemporary African";
                $output["2:36"] = "Country";
                $output["2:39"] = "Dance / Disco";
                $output["2:37"] = "Electro / Techno";
                $output["2:28"] = "Gospel";
                $output["2:43"] = "Hip-Hop / Rap";
                $output["2:38"] = "Industrial";
                $output["2:184"] = "J-Pop";
                $output["2:29"] = "Jazz";
                $output["2:30"] = "Latin American";
                $output["2:40"] = "Melodic";
                $output["2:35"] = "Metal";
                $output["2:63"] = "Other";
                $output["2:31"] = "Pop";
                $output["2:42"] = "Punk";
                $output["2:41"] = "Reggae";
                $output["2:33"] = "Rhythm and blues";
                $output["2:32"] = "Rock";
                $output["2:188"] = "Soul";
                $output["2:190"] = "Soundtrack";
                $output["2:191"] = "Trance";
                break;
			case "3" :
				$output["3:All"] = "All";
                $output["3:192"] = "Action";
                $output["3:193"] = "Adventure";
                $output["3:194"] = "Animation";
                $output["3:195"] = "Biography";
                $output["3:196"] = "Comedy";
                $output["3:213"] = "Concerts";
                $output["3:214"] = "Crime";
                $output["3:197"] = "Documentary";
                $output["3:198"] = "Drama";
                $output["3:199"] = "Family";
                $output["3:200"] = "Fantasy";
                $output["3:201"] = "Film-Noir";
                $output["3:203"] = "Horror";
                $output["3:202"] = "Musical";
                $output["3:204"] = "Mystery";
                $output["3:212"] = "Other";
                $output["3:205"] = "Romance";
                $output["3:206"] = "Sci-Fi";
                $output["3:207"] = "Short film";
                $output["3:208"] = "Sports";
                $output["3:259"] = "Talk show";
                $output["3:209"] = "Thriller";
                $output["3:215"] = "Trailers";
                $output["3:210"] = "War";
                $output["3:211"] = "Western";
                break;
            case "4" :
				$output["4:All"] = "All";
                $output["4:177"] = "DOS";
                $output["4:176"] = "Dreamcast";
                $output["4:178"] = "Emulators";
                $output["4:167"] = "GameBoy";
                $output["4:175"] = "GameCube";
                $output["4:162"] = "Linux";
                $output["4:163"] = "Macintosh";
                $output["4:168"] = "Mobile phone";
                $output["4:261"] = "Nintendo DS";
                $output["4:164"] = "Palm";
                $output["4:170"] = "Playstation 1";
                $output["4:171"] = "Playstation 2";
                $output["4:172"] = "Playstation 3";
                $output["4:165"] = "PocketPC";
                $output["4:169"] = "PSP";
                $output["4:166"] = "Windows";
                $output["4:173"] = "XBox";
                $output["4:174"] = "XBox 360";
                break;
            case "5" :
				$output["5:All"] = "All";
                $output["5:2"] = "Linux";
				$output["5:3"] = "Macintosh";
				$output["5:118"] = "Mobile phone";
				$output["5:5"] = "Palm";
				$output["5:4"] = "PocketPC";
				$output["5:1"] = "Windows";
                break;
            case "6" :
                $output["6:All"] = "All";
                break;
            case "8" :
				$output["8:All"] = "All";
                $output["8:66"] = "Art";
                $output["8:67"] = "Commercial";
                $output["8:69"] = "Glamour";
                $output["8:73"] = "Other";
                $output["8:68"] = "Photojournalism";
                $output["8:70"] = "Snapshots";
                $output["8:71"] = "Sports";
                $output["8:72"] = "Wildlife";
                break;
			case "9" :
				$output["9:All"] = "All";
                $output["9:111"] = "Action";
				$output["9:220"] = "Adventure";
				$output["9:112"] = "Comedy";
				$output["9:113"] = "Drama";
				$output["9:114"] = "Fantasy";
				$output["9:115"] = "Horror";
				$output["9:221"] = "Other";
				$output["9:117"] = "Romance";
				$output["9:116"] = "Sci-Fi";

                break;
			case "10" :
				$output["10:All"] = "All";
                $output["10:159"] = "Action / Adventure";
                $output["10:227"] = "Crime";
                $output["10:160"] = "Drama";
                $output["10:223"] = "Fantasy";
                $output["10:228"] = "Historical fiction";
                $output["10:224"] = "Horror";
                $output["10:161"] = "Illustrated novel";
                $output["10:229"] = "Other";
                $output["10:226"] = "Real-Life";
                $output["10:225"] = "Sci-Fi";
				$output["10:222"] = "Super Hero";
                break;
			case "11" :
				$output["11:All"] = "All";
                $output["11:119"] = "Action";
                $output["11:120"] = "Adventure";
                $output["11:122"] = "Childrens";
                $output["11:137"] = "Computers";
                $output["11:123"] = "Contemporary";
                $output["11:124"] = "Fantasy";
                $output["11:125"] = "General";
                $output["11:126"] = "Horror";
                $output["11:127"] = "Humor";
                $output["11:128"] = "Literary";
                $output["11:129"] = "Mainstream";
                $output["11:138"] = "Misc. Educational";
                $output["11:130"] = "Mystery";
                $output["11:230"] = "Other";
                $output["11:131"] = "Paranormal";
                $output["11:132"] = "Romance";
                $output["11:260"] = "RPG";
                $output["11:133"] = "Sci-Fi";
                $output["11:121"] = "Self-help";
                $output["11:134"] = "Suspense";
                $output["11:135"] = "Thriller";
                $output["11:136"] = "Western";
                break;
			case "13" :
				$output["13:All"] = "All";
                $output["13:251"] = "Alternative";
                $output["13:239"] = "Blues";
                $output["13:253"] = "Christian";
                $output["13:232"] = "Classical";
                $output["13:254"] = "Comedy";
                $output["13:249"] = "Contemporary African";
                $output["13:241"] = "Country";
                $output["13:244"] = "Dance / Disco";
                $output["13:242"] = "Electro / Techno";
                $output["13:233"] = "Gospel";
                $output["13:248"] = "Hip-Hop / Rap";
                $output["13:243"] = "Industrial";
                $output["13:252"] = "J-Pop";
                $output["13:234"] = "Jazz";
                $output["13:235"] = "Latin American";
                $output["13:245"] = "Melodic";
                $output["13:240"] = "Metal";
                $output["13:250"] = "Other";
                $output["13:236"] = "Pop";
                $output["13:247"] = "Punk";
                $output["13:246"] = "Reggae";
                $output["13:256"] = "Reggae";
                $output["13:238"] = "Rhythm and blues";
                $output["13:237"] = "Rock";
                $output["13:255"] = "Soul";
                $output["13:257"] = "Soundtrack";
                $output["13:258"] = "Trance";
                break;
			case "17" :
				$output["17:All"] = "All";
                $output["17:140"] = "Action";
                $output["17:139"] = "Adventure";
                $output["17:142"] = "Childrens";
                $output["17:157"] = "Computers";
                $output["17:143"] = "Contemporary";
                $output["17:144"] = "Fantasy";
                $output["17:145"] = "General";
                $output["17:146"] = "Horror";
                $output["17:147"] = "Humor";
                $output["17:148"] = "Literary";
                $output["17:149"] = "Mainstream";
                $output["17:158"] = "Misc. Educational";
                $output["17:150"] = "Mystery";
                $output["17:231"] = "Other";
                $output["17:151"] = "Paranormal";
                $output["17:152"] = "Romance";
                $output["17:153"] = "Sci-Fi";
                $output["17:141"] = "Self-help";
                $output["17:154"] = "Suspense";
                $output["17:155"] = "Thriller";
                $output["17:156"] = "Western";
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
        $request = '/files/';

		if (array_key_exists("mainGenre",$_REQUEST) && array_key_exists("subGenre",$_REQUEST))
        {
            $request = "/files/?category=".$_REQUEST["mainGenre"]."&subcategory=".$_REQUEST["subGenre"]."&language=0&seeded=0&external=2&query=&uid=0";

        }
		elseif (array_key_exists("subGenre",$_REQUEST))
        {
			$splitted = explode(":", $_REQUEST['subGenre']);
            $request = "/files/?category=".$splitted[0]."&subcategory=".$splitted[1]."&language=0&seeded=0&external=2&query=&uid=0";

        }
        else
        {
            $request = "/files/";

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
        if (array_key_exists("mainGenre",$_REQUEST) && array_key_exists("subGenre",$_REQUEST))
        {
            $request = "/files/?category=".$_REQUEST['mainGenre']."&subcategory=".$_REQUEST["subGenre"]."&language=0&seeded=0&external=2&query=&uid=0";

        }
        // elseif (array_key_exists("mainGenre",$_REQUEST))
        // {
            // $request = "/torrents/?category=".$_REQUEST["mainGenre"]."&subcategory=All&language=0&seeded=0&external=2&query=&uid=0";

        // }
        else
        {
            $request = "/files/?query=".$searchTerm;

		}

        if(strlen($searchTerm) > 0)
        {
            $searchTerm = str_replace(" ", "+", $searchTerm);
        }

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
    // Override the base to show custom table header.
    // Function to setup the table header
    function tableHeader()
    {
		$output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>";

        $output .= "<br>\n";

		//v1.05 Update Starts here
		if (is_integer(strpos($this->htmlPage,"class=user_box>")))
        {
			$userinfo = substr($this->htmlPage,strpos($this->htmlPage,"<td class=user_box>")+strlen("<td class=user_box>"));
			$userinfo = substr($userinfo,0,strpos($userinfo,"</td>"));
			$userinfo = substr($userinfo,strpos($userinfo,"<br>")+strlen("<br>"));
			$userinfo = str_replace("<font class=\"font_10px\">","",$userinfo);
			$userinfo = str_replace("</font>","",$userinfo);
			$userinfo = str_replace("<br>","",$userinfo);
            $output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
			$output .= "<td colspan=6 align=center><b>".$userinfo."</td></tr>";
        }
		//v1.05 Update Ends here

        $output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
        $output .= "  <td>&nbsp;</td>";
        $output .= "  <td><strong>Torrent Name</strong> &nbsp;(";

        $tmpURI = str_replace(array("?hideSeedless=yes","&hideSeedless=yes","?hideSeedless=no","&hideSeedless=no"),"",$_SERVER["REQUEST_URI"]);

        //Check to see if Question mark is there.
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
        $output .= "</tr>\n";

        return $output;
    }

    //----------------------------------------------------------------
    // Function to parse the response.
    function parseResponse()
    {

        $output = $this->tableHeader();

		if (is_integer(strpos($this->htmlPage,"class=\"added_today\">")))
        {
			$dateheader = substr($this->htmlPage,strpos($this->htmlPage,"<td colspan=\"10\" class=\"added_today\">")+strlen("<td colspan=\"10\" class=\"added_today\">"));
			$dateheader = substr($dateheader,0,strpos($dateheader,"</td>"));
            $output .= "<tr bgcolor=\"\">   <td colspan=\"6\" align=center>".$dateheader."</td></tr>\n";
        }

        $thing = $this->htmlPage;
		$thing = str_replace("<a href=\"","<a href=\"http://www.demonoid.com",$thing);
		$thing = str_replace("<img src=\"","<img src=\"http://www.demonoid.com",$thing);
        // We got a response so display it.
        // Chop the front end off.

        while (is_integer(strpos($thing,"class=\"added")))

        {

			$thing = substr($thing,strpos($thing,"class=\"added"));
			$thing = str_replace("<td colspan=\"10\" class=\"added_today\">","<td colspan=\"10\" class=\"today\">",$thing);
			$thing = substr($thing,strpos($thing,"<tr>"));

			$tmplist = substr($thing,0,strpos($thing,"<tr><td colspan=\"10\" align=\"center\""));

            // ok so now we have the listing.
            $tmpListArr = explode("</tr><tr>",$tmplist);
			$bg = $this->cfg["bgLight"];
            foreach($tmpListArr as $key =>$value)
            {

			$buildLine = true;
                if (strpos($value,"www.demonoid.com"))
                {

                    $ts = new dmnd($value);

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
        }

        $output .= "</table>";

        // is there paging at the bottom?
        if (strpos($thing, "&page=") != false)

        {
			// Yes, then lets grab it and display it!  ;)
            $thing = substr($thing,strpos($thing,"<tr><td colspan")+strlen("<tr><td colspan"));
            $thing = substr($thing,strpos($thing,">")+1);
            $pages = substr($thing,0,strpos($thing,"</td>"));

            if(strpos($this->curRequest,"LATEST"))
            {

                $pages = str_replace("http://www.demonoid.com/files/?",$this->searchURL()."&LATEST=1&",$pages);

            }
            else
            {

                $pages = str_replace("http://www.demonoid.com/files/?",$this->searchURL()."&",$pages);
            }

            $pages = str_replace("page=","pg=",$pages);
			$pages = str_replace("subcategory=","subGenre=",$pages);
            $pages = str_replace("category=","mainGenre=",$pages);
            $pages = str_replace("&&","&",$pages);

            $output .= "<div align=center>".$pages."</div>";
        }

        return $output;
    }
}


// This is a worker class that takes in a row in a table and parses it.
class dmnd
{
    var $torrentName = "";
    var $torrentDisplayName = "";
    var $torrentFile = "";
    var $torrentSize = "";
    var $torrentStatus = "";
    var $CatName = "";
    var $CatId = "";
    var $MainId = "";
    var $MainCategory = "";
    var $SubId = "";
    var $SubCategory = "";
    var $Seeds = "";
    var $Peers = "";
    var $Data = "";

    var $dateAdded = "";
    var $dwnldCount = "";


 function dmnd( $htmlLine )
    {
        if (strlen($htmlLine) > 0)
        {
            $this->Data = $htmlLine;
			$tmpListArr = explode("</td>",$htmlLine);

            if(count($tmpListArr) >= 13)
            {

				// Category Id
                $tmpStr = "";
				$tmpStr = substr($tmpListArr["0"],strpos($tmpListArr["0"],"category=")+strlen("category="));
                $this->CatId = substr($tmpStr,0,strpos($tmpStr,"&"));

				//Category Name
				$tmpStr = "";
				$tmpStr = substr($tmpListArr["0"],strpos($tmpListArr["0"],"title=\"")+strlen("title=\""));
                $this->CatName = substr($tmpStr,0,strpos($tmpStr,"\""));

				//SubCategory ID
				$tmpStr = "";
				$tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"subcategory=")+strlen("subcategory="));
                $this->SubId = substr($tmpStr,0,strpos($tmpStr,"&"));

				//SubCategory Name
				$tmpStr = "";
				$tmpStr = substr($tmpListArr["2"],strpos($tmpListArr["2"],"subcategory\"")+strlen("subcategory\">"));
                $this->SubCategory = substr($tmpStr,0,strpos($tmpStr,"<"));

				//validate Subcategory ID
				if (!is_numeric($this->SubId)){
				$this->SubId = "0";
				$this->SubCategory = "All";
				}

				//Set  Category
				$this->MainCategory = $this->CatName;
				$this->MainId = $this->CatId;
				$this->SubId = $this->CatId.":".$this->SubId;

				// TorrentName
                $this->torrentName = $this->cleanLine($tmpListArr["1"]);

                // Download Link
				$tmpStr = "";
                $tmpStr = substr($tmpListArr["4"],strpos($tmpListArr["4"],"href=\"")+strlen("href=\""));
                $this->torrentFile = substr($tmpStr,0,strpos($tmpStr,"\""));

                // Size of File
				$this->torrentSize = $this->cleanLine($tmpListArr["7"]);

				// Seeds
				$this->Seeds = $this->cleanLine($tmpListArr["10"]);

				// Peers
                $this->Peers = $this->cleanLine($tmpListArr["11"]);

				if ($this->Peers == '')
                {
                    $this->Peers = "N/A";
                    if (empty($this->Seeds)) $this->Seeds = "N/A";
                }
                if ($this->Seeds == '') $this->Seeds = "N/A";

				//set torrent display name
                $this->torrentDisplayName = $this->torrentName;
                if(strlen($this->torrentDisplayName) > 50)
                {
                    $this->torrentDisplayName = substr($this->torrentDisplayName,0,50)."...";
                }

				//Check Date.
				if (!empty($tmpListArr["12"])) {
					$this->dateAdded = $this->cleanline($tmpListArr["12"]);
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
    function BuildOutput($bg, $searchURL)
    {
        $output = "<tr>\n";
        $output .= "    <td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "    <td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".$this->torrentFile."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";

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
		if (!empty($this->dateAdded)){
			$output .= "<tr bgcolor=\"\">   <td colspan=\"6\" align=center>".$this->dateAdded."</td></tr>\n";
			}

        return $output;

    }
}

?>