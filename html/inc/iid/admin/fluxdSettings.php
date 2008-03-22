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

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.fluxdSettings.tmpl");

// superadmin-links
$tmpl->setvar('SuperAdminLink1', getSuperAdminLink('?f=1','<font class="adminlink">log</font></a>'));
$tmpl->setvar('SuperAdminLink2', getSuperAdminLink('?f=2','<font class="adminlink">error-log</font></a>'));
$tmpl->setvar('SuperAdminLink3', getSuperAdminLink('?f=3','<font class="adminlink">ps</font></a>'));
$tmpl->setvar('SuperAdminLink4', getSuperAdminLink('?f=4','<font class="adminlink">status</font></a>'));
$tmpl->setvar('SuperAdminLink5', getSuperAdminLink('?f=5','<font class="adminlink">check</font></a>'));
$tmpl->setvar('SuperAdminLink6', getSuperAdminLink('?f=6','<font class="adminlink">db-debug</font></a>'));
$tmpl->setvar('SuperAdminLink9', getSuperAdminLink('?f=9','<font class="adminlink">version</font></a>'));

// message section
$message = tfb_getRequestVar('m');
if ($message != "")
	$tmpl->setvar('message', urldecode($message));

// fluxd requirements checks
$failed = 0;

// check the needed bins
// perl
if (@file_exists($cfg['perlCmd']) !== true) {
	$failed++;
	$tmpl->setvar('perlMissing', 1);
}
// php-cli
if (@file_exists($cfg['bin_php']) !== true) {
	$failed++;
	$tmpl->setvar('phpMissing', 1);
}

// check for sockets
$loadedExtensions = get_loaded_extensions();
if (!in_array("sockets", $loadedExtensions)) {
	$failed++;
	$tmpl->setvar('socketsMissing', 1);
}

// set supported-var
$tmpl->setvar('fluxdSupported', ($failed > 0) ? 0 : 1);

// fluxd core
if (Fluxd::isRunning()) {
	$tmpl->setvar('fluxdRunning', 1);
	$tmpl->setvar('fluxdPid', Fluxd::getPid());
} else {
	$tmpl->setvar('fluxdRunning', 0);
}
$tmpl->setvar('fluxd_dbmode', $cfg["fluxd_dbmode"]);
$tmpl->setvar('fluxd_loglevel', $cfg["fluxd_loglevel"]);

// Qmgr
FluxdServiceMod::initializeServiceMod('Qmgr'); // would not be needed as its done in main
$tmpl->setvar('fluxd_Qmgr_enabled', $cfg["fluxd_Qmgr_enabled"]);
$tmpl->setvar('fluxd_Qmgr_state', FluxdQmgr::getModState());
$tmpl->setvar('fluxd_Qmgr_interval', $cfg["fluxd_Qmgr_interval"]);
$tmpl->setvar('fluxd_Qmgr_maxTotalTransfers', $cfg["fluxd_Qmgr_maxTotalTransfers"]);
$tmpl->setvar('fluxd_Qmgr_maxUserTransfers', $cfg["fluxd_Qmgr_maxUserTransfers"]);

// Watch
FluxdServiceMod::initializeServiceMod('Watch');
$tmpl->setvar('fluxd_Watch_enabled', $cfg["fluxd_Watch_enabled"]);
$tmpl->setvar('fluxd_Watch_state', FluxdWatch::getModState());
$tmpl->setvar('fluxd_Watch_interval', $cfg["fluxd_Watch_interval"]);

// Rssad
FluxdServiceMod::initializeServiceMod('Rssad');
$tmpl->setvar('fluxd_Rssad_enabled', $cfg["fluxd_Rssad_enabled"]);
$tmpl->setvar('fluxd_Rssad_state', FluxdRssad::getModState());
$tmpl->setvar('fluxd_Rssad_interval', $cfg["fluxd_Rssad_interval"]);

// Fluxinet
FluxdServiceMod::initializeServiceMod('Fluxinet');
$tmpl->setvar('fluxd_Fluxinet_enabled', $cfg["fluxd_Fluxinet_enabled"]);
$tmpl->setvar('fluxd_Fluxinet_state', FluxdFluxinet::getModState());
$tmpl->setvar('fluxd_Fluxinet_port', $cfg["fluxd_Fluxinet_port"]);

// Maintenance
FluxdServiceMod::initializeServiceMod('Maintenance');
$tmpl->setvar('fluxd_Maintenance_enabled', $cfg["fluxd_Maintenance_enabled"]);
$tmpl->setvar('fluxd_Maintenance_state', FluxdMaintenance::getModState());
$tmpl->setvar('fluxd_Maintenance_interval', $cfg["fluxd_Maintenance_interval"]);
$tmpl->setvar('fluxd_Maintenance_trestart', $cfg["fluxd_Maintenance_trestart"]);

// Trigger
FluxdServiceMod::initializeServiceMod('Trigger');
$tmpl->setvar('fluxd_Trigger_enabled', $cfg["fluxd_Trigger_enabled"]);
$tmpl->setvar('fluxd_Trigger_state', FluxdTrigger::getModState());
$tmpl->setvar('fluxd_Trigger_interval', $cfg["fluxd_Trigger_interval"]);

// more vars
$tmpl->setvar('_USER', $cfg['_USER']);
$tmpl->setvar('_FILE', $cfg['_FILE']);
$tmpl->setvar('_TIMESTAMP', $cfg['_TIMESTAMP']);
$tmpl->setvar('_FORCESTOP', str_replace(" ","<br>",$cfg['_FORCESTOP']));
// templ-calls
tmplSetTitleBar("Administration - Fluxd Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>