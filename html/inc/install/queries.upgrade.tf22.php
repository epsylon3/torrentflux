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
// updates + deletes
array_push($queries[$cqt][$cdb], "UPDATE tf_users SET theme = 'default'");
// tf_settings
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('max_upload_rate','10')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('max_download_rate','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('max_uploads','4')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('minport','49160')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('maxport','49300')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('rerequest_interval','1800')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_search','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('show_server_load','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('loadavg_path','/proc/loadavg')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('days_to_keep','30')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('minutes_to_keep','3')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('rss_cache_min','20')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('page_refresh','60')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('default_theme','default')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('default_language','lang-english.php')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('debug_sql','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('die_when_done','False')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('sharekill','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('pythonCmd','/usr/bin/python')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('searchEngine','TorrentSpy')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('TorrentSpyGenreFilter','a:1:{i:0;s:0:\"\";}')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('TorrentBoxGenreFilter','a:1:{i:0;s:0:\"\";}')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('TorrentPortalGenreFilter','a:1:{i:0;s:0:\"\";}')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_metafile_download','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_file_priority','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('searchEngineLinks','a:5:{s:7:\"isoHunt\";s:11:\"isohunt.com\";s:7:\"NewNova\";s:11:\"newnova.org\";s:10:\"TorrentBox\";s:14:\"torrentbox.com\";s:13:\"TorrentPortal\";s:17:\"torrentportal.com\";s:10:\"TorrentSpy\";s:14:\"torrentspy.com\";}')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('maxcons','40')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('showdirtree','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('maxdepth','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_multiops','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_wget','2')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_multiupload','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_xfer','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_public_xfer','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_grep','/bin/grep')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_netstat','/bin/netstat')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_php','/usr/bin/php')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_awk','/usr/bin/awk')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_du','/usr/bin/du')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_wget','/usr/bin/wget')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_unrar','/usr/bin/unrar')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_unzip','/usr/bin/unzip')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_cksfv','/usr/bin/cksfv')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_uudeview','/usr/local/bin/uudeview')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('btclient','tornado')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('btclient_tornado_options','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('btclient_transmission_bin','/usr/local/bin/transmissioncli')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('btclient_transmission_options','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('metainfoclient','btshowmetainfo.py')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_restrictivetview','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('perlCmd','/usr/bin/perl')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('ui_displayfluxlink','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('ui_dim_main_w','900')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_bigboldwarning','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_goodlookstats','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('ui_displaylinks','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('ui_displayusers','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('xfer_total','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('xfer_month','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('xfer_week','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('xfer_day','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_bulkops','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('week_start','Monday')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('month_start','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('hack_multiupload_rows','6')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('hack_goodlookstats_settings','63')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_dereferrer','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('auth_type','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_page_connections','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_page_stats','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_page_sortorder','dd')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_page_settings','1266')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_sockstat','/usr/bin/sockstat')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nice_adjust','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('xfer_realtime','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('skiphashcheck','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_umask','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_sorttable','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('drivespacebar','tf')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bin_vlc','/usr/local/bin/vlc')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('debuglevel','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('docroot','/var/www/')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_ajax_update_silent','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_ajax_update_users','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('wget_ftp_pasv','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('wget_limit_retries','3')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('wget_limit_rate','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_ajax_update_title','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_ajax_update_list','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_meta_refresh','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_index_ajax_update','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_ajax_update','10')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transferStatsType','ajax')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transferStatsUpdate','5')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('auth_basic_realm','torrentflux-b4rt')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('servermon_update','5')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_home_dirs','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('path_incoming','incoming')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_tmpl_cache','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('btclient_mainline_options','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bandwidthbar','tf')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('display_seeding_time','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('ui_displaybandwidthbars','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bandwidth_down','10240')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('bandwidth_up','10240')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('webapp_locked','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_btclient_chooser','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transfer_profiles','3')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transfer_customize_settings','2')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transferHosts','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('pagetitle','torrentflux-b4rt')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_sharekill','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('transfer_window_default','transferStats')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('index_show_seeding','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_personal_settings','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('enable_nzbperl','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_badAction','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_server','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_user','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_pw','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_threads','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_conn','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_rate','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_create','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('nzbperl_options','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluazu_host','localhost')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluazu_port','6884')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluazu_secure','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluazu_user','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluazu_pw','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_dbmode','php')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_loglevel','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Fluxinet_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Qmgr_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Rssad_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Watch_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Trigger_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Maintenance_enabled','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Fluxinet_port','3150')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Qmgr_interval','15')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Qmgr_maxTotalTransfers','5')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Qmgr_maxUserTransfers','2')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Rssad_interval','1800')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Rssad_jobs','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Watch_interval','120')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Watch_jobs','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Maintenance_interval','600')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Maintenance_trestart','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings VALUES ('fluxd_Trigger_interval','600')");
// tf_settings_dir
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('dir_public_read','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('dir_public_write','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('dir_enable_chmod','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_dirstats','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_maketorrent','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('dir_maketorrent_default','tornado')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_file_download','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_view_nfo','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('package_type','tar')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_sfvcheck','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_rar','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_move','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_rename','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('move_paths','')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('dir_restricted','lost+found:CVS:Temporary Items:Network Trash Folder:TheVolumeSettingsFolder')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('enable_vlc','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_dir VALUES ('vlc_port','8080')");
// tf_settings_stats
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_enable_public','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_show_usage','1')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_deflate_level','9')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_txt_delim',';')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_default_header','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_default_type','all')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_default_format','xml')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_default_attach','0')");
array_push($queries[$cqt][$cdb], "INSERT INTO tf_settings_stats VALUES ('stats_default_compress','0')");

