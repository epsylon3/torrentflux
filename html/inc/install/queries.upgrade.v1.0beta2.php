<?php
/*******************************************************************************
 $Id: queries.upgrade.v1.0beta2.php $

 @package torrentflux_Setup
 @license LICENSE http://www.gnu.org/copyleft/gpl.html

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 Set tabs to 4.

*******************************************************************************/

// -----------------------------------------------------------------------------
// SQL : common
// -----------------------------------------------------------------------------
$cdb = 'common';
$queries = array();
$queries['test'] = array();
$queries['create'] = array();
$queries['data'] = array();

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();

// insert
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transmission_rpc_enable','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transmission_rpc_host','127.0.0.1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transmission_rpc_port','9091')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transmission_rpc_user','transmission')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transmission_rpc_password','')");

array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('vuze_rpc_enable','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('vuze_rpc_host','127.0.0.1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('vuze_rpc_port','19091')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('vuze_rpc_user','vuze')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('vuze_rpc_password','')");

array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_torrent','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_ssl','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_port','119')");

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
) ENGINE=MYISAM");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_test");

$cqt = 'create';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE IF NOT EXISTS tf_transmission_user (
  tid VARCHAR(40) NOT NULL default '',
  uid INT(10) NOT NULL default '0',
  PRIMARY KEY  (tid,uid)
) ENGINE=MYISAM");

// ALTER TABLE (need to check for sqlite and postgre)
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfers ADD INDEX hash_idx ( `hash`(8))");

//only needed in mysql, remove enums
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfers CHANGE `type` `type` VARCHAR(32) NOT NULL DEFAULT  'torrent'");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfers CHANGE `client` `client` VARCHAR(32) NOT NULL DEFAULT  'tornado'");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfers ADD `created` TIMESTAMP NULL default CURRENT_TIMESTAMP");

//add user id
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD `uid` INT(10) NOT NULL default '0' AFTER `tid`");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD `created` TIMESTAMP NULL default CURRENT_TIMESTAMP");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals DROP PRIMARY KEY");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD PRIMARY KEY (`tid`,`uid`)");

//add user email
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users ADD email_address VARCHAR(100) NOT NULL default '' AFTER `password`");

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

// CREATE
$cqt = 'create';
$queries[$cqt][$cdb] = array();
//array_push($queries[$cqt][$cdb], "DROP TABLE tf_transmission_user");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transmission_user (
  tid VARCHAR(40) NOT NULL default '',
  uid INTEGER(10) NOT NULL default '0',
  PRIMARY KEY (tid,uid)
)");

// ALTER
//array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD COLUMN uid INTEGER(10) NOT NULL DEFAULT 0");
//array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD COLUMN created CHAR(19) NULL default CURRENT_TIMESTAMP");
array_push($queries[$cqt][$cdb], "CREATE TEMPORARY TABLE bk_transfer_totals AS SELECT * FROM tf_transfer_totals");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_transfer_totals");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfer_totals (
  tid VARCHAR(40) NOT NULL default '',
  uid INTEGER(10) NOT NULL default '0',
  uptotal BIGINT(80) NOT NULL default '0',
  downtotal BIGINT(80) NOT NULL default '0',
  created CHAR(19) NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (tid,uid)
)");
//CURRENT_TIMESTAMP sqlite: "YYYY-MM-DD HH:MM:SS"
array_push($queries[$cqt][$cdb], "INSERT INTO tf_transfer_totals(tid,uid,uptotal,downtotal) SELECT tid,0,uptotal,downtotal FROM bk_transfer_totals");
array_push($queries[$cqt][$cdb], "DROP TABLE bk_transfer_totals");

//array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfers ADD COLUMN created CHAR(19) NULL default CURRENT_TIMESTAMP");
array_push($queries[$cqt][$cdb], "CREATE TEMPORARY TABLE bk_transfers AS SELECT * FROM tf_transfers");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_transfers");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfers (
  transfer VARCHAR(255) NOT NULL default '',
  type VARCHAR(32) NOT NULL default 'torrent',
  client VARCHAR(32) NOT NULL default 'tornado',
  hash VARCHAR(40) DEFAULT '' NOT NULL,
  datapath VARCHAR(255) NOT NULL default '',
  savepath VARCHAR(255) NOT NULL default '',
  running INTEGER(1) NOT NULL default '0',
  rate INTEGER(4) NOT NULL default '0',
  drate INTEGER(4) NOT NULL default '0',
  maxuploads INTEGER(3) NOT NULL default '0',
  superseeder INTEGER(1) NOT NULL default '0',
  runtime VARCHAR(5) NOT NULL default 'False',
  sharekill INTEGER(4) NOT NULL default '0',
  minport INTEGER(5) NOT NULL default '0',
  maxport INTEGER(5) NOT NULL default '0',
  maxcons INTEGER(4) NOT NULL default '0',
  rerequest INTEGER(8) NOT NULL default '1800',
  created CHAR(19) NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (transfer)
)");
//CURRENT_TIMESTAMP sqlite: "YYYY-MM-DD HH:MM:SS"
array_push($queries[$cqt][$cdb], "INSERT INTO tf_transfers(transfer,type,client,hash,datapath,savepath,running,rate,drate,maxuploads,superseeder,runtime,sharekill,minport,maxport,maxcons,rerequest) SELECT transfer,type,client,hash,datapath,savepath,running,rate,drate,maxuploads,superseeder,runtime,sharekill,minport,maxport,maxcons,rerequest FROM bk_transfers");
array_push($queries[$cqt][$cdb], "DROP TABLE bk_transfers");

array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals DROP PRIMARY KEY");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD PRIMARY KEY (tid,uid)");

//array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users ADD COLUMN email_address VARCHAR(100) NOT NULL DEFAULT ''");
array_push($queries[$cqt][$cdb], "CREATE TEMPORARY TABLE bk_users AS SELECT * FROM tf_users");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_users");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_users (
  uid INTEGER PRIMARY KEY,
  user_id VARCHAR(32) NOT NULL default '',
  password VARCHAR(34) NOT NULL default '',
  email_address VARCHAR(100) NOT NULL default '',
  hits INTEGER(10) NOT NULL default '0',
  last_visit VARCHAR(14) NOT NULL default '0',
  time_created VARCHAR(14) NOT NULL default '0',
  user_level TINYINT(1) NOT NULL default '0',
  hide_offline TINYINT(1) NOT NULL default '0',
  theme VARCHAR(100) NOT NULL default 'RedRound',
  language_file VARCHAR(60) default 'lang-english.php',
  state TINYINT(1) NOT NULL default '1'
)");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_users (uid,user_id,password,email_address,hits,last_visit,time_created,user_level,hide_offline,theme,language_file,state)
SELECT uid,user_id,password,'',hits,last_visit,time_created,user_level,hide_offline,theme,language_file,state FROM bk_users");
array_push($queries[$cqt][$cdb], "DROP TABLE bk_users");

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

// CREATE
$cqt = 'create';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transmission_user (
  tid VARCHAR(40) NOT NULL DEFAULT '',
  uid INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (tid,uid) )");

// ALTER TABLE 
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD uid INTEGER NOT NULL DEFAULT 0");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals DROP CONSTRAINT tf_transmission_user_pkey");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_transfer_totals ADD PRIMARY KEY (tid,uid)");

array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users ADD email_address VARCHAR(100) NOT NULL default ''");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);

// sequences
array_push($queries[$cqt][$cdb], "SELECT SETVAL('tf_trprofiles_id_seq',(select case when max(id)>0 then max(id)+1 else 1 end from tf_trprofiles))");

?>