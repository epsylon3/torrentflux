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
 * startTransfer
 *
 * @param $transfer
 */
function dispatcher_startTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// interactive
	$interactive = (tfb_getRequestVar('interactive') == 1) ? 1 : 0;
	// ch
	$ch = ($interactive == 1)
		? ClientHandler::getInstance(tfb_getRequestVar('client'))
		: ClientHandler::getInstance(getTransferClient($transfer));
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "start");
	// start
	if ($interactive == 1)
		$ch->start($transfer, true, (tfb_getRequestVar('queue') == '1') ? FluxdQmgr::isRunning() : false);
	else
		$ch->start($transfer, false, FluxdQmgr::isRunning());
	// check
	if ($ch->state == CLIENTHANDLER_STATE_ERROR) { // start failed
		$msgs = array();
		array_push($msgs, "transfer : ".$transfer);
		array_push($msgs, "\nmessages :");
		$msgs = array_merge($msgs, $ch->messages);
		AuditAction($cfg["constants"]["error"], "Start failed: ".$transfer."\n".implode("\n", $ch->messages));
		@error("Start failed", "", "", $msgs);
	} else {
		if (($interactive == 1) && (isset($_REQUEST["close"]))) {
			echo '<script  language="JavaScript">';
			echo ' window.opener.location.reload(true);';
			echo ' window.close();';
			echo '</script>';
			// Prevent dispatcher_exit from running and redirecting client, otherwise script won't be executed.
			exit();
		}
	}
}

/**
 * stopTransfer
 *
 * @param $transfer
 */
function dispatcher_stopTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// ch
	$ch = ClientHandler::getInstance(getTransferClient($transfer));
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "stop");
	// stop
	$ch->stop($transfer);
	// check
	if (count($ch->messages) > 0)
    	@error("There were Problems", "", "", $ch->messages);
}

/**
 * restartTransfer
 *
 * @param $transfer
 */
function dispatcher_restartTransfer($transfer) {
	global $cfg;
	// stop if running
	$tRunningFlag = isTransferRunning($transfer);
	if ($tRunningFlag) {
		dispatcher_stopTransfer($transfer);
		$tRunningFlag = isTransferRunning($transfer);
	}
	// start if not running
	if (!$tRunningFlag)
		dispatcher_startTransfer($transfer);
}

/**
 * forceStopTransfer
 *
 * @param $transfer
 */
function dispatcher_forceStopTransfer($transfer, $pid) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// ch
	$ch = ClientHandler::getInstance(getTransferClient($transfer));
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "forceStop");
	// forceStop
	$ch->stop($transfer, true, $pid);
	// check
	if (count($ch->messages) > 0)
    	@error("There were Problems", "", "", $ch->messages);
}

/**
 * deleteTransfer
 *
 * @param $transfer
 */
function dispatcher_deleteTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// client
	$client = getTransferClient($transfer);
	// ch
	$ch = ClientHandler::getInstance($client);
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "delete");
	// is transfer running ?
	$tRunningFlag = isTransferRunning($transfer);
	if ($tRunningFlag) {
		// stop first
		$ch->stop($transfer);
		if (count($ch->messages) > 0)
    		@error("There were Problems", "", "", $ch->messages);
		// is transfer running ?
		$tRunningFlag = isTransferRunning($transfer);
	}
	// if it was running... hope the thing is down...
	// only continue if it is
	if ($tRunningFlag) {
		@error("Delete failed, Transfer is running and stop failed", "", "", $ch->messages);
	} else {
		// delete
		$ch->delete($transfer);
		// check
		if (count($ch->messages) > 0)
	    	@error("There were Problems", "", "", $ch->messages);
	}
}

/**
 * deleteDataTransfer
 *
 * @param $transfer
 */
