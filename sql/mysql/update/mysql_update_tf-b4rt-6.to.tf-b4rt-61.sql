-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-61'.
-- Updates a 'Torrentflux 2.1-b4rt-6' Database to a 'Torrentflux 2.1-b4rt-61'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- to do an update use the following statement. 
-- but you have to assign the sort_order-values manual after doing so !
-- or you wont be able to re-order your existing links.
--
-- ALTER TABLE tf_links ADD `sitename` VARCHAR(255) DEFAULT 'Old Link' NOT NULL , ADD `sort_order` TINYINT(3) UNSIGNED DEFAULT '0';
--
--

--
-- tf_links
--
DROP TABLE IF EXISTS tf_links;

CREATE TABLE tf_links (
  lid int(10) NOT NULL auto_increment,
  url VARCHAR(255) NOT NULL default '',
  sitename VARCHAR(255) NOT NULL default 'Old Link',
  sort_order TINYINT(3) UNSIGNED default '0',
  PRIMARY KEY  (lid)
) TYPE=MyISAM;

INSERT INTO tf_links VALUES (NULL,'http://www.torrentflux.com/forum/index.php/topic,1265.0.html','Home','0');


