-- -----------------------------------------------------------------------------
-- $Id$
-- -----------------------------------------------------------------------------
--
-- MySQL-Update-File for 'Torrentflux-2.1-b4rt-5'.
-- Updates a 'Torrentflux 2.1-b4rt-4' Database to a 'Torrentflux 2.1-b4rt-5'.
--
-- This Stuff is provided 'as-is'. In no way will the author be held
-- liable for any damages to your soft- or hardware from this.
-- -----------------------------------------------------------------------------

--
-- tf_cookies
--
ALTER TABLE tf_cookies CHANGE `cid` `cid` INT(5) NOT NULL AUTO_INCREMENT;

--
-- extra inserts + updates
--
UPDATE tf_settings SET tf_value = '1' WHERE tf_key = 'advanced_start';
INSERT INTO tf_links VALUES (NULL,'http://www.torrentflux.com/forum/index.php/topic,1265.0.html');
--
INSERT INTO tf_settings VALUES ('enable_multiops','1');
INSERT INTO tf_settings VALUES ('enable_basicauth','0');
INSERT INTO tf_settings VALUES ('enable_rememberme','0');
INSERT INTO tf_settings VALUES ('enable_wget','1');
INSERT INTO tf_settings VALUES ('enable_dirstats','1');
INSERT INTO tf_settings VALUES ('enable_multiupload','1');
INSERT INTO tf_settings VALUES ('enable_xfer','1');
INSERT INTO tf_settings VALUES ('enable_public_xfer','1');
INSERT INTO tf_settings VALUES ('enable_sfvcheck','1');
INSERT INTO tf_settings VALUES ('bin_grep','/bin/grep');
INSERT INTO tf_settings VALUES ('bin_cat','/bin/cat');
INSERT INTO tf_settings VALUES ('bin_netstat','/bin/netstat');
INSERT INTO tf_settings VALUES ('bin_php','/usr/bin/php');
INSERT INTO tf_settings VALUES ('bin_awk','/usr/bin/awk');
INSERT INTO tf_settings VALUES ('bin_du','/usr/bin/du');
INSERT INTO tf_settings VALUES ('bin_wget','/usr/bin/wget');
INSERT INTO tf_settings VALUES ('bin_unrar','/usr/bin/unrar');
INSERT INTO tf_settings VALUES ('bin_unzip','/usr/bin/unzip');
INSERT INTO tf_settings VALUES ('bin_cksfv','/usr/bin/cksfv');