// -----------------------------------------------------------------------------
// SQL : mysql
// -----------------------------------------------------------------------------
$cdb = 'mysql';

// sql-queries : Test
$cqt = 'test';
$queries[$cqt][$cdb] = array();
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_test (
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL,
  PRIMARY KEY (tf_key)
) TYPE=MyISAM");
array_push($queries[$cqt][$cdb], "DROP TABLE tf_test");

// sql-queries : Create
$cqt = 'create';
$queries[$cqt][$cdb] = array();
// tf_transfers
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfers (
  transfer VARCHAR(255) NOT NULL default '',
  type ENUM('torrent','wget','nzb') NOT NULL default 'torrent',
  client ENUM('tornado','transmission','mainline','azureus','wget','nzbperl') NOT NULL default 'tornado',
  hash VARCHAR(40) NOT NULL DEFAULT '',
  datapath VARCHAR(255) NOT NULL default '',
  savepath VARCHAR(255) NOT NULL default '',
  running ENUM('0','1') NOT NULL default '0',
  rate SMALLINT(4) NOT NULL default '0',
  drate SMALLINT(4) NOT NULL default '0',
  maxuploads TINYINT(3) unsigned NOT NULL default '0',
  superseeder ENUM('0','1') NOT NULL default '0',
  runtime ENUM('True','False') NOT NULL default 'False',
  sharekill SMALLINT(4) unsigned NOT NULL default '0',
  minport SMALLINT(5) unsigned NOT NULL default '0',
  maxport SMALLINT(5) unsigned NOT NULL default '0',
  maxcons SMALLINT(4) unsigned NOT NULL default '0',
  rerequest MEDIUMINT(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (transfer)
) TYPE=MyISAM");
// tf_transfer_totals
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfer_totals (
  tid VARCHAR(40) NOT NULL default '',
  uptotal BIGINT(80) NOT NULL default '0',
  downtotal BIGINT(80) NOT NULL default '0',
  PRIMARY KEY  (tid)
) TYPE=MyISAM");
// tf_trprofiles
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_trprofiles (
  id MEDIUMINT(8) NOT NULL auto_increment,
  name VARCHAR(255) NOT NULL default '',
  owner INT(10) NOT NULL default '0',
  public ENUM('0','1') NOT NULL default '0',
  rate SMALLINT(4) NOT NULL default '0',
  drate SMALLINT(4) NOT NULL default '0',
  maxuploads TINYINT(3) unsigned NOT NULL default '0',
  superseeder ENUM('0','1') NOT NULL default '0',
  runtime ENUM('True','False') NOT NULL default 'False',
  sharekill SMALLINT(4) unsigned NOT NULL default '0',
  minport SMALLINT(5) unsigned NOT NULL default '0',
  maxport SMALLINT(5) unsigned NOT NULL default '0',
  maxcons SMALLINT(4) unsigned NOT NULL default '0',
  rerequest MEDIUMINT(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) TYPE=MyISAM");
// tf_xfer
array_push($queries[$cqt][$cdb], "DROP TABLE IF EXISTS tf_xfer");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_xfer (
  user_id VARCHAR(32) NOT NULL default '',
  date DATE NOT NULL default '0000-00-00',
  download BIGINT(80) NOT NULL default '0',
  upload BIGINT(80) NOT NULL default '0',
  PRIMARY KEY  (user_id,date)
) TYPE=MyISAM");
// tf_settings_user
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_user (
  uid INT(10) NOT NULL,
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL
) TYPE=MyISAM");
// tf_settings_dir
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_dir (
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL,
  PRIMARY KEY  (tf_key)
) TYPE=MyISAM");
// tf_settings_stats
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_stats (
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL,
  PRIMARY KEY  (tf_key)
) TYPE=MyISAM");
// ALTER
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users CHANGE user_id user_id VARCHAR(32) BINARY NOT NULL");
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users ADD state TINYINT(1) DEFAULT '1' NOT NULL");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);
// tf_links
array_push($queries[$cqt][$cdb], "INSERT INTO tf_links VALUES (NULL,'http://tf-b4rt.berlios.de/','tf-b4rt','0')");

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

// sql-queries : Create
$cqt = 'create';
$queries[$cqt][$cdb] = array();
// tf_transfers
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfers (
  transfer VARCHAR(255) NOT NULL DEFAULT '',
  type VARCHAR(32) NOT NULL DEFAULT 'torrent',
  client VARCHAR(32) NOT NULL DEFAULT 'tornado',
  hash VARCHAR(40) DEFAULT '' NOT NULL,
  datapath VARCHAR(255) NOT NULL DEFAULT '',
  savepath VARCHAR(255) NOT NULL DEFAULT '',
  running SMALLINT NOT NULL DEFAULT '0',
  rate INTEGER NOT NULL DEFAULT '0',
  drate INTEGER NOT NULL DEFAULT '0',
  maxuploads SMALLINT NOT NULL DEFAULT '0',
  superseeder SMALLINT NOT NULL DEFAULT '0',
  runtime VARCHAR(5) NOT NULL DEFAULT 'False',
  sharekill INTEGER NOT NULL DEFAULT '0',
  minport INTEGER NOT NULL DEFAULT '0',
  maxport INTEGER NOT NULL DEFAULT '0',
  maxcons INTEGER NOT NULL DEFAULT '0',
  rerequest INTEGER NOT NULL DEFAULT '0',
  PRIMARY KEY (transfer),
  CHECK (running>=0),
  CHECK (maxuploads>=0),
  CHECK (minport>=0),
  CHECK (maxport>=0),
  CHECK (maxcons>=0),
  CHECK (rerequest>=0)
)");
// tf_transfer_totals
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_transfer_totals (
  tid VARCHAR(40) NOT NULL DEFAULT '',
  uptotal BIGINT NOT NULL DEFAULT '0',
  downtotal BIGINT NOT NULL DEFAULT '0',
  PRIMARY KEY (tid)
)");
// tf_trprofiles
array_push($queries[$cqt][$cdb], "CREATE SEQUENCE tf_trprofiles_id_seq");
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_trprofiles (
  id INTEGER DEFAULT nextval('tf_trprofiles_id_seq'),
  name VARCHAR(255) NOT NULL DEFAULT '',
  owner INTEGER NOT NULL DEFAULT '0',
  public SMALLINT NOT NULL DEFAULT '0',
  rate INTEGER NOT NULL DEFAULT '0',
  drate INTEGER NOT NULL DEFAULT '0',
  maxuploads SMALLINT NOT NULL DEFAULT '0',
  superseeder SMALLINT NOT NULL DEFAULT '0',
  runtime VARCHAR(5) NOT NULL DEFAULT 'False',
  sharekill INTEGER NOT NULL DEFAULT '0',
  minport INTEGER NOT NULL DEFAULT '0',
  maxport INTEGER NOT NULL DEFAULT '0',
  maxcons INTEGER NOT NULL DEFAULT '0',
  rerequest INTEGER NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  CHECK (public>=0),
  CHECK (maxuploads>=0),
  CHECK (minport>=0),
  CHECK (maxport>=0),
  CHECK (maxcons>=0),
  CHECK (rerequest>=0)
)");
// tf_xfer
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_xfer (
  user_id VARCHAR(32) NOT NULL DEFAULT '',
  date DATE NOT NULL DEFAULT '0001-01-01',
  download BIGINT NOT NULL DEFAULT '0',
  upload BIGINT NOT NULL DEFAULT '0'
)");
// tf_settings_user
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_user (
  uid INTEGER NOT NULL,
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL
)");
// tf_settings_dir
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_dir (
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY (tf_key)
)");
// tf_settings_stats
array_push($queries[$cqt][$cdb], "
CREATE TABLE tf_settings_stats (
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY (tf_key)
)");
// ALTER
array_push($queries[$cqt][$cdb], "ALTER TABLE tf_users ADD state SMALLINT NOT NULL DEFAULT '1'");

// sql-queries : Data
$cqt = 'data';
$queries[$cqt][$cdb] = array();
foreach ($queries['data']['common'] as $dataQuery)
	array_push($queries[$cqt][$cdb], $dataQuery);

// sequences
array_push($queries[$cqt][$cdb], "SELECT SETVAL('tf_trprofiles_id_seq',(select case when max(id)>0 then max(id)+1 else 1 end from tf_trprofiles))");

?>