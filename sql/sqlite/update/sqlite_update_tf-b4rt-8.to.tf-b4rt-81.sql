-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- SQLite-Update-File for 'Torrentflux-2.1-b4rt-81'.
-- Updates a 'Torrentflux 2.1-b4rt-81' Database to a 'Torrentflux 2.1-b4rt-81'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- begin transaction
--
BEGIN TRANSACTION;

--
-- tf_settings_user
--
CREATE TABLE tf_settings_user (
  uid INTEGER NOT NULL,
  tf_key VARCHAR(255) NOT NULL default '',
  tf_value TEXT NOT NULL
) ;

--
-- inserts
--
INSERT INTO tf_settings VALUES ('enable_bigboldwarning','1');
INSERT INTO tf_settings VALUES ('enable_goodlookstats','1');
INSERT INTO tf_settings VALUES ('ui_displaylinks','1');
INSERT INTO tf_settings VALUES ('ui_displayusers','1');
INSERT INTO tf_settings VALUES ('xfer_total','0');
INSERT INTO tf_settings VALUES ('xfer_month','0');
INSERT INTO tf_settings VALUES ('xfer_week','0');
INSERT INTO tf_settings VALUES ('xfer_day','0');

--
-- commit
--
COMMIT;
