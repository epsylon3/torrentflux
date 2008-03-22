<?php

/* $Id$ */

/*******************************************************************************

 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html

*******************************************************************************/

// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../index.php");
	exit();
}

/******************************************************************************/

// is enabled ?
if ($cfg["enable_search"] != 1) {
	AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use search");
	@error("search is disabled", "index.php?iid=index", "");
}

// common functions
require_once('inc/functions/functions.common.php');

// require
require_once("inc/searchEngines/SearchEngineBase.php");

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.torrentSearch.tmpl");

// Go get the if this is a search request. go get the data and produce output.
$hideSeedless = tfb_getRequestVar('hideSeedless');
if (!empty($hideSeedless))
	$_SESSION['hideSeedless'] = $hideSeedless;
if (!isset($_SESSION['hideSeedless']))
	$_SESSION['hideSeedless'] = 'no';
$hideSeedless = $_SESSION['hideSeedless'];
$pg = tfb_getRequestVar('pg');
$searchEngine = tfb_getRequestVar('searchEngine');
if (empty($searchEngine))
	$searchEngine = $cfg["searchEngine"];
if (!preg_match('/^[a-zA-Z0-9]+$/D', $searchEngine))
	error("Invalid SearchEngine", "", "");
$searchterm = tfb_getRequestVar('searchterm');
if (empty($searchterm))
	$searchterm = tfb_getRequestVar('query');
$searchterm = str_replace(" ", "+",$searchterm);
if (empty($searchterm)) {
	// no searchterm set the get latest flag.
	$_REQUEST["LATEST"] = "1";
}
$tmpl->setvar('searchterm', str_replace("+", " ",$searchterm));
$tmpl->setloop('Engine_List', tmplSetSearchEngineDDL($searchEngine));
$tmpl->setvar('searchEngine', $searchEngine);
// Check if Search Engine works properly
if (!is_file('inc/searchEngines/'.$searchEngine.'Engine.php')) {
	$tmpl->setvar('sEngine_error', 1);
	$tmpl->setvar('sEngine_msg', "Search Engine not installed.");
} else {
	include_once('inc/searchEngines/'.$searchEngine.'Engine.php');
	$sEngine = new SearchEngine(serialize($cfg));
	if (!$sEngine->initialized) {
		$tmpl->setvar('sEngine_error', 1);
		$tmpl->setvar('sEngine_msg', $sEngine->msg);
	} else {
		// Search Engine ready to go
		$mainStart = true;
		$catLinks = '';
		$tmpCatLinks = '';
		$tmpLen = 0;
		$link_list = array();
		foreach ($sEngine->getMainCategories() as $mainId => $mainName) {
			array_push($link_list, array(
				'searchEngine' => $searchEngine,
				'mainId' => $mainId,
				'mainName' => $mainName
				)
			);
		}
		$tmpl->setloop('link_list', $link_list);
		$mainGenre = tfb_getRequestVar('mainGenre');
		$subCats = $sEngine->getSubCategories($mainGenre);
		if ((empty($mainGenre) && array_key_exists("subGenre", $_REQUEST)) || (count($subCats) <= 0)) {
			$tmpl->setvar('no_genre', 1);
			$tmpl->setvar('performSearch', (array_key_exists("LATEST", $_REQUEST) && $_REQUEST["LATEST"] == "1")
				? $sEngine->getLatest()
				: $sEngine->performSearch($searchterm)
			);
		} else {
			$mainGenreName = $sEngine->GetMainCatName($mainGenre);
			$tmpl->setvar('mainGenreName', $mainGenreName);
			$list_cats = array();
			foreach ($subCats as $subId => $subName) {
				array_push($list_cats, array(
					'subId' => $subId,
					'subName' => $subName
					)
				);
			}
			$tmpl->setloop('list_cats', $list_cats);
		}
	}
}
//
$tmpl->setvar('_SEARCH', $cfg['_SEARCH']);
//
tmplSetTitleBar("Torrent ".$cfg['_SEARCH']);
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>