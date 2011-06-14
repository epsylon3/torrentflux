#!/usr/bin/php
<?php
/*
 SQL update file generator - Command line tool

 usage: ./sqlscript.php.sh mysql install

 upgrade (from v1.0beta2):
	./sqlscript.php.sh mysql
        ./sqlscript.php.sh sqlite
        ./sqlscript.php.sh postgres
*/

$UPGRADE_FROM   = 'v1.0beta2';
$UPGRADE_TO     = 'v1.0git';
$DEFAULT_DBTYPE = 'mysql'; //'sqlite','mysql','postgres'

$DBCONF = '../html/inc/config/config.db.php';

global $argv;
if (isset($argv[1]) )
	$type = $argv[1];
else
	$type = $DEFAULT_DBTYPE;

if (isset($argv[2]) && $argv[2]="install") {
	$resfile='queries.install.php';
	$UPGRADE_FROM = 'install';
}
else
	$resfile='queries.upgrade.'.$UPGRADE_FROM.'.php';

$queries = array();
require('../html/inc/install/'.$resfile);
global $queries;

$res = "";
foreach ($queries['create'][$type] as $databaseTypeName => $databaseQuery) {
	if ($databaseTypeName == "mysql") {
		$databaseQuery = str_replace('TYPE=MyISAM','ENGINE=MyISAM DEFAULT CHARSET=utf8;',$databaseQuery);
	}
	echo $databaseQuery.";\n";
	$res .= $databaseQuery.";\n";
}
echo "\n";
$res .= "\n";
foreach ($queries['data'][$type] as $databaseTypeName => $databaseQuery) {
	echo $databaseQuery.";\n";
	$res .= $databaseQuery.";\n";
}

file_put_contents("{$type}_{$UPGRADE_FROM}_{$UPGRADE_TO}.sql",$res);