function dispatcher_deleteDataTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// client
	$client = getTransferClient($transfer);
	// ch
	$ch = ClientHandler::getInstance($client);
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "deleteWithData");
	// is transfer running ?
	$tRunningFlag = isTransferRunning($transfer);
	if ($tRunningFlag) {
		// stop first
		$ch->stop($transfer);
		if (count($ch->messages) > 0)
    		@error("There were Problems", "", "", $ch->messages);
		// is transfer running ?
		$tRunningFlag = isTransferRunning($transfer);
	}
	// if it was running... hope the thing is down...
	// only continue if it is
	if ($tRunningFlag) {
		@error("Delete with Data failed, Transfer is running and stop failed", "", "", $ch->messages);
	} else {
		// transferData
		$msgsDelete = deleteTransferData($transfer);
		if (count($msgsDelete) > 0)
			@error("There were Problems deleting Transfer-Data", "", "", $msgsDelete);
		// transfer
		$ch->delete($transfer);
		if (count($ch->messages) > 0)
			@error("There were Problems", "", "", $ch->messages);
	}
}

/**
 * wipeTransfer
 *
 * @param $transfer
 */
function dispatcher_wipeTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// client
	$client = getTransferClient($transfer);
	// ch
	$ch = ClientHandler::getInstance($client);
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "wipe");
	// is transfer running ?
	$tRunningFlag = isTransferRunning($transfer);
	if ($tRunningFlag) {
		// stop first
		$ch->stop($transfer);
		if (count($ch->messages) > 0)
    		@error("There were Problems", "", "", $ch->messages);
		// is transfer running ?
		$tRunningFlag = isTransferRunning($transfer);
	}
	// if it was running... hope the thing is down...
	// only continue if it is
	if ($tRunningFlag) {
		@error("Wipe failed, Transfer is running and stop failed", "", "", $ch->messages);
	} else {
		// transferData
		$msgsDelete = deleteTransferData($transfer);
		if (count($msgsDelete) > 0)
			@error("There were Problems deleting Transfer-Data", "", "", $msgsDelete);
		// totals + t
		$msgsReset = resetTransferTotals($transfer, true);
		if (count($msgsReset) > 0)
			@error("There were Problems wiping the Transfer", "", "", $msgsReset);
	}
}

/**
 * deQueueTransfer
 *
 * @param $transfer
 */
function dispatcher_deQueueTransfer($transfer) {
	global $cfg;
	// valid
	if (tfb_isValidTransfer($transfer) !== true) {
		AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$transfer);
		@error("Invalid Transfer", "", "", array($transfer));
	}
	// ch
	$ch = ClientHandler::getInstance(getTransferClient($transfer));
	// permission
	dispatcher_checkTypePermission($transfer, $ch->type, "dequeue");
	// dequeue
	FluxdQmgr::dequeueTransfer($transfer, $cfg['user']);
}

/**
 * injectWget
 *
 * @param $url
 */
function dispatcher_injectWget($url) {
	global $cfg;
	// permission
	dispatcher_checkTypePermission($url, 'wget', "inject");
	// inject
	if (!empty($url)) {
		$ch = ClientHandler::getInstance('wget');
		$ch->inject($url);
		// instant action ?
		$actionId = tfb_getRequestVar('aid');
		if ($actionId > 1) {
			switch ($actionId) {
				case 3:
					$ch->start($ch->transfer, false, true);
					break;
				case 2:
					$ch->start($ch->transfer, false, false);
					break;
			}
			if ($ch->state == CLIENTHANDLER_STATE_ERROR) { // start failed
				$msgs = array();
				array_push($msgs, "url : ".$url);
				array_push($msgs, "\nmessages :");
				$msgs = array_merge($msgs, $ch->messages);
				AuditAction($cfg["constants"]["error"], "Start failed: ".$url."\n".implode("\n", $ch->messages));
				@error("Start failed", "", "", $msgs);
			}
		}
	}
}

/**
 * setFilePriority
 *
 * @param $transfer
 */
function dispatcher_setFilePriority($transfer) {
	global $cfg;
	if ($cfg["enable_file_priority"])
		setFilePriority($transfer);
}

/**
 * set
 *
 * @param $key
 * @param $val
 */
