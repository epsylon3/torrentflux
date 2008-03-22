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

// Image class
require_once('inc/classes/Image.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.webappSettings.tmpl");

// set vars

// auth-vars
$authlist = array();
// Form-Auth
array_push($authlist, array(
	'avalue' => 0,
	'atype' => "Form-Auth",
	'aselected' => ($cfg["auth_type"] == 0) ? 1 : 0
	)
);
// Form-Auth + Cookie
array_push($authlist, array(
	'avalue' => 1,
	'atype' => "Form-Auth + Cookie",
	'aselected' => ($cfg["auth_type"] == 1) ? 1 : 0
	)
);
// Form-Auth + Image-Validation
if (Image::isSupported()) {
	array_push($authlist, array(
		'avalue' => 4,
		'atype' => "Form-Auth + Image-Validation",
		'aselected' => ($cfg["auth_type"] == 4) ? 1 : 0
		)
	);
}
// Basic-Auth
array_push($authlist, array(
	'avalue' => 2,
	'atype' => "Basic-Auth",
	'aselected' => ($cfg["auth_type"] == 2) ? 1 : 0
	)
);
// Basic-Passthru
array_push($authlist, array(
	'avalue' => 3,
	'atype' => "Basic-Passthru",
	'aselected' => ($cfg["auth_type"] == 3) ? 1 : 0
	)
);
$tmpl->setloop('auth_type_list', $authlist);
$tmpl->setvar('auth_type', $cfg["auth_type"]);
$tmpl->setvar('auth_basic_realm', $cfg["auth_basic_realm"]);

// more vars
$tmpl->setvar('enable_tmpl_cache', $cfg["enable_tmpl_cache"]);
$link = '<img src="themes/';
if ((strpos($cfg["theme"], '/')) === false)
	$link .= $cfg["theme"].'/images/';
else
	$link .= 'tf_standard_themes/images/';
$link .= 'arrow.gif" width="9" height="9" title="Clean Template Cache Now" border="0"> Clean Template Cache Now</a>';
$tmpl->setvar('SuperAdminLink_tmplCache', getSuperAdminLink('?m=35', $link));
$tmpl->setvar('enable_personal_settings', $cfg["enable_personal_settings"]);
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
$tmpl->setvar('days_to_keep', $cfg["days_to_keep"]);
$tmpl->setvar('minutes_to_keep', $cfg["minutes_to_keep"]);
$tmpl->setvar('rss_cache_min', $cfg["rss_cache_min"]);
$tmpl->setvar('debug_sql', $cfg["debug_sql"]);
$tmpl->setvar('debuglevel', $cfg["debuglevel"]);
//
$tmpl->setvar('drivespacebar', $cfg["drivespacebar"]);
$tmpl->setvar('servermon_update', $cfg["servermon_update"]);
$tmpl->setvar('transferHosts', $cfg["transferHosts"]);
$tmpl->setvar('transferStatsType', $cfg["transferStatsType"]);
$tmpl->setvar('transferStatsUpdate', $cfg["transferStatsUpdate"]);
// themes
$theme_list = array();
$arThemes = GetThemes();
for ($inx = 0; $inx < sizeof($arThemes); $inx++) {
	array_push($theme_list, array(
		'arThemes' => preg_replace('/_beta$/',' (beta)',$arThemes[$inx]),
		'arThemes2' => $arThemes[$inx],
		'selected' => ($cfg["default_theme"] == $arThemes[$inx]) ? "selected=\"selected\"" : ""
		)
	);
}
$tmpl->setloop('theme_list', $theme_list);
// tf standard themes
$tfstandard_theme_list = array();
$arThemes = GetThemesStandard();
for ($inx = 0; $inx < sizeof($arThemes); $inx++) {
	$arThemes2[$inx] = "tf_standard_themes/".$arThemes[$inx];
	array_push($tfstandard_theme_list, array(
		'arThemes'  => $arThemes[$inx],
		'arThemes2' => $arThemes2[$inx],
		'selected'  => ($cfg["default_theme"] == $arThemes2[$inx]) ? "selected=\"selected\"" : ""
		)
	);
}
$tmpl->setloop('tfstandard_theme_list', $tfstandard_theme_list);
// languages
$lang_list = array();
$arLanguage = GetLanguages();
for ($inx = 0; $inx < sizeof($arLanguage); $inx++) {
	array_push($lang_list, array(
		'arLanguage' => $arLanguage[$inx],
		'selected' => ($cfg["default_language"] == $arLanguage[$inx]) ? "selected" : "",
		'GetLanguageFromFile' => GetLanguageFromFile($arLanguage[$inx])
		)
	);
}
$tmpl->setloop('lang_list', $lang_list);
//
tmplSetTitleBar("Administration - WebApp Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>