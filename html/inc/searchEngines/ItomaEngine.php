<?php

/*************************************************************
*  TorrentFlux PHP Torrent Manager
*  www.torrentflux-ng.org
**************************************************************/
/*
	v 1.11 - Epsylon3 Dec, 2010 (paging)
	v 1.10 - Epsylon3 May, 2010
	v 1.00 - PTiRouZ  Feb, 2007
*/

class SearchEngine extends SearchEngineBase
{

	function SearchEngine($cfg)
	{
		$this->mainURL = "www.itoma.info";
		$this->altURL = "www.itoma.info/tracker/";
		$this->mainTitle = "Itoma";
		$this->engineName = "Itoma";

		$this->needAuth = true;

		$this->author = "Epsylon3";
		$this->version = "1.11";
		//$this->updateURL = "http://www.torrentflux-ng.org";

		$this->Initialize($cfg);

		error_reporting(E_ALL);
	}


	//----------------------------------------------------------------
	// Function to Get Main Categories
	function populateMainCategories()
	{

		$this->mainCatalog["18"]="Apps: PC";
		$this->mainCatalog["19"]="Apps: Mac";
		$this->mainCatalog["20"]="Apps: Linux";
		$this->mainCatalog["21"]="Apps: Autres";
		$this->mainCatalog["10"]="Games: PC";
		$this->mainCatalog["11"]="Games: PS2";
		$this->mainCatalog["43"]="Games: PS3";
		$this->mainCatalog["12"]="Games: PSP";
		$this->mainCatalog["13"]="Games: Xbox";
		$this->mainCatalog["14"]="Games: Xbox360";
		$this->mainCatalog["44"]="Games: Wii";
		$this->mainCatalog["45"]="Games: DS";
		$this->mainCatalog["47"]="Movies: XXX";
		$this->mainCatalog["48"]="Movies: CAM";
		$this->mainCatalog["1"] ="Movies: DVD";
		$this->mainCatalog["2"] ="Movies: Divx/Xvid";
		$this->mainCatalog["42"]="Movies: HD";
		$this->mainCatalog["22"]="Music: MP3";
		$this->mainCatalog["24"]="Music: DVD";
		$this->mainCatalog["25"]="Music: Video";
		$this->mainCatalog["36"]="Other: E-Books";
		$this->mainCatalog["50"]="Other: Sport";
		$this->mainCatalog["40"]="Other: Other";
		$this->mainCatalog["5"] ="TV: DVD Series";
		$this->mainCatalog["41"]="TV: HD";
		$this->mainCatalog["6"] ="TV: Divx/Xvid";
		$this->mainCatalog["7"] ="TV: SVCD/VCD";
		$this->mainCatalog["9"] ="TV: Docs";
		$this->mainCatalog["49"]="TV: Vost-FR";

	}

