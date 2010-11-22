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

// -----------------------------------------------------------------------------
// SQL : common
// -----------------------------------------------------------------------------
$cdb = 'common';

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();

// insert
array_push($queries[$cqt][$cdb], "INSERT INTO tf_links VALUES (NULL,'http://www.torrentflux-ng.org/','TorrentFlux-NG','0')");

// updates + deletes
array_push($queries[$cqt][$cdb], "UPDATE tf_settings SET tf_value = 'TorrentFlux-NG' WHERE tf_key = 'auth_basic_realm'");
array_push($queries[$cqt][$cdb], "UPDATE tf_settings SET tf_value = 'TorrentFlux-NG' WHERE tf_key = 'pagetitle'");
array_push($queries[$cqt][$cdb], "UPDATE tf_settings SET tf_value = 'RedRound' WHERE tf_key = 'default_theme'");
array_push($queries[$cqt][$cdb], "UPDATE tf_users SET theme = 'RedRound'");

// -----------------------------------------------------------------------------
// SQL : mysql
// -----------------------------------------------------------------------------
$cdb = 'mysql';

// sql-queries : Test
$cqt = 'test';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE IF NOT EXISTS tf_test (
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL,
  PRIMARY KEY (tf_key)
) TYPE=MyISAM");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_test");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);

// -----------------------------------------------------------------------------
// SQL : sqlite
// -----------------------------------------------------------------------------
$cdb = 'sqlite';

// sql-queries : Test
$cqt = 'test';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_test (
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL,
  PRIMARY KEY (tf_key) )");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_test");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);

// -----------------------------------------------------------------------------
// SQL : postgres
// -----------------------------------------------------------------------------
$cdb = 'postgres';

// sql-queries : Test
$cqt = 'test';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_test (
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY (tf_key) )");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_test");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);

// sequences
array_push($queries[$cqt][$cdb], "SELECT SETVAL('tf_trprofiles_id_seq',(select case when max(id)>0 then max(id)+1 else 1 end from tf_trprofiles))");

?>