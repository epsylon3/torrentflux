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

// readrss functions
require_once('inc/functions/functions.readrss.php');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.default.tmpl");

// set vars
$tmpl->setvar('enable_xfer', $cfg["enable_xfer"]);
tmplSetTitleBar($cfg['_ADMINISTRATION']);
tmplSetAdminMenu();

// L: tfb-stats
// transfers
$arTransfers = getTransferArray();
$countTransfers = count($arTransfers);
$tmpl->setvar('server_transfers_total', $countTransfers);
// users
$countUsers = count($cfg['users']);
$tmpl->setvar('server_users_total', $countUsers);
// hits
$hits = $db->GetOne("SELECT SUM(hits) AS hits FROM tf_users");
$tmpl->setvar('server_hits_total', $hits);
// log
$log = $db->GetOne("SELECT COUNT(cid) AS cid FROM tf_log");
$tmpl->setvar('server_logs_total', $log);
// messages
$messages = $db->GetOne("SELECT COUNT(mid) AS mid FROM tf_messages");
$tmpl->setvar('server_messages_total', $messages);
// links
$countLinks = (isset($cfg['linklist']))
	? count($cfg['linklist'])
	: 0;
$tmpl->setvar('server_links_total', $countLinks);
// rss
$arRss = GetRSSLinks();
$countRss = count($arRss);
$tmpl->setvar('server_rss_total', $countRss);
// cookies
$cookies = $db->GetOne("SELECT COUNT(cid) AS cid FROM tf_cookies");
$tmpl->setvar('server_cookies_total', $cookies);
// profiles
$profiles = $db->GetOne("SELECT COUNT(id) AS id FROM tf_trprofiles");
$tmpl->setvar('server_profiles_total', $profiles);
// search-engines
$arSearchEngines = tmplSetSearchEngineDDL($cfg["searchEngine"]);
$countSearchEngines = count($arSearchEngines);
$tmpl->setvar('server_searchengines_total', $countSearchEngines);
// themes
$arThemes = GetThemes();
$countThemes = count($arThemes);
$tmpl->setvar('server_themes_total', $countThemes);
// themes standard
$arThemesStandard = GetThemesStandard();
$countThemesStandard = count($arThemesStandard);
$tmpl->setvar('server_themes_standard_total', $countThemesStandard);
// languages
$arLang = GetLanguages();
$countLang = count($arLang);
$tmpl->setvar('server_lang_total', $countLang);
// du
switch ($cfg["_OS"]) {
	case 1: //Linux
		$duArg = "-D";
		break;
	case 2: //BSD
		$duArg = "-L";
		break;
}
$du = @shell_exec($cfg['bin_du']." -ch ".tfb_shellencode($duArg)." ".tfb_shellencode($cfg['docroot'])." | ".$cfg['bin_grep']." \"total\"");
$tmpl->setvar('server_du_total', substr($du, 0, -7));
// version
$tmpl->setvar('server_version', $cfg["version"]);

// M: db-settings
$tmpl->setvar('db_type', $cfg["db_type"]);
$tmpl->setvar('db_host', $cfg["db_host"]);
$tmpl->setvar('db_name', $cfg["db_name"]);
$tmpl->setvar('db_user', $cfg["db_user"]);
$tmpl->setvar('db_pcon', ($cfg["db_pcon"]) ? "true" : "false");

