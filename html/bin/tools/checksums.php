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

// prevent invocation from web
if (empty($argv[0])) die();
if (isset($_REQUEST['argv'])) die();

/******************************************************************************/

// change to docroot if needed
if (!is_file(realpath(getcwd().'/inc/functions/functions.tools.php')))
	chdir(realpath(dirname(__FILE__)."/../.."));

// check for home
if (!is_file('inc/functions/functions.tools.php'))
	exit("Error: this script can only be used in its default-path (DOCROOT/bin/tools/)\n");

// tools-functions
require_once('inc/functions/functions.tools.php');

// action
if (isset($argv[1]))
	printFileList($argv[1], 2, 1);
else
	echo "missing param : dir\n";

// exit
exit();

?>