#!/usr/bin/env php
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

// declare ticks
declare(ticks = 1);

// prevent invocation from web
if (empty($argv[0])) die();
if (isset($_REQUEST['argv'])) die();

// dummy
$_SESSION = array('cache' => false);

/******************************************************************************/

// change to docroot if needed
if (!is_file(realpath(getcwd().'/inc/main.core.php')))
	chdir(realpath(dirname(__FILE__)."/.."));

// check for home
if (!is_file('inc/main.core.php'))
	exit("Error: this script can only be used in its default-path (DOCROOT/bin/)\n");

// check args
if ((!isset($argc)) || ($argc < 7))
	exit("Arg Error\n");

// signals
require_once('inc/defines/defines.signals.php');

// main.core
require_once('inc/main.core.php');

// common functions
require_once('inc/functions/functions.common.php');

// wget wrapper class
require_once('inc/classes/Wrapper.wget.php');

// load default-language
loadLanguageFile($cfg["default_language"]);

// from here on the wget-wrapper-class takes over
WrapperWget::start($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6]);

?>