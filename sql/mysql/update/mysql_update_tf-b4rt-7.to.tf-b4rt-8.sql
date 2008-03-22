-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-8'.
-- Updates a 'Torrentflux 2.1-b4rt-7' Database to a 'Torrentflux 2.1-b4rt-8'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- inserts
--
INSERT INTO tf_settings VALUES ('queuemanager','tfqmgr');
INSERT INTO tf_settings VALUES ('perlCmd','/usr/bin/perl');
INSERT INTO tf_settings VALUES ('tfqmgr_path','/var/www/tfqmgr');
INSERT INTO tf_settings VALUES ('tfqmgr_path_fluxcli','/var/www');
INSERT INTO tf_settings VALUES ('tfqmgr_limit_global','5');
INSERT INTO tf_settings VALUES ('tfqmgr_limit_user','2');
INSERT INTO tf_settings VALUES ('ui_displayfluxlink','1');
INSERT INTO tf_settings VALUES ('ui_dim_main_w','780');
INSERT INTO tf_settings VALUES ('ui_dim_details_w','450');
INSERT INTO tf_settings VALUES ('ui_dim_details_h','290');
INSERT INTO tf_settings VALUES ('ui_dim_superadmin_w','800');
INSERT INTO tf_settings VALUES ('ui_dim_superadmin_h','600');
