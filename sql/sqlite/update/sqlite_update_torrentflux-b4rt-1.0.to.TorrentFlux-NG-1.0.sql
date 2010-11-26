-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-b4rt-beta2'.
-- Updates a 'Torrentflux-b4rt-1.0beta2' Database to a 'TorrentFlux-NG-1.0'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- alter
--
ALTER TABLE tf_transfer_totals ADD uid INTEGER(10) NOT NULL DEFAULT 0;

--
-- delete
--

--
-- inserts
--
INSERT INTO tf_settings VALUES ('btclient_transmission_enable','0');
INSERT INTO tf_settings VALUES ('vuze_rpc_enable','0');
INSERT INTO tf_settings VALUES ('vuze_rpc_host','127.0.0.1');
INSERT INTO tf_settings VALUES ('vuze_rpc_port','9091');
INSERT INTO tf_settings VALUES ('vuze_rpc_user','vuze');
INSERT INTO tf_settings VALUES ('vuze_rpc_password','mypassword');

--
-- updates
--

UPDATE tf_settings SET tf_value = 'TorrentFlux-NG' WHERE tf_key = 'auth_basic_realm';
UPDATE tf_settings SET tf_value = 'TorrentFlux-NG' WHERE tf_key = 'pagetitle';
UPDATE tf_settings SET tf_value = 'RedRound' WHERE tf_key = 'default_theme';
UPDATE tf_users SET theme = 'RedRound';