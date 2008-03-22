<?php

/* $Id$ */

/*******************************************************************************

 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html

*******************************************************************************/

/**
 * client-support-map
 */
$cfg["supportMap"] = array(
	'tornado' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 1,
		'superseeder'       => 1,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 1,
		'maxport'           => 1,
		'maxcons'           => 1,
		'rerequest'         => 1,
		'file_priority'     => 1,
		'skip_hash_check'   => 1,
		'savepath'          => 1
	),
	'transmission' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 1,
		'maxport'           => 1,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 1
	),
	'mainline' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 1,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 1,
		'maxport'           => 1,
		'maxcons'           => 1,
		'rerequest'         => 1,
		'file_priority'     => 0,
		'skip_hash_check'   => 1,
		'savepath'          => 1
	),
	'azureus' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 1
	),
	'wget' => array(
		'max_upload_rate'   => 0,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 0,
		'sharekill'         => 0,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 1
	),
	'nzbperl' => array(
		'max_upload_rate'   => 0,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 0,
		'sharekill'         => 0,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 1,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 1
	)
);

/**
 * client-support-map (runtime)
 */
$cfg["runtimeMap"] = array(
	'tornado' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	),
	'transmission' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	),
	'mainline' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	),
	'azureus' => array(
		'max_upload_rate'   => 1,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 1,
		'sharekill'         => 1,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	),
	'wget' => array(
		'max_upload_rate'   => 0,
		'max_download_rate' => 0,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 0,
		'sharekill'         => 0,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	),
	'nzbperl' => array(
		'max_upload_rate'   => 0,
		'max_download_rate' => 1,
		'max_uploads'       => 0,
		'superseeder'       => 0,
		'die_when_done'     => 0,
		'sharekill'         => 0,
		'minport'           => 0,
		'maxport'           => 0,
		'maxcons'           => 0,
		'rerequest'         => 0,
		'file_priority'     => 0,
		'skip_hash_check'   => 0,
		'savepath'          => 0
	)
);

?>