function dispatcher_set($key, $val) {
	if (!empty($key)) {
		if ($key == "_all_") {
			$keys = array_keys($_SESSION['settings']);
			foreach ($keys as $settingKey)
				$_SESSION['settings'][$settingKey] = $val;
		} elseif ($key == "_refresh_") {
			$_SESSION['settings']['index_ajax_update'] = $val;
			$_SESSION['settings']['index_meta_refresh'] = $val;
		} else {
			$_SESSION['settings'][$key] = $val;
		}
	}
}

/**
 * bulk
 *
 * @param $op
 */
function dispatcher_bulk($op) {
	global $cfg;
	// is enabled ?
	if ($cfg["enable_bulkops"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use ".$op);
		@error("bulkops are disabled", "", "");
	}
	// messages
	$dispatcherMessages = array();
	// op-switch
	switch ($op) {
	    case "stop":
	    	$transferList = getTransferArray();
	    	foreach ($transferList as $transfer) {
	            if (isTransferRunning($transfer)) {
	                if (($cfg['isAdmin']) || (IsOwner($cfg["user"], getOwner($transfer)))) {
	                    $ch = ClientHandler::getInstance(getTransferClient($transfer));
	                    $ch->stop($transfer);
	                    if (count($ch->messages) > 0)
	                    	$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
	                }
	            }
	    	}
	    	break;
	    case "resume":
	    	$transferList = getTransferArray();
	    	$sf = new StatFile("");
	    	foreach ($transferList as $transfer) {
				$sf->init($transfer);
		        if (((trim($sf->running)) == 0) && (!isTransferRunning($transfer))) {
	                if (($cfg['isAdmin']) || (IsOwner($cfg["user"], getOwner($transfer)))) {
	                    $ch = ClientHandler::getInstance(getTransferClient($transfer));
	                    $ch->start($transfer, false, false);
	                    if (count($ch->messages) > 0)
	                    	$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
	                }
	            }
	    	}
	    	break;
	    case "start":
	    	$transferList = getTransferArray();
	    	foreach ($transferList as $transfer) {
	            if (!isTransferRunning($transfer)) {
	                if (($cfg['isAdmin']) || (IsOwner($cfg["user"], getOwner($transfer)))) {
	                    $ch = ClientHandler::getInstance(getTransferClient($transfer));
	                    $ch->start($transfer, false, false);
	                    if (count($ch->messages) > 0)
	                    	$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
	                }
	            }
	    	}
	    	break;
	}
	// error if messages
	if (count($dispatcherMessages) > 0)
		@error("There were Problems", "", "", $dispatcherMessages);
}

/**
 * multi
 *
 * @param $action
 */
function dispatcher_multi($action) {
	global $cfg;

	// is enabled ?
	if ($cfg["enable_multiops"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use multi-op ".$action);
		@error("multiops are disabled", "", "");
	}

	// messages-ary
	$dispatcherMessages = array();

	// loop
	if (empty($_POST['transfer'])) return;
	foreach ($_POST['transfer'] as $key => $element) {

		// url-decode
		$transfer = urldecode($element);

		// is valid transfer ? + check permissions
		$invalid = true;
		if (tfb_isValidTransfer($transfer) === true) {
			if (substr($transfer, -8) == ".torrent") {
				// this is a torrent-client
				$invalid = false;
			} else if (substr($transfer, -5) == ".wget") {
				// this is wget.
				$invalid = false;
				// is enabled ?
				if ($cfg["enable_wget"] == 0) {
					$invalid = true;
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use wget");
					array_push($dispatcherMessages, "wget is disabled : ".$transfer);
				} else if ($cfg["enable_wget"] == 1) {
					if (!$cfg['isAdmin']) {
						$invalid = true;
						AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use wget");
						array_push($dispatcherMessages, "wget is disabled for users : ".$transfer);
					}
				}
			} else if (substr($transfer, -4) == ".nzb") {
				// This is nzbperl.
				$invalid = false;
				if ($cfg["enable_nzbperl"] == 0) {
					$invalid = true;
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use nzbperl");
					array_push($dispatcherMessages, "nzbperl is disabled : ".$transfer);
				} else if ($cfg["enable_nzbperl"] == 1) {
					if (!$cfg['isAdmin']) {
						$invalid = true;
						AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use nzbperl");
						array_push($dispatcherMessages, "nzbperl is disabled for users : ".$transfer);
					}
				}
			}
		}
		if ($invalid) {
			AuditAction($cfg["constants"]["error"], "INVALID TRANSFER: ".$cfg["user"]." tried to ".$action." ".$transfer);
			array_push($dispatcherMessages, "Invalid Transfer : ".$transfer);
			continue;
		}

		// client
		$client = getTransferClient($transfer);

		// is transfer running ?
		$tRunningFlag = isTransferRunning($transfer);

		// action switch
		switch ($action) {

			case "transferStart": /* transferStart */
				if (!$tRunningFlag) {
					$ch = ClientHandler::getInstance($client);
					$ch->start($transfer, false, FluxdQmgr::isRunning());
					if (count($ch->messages) > 0)
                		$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
				}
				break;

			case "transferStop": /* transferStop */
				if ($tRunningFlag) {
					$ch = ClientHandler::getInstance($client);
					$ch->stop($transfer);
					if (count($ch->messages) > 0)
                		$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
				}
				break;

			case "transferEnQueue": /* transferEnQueue */
				if (!$tRunningFlag) {
					// enqueue it
					$ch = ClientHandler::getInstance($client);
					$ch->start($transfer, false, true);
					if (count($ch->messages) > 0)
                		$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
				}
				break;

			case "transferDeQueue": /* transferDeQueue */
				if (!$tRunningFlag) {
					// dequeue it
					FluxdQmgr::dequeueTransfer($transfer, $cfg['user']);
				}
				break;

			case "transferResetTotals": /* transferResetTotals */
				$msgs = resetTransferTotals($transfer, false);
				if (count($msgs) > 0)
                	$dispatcherMessages = array_merge($dispatcherMessages, $msgs);
				break;

			default:
				if ($tRunningFlag) {
					// stop first
					$ch = ClientHandler::getInstance($client);
					$ch->stop($transfer);
					if (count($ch->messages) > 0)
                		$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
					// is transfer running ?
					$tRunningFlag = isTransferRunning($transfer);
				}
				// if it was running... hope the thing is down...
				// only continue if it is
				if (!$tRunningFlag) {
					switch ($action) {
						case "transferWipe": /* transferWipe */
							$msgsDelete = deleteTransferData($transfer);
							if (count($msgsDelete) > 0)
                				$dispatcherMessages = array_merge($dispatcherMessages, $msgsDelete);
							$msgsReset = resetTransferTotals($transfer, true);
							if (count($msgsReset) > 0)
            					$dispatcherMessages = array_merge($dispatcherMessages, $msgsReset);
							break;
						case "transferData": /* transferData */
							$msgsDelete = deleteTransferData($transfer);
							if (count($msgsDelete) > 0)
                				$dispatcherMessages = array_merge($dispatcherMessages, $msgsDelete);
						case "transfer": /* transfer */
							$ch = ClientHandler::getInstance($client);
							$ch->delete($transfer);
							if (count($ch->messages) > 0)
                				$dispatcherMessages = array_merge($dispatcherMessages, $ch->messages);
					}
				}

		} // end switch

	} // end loop

	// error if messages
	if (count($dispatcherMessages) > 0)
		@error("There were Problems", "", "", $dispatcherMessages);
}

/**
 * processDownload
 *
 * @param $url url of metafile to download
 */
function dispatcher_processDownload($url, $type = 'torrent') {
	global $cfg;
	switch ($type) {
		default:
		case 'torrent':
			// process download
			_dispatcher_processDownload($url, 'torrent', '.torrent');
			break;
		case 'nzb':
			// is enabled ?
			if ($cfg["enable_nzbperl"] == 0) {
				AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use nzb-download");
				@error("nzbperl is disabled", "", "");
			} else if ($cfg["enable_nzbperl"] == 1) {
				if (!$cfg['isAdmin']) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use nzb-download");
					@error("nzbperl is disabled for users", "", "");
				}
			}
			// process download
			_dispatcher_processDownload($url, 'nzb', '.nzb');
			break;
	}
}

/**
 * (internal) Function with which metafiles are downloaded and injected
 *
 * @param $url url to download
 * @param $type
 */
function _dispatcher_processDownload($url, $type = 'torrent', $ext = '.torrent') {
	global $cfg;
	$filename = "";
	$downloadMessages = array();
	if (!empty($url)) {
		$arURL = explode("/", $url);
		$filename = urldecode($arURL[count($arURL)-1]); // get the file name
		$filename = str_replace(array("'",","), "", $filename);
		$filename = stripslashes($filename);
		// Check to see if url has something like ?passkey=12345
		// If so remove it.
		if (($point = strrpos($filename, "?")) !== false )
			$filename = substr($filename, 0, $point);
		$ret = strrpos($filename, ".");
		if ($ret === false) {
			$filename .= $ext;
		} else {
			if (!strcmp(strtolower(substr($filename, -(strlen($ext)))), $ext) == 0)
				$filename .= $ext;
		}
		$url = str_replace(" ", "%20", $url);
		// This is to support Sites that pass an id along with the url for downloads.
		$tmpId = tfb_getRequestVar("id");
		if(!empty($tmpId))
			$url .= "&id=".$tmpId;
		// retrieve the file
		require_once("inc/classes/SimpleHTTP.php");
		$content = "";
		switch ($type) {
			default:
			case 'torrent':
				$content = SimpleHTTP::getTorrent($url);
				break;
			case 'nzb':
				$content = SimpleHTTP::getNzb($url);
				break;
		}
		if ((SimpleHTTP::getState() == SIMPLEHTTP_STATE_OK) && (strlen($content) > 0)) {
			$fileNameBackup = $filename;
			$filename = SimpleHTTP::getFilename();
			if ($filename != "") {
				$filename = ((strpos($filename, $ext) !== false))
					? tfb_cleanFileName($filename)
					: tfb_cleanFileName($filename.$ext);
			}
			if (($filename == "") || ($filename === false) || (transferExists($filename))) {
				$filename = tfb_cleanFileName($fileNameBackup);
				if (($filename === false) || (transferExists($filename))) {
					$filename = tfb_cleanFileName($url.$ext);
					if (($filename === false) || (transferExists($filename))) {
						$filename = tfb_cleanFileName(md5($url.strval(@microtime())).$ext);
						if (($filename === false) || (transferExists($filename))) {
							// Error
							array_push($downloadMessages , "failed to get a valid transfer-filename for ".$url);
						}
					}
				}
			}
			if (empty($downloadMessages)) { // no messages
				// check if content contains html
				if ($cfg['debuglevel'] > 0) {
					if (strpos($content, "<br />") !== false)
						AuditAction($cfg["constants"]["debug"], "download-content contained html : ".htmlentities(addslashes($url), ENT_QUOTES));
				}
				if (is_file($cfg["transfer_file_path"].$filename)) {
					// Error
					array_push($downloadMessages, "the file ".$filename." already exists on the server.");
				} else {
					// write to file
					$handle = false;
					$handle = @fopen($cfg["transfer_file_path"].$filename, "w");
					if (!$handle) {
						array_push($downloadMessages, "cannot open ".$filename." for writing.");
					} else {
						$result = @fwrite($handle, $content);
						@fclose($handle);
						if ($result === false)
							array_push($downloadMessages, "cannot write content to ".$filename.".");
					}
				}
			}
		} else {
			$msgs = SimpleHTTP::getMessages();
			if (count($msgs) > 0)
				$downloadMessages = array_merge($downloadMessages, $msgs);
		}
		if (empty($downloadMessages)) { // no messages
			AuditAction($cfg["constants"]["url_upload"], $filename);
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
               		$downloadMessages = array_merge($downloadMessages, $ch->messages);
			}
		}
	} else {
		array_push($downloadMessages, "Invalid Url : ".$url);
	}
	if (count($downloadMessages) > 0) {
		AuditAction($cfg["constants"]["error"], $cfg["constants"]["url_upload"]." :: ".$filename);
		@error("There were Problems", "", "", $downloadMessages);
	}
}

