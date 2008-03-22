-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-9'.
-- Updates a 'Torrentflux 2.1-b4rt-85' Database to a 'Torrentflux 2.1-b4rt-9'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- deletes
--
DELETE FROM tf_settings WHERE tf_key = 'enable_basicauth';
DELETE FROM tf_settings WHERE tf_key = 'enable_rememberme';

--
-- inserts
--
INSERT INTO tf_settings VALUES ('Qmgr_path','/var/www/Qmgr');
INSERT INTO tf_settings VALUES ('Qmgr_maxUserTorrents','2');
INSERT INTO tf_settings VALUES ('Qmgr_maxTotalTorrents','5');
INSERT INTO tf_settings VALUES ('Qmgr_perl','/usr/bin/perl');
INSERT INTO tf_settings VALUES ('Qmgr_fluxcli','/var/www');
INSERT INTO tf_settings VALUES ('Qmgr_host','localhost');
INSERT INTO tf_settings VALUES ('Qmgr_port','2606');
INSERT INTO tf_settings VALUES ('auth_type','0');
INSERT INTO tf_settings VALUES ('index_page_connections','1');
INSERT INTO tf_settings VALUES ('index_page_stats','1');
INSERT INTO tf_settings VALUES ('index_page_sortorder','dd');
INSERT INTO tf_settings VALUES ('index_page','b4rt');
INSERT INTO tf_settings VALUES ('index_page_settings','1266');
INSERT INTO tf_settings VALUES ('enable_move','0');
INSERT INTO tf_settings VALUES ('enable_rename','1');
INSERT INTO tf_settings VALUES ('move_paths','');
INSERT INTO tf_settings VALUES ('bin_sockstat','/usr/bin/sockstat');

