-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- SQLite-Update-File for 'Torrentflux-2.1-b4rt-93'.
-- Updates a 'Torrentflux 2.1-b4rt-92' Database to a 'Torrentflux 2.1-b4rt-93'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- begin transaction
--
BEGIN TRANSACTION;

--
-- alter
--
ALTER TABLE tf_torrents ADD hash VARCHAR(40) DEFAULT '' NOT NULL;

--
-- inserts
--
INSERT INTO tf_settings VALUES ('enable_sorttable','1');
INSERT INTO tf_settings VALUES ('drivespacebar','xfer');

--
-- commit
--
COMMIT;
