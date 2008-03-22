-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-6'.
-- Updates a 'Torrentflux 2.1-b4rt-5' Database to a 'Torrentflux 2.1-b4rt-6'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- tf_torrent_totals
--
CREATE TABLE tf_torrent_totals (
  tid VARCHAR(40) NOT NULL default '',
  uptotal BIGINT(80) NOT NULL default '0',
  downtotal BIGINT(80) NOT NULL default '0',
  PRIMARY KEY  (tid)
) TYPE=MyISAM;

--
-- tf_torrents
--
ALTER TABLE tf_torrents ADD `btclient` VARCHAR(32) DEFAULT 'tornado' NOT NULL ;

--
-- tf_xfer
--
ALTER TABLE tf_xfer CHANGE `download` `download` BIGINT(80) DEFAULT '0' NOT NULL;
ALTER TABLE tf_xfer CHANGE `upload` `upload` BIGINT(80) DEFAULT '0' NOT NULL;

--
-- extra inserts
--
INSERT INTO tf_settings VALUES ('btclient','tornado');
INSERT INTO tf_settings VALUES ('btclient_tornado_bin','/var/www/TF_BitTornado/btphptornado.py');
INSERT INTO tf_settings VALUES ('btclient_tornado_options','--alloc_type sparse --min_peers 40 --upnp_nat_access 0 --write_buffer_size 8');
INSERT INTO tf_settings VALUES ('btclient_transmission_bin','/usr/local/bin/transmissioncli');
INSERT INTO tf_settings VALUES ('btclient_transmission_options','');


