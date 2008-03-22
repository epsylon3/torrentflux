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
tmplInitializeInstance($cfg["theme"], "page.admin.serverSettings.tmpl");

// set vars
// path
$tmpl->setvar('path', $cfg["path"]);
if (is_dir($cfg["path"])) {
	$tmpl->setvar('is_path', 1);
	$tmpl->setvar('is_writable', (is_writable($cfg["path"])) ? 1 : 0);
} else {
	$tmpl->setvar('is_path', 0);
}
// docroot
$tmpl->setvar('docroot', $cfg["docroot"]);
$tmpl->setvar('is_docroot', (is_file($cfg["docroot"]."version.php")) ? 1 : 0);
// homedirs + incoming
$tmpl->setvar('enable_home_dirs', $cfg["enable_home_dirs"]);
$tmpl->setvar('path_incoming', $cfg["path_incoming"]);
$tmpl->setvar('path_incoming_ok', (checkDirectory($cfg["path"].$cfg["path_incoming"], 0777)) ? 1 : 0);
// bins
$tmpl->setvar('btclient_transmission_bin', $cfg["btclient_transmission_bin"]);
$tmpl->setvar('validate_transmission_bin', validateTransmissionCli($cfg["btclient_transmission_bin"]));
$tmpl->setvar('perlCmd', $cfg["perlCmd"]);
$tmpl->setvar('validate_perl', validateBinary($cfg["perlCmd"]));
$tmpl->setvar('bin_grep', $cfg["bin_grep"]);
$tmpl->setvar('validate_grep', validateBinary($cfg["bin_grep"]));
$tmpl->setvar('bin_php', $cfg["bin_php"]);
$tmpl->setvar('validate_php', validatePhpCli($cfg["bin_php"]));
$tmpl->setvar('pythonCmd', $cfg["pythonCmd"]);
$tmpl->setvar('validate_python', validateBinary($cfg["pythonCmd"]));
$tmpl->setvar('bin_awk', $cfg["bin_awk"]);
$tmpl->setvar('validate_awk', validateBinary($cfg["bin_awk"]));
$tmpl->setvar('bin_du', $cfg["bin_du"]);
$tmpl->setvar('validate_du', validateBinary($cfg["bin_du"]));
$tmpl->setvar('bin_wget', $cfg["bin_wget"]);
$tmpl->setvar('validate_wget', validateBinary($cfg["bin_wget"]));
$tmpl->setvar('bin_uudeview', $cfg["bin_uudeview"]);
$tmpl->setvar('validate_uudeview', validateBinary($cfg["bin_uudeview"]));
$tmpl->setvar('bin_unzip', $cfg["bin_unzip"]);
$tmpl->setvar('validate_unzip', validateBinary($cfg["bin_unzip"]));
$tmpl->setvar('bin_cksfv', $cfg["bin_cksfv"]);
$tmpl->setvar('validate_cksfv', validateBinary($cfg["bin_cksfv"]));
$tmpl->setvar('bin_vlc', $cfg["bin_vlc"]);
$tmpl->setvar('validate_vlc', validateBinary($cfg["bin_vlc"]));
$tmpl->setvar('php_uname1', php_uname('s'));
$tmpl->setvar('php_uname2', php_uname('r'));
$tmpl->setvar('bin_unrar', $cfg["bin_unrar"]);
$tmpl->setvar('validate_unrar', validateBinary($cfg["bin_unrar"]));
switch ($cfg["_OS"]) {
	case 1:
		$tmpl->setvar('loadavg_path', $cfg["loadavg_path"]);
		$tmpl->setvar('validate_loadavg', validateFile($cfg["loadavg_path"]));
		$tmpl->setvar('bin_netstat', $cfg["bin_netstat"]);
		$tmpl->setvar('validate_netstat', validateBinary($cfg["bin_netstat"]));
		break;
	case 2:
		$tmpl->setvar('bin_sockstat', $cfg["bin_sockstat"]);
		$tmpl->setvar('validate_sockstat', validateBinary($cfg["bin_sockstat"]));
		break;
}
//
$tmpl->setvar('_OS', $cfg["_OS"]);
//
tmplSetTitleBar("Administration - Server Settings");
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>