/**
 * processUpload
 */
function dispatcher_processUpload() {
	global $cfg;
	// check if files exist
	if (empty($_FILES)) {
		// log
		AuditAction($cfg["constants"]["error"], "no file in file-upload");
		// return
		return;
	}
	// action-id
	$actionId = tfb_getRequestVar('aid');
	// file upload
	$uploadMessages = array();
	// stack
	$tStack = array();
	// process upload
	while (count($_FILES) > 0) {
		$upload = array_shift($_FILES);
		if (is_array($upload['size'])) {
			foreach ($upload['size'] as $id => $size) {
				if ($size > 0) {
					_dispatcher_processUpload(
						$upload['name'][$id], $upload['tmp_name'][$id], $size,
						$actionId, $uploadMessages, $tStack);
				}
			}
		} else {
			if ($upload['size'] > 0) {
				_dispatcher_processUpload(
					$upload['name'], $upload['tmp_name'], $upload['size'],
					$actionId, $uploadMessages, $tStack);
			}
		}
	}
	// instant action ?
	if (($actionId > 1) && (!empty($tStack))) {
		foreach ($tStack as $transfer) {
			$ch = ClientHandler::getInstance(getTransferClient($transfer));
			switch ($actionId) {
				case 3:
					$ch->start($transfer, false, true);
					break;
				case 2:
					$ch->start($transfer, false, false);
					break;
			}
			if (count($ch->messages) > 0)
       			$uploadMessages = array_merge($uploadMessages, $ch->messages);
		}
	}
	// messages
	if (count($uploadMessages) > 0) {
		@error("There were Problems", "", "", $uploadMessages);
	}
}

/**
 * _dispatcher_processUpload
 *
 * @param $name
 * @param $tmp_name
 * @param $size
 * @param $actionId
 * @param &$uploadMessages
 * @param &$tStack
 * @return bool
 */
function _dispatcher_processUpload($name, $tmp_name, $size, $actionId, &$uploadMessages, &$tStack) {
	global $cfg;
	$filename = tfb_cleanFileName(stripslashes($name));
	if ($filename === false) {
		// invalid file
		array_push($uploadMessages, "The type of file ".stripslashes($name)." is not allowed.");
		array_push($uploadMessages, "\nvalid file-extensions: ");
		array_push($uploadMessages, $cfg["file_types_label"]);
		return false;
	} else {
		// file is valid
		if (substr($filename, -5) == ".wget") {
			// is enabled ?
			if ($cfg["enable_wget"] == 0) {
				AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload wget-file ".$filename);
				array_push($uploadMessages, "wget is disabled  : ".$filename);
				return false;
			} else if ($cfg["enable_wget"] == 1) {
				if (!$cfg['isAdmin']) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload wget-file ".$filename);
					array_push($uploadMessages, "wget is disabled for users : ".$filename);
					return false;
				}
			}
		} else if (substr($filename, -4) == ".nzb") {
			// is enabled ?
			if ($cfg["enable_nzbperl"] == 0) {
				AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload nzb-file ".$filename);
				array_push($uploadMessages, "nzbperl is disabled  : ".$filename);
				return false;
			} else if ($cfg["enable_nzbperl"] == 1) {
				if (!$cfg['isAdmin']) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to upload nzb-file ".$filename);
					array_push($uploadMessages, "nzbperl is disabled for users : ".$filename);
					return false;
				}
			}
		}
		if ($size <= $cfg["upload_limit"] && $size > 0) {
			//FILE IS BEING UPLOADED
			if (@is_file($cfg["transfer_file_path"].$filename)) {
				// Error
				array_push($uploadMessages, "the file ".$filename." already exists on the server.");
				return false;
			} else {
				if (@move_uploaded_file($tmp_name, $cfg["transfer_file_path"].$filename)) {
					@chmod($cfg["transfer_file_path"].$filename, 0644);
					AuditAction($cfg["constants"]["file_upload"], $filename);
					// inject
					injectTransfer($filename);
					// instant action ?
					if ($actionId > 1)
						array_push($tStack,$filename);
					// return
					return true;
				} else {
					array_push($uploadMessages, "File not uploaded, file could not be found or could not be moved: ".$cfg["transfer_file_path"].$filename);
					return false;
			  	}
			}
		} else {
			array_push($uploadMessages, "File not uploaded, file size limit is ".$cfg["upload_limit"].". file has ".$size);
			return false;
		}
	}
}

/**
 * sendMetafile
 *
 * @param $mfile
 */
function dispatcher_sendMetafile($mfile) {
	global $cfg;
	// is enabled ?
	if ($cfg["enable_metafile_download"] != 1) {
		AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to download a metafile");
		@error("metafile download is disabled", "", "");
	}
	if (tfb_isValidTransfer($mfile) === true) {
		// Does the file exist?
		if (@file_exists($cfg["transfer_file_path"].$mfile)) {
			// filenames in IE containing dots will screw up the filename
			$headerName = (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
				? preg_replace('/\./', '%2e', $mfile, substr_count($mfile, '.') - 1)
				: $mfile;
			// Prompt the user to download file.
			if (substr($mfile, -8) == ".torrent")
				@header("Content-type: application/x-bittorrent\n");
			else
				@header( "Content-type: application/octet-stream\n" );
			@header("Content-disposition: attachment; filename=\"".$headerName."\"\n");
			@header("Content-transfer-encoding: binary\n");
			@header("Content-length: ".@filesize($cfg["transfer_file_path"].$mfile)."\n");
			// write the session to close so you can continue to browse on the site.
			@session_write_close();
			// Send the file
			$fp = @fopen($cfg["transfer_file_path"].$mfile, "r");
			@fpassthru($fp);
			@fclose($fp);
			AuditAction($cfg["constants"]["fm_download"], $mfile);
			exit();
		} else {
			AuditAction($cfg["constants"]["error"], "File Not found for download: ".$mfile);
			@error("File Not found for download", "", "", array($mfile));
		}
	} else {
		AuditAction($cfg["constants"]["error"], "ILLEGAL DOWNLOAD: ".$mfile);
		@error("Invalid File", "", "", array($mfile));
	}
}

/**
 * checkTypePermission
 *
 * @param $transfer
 * @param $type
 * @param $action
 */
function dispatcher_checkTypePermission($transfer, $type, $action) {
	global $cfg;
	switch ($type) {
		case "wget":
			// is enabled ?
			if ($cfg["enable_wget"] == 0) {
				AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use ".$action.". wget-transfer: ".$transfer);
				@error("wget is disabled", "", "");
			} else if ($cfg["enable_wget"] == 1) {
				if (!$cfg['isAdmin']) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use ".$action.". wget-transfer: ".$transfer);
					@error("wget is disabled for users", "", "");
				}
			}
			break;
		case "nzb":
			// is enabled ?
			if ($cfg["enable_nzbperl"] == 0) {
				AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use ".$action.". nzb-transfer: ".$transfer);
				@error("nzbperl is disabled", "", "");
			} else if ($cfg["enable_nzbperl"] == 1) {
				if (!$cfg['isAdmin']) {
					AuditAction($cfg["constants"]["error"], "ILLEGAL ACCESS: ".$cfg["user"]." tried to use ".$action.". nzb-transfer: ".$transfer);
					@error("nzbperl is disabled for users", "", "");
				}
			}
			break;
	}
}

/**
 * exit
 */
function dispatcher_exit() {
	global $cfg;
	$redir = (isset($_REQUEST['riid']))
		? tfb_getRequestVar('riid')
		: "index";
	switch ($redir) {
		case "_exit_":
			exit("1");
		case "_none_":
			break;
		case "_referer_":
			if (isset($_SERVER["HTTP_REFERER"]))
				@header("location: ".$_SERVER["HTTP_REFERER"]);
			break;
		default:
			if (preg_match('/^[a-zA-Z]+$/D', $redir)) {
				@header("location: index.php?iid=".$redir);
			} else {
				AuditAction($cfg["constants"]["error"], "INVALID PAGE (riid): ".$redir);
				@error("Invalid Page", "", "", array($redir));
			}
	}
	// exit
	exit();
}

?>