// R: server-stats
$tmpl->setvar('server_os', php_uname('s'));
$tmpl->setvar('server_php', PHP_VERSION);
$tmpl->setvar('server_php_state', (PHP_VERSION < 4.3) ? 0 : 1);
$loadedExtensions = get_loaded_extensions();
if (in_array("session", $loadedExtensions)) {
	$tmpl->setvar('server_extension_session', "yes");
	$tmpl->setvar('server_extension_session_state', 1);
} else {
	$tmpl->setvar('server_extension_session', "no");
	$tmpl->setvar('server_extension_session_state', 0);
}
if (in_array("pcre", $loadedExtensions)) {
	$tmpl->setvar('server_extension_pcre', "yes");
	$tmpl->setvar('server_extension_pcre_state', 1);
} else {
	$tmpl->setvar('server_extension_pcre', "no");
	$tmpl->setvar('server_extension_pcre_state', 0);
}
if (in_array("sockets", $loadedExtensions)) {
	$tmpl->setvar('server_extension_sockets', "yes");
	$tmpl->setvar('server_extension_sockets_state', 1);
} else {
	$tmpl->setvar('server_extension_sockets', "no");
	$tmpl->setvar('server_extension_sockets_state', 0);
}
$safe_mode = ini_get("safe_mode");
if ($safe_mode) {
	$tmpl->setvar('server_ini_safe_mode', "on");
	$tmpl->setvar('server_ini_safe_mode_state', 0);
} else {
	$tmpl->setvar('server_ini_safe_mode', "off");
	$tmpl->setvar('server_ini_safe_mode_state', 1);
}
$allow_url_fopen = ini_get("allow_url_fopen");
if ($allow_url_fopen) {
	$tmpl->setvar('server_ini_allow_url_fopen', "on");
	$tmpl->setvar('server_ini_allow_url_fopen_state', 1);
} else {
	$tmpl->setvar('server_ini_allow_url_fopen', "off");
	$tmpl->setvar('server_ini_allow_url_fopen_state', 0);
}
$register_globals = ini_get("register_globals");
if ($register_globals) {
	$tmpl->setvar('server_ini_register_globals', "on");
	$tmpl->setvar('server_ini_register_globals_state', 0);
} else {
	$tmpl->setvar('server_ini_register_globals', "off");
	$tmpl->setvar('server_ini_register_globals_state', 1);
}
$imageSupported = Image::isSupported();
$imageTypes = array();
if (Image::isTypeSupported(IMG_GIF))
	array_push($imageTypes, "gif");
if (Image::isTypeSupported(IMG_PNG))
	array_push($imageTypes, "png");
if (Image::isTypeSupported(IMG_JPG))
	array_push($imageTypes, "jpg");
if ($imageSupported) {
	$tmpl->setvar('server_image', implode("/", $imageTypes));
	$tmpl->setvar('server_image_state', 1);
} else {
	$tmpl->setvar('server_image', "none");
	$tmpl->setvar('server_image_state', 0);
}

if (IsSuperAdmin()) {

	// superadmin-link-prefix
	$linkPrefix = '<img src="themes/';
	$linkPrefix .= ((strpos($cfg["theme"], '/')) === false)
		? $cfg["theme"].'/images/'
		: 'tf_standard_themes/images/';
	$linkPrefix .= 'arrow.gif" width="9" height="9"';

	// superadmin-main-links
	$sa_links_main = array();
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?t=0', $linkPrefix.' title="Superadmin - Transfer Bulk Ops" border="0"> Transfer Bulk Ops</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?p=0', $linkPrefix.' title="Superadmin - Processes" border="0"> Processes</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?m=0', $linkPrefix.' title="Superadmin - Maintenance" border="0"> Maintenance</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?b=0', $linkPrefix.' title="Superadmin - Backup" border="0"> Backup</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?l=0', $linkPrefix.' title="Superadmin - Log" border="0"> Log</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?y=0', $linkPrefix.' title="Superadmin - Misc" border="0"> Misc</a>')));
	array_push($sa_links_main, array("sa_link" => getSuperAdminLink('?z=0', $linkPrefix.' title="Superadmin - About" border="0"> About</a>')));
	$tmpl->setloop('superadminlinks_main', $sa_links_main);

	// check-links
	$sa_links_check = array();
	array_push($sa_links_check, array("sa_link" => getSuperAdminLink('?y=51', $linkPrefix.' title="PHP-Web Requirements Check" border="0"> Check PHP-Web</a>')));
	array_push($sa_links_check, array("sa_link" => getSuperAdminLink('?y=52', $linkPrefix.' title="PHP-CLI Requirements Check" border="0"> Check PHP-CLI</a>')));
	array_push($sa_links_check, array("sa_link" => getSuperAdminLink('?y=53', $linkPrefix.' title="Perl Requirements Check" border="0"> Check Perl</a>')));
	$tmpl->setloop('superadminlinks_check', $sa_links_check);
}

// foot
tmplSetFoot();

// set iid-vars
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>