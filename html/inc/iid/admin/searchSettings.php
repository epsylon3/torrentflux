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
	@header("location: ../../../index.php");
	exit();
}

/******************************************************************************/

// search engine base
require_once("inc/searchEngines/SearchEngineBase.php");

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.searchSettings.tmpl");

// set vars
$searchEngine = tfb_getRequestVar('searchEngine');
if (empty($searchEngine))
	$searchEngine = $cfg["searchEngine"];
if (is_file('inc/searchEngines/'.$searchEngine.'Engine.php')) {
	include_once('inc/searchEngines/'.$searchEngine.'Engine.php');
	$sEngine = new SearchEngine(serialize($cfg));
	if ($sEngine->initialized) {
		$tmpl->setvar('is_file', 1);
		$tmpl->setvar('mainTitle', $sEngine->mainTitle);
		$tmpl->setvar('searchEngine', $searchEngine);
		$tmpl->setvar('mainURL', $sEngine->mainURL);
		$tmpl->setvar('author', $sEngine->author);
		$tmpl->setvar('version', $sEngine->version);
		if (strlen($sEngine->updateURL) > 0) {
			$tmpl->setvar('update_pos', 1);
			$tmpl->setvar('updateURL', $sEngine->updateURL);
		}
		if (!($sEngine->catFilterName == '')) {
			$tmpl->setvar('cat_pos', 1);
			$tmpl->setvar('catFilterName', $sEngine->catFilterName);
			$cats = array();
			foreach ($sEngine->getMainCategories(false) as $mainId => $mainName) {
				array_push($cats, array(
					'mainId' => $mainId,
					'in_array' => (@in_array($mainId, $sEngine->catFilter)) ? 1 : 0,
					'mainName' => $mainName
					)
				);
			}
			$tmpl->setloop('cats', $cats);
		}
	} else {
		$tmpl->setvar('is_file', 0);
	}
}
$tmpl->setloop('Engine_List', tmplSetSearchEngineDDL($searchEngine,true));
//
tmplSetTitleBar("Administration - Search Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>