	//----------------------------------------------------------------
	// Function to get Latest..
	function getLatest()
	{
		$cat = tfb_getRequestVar('mainGenre');

		if (empty($cat) && !empty($_REQUEST['cat']))
			$cat = $_REQUEST['cat'];

		$request = "torrents.php";

		if(!empty($cat))
		{
			if(strpos($request,"?")) {
				$request .= "&cat=".$cat;
			} else {
				$request .= "?cat=".$cat;
			}
		}

		if (!empty($this->pg))
		{
			if(strpos($request,"?")) {
				$request .= "&page=" . $this->pg;
			} else {
				$request .= "?page=" . $this->pg;
			}
		}

		if ($this->makeRequest($request,true))
		{
			if (strlen($this->htmlPage) > 0 ) {
			  return $this->parseResponse();
			} else {
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
		$request = "torrents-search.php?search=".urlencode($searchTerm);

		if (!empty($_REQUEST['cat']))
			$cat = $_REQUEST['cat'];

		if(!empty($cat)) {
			$request .= "&cat=".$cat;
		}

		//$incldead = getRequestVar('incldead');
		if (empty($incldead)) $incldead = "0";
		$request .= "&incldead=".$incldead;

		if (!empty($this->pg)) {
			$request .= "&page=" . $this->pg;
		}

		if ($this->makeRequest($request,true)) {
			return $this->parseResponse();
		} else {
			return $this->msg;
		}
	}

	//----------------------------------------------------------------
	// Override the base to show custom table header.
	// Function to setup the table header
	function tableHeader()
	{
		$tmpStr = $this->htmlPage;

		$output = "<table width=\"100%\" cellpadding=3 cellspacing=0 border=0>\n";
		$output .= "<tr bgcolor=\"".$this->cfg["bgLight"]."\">";
		$output .= "  <td colspan=7 align=center>";

		$tmpStr = substr($tmpStr,strpos($tmpStr,"userdetails"));
		$tmpStr = substr($tmpStr,strpos($tmpStr,"Downloaded"));
		if (strpos($tmpStr,"Privacy"))
			$tmpStr = substr($tmpStr,0,strpos($tmpStr,"Privacy"));
		$stats = str_replace('<br>',' | ',$tmpStr);
		$stats = preg_replace('#[\s\n]+#m',' ',$stats);

		if (strstr($stats,"Enregistrement"))
			$output .= "<b>Connexion Impossible : Veuillez enregistrer le cookie d'identification Itoma dans votre profil.</b>";
		else
			$output .= "<b>Current Stats : ".strip_tags($stats)."</b>";

		$output .= "<tr bgcolor=\"".$this->cfg["table_header_bg"]."\">";
		$output .= "  <td>&nbsp;</td>";
		$output .= "  <td><strong>Nom</strong> &nbsp;[Seedless: ";

		$tmpURI = $_SERVER["REQUEST_URI"];
		$tmpURI = preg_replace('#[\?\&]+hideSeedless=(yes|no|only)#i', "", $tmpURI);

		// Check to see if Question mark is there.
		if (strpos($tmpURI,'?') !== false) {
			$tmpURI .= "&";
		} else {
			$tmpURI .= "?";
		}

		if($this->hideSeedless == "yes") {
			$output .= "<a href=\"". $tmpURI . "hideSeedless=no\">show</a>|<a href=\"". $tmpURI . "hideSeedless=only\">only</a>";
		} elseif($this->hideSeedless == "only") {
			$output .= "<a href=\"". $tmpURI . "hideSeedless=no\">show</a>";
		} else {
			$output .= "<a href=\"". $tmpURI . "hideSeedless=yes\">hide</a>";
		}

		$output .= "]</td>";
		$output .= "  <td><strong>Category</strong></td>";
		$output .= "  <td align=center><strong>&nbsp;&nbsp;Size</strong></td>";
		$output .= "  <td><strong>S.</strong></td>";
		$output .= "  <td><strong>L.</strong></td>";

		//$output .= "  <td><strong>Santé</strong></td>";
		//$output .= "  <td><strong>Commentaires</strong></td>";
		$output .= "  <td><strong>Complétés</strong></td>";
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

		$thing = substr($thing,strpos($thing,"CONTENT START"));
		$thing = substr($thing,strpos($thing,'Health</td></tr>')+strlen('Health</td></tr>'));
		$table = substr($thing,0,strpos($thing,"</table><BR>"))
		;
		//var_dump(htmlentities($thing)); exit;

		// ok so now we have the listing.
		$tmpListArr = explode("<tr>",$table);

		//print_r($tmpListArr);

		$bg = $this->cfg["bgLight"];

		foreach($tmpListArr as $key =>$value)
		{
			$buildLine = true;
			if (strpos($value,"download.php?id"))
			{
				$ts = new fileItoma($value, $this->altURL);

				//print_r($ts);
				// Determine if we should build this output
				if (is_int(array_search($ts->MainId,$this->catFilter)))
				{
					$buildLine = false;
				}

				if ($this->hideSeedless == "yes")
				{
					if(intval($ts->Seeds) == 0) {
						$buildLine = false;
					}
				}
				elseif ($this->hideSeedless == "only")
				{
					if(intval($ts->Seeds) > 0) {
						$buildLine = false;
					}
				}
				
				if (!empty($ts->torrentFile) && $buildLine) {

					$output .= trim($ts->BuildOutput($bg,$this->searchURL()));

					// ok switch colors.
					if ($bg == $this->cfg["bgLight"]) {
						$bg = $this->cfg["bgDark"];
					} else {
						$bg = $this->cfg["bgLight"];
					}
				}

			}
		}

		// set thing to end of this table.
		//$thing = substr($thing,strpos($thing,"</table>"));

		$output .= "</table>";

		// is there paging at the bottom?
		if (strpos($thing, "page=") !== false)
		{
			// Yes, then lets grab it and display it!  ;)
			$pages = substr($thing,strpos($thing,"<p"));
			$pages = substr($pages,strpos($pages,">"));
			$pages = substr($pages,0,strpos($pages,"</p>"));

			$pages = str_replace("&nbsp; ",'',$pages);

			$tmpPageArr = explode("</a>",$pages);
			array_pop($tmpPageArr);

			$pagesout = '';
			foreach($tmpPageArr as $key => $value)
			{
				$value .= "</a> &nbsp;";
				if (!preg_match("#((browse|torrents\-search|torrents|search)\.php[^>]+)#",$value,$matches))
					continue;

				$url = rtrim($matches[0],'"');
				$php = $matches[2]; //browse,search,torrents...
				
				if (!preg_match("#page=([\d]+)#",$value,$matches))
					continue;

				$pgNum = (int) $matches[1];

				$pagesout .= str_replace($url,"XXXURLXXX".$pgNum,$value);
			}

			$pagesout = str_replace($php.".php?page=","",$pagesout);

			$cat = tfb_getRequestVar('mainGenre');

			if (empty($cat) && !empty($_REQUEST['cat']))
				$cat = $_REQUEST['cat'];

			if(stripos($this->curRequest,"LATEST"))
			{
				if (!empty($cat)) {
					$pages = str_replace("XXXURLXXX",$this->searchURL()."&LATEST=1&cat=".$cat."&pg=",$pagesout);
				} else {
					$pages = str_replace("XXXURLXXX",$this->searchURL()."&LATEST=1&pg=",$pagesout);
				}

			} else {

				if(!empty($cat)) {
					$pages = str_replace("XXXURLXXX",$this->searchURL()."&searchterm=".$_REQUEST["searchterm"]."&cat=".$cat."&pg=",$pagesout);
				} else {
					$pages = str_replace("XXXURLXXX",$this->searchURL()."&searchterm=".$_REQUEST["searchterm"]."&pg=",$pagesout);
				}
			}

			$output .= "<div align=center>".substr($pages,1)."</div>";

		}

		return $output;
	}
}


// This is a worker class that takes in a row in a table and parses it.
class fileItoma
{
	var $torrentName = "";
	var $torrentDisplayName = "";
	var $torrentFile = "";
	var $torrentId = "";
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

	function fileItoma( $htmlLine, $dwnURL )
	{
		if (strlen($htmlLine) > 0)
		{

			$this->Data = $htmlLine;

			// Cleanup any bugs in the HTML
			$htmlLine = preg_replace("#</td>\n</td>#i",'</td>',$htmlLine);

			// Chunck up the row into columns.
			$tmpListArr = explode("<td ",$htmlLine);

			//echo "<pre>";
			//print_r($tmpListArr);
/*
 	[1] => class=ttable_col1 align=center valign=middle> {CatImg/Link}
    [2] => class=ttable_col2 nowrap> BLACK.LAGOON.VOL01.FRENCH.DVDRIP.Xv...
    [3] => class=ttable_col1 align=center> {DL Link/Img}
    [4] => class=ttable_col1 align=center>9
    [5] => class=ttable_col2 align=center>739.64 MB
    [6] => class=ttable_col1 align=center>8
    [7] => class=ttable_col2 align=center>11
    [8] => class=ttable_col1 align=center> {Health_N}
*/
			$tmpStr = substr($tmpListArr[1],strpos($tmpListArr["1"],"alt=\"")+strlen("alt=\"")); // MainCategory
			$this->MainCategory = substr($tmpStr,0,strpos($tmpStr,"\""));

			$tmpStr = substr($tmpListArr[1],strpos($tmpListArr["1"],"cat=")+strlen("cat=")); // Main Id
			$this->MainId = trim(substr($tmpStr,0,strpos($tmpStr,"\"")));

			$tmpStr = $tmpListArr[3];
			$tmpStr = substr($tmpStr,(strpos($tmpStr,"<a href=\"")+9));

			//torrent File
			$tmpStr = substr($tmpStr,0,strpos($tmpStr,"<img"));
			$this->torrentFile = substr($tmpStr,0,strpos($tmpStr,'">'));
			$this->torrentFile = "http://".$dwnURL.str_replace(" ","_",$this->torrentFile);

			//torent Name
			$this->torrentName = substr($this->torrentFile,strpos($this->torrentFile,"&name=")+6);

			//torrent ID
			$tmpStr = $this->torrentFile;
			$tmpStr = substr($tmpStr,strpos($this->torrentFile,"?id=")+4);
			$this->torrentId = substr($tmpStr,0,strpos($tmpStr,"&name="));

			//DisplayName
			$this->torrentDisplayName = substr($this->torrentName,0,-9);
			$this->torrentDisplayName = str_replace("%20","&nbsp;",$this->torrentDisplayName);
			if(strlen($this->torrentDisplayName) > 50)
				$this->torrentDisplayName = substr($this->torrentDisplayName,0,50)."...";

			//$this->needsWait = false;
			//$this->waitTime = $this->cleanLine("<td ".$tmpListArr[1]."</td>");  // Wait Time

			$this->fileLife = $this->cleanLine("<td ".$tmpListArr[8]."</td>");  // File Life
			$this->fileLife = str_replace("hours","hrs",$this->fileLife);

			$this->torrentSize = $this->cleanLine("<td ".$tmpListArr[5]."</td>");  // Size of File

			$this->torrentStatus = $this->cleanLine(str_replace("<br>"," ","<td ".$tmpListArr[4]."</td>"));  // Snatched

			$this->Seeds = $this->cleanLine("<td ".$tmpListArr[6]."</td>");  // Seeds
			$this->Peers = $this->cleanLine("<td ".$tmpListArr[7]."</td>");  // Leech

			if ($this->Peers == '')
			{
				$this->Peers = "N/A";
				if (empty($this->Seeds)) $this->Seeds = "N/A";
			}
			if ($this->Seeds == '') $this->Seeds = "N/A";


		}

		//print_r($this);

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
	function BuildOutput($bg, $searchURL = '', $maxDisplayLength=80)
	{
		if(strlen($this->torrentDisplayName) > $maxDisplayLength)
		{
			$this->torrentDisplayName = substr($this->torrentDisplayName,0,$maxDisplayLength-3)."...";
		}

		$output = "<tr>\n";
		$output .= "	<td width=16 bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".urlencode($this->torrentFile)."\"><img src=\"".getImagesPath()."download_owner.gif\" width=\"16\" height=\"16\" title=\"".$this->torrentName."\" border=0></a></td>\n";
		$output .= "	<td bgcolor=\"".$bg."\"><a href=\"dispatcher.php?action=urlUpload&type=torrent&url=".urlencode($this->torrentFile)."\" title=\"".$this->torrentName."\">".$this->torrentDisplayName."</a></td>\n";

		if (strlen($this->MainCategory) > 1){
			$genre = $this->MainCategory;
			//$genre = "<a href=\"".$searchURL."&mainGenre=".$this->MainId."\">".$this->MainCategory."</a>";
		}else{
			$genre = "";
		}

		$output .= "	<td bgcolor=\"".$bg."\">". $genre ."</td>\n";

		$output .= "	<td bgcolor=\"".$bg."\" align=right>".$this->torrentSize."</td>\n";
		$output .= "	<td bgcolor=\"".$bg."\" align=center>".$this->Seeds."</td>\n";
		$output .= "	<td bgcolor=\"".$bg."\" align=center>".$this->Peers."</td>\n";

		$output .= "	<td bgcolor=\"".$bg."\" align=center>".$this->torrentStatus."</td>\n";
		$output .= "</tr>\n";

		return $output;

	}
}

?>