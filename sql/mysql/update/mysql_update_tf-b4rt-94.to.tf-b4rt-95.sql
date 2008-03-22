-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-95'.
-- Updates a 'Torrentflux 2.1-b4rt-94' Database to a 'Torrentflux 2.1-b4rt-95'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- alter
--
ALTER TABLE tf_xfer CHANGE user user_id VARCHAR(32) NOT NULL;

--
-- inserts
--

