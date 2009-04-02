-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- PostgreSQL-Update-File for 'torrentflux-b4rt-1.0'.
-- Updates a 'Torrentflux 2.2 Final' Database to a 'torrentflux-b4rt-1.0'.
--
-- This Stuff is provided 'as-is'. In no way will the authors be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- begin transaction
--
BEGIN;

--
-- tf_transfers
--
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
);

--
-- tf_transfer_totals
--
CREATE TABLE tf_transfer_totals (
  tid VARCHAR(40) NOT NULL DEFAULT '',
  uptotal BIGINT NOT NULL DEFAULT '0',
  downtotal BIGINT NOT NULL DEFAULT '0',
  PRIMARY KEY (tid)
);

--
-- Sequences for table tf_trprofiles
--
CREATE SEQUENCE tf_trprofiles_id_seq;

--
-- tf_trprofiles
--
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
  savepath VARCHAR(255) NOT NULL default '',
  PRIMARY KEY (id),
  CHECK (public>=0),
  CHECK (maxuploads>=0),
  CHECK (minport>=0),
  CHECK (maxport>=0),
  CHECK (maxcons>=0),
  CHECK (rerequest>=0)
);

--
-- tf_xfer
--
CREATE TABLE tf_xfer (
  user_id VARCHAR(32) NOT NULL DEFAULT '',
  date DATE NOT NULL DEFAULT '0001-01-01',
  download BIGINT NOT NULL DEFAULT '0',
  upload BIGINT NOT NULL DEFAULT '0'
);

--
-- tf_settings_user
--
CREATE TABLE tf_settings_user (
  uid INTEGER NOT NULL,
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL
);

--
-- tf_settings_dir
--
CREATE TABLE tf_settings_dir (
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY (tf_key)
);

INSERT INTO tf_settings_dir VALUES ('dir_public_read','1');
INSERT INTO tf_settings_dir VALUES ('dir_public_write','0');
INSERT INTO tf_settings_dir VALUES ('dir_enable_chmod','1');
INSERT INTO tf_settings_dir VALUES ('enable_dirstats','1');
INSERT INTO tf_settings_dir VALUES ('enable_maketorrent','1');
INSERT INTO tf_settings_dir VALUES ('dir_maketorrent_default','tornado');
INSERT INTO tf_settings_dir VALUES ('enable_file_download','1');
INSERT INTO tf_settings_dir VALUES ('enable_view_nfo','1');
INSERT INTO tf_settings_dir VALUES ('package_type','tar');
INSERT INTO tf_settings_dir VALUES ('enable_sfvcheck','1');
INSERT INTO tf_settings_dir VALUES ('enable_rar','1');
INSERT INTO tf_settings_dir VALUES ('enable_move','0');
INSERT INTO tf_settings_dir VALUES ('enable_rename','1');
INSERT INTO tf_settings_dir VALUES ('move_paths','');
INSERT INTO tf_settings_dir VALUES ('dir_restricted','lost+found:CVS:Temporary Items:Network Trash Folder:TheVolumeSettingsFolder');
INSERT INTO tf_settings_dir VALUES ('enable_vlc','1');
INSERT INTO tf_settings_dir VALUES ('vlc_port','8080');

--
-- tf_settings_stats
--
CREATE TABLE tf_settings_stats (
  tf_key VARCHAR(255) NOT NULL DEFAULT '',
  tf_value TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY (tf_key)
);

INSERT INTO tf_settings_stats VALUES ('stats_enable_public','0');
INSERT INTO tf_settings_stats VALUES ('stats_show_usage','1');
INSERT INTO tf_settings_stats VALUES ('stats_deflate_level','9');
INSERT INTO tf_settings_stats VALUES ('stats_txt_delim',';');
INSERT INTO tf_settings_stats VALUES ('stats_default_header','0');
INSERT INTO tf_settings_stats VALUES ('stats_default_type','all');
INSERT INTO tf_settings_stats VALUES ('stats_default_format','xml');
INSERT INTO tf_settings_stats VALUES ('stats_default_attach','0');
INSERT INTO tf_settings_stats VALUES ('stats_default_compress','0');

--
-- alter
--
ALTER TABLE tf_users ADD state SMALLINT NOT NULL DEFAULT '1';

--
-- updates
--
UPDATE tf_users SET theme = 'default';

--
-- deletes + inserts
--
DELETE FROM tf_settings WHERE tf_key NOT LIKE 'path';
INSERT INTO tf_settings VALUES ('max_upload_rate','10');
INSERT INTO tf_settings VALUES ('max_download_rate','0');
INSERT INTO tf_settings VALUES ('max_uploads','4');
INSERT INTO tf_settings VALUES ('minport','49160');
INSERT INTO tf_settings VALUES ('maxport','49300');
INSERT INTO tf_settings VALUES ('superseeder','0');
INSERT INTO tf_settings VALUES ('rerequest_interval','1800');
INSERT INTO tf_settings VALUES ('enable_search','1');
INSERT INTO tf_settings VALUES ('show_server_load','1');
INSERT INTO tf_settings VALUES ('loadavg_path','/proc/loadavg');
INSERT INTO tf_settings VALUES ('days_to_keep','30');
INSERT INTO tf_settings VALUES ('minutes_to_keep','3');
INSERT INTO tf_settings VALUES ('rss_cache_min','20');
INSERT INTO tf_settings VALUES ('page_refresh','60');
INSERT INTO tf_settings VALUES ('default_theme','default');
INSERT INTO tf_settings VALUES ('default_language','lang-english.php');
INSERT INTO tf_settings VALUES ('debug_sql','1');
INSERT INTO tf_settings VALUES ('die_when_done','False');
INSERT INTO tf_settings VALUES ('sharekill','0');
INSERT INTO tf_settings VALUES ('pythonCmd','/usr/bin/python');
INSERT INTO tf_settings VALUES ('searchEngine','TorrentSpy');
INSERT INTO tf_settings VALUES ('TorrentSpyGenreFilter','a:1:{i:0;s:0:\"\";}');
INSERT INTO tf_settings VALUES ('TorrentBoxGenreFilter','a:1:{i:0;s:0:\"\";}');
INSERT INTO tf_settings VALUES ('TorrentPortalGenreFilter','a:1:{i:0;s:0:\"\";}');
INSERT INTO tf_settings VALUES ('enable_metafile_download','1');
INSERT INTO tf_settings VALUES ('enable_file_priority','1');
INSERT INTO tf_settings VALUES ('searchEngineLinks','a:5:{s:7:\"isoHunt\";s:11:\"isohunt.com\";s:7:\"NewNova\";s:11:\"newnova.org\";s:10:\"TorrentBox\";s:14:\"torrentbox.com\";s:13:\"TorrentPortal\";s:17:\"torrentportal.com\";s:10:\"TorrentSpy\";s:14:\"torrentspy.com\";}');
INSERT INTO tf_settings VALUES ('maxcons','40');
INSERT INTO tf_settings VALUES ('showdirtree','1');
INSERT INTO tf_settings VALUES ('maxdepth','0');
INSERT INTO tf_settings VALUES ('enable_multiops','1');
INSERT INTO tf_settings VALUES ('enable_wget','2');
INSERT INTO tf_settings VALUES ('enable_multiupload','1');
INSERT INTO tf_settings VALUES ('enable_xfer','1');
INSERT INTO tf_settings VALUES ('enable_public_xfer','1');
INSERT INTO tf_settings VALUES ('bin_grep','/bin/grep');
INSERT INTO tf_settings VALUES ('bin_netstat','/bin/netstat');
INSERT INTO tf_settings VALUES ('bin_php','/usr/bin/php');
INSERT INTO tf_settings VALUES ('bin_awk','/usr/bin/awk');
INSERT INTO tf_settings VALUES ('bin_du','/usr/bin/du');
INSERT INTO tf_settings VALUES ('bin_wget','/usr/bin/wget');
INSERT INTO tf_settings VALUES ('bin_unrar','/usr/bin/unrar');
INSERT INTO tf_settings VALUES ('bin_unzip','/usr/bin/unzip');
INSERT INTO tf_settings VALUES ('bin_cksfv','/usr/bin/cksfv');
INSERT INTO tf_settings VALUES ('bin_uudeview','/usr/local/bin/uudeview');
INSERT INTO tf_settings VALUES ('btclient','tornado');
INSERT INTO tf_settings VALUES ('btclient_tornado_options','');
INSERT INTO tf_settings VALUES ('btclient_transmission_bin','/usr/local/bin/transmissioncli');
INSERT INTO tf_settings VALUES ('btclient_transmission_options','');
INSERT INTO tf_settings VALUES ('metainfoclient','btshowmetainfo.py');
INSERT INTO tf_settings VALUES ('enable_restrictivetview','1');
INSERT INTO tf_settings VALUES ('perlCmd','/usr/bin/perl');
INSERT INTO tf_settings VALUES ('ui_displayfluxlink','1');
INSERT INTO tf_settings VALUES ('ui_dim_main_w','900');
INSERT INTO tf_settings VALUES ('enable_bigboldwarning','1');
INSERT INTO tf_settings VALUES ('enable_goodlookstats','1');
INSERT INTO tf_settings VALUES ('ui_displaylinks','1');
INSERT INTO tf_settings VALUES ('ui_displayusers','1');
INSERT INTO tf_settings VALUES ('xfer_total','0');
INSERT INTO tf_settings VALUES ('xfer_month','0');
INSERT INTO tf_settings VALUES ('xfer_week','0');
INSERT INTO tf_settings VALUES ('xfer_day','0');
INSERT INTO tf_settings VALUES ('enable_bulkops','1');
INSERT INTO tf_settings VALUES ('week_start','Monday');
INSERT INTO tf_settings VALUES ('month_start','1');
INSERT INTO tf_settings VALUES ('hack_multiupload_rows','6');
INSERT INTO tf_settings VALUES ('hack_goodlookstats_settings','63');
INSERT INTO tf_settings VALUES ('enable_dereferrer','1');
INSERT INTO tf_settings VALUES ('auth_type','0');
INSERT INTO tf_settings VALUES ('index_page_connections','1');
INSERT INTO tf_settings VALUES ('index_page_stats','1');
INSERT INTO tf_settings VALUES ('index_page_sortorder','dd');
INSERT INTO tf_settings VALUES ('index_page_settings','1266');
INSERT INTO tf_settings VALUES ('bin_sockstat','/usr/bin/sockstat');
INSERT INTO tf_settings VALUES ('nice_adjust','0');
INSERT INTO tf_settings VALUES ('xfer_realtime','1');
INSERT INTO tf_settings VALUES ('skiphashcheck','0');
INSERT INTO tf_settings VALUES ('enable_umask','0');
INSERT INTO tf_settings VALUES ('enable_sorttable','1');
INSERT INTO tf_settings VALUES ('drivespacebar','tf');
INSERT INTO tf_settings VALUES ('bin_vlc','/usr/local/bin/vlc');
INSERT INTO tf_settings VALUES ('debuglevel','0');
INSERT INTO tf_settings VALUES ('docroot','/var/www/');
INSERT INTO tf_settings VALUES ('enable_index_ajax_update_silent','0');
INSERT INTO tf_settings VALUES ('enable_index_ajax_update_users','1');
INSERT INTO tf_settings VALUES ('wget_ftp_pasv','0');
INSERT INTO tf_settings VALUES ('wget_limit_retries','3');
INSERT INTO tf_settings VALUES ('wget_limit_rate','0');
INSERT INTO tf_settings VALUES ('enable_index_ajax_update_title','1');
INSERT INTO tf_settings VALUES ('enable_index_ajax_update_list','1');
INSERT INTO tf_settings VALUES ('enable_index_meta_refresh','0');
INSERT INTO tf_settings VALUES ('enable_index_ajax_update','0');
INSERT INTO tf_settings VALUES ('index_ajax_update','10');
INSERT INTO tf_settings VALUES ('transferStatsType','ajax');
INSERT INTO tf_settings VALUES ('transferStatsUpdate','5');
INSERT INTO tf_settings VALUES ('auth_basic_realm','torrentflux-b4rt');
INSERT INTO tf_settings VALUES ('servermon_update','5');
INSERT INTO tf_settings VALUES ('enable_home_dirs','1');
INSERT INTO tf_settings VALUES ('path_incoming','incoming');
INSERT INTO tf_settings VALUES ('enable_tmpl_cache','0');
INSERT INTO tf_settings VALUES ('btclient_mainline_options','');
INSERT INTO tf_settings VALUES ('bandwidthbar','tf');
INSERT INTO tf_settings VALUES ('display_seeding_time','1');
INSERT INTO tf_settings VALUES ('ui_displaybandwidthbars','1');
INSERT INTO tf_settings VALUES ('bandwidth_down','10240');
INSERT INTO tf_settings VALUES ('bandwidth_up','10240');
INSERT INTO tf_settings VALUES ('webapp_locked','0');
INSERT INTO tf_settings VALUES ('enable_btclient_chooser','1');
INSERT INTO tf_settings VALUES ('transfer_profiles','3');
INSERT INTO tf_settings VALUES ('transfer_customize_settings','2');
INSERT INTO tf_settings VALUES ('transferHosts','0');
INSERT INTO tf_settings VALUES ('pagetitle','torrentflux-b4rt');
INSERT INTO tf_settings VALUES ('enable_sharekill','1');
INSERT INTO tf_settings VALUES ('transfer_window_default','transferStats');
INSERT INTO tf_settings VALUES ('index_show_seeding','1');
INSERT INTO tf_settings VALUES ('enable_personal_settings','1');
INSERT INTO tf_settings VALUES ('enable_nzbperl','0');
INSERT INTO tf_settings VALUES ('nzbperl_badAction','0');
INSERT INTO tf_settings VALUES ('nzbperl_server','');
INSERT INTO tf_settings VALUES ('nzbperl_user','');
INSERT INTO tf_settings VALUES ('nzbperl_pw','');
INSERT INTO tf_settings VALUES ('nzbperl_threads','0');
INSERT INTO tf_settings VALUES ('nzbperl_conn','1');
INSERT INTO tf_settings VALUES ('nzbperl_rate','0');
INSERT INTO tf_settings VALUES ('nzbperl_create','0');
INSERT INTO tf_settings VALUES ('nzbperl_options','');
INSERT INTO tf_settings VALUES ('fluazu_host','localhost');
INSERT INTO tf_settings VALUES ('fluazu_port','6884');
INSERT INTO tf_settings VALUES ('fluazu_secure','0');
INSERT INTO tf_settings VALUES ('fluazu_user','');
INSERT INTO tf_settings VALUES ('fluazu_pw','');
INSERT INTO tf_settings VALUES ('fluxd_dbmode','php');
INSERT INTO tf_settings VALUES ('fluxd_loglevel','0');
INSERT INTO tf_settings VALUES ('fluxd_Fluxinet_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Qmgr_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Rssad_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Watch_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Trigger_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Maintenance_enabled','0');
INSERT INTO tf_settings VALUES ('fluxd_Fluxinet_port','3150');
INSERT INTO tf_settings VALUES ('fluxd_Qmgr_interval','15');
INSERT INTO tf_settings VALUES ('fluxd_Qmgr_maxTotalTransfers','5');
INSERT INTO tf_settings VALUES ('fluxd_Qmgr_maxUserTransfers','2');
INSERT INTO tf_settings VALUES ('fluxd_Rssad_interval','1800');
INSERT INTO tf_settings VALUES ('fluxd_Rssad_jobs','');
INSERT INTO tf_settings VALUES ('fluxd_Watch_interval','120');
INSERT INTO tf_settings VALUES ('fluxd_Watch_jobs','');
INSERT INTO tf_settings VALUES ('fluxd_Maintenance_interval','600');
INSERT INTO tf_settings VALUES ('fluxd_Maintenance_trestart','0');
INSERT INTO tf_settings VALUES ('fluxd_Trigger_interval','600');

--
-- Sequences for table tf_trprofiles
--
SELECT SETVAL('tf_trprofiles_id_seq',(select case when max(id)>0 then max(id)+1 else 1 end from tf_trprofiles));

--
-- commit
--
COMMIT;
