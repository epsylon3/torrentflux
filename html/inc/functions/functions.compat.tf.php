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
 * indexDispatch
 */
function compat_tf_indexDispatch() {
	// dispatcher-functions
	require_once("inc/functions/functions.dispatcher.php");
	// start
	if (isset($_REQUEST['torrent']))
		dispatcher_startTransfer(urldecode(tfb_getRequestVar('torrent')));
	// stop
	if (isset($_REQUEST["kill_torrent"]))
		dispatcher_stopTransfer(urldecode(tfb_getRequestVar('kill_torrent')));
	// del
	if (isset($_REQUEST['delfile']))
		dispatcher_deleteTransfer(urldecode(tfb_getRequestVar('delfile')));
	// deQueue
	if (isset($_REQUEST["QEntry"]))
		dispatcher_deQueueTransfer(urldecode(tfb_getRequestVar('QEntry')));
	// get torrent via url
	if (isset($_REQUEST['url_upload']))
		dispatcher_processDownload(tfb_getRequestVarRaw('url_upload'), 'torrent');
	// file upload
	if ((isset($_FILES['upload_file'])) && (!empty($_FILES['upload_file']['name'])))
		compat_tf_processUpload();
}

/**
 * Function with which metafiles are uploaded and injected
 *
 * @deprecated
 */
function compat_tf_processUpload() {
	global $cfg;
	$filename = "";
	$uploadMessages = array();
	if ((isset($_FILES['upload_file'])) && (!empty($_FILES['upload_file']['name']))) {
		$filename = stripslashes($_FILES['upload_file']['name']);
		$filename = tfb_cleanFileName($filename);
		if ($filename === false) {
			// invalid file
			array_push($uploadMessages, "The type of file you are uploading is not allowed.");
			array_push($uploadMessages, "\nvalid file-extensions: ");
			array_push($uploadMessages, $cfg["file_types_label"]);
		} else {
			// file is valid
			if (substr($filename, -5) == ".wget") {
				// is enabled ?
				if ($cfg["enable_wget"] == 0) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload wget-file ".$filename);
					@error("wget is disabled", "", "");
				} else if ($cfg["enable_wget"] == 1) {
					if (!$cfg['isAdmin']) {
						AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload wget-file ".$filename);
						@error("wget is disabled for users", "", "");
					}
				}
			} else if (substr($filename, -4) == ".nzb") {
				// is enabled ?
				if ($cfg["enable_nzbperl"] == 0) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload nzb-file ".$filename);
					@error("nzbperl is disabled", "", "");
				} else if ($cfg["enable_nzbperl"] == 1) {
					if (!$cfg['isAdmin']) {
						AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload nzb-file ".$filename);
						@error("nzbperl is disabled for users", "", "");
					}
				}
			}
			if ($_FILES['upload_file']['size'] <= $cfg["upload_limit"] && $_FILES['upload_file']['size'] > 0) {
				//FILE IS BEING UPLOADED
				if (@is_file($cfg["transfer_file_path"].$filename)) {
					// Error
					array_push($uploadMessages, "the file ".$filename." already exists on the server.");
				} else {
					if (@move_uploaded_file($_FILES['upload_file']['tmp_name'], $cfg["transfer_file_path"].$filename)) {
						@chmod($cfg["transfer_file_path"].$filename, 0644);
						AuditAction($cfg["constants"]["file_upload"], $filename);
						// inject
						injectTransfer($filename);
						// instant action ?
						$actionId = tfb_getRequestVar('aid');
						if ($actionId > 1) {
							$ch = ClientHandler::getInstance(getTransferClient($filename));
							switch ($actionId) {
								case 3:
									$ch->start($filename, false, true);
									break;
								case 2:
									$ch->start($filename, false, false);
									break;
							}
							if (count($ch->messages) > 0)
	           					$uploadMessages = array_merge($uploadMessages, $ch->messages);
						}
					} else {
						array_push($uploadMessages, "File not uploaded, file could not be found or could not be moved: ".$cfg["transfer_file_path"].$filename);
					}
				}
			} else {
				array_push($uploadMessages, "File not uploaded, file size limit is ".$cfg["upload_limit"].". file has ".$_FILES['upload_file']['size']);
			}
		}
	}
	if (count($uploadMessages) > 0) {
		AuditAction($cfg["constants"]["error"], $cfg["constants"]["file_upload"]." :: ".$filename);
		@error("There were Problems", "", "", $uploadMessages);
	}
}

?>