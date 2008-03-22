-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- SQL-Update-File for 'Torrentflux-2.1-b4rt-4'.
-- Updates a 'Torrentflux 2.1-b4rt-3' Database to a 'Torrentflux 2.1-b4rt-4'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- tf_links
--
DROP TABLE IF EXISTS tf_torrents;

--
-- Table structure for table `tf_torrents`
--
CREATE TABLE tf_torrents (
  torrent varchar(255) NOT NULL default '',
  running enum('0','1') NOT NULL default '1',
  rate smallint(4) unsigned NOT NULL default '0',
  drate smallint(4) unsigned NOT NULL default '0',
  maxuploads tinyint(3) unsigned NOT NULL default '0',
  superseeder enum('0','1') NOT NULL default '0',
  runtime enum('True','False') NOT NULL default 'False',
  sharekill smallint(4) unsigned NOT NULL default '0',
  minport smallint(5) unsigned NOT NULL default '0',
  maxport smallint(5) unsigned NOT NULL default '0',
  maxcons smallint(4) unsigned NOT NULL default '0',
  savepath varchar(255) NOT NULL default '',
  PRIMARY KEY  (torrent)
) TYPE=MyISAM;

--
-- extra inserts + updates
--
INSERT INTO tf_links VALUES (NULL,'http://www.torrentflux.com/forum/index.php/topic,1265.0.html');
INSERT INTO tf_settings VALUES ('enable_mrtg','1');
INSERT INTO tf_settings VALUES ('enable_rar','1');
INSERT INTO tf_settings VALUES ('showdirtree','1');
INSERT INTO tf_settings VALUES ('maxdepth','0');
UPDATE tf_settings SET tf_value = '1' WHERE tf_key = 'advanced_start';