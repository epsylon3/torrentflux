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

// states
define('SIMPLEHTTP_STATE_NULL', 0);                                      // null
define('SIMPLEHTTP_STATE_OK', 1);                                          // ok
define('SIMPLEHTTP_STATE_ERROR', -1);                                   // error

/**
 * SimpleHTTP
 */
class SimpleHTTP
{
	// public fields

    // timeout
    var $timeout = 20;

	/**
	 * Temporarily use HTTP/1.0 until chunked encoding is sorted out
	 * Valid values are '1.0' or '1.1'
	 * @param	string	$httpVersion
	 */
	var $httpVersion = "1.0";

	/**
	 * Cookie string used in raw HTTP request
	 * @param	string	$cookie
	 */
	var $cookie = "";

	/**
	 * URI/path used in GET request:
	 * @param	string	$getcmd
	 */
	var $getcmd	= "";

	/**
	 * the raw HTTP request to send to the remote webserver
	 * @param	string	$request
	 */
	var $request = "";

	/**
	 * the raw HTTP response received from the remote webserver
	 * @param	string	$responseBody
	 */
	var $responseBody = "";

	/**
	 * Array of HTTP response headers
	 * @param	array	$responseHeaders
	 */
	var $responseHeaders = array();

	/**
	 * Indicates if we got the response line or not from webserver
	 * 'HTTP/1.1 200 OK
	 * etc
	 * @param	bool	$gotResponseLine
	 */
	var $gotResponseLine = false;

	/**
	 * Status code of webserver response
	 * @param	string	$status
	 */
	var $status = "";

	/**
	 * socket
	 */
	var $socket = 0;

	/**
	 * Error string
	 * @param	string	$errstr
	 */
	var $errstr = "";

	/**
	 * Error number
	 * @param	int		$errno
	 */
	var $errno = 0;

	// user-agent
	var $userAgent = "";

    // filename
    var $filename = "";

    // url
    var $url = "";

    // referer
    var $referer = "";

    // messages
    var $messages = array();

    // state
    var $state = SIMPLEHTTP_STATE_NULL;

	// Number of redirects we've followed so far:
	var $redirectCount = 0;

	// Maximum number of redirects to follow:
	var $redirectMax = 5;

	// The redirect URL specified in the location header for a 30x status code:
	var $redirectUrl = "";

	// Can PHP do TLS?
	var $canTLS = null;


	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return SimpleHTTP
     */
    function getInstance() {
		global $instanceSimpleHTTP;
		// initialize if needed
		if (!isset($instanceSimpleHTTP))
			SimpleHTTP::initialize();
		return $instanceSimpleHTTP;
    }

    /**
     * initialize SimpleHTTP.
     */
    function initialize() {
    	global $instanceSimpleHTTP;
    	// create instance
    	if (!isset($instanceSimpleHTTP))
    		$instanceSimpleHTTP = new SimpleHTTP();
    }

	/**
	 * getState
	 *
	 * @return state
	 */
    function getState() {
		global $instanceSimpleHTTP;
		return (isset($instanceSimpleHTTP))
			? $instanceSimpleHTTP->state
			: SIMPLEHTTP_STATE_NULL;
    }

    /**
     * getMessages
     *
     * @return array
     */
    function getMessages() {
		global $instanceSimpleHTTP;
		return (isset($instanceSimpleHTTP))
			? $instanceSimpleHTTP->messages
			: array();
    }

    /**
     * getMessages
     *
     * @return string
     */
    function getFilename() {
		global $instanceSimpleHTTP;
		return (isset($instanceSimpleHTTP))
			? $instanceSimpleHTTP->filename
			: "";
    }

	/**
	 * method to get data from URL -- uses timeout and user agent
	 *
	 * @param $get_url
	 * @param $get_referer
	 * @return string
	 */
	function getData($get_url, $get_referer = "") {
		global $instanceSimpleHTTP;
		// initialize if needed
		if (!isset($instanceSimpleHTTP))
			SimpleHTTP::initialize();
		// call instance-method
		return $instanceSimpleHTTP->instance_getData($get_url, $get_referer);
	}

	/**
	 * get torrent from URL. Has support for specific sites
	 *
	 * @param $durl
	 * @return string
	 */
	function getTorrent($durl) {
		global $instanceSimpleHTTP;
		// initialize if needed
		if (!isset($instanceSimpleHTTP))
			SimpleHTTP::initialize();
		// call instance-method
		return $instanceSimpleHTTP->instance_getTorrent($durl);
	}

	/**
	 * get nzb from URL.
	 *
	 * @param $durl
	 * @return string
	 */
	function getNzb($durl) {
		global $instanceSimpleHTTP;
		// initialize if needed
		if (!isset($instanceSimpleHTTP))
			SimpleHTTP::initialize();
		// call instance-method
		return $instanceSimpleHTTP->instance_getNzb($durl);
	}

	/**
	 * get size from URL.
	 *
	 * @param $durl
	 * @return int
	 */
	function getRemoteSize($durl) {
		global $instanceSimpleHTTP;
		// initialize if needed
		if (!isset($instanceSimpleHTTP))
			SimpleHTTP::initialize();
		// call instance-method
		return $instanceSimpleHTTP->instance_getRemoteSize($durl);
	}

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the factory-method !
     *
     * @return SimpleHTTP
     */
    function SimpleHTTP() {
    	global $cfg;
		// user-agent
		$this->userAgent = $cfg['user_agent'];
		// ini-settings
		@ini_set("allow_url_fopen", "1");
		@ini_set("user_agent", $this->userAgent);
    }

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * method to get data from URL -- uses timeout and user agent
	 *
	 * @param $get_url
	 * @param $get_referer
	 * @return string
	 */
	function instance_getData($get_url, $get_referer = "") {
		global $cfg, $db;

		// set fields
		$this->url = $get_url;
		$this->referer = $get_referer;

    	// (re)set state
    	$this->state = SIMPLEHTTP_STATE_NULL;

		// (re-)set some vars
		$this->cookie = "";
		$this->request = "";
		$this->responseBody = "";
		$this->responseHeaders = array();
		$this->gotResponseLine = false;
		$this->status = "";
		$this->errstr = "";
		$this->errno = 0;
		$this->socket = 0;

		/**
		 * array of URL component parts for use in raw HTTP request
		 * @param	array	$domain
		 */
		$domain = parse_url($this->url);

		if (
			empty($domain) ||   // Check URL is a well-formed HTTP/HTTPS URL.
			empty($domain['scheme']) || ($domain['scheme'] != 'http' && $domain['scheme'] != 'https') ||
			empty($domain['host'])
		) {
			$this->state = SIMPLEHTTP_STATE_ERROR;
			$msg = "Error fetching " . $this->url .".  This is not a valid HTTP/HTTPS URL.";
			array_push($this->messages, $msg);
			AuditAction($cfg["constants"]["error"], $msg);
			return($data="");
		}

		$secure = $domain['scheme'] == 'https';
		if ($secure && !$this->_canTLS()) {
			$this->state = SIMPLEHTTP_STATE_ERROR;
			$msg = "Error fetching " . $this->url .".  PHP does not have module OpenSSL, which is needed for HTTPS.";
			array_push($this->messages, $msg);
			AuditAction($cfg["constants"]["error"], $msg);
			return($data="");
		}

		// get-command
		if (!array_key_exists("path", $domain))
			$domain["path"] = "/";
		$this->getcmd = $domain["path"];

	    if (!array_key_exists("query", $domain))
	        $domain["query"] = "";

		// append the query string if included:
	    $this->getcmd .= (!empty($domain["query"])) ? "?" . $domain["query"] : "";

		// Check to see if cookie required for this domain:
		$sql = "SELECT c.data AS data FROM tf_cookies AS c LEFT JOIN tf_users AS u ON ( u.uid = c.uid ) WHERE u.user_id = ".$db->qstr($cfg["user"])." AND c.host = ".$db->qstr($domain['host']);
		$this->cookie = $db->GetOne($sql);
		if ($db->ErrorNo() != 0) dbError($sql);

		if (!array_key_exists("port", $domain))
			$domain["port"] = $secure ? 443 : 80;

		// Fetch the data using fsockopen():
		$this->socket = @fsockopen(	// connect to server, let PHP handle TLS layer for an HTTPS connection
			($secure ? 'tls://' : '') . $domain["host"], $domain["port"],
			$this->errno, $this->errstr, $this->timeout
		);

		if (!empty($this->socket)) {
			// Write the outgoing HTTP request using cookie info

			// Standard HTTP/1.1 request looks like:
			//
			// GET /url/path/example.php HTTP/1.1
			// Host: example.com
			// Accept: */*
			// Accept-Language: en-us
			// User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1) Gecko/20061010 Firefox/2.0
			// Connection: Close
			// Cookie: uid=12345;pass=asdfasdf;
			//
			//$this->request  = "GET " . ($this->httpVersion=="1.1" ? $this->getcmd : $this->url ). " HTTP/" . $this->httpVersion ."\r\n";
			$this->request  = "GET ".$this->_fullURLEncode($this->getcmd)." HTTP/".$this->httpVersion."\r\n";
			$this->request .= (!empty($this->referer)) ? "Referer: " . $this->referer . "\r\n" : "";
			$this->request .= "Accept: */*\r\n";
			$this->request .= "Accept-Language: en-us\r\n";
			$this->request .= "User-Agent: ".$this->userAgent."\r\n";
			$this->request .= "Host: " . $domain["host"] . "\r\n";
			if($this->httpVersion=="1.1"){
				$this->request .= "Connection: Close\r\n";
			}
			if(!empty($this->cookie)){
				$this->request .= "Cookie: " . $this->cookie . "\r\n";
			}
			$this->request .= "\r\n";

			// Send header packet information to server
			fputs($this->socket, $this->request);

			// socket-options
			stream_set_timeout($this->socket, $this->timeout);

			// meta-data
			$info = stream_get_meta_data($this->socket);

			// Get response headers:
			while ((!$info['timed_out']) && ($line = @fgets($this->socket, 500000))) {
				// First empty line/\r\n indicates end of response headers:
				if($line == "\r\n"){
					break;
				}

				if (!$this->gotResponseLine) {
					preg_match("@HTTP/[^ ]+ (\d\d\d)@", $line, $matches);
					// TODO: Use this to see if we redirected (30x) and follow the redirect:
					$this->status = $matches[1];
					$this->gotResponseLine = true;
					continue;
				}

				// Get response headers:
				preg_match("/^([^:]+):\s*(.*)/", trim($line), $matches);
				$this->responseHeaders[strtolower($matches[1])] = $matches[2];

				// meta-data
				$info = stream_get_meta_data($this->socket);
			}

			if(
				$this->httpVersion=="1.1"
				&& isset($this->responseHeaders["transfer-encoding"])
				&& !empty($this->responseHeaders["transfer-encoding"])
			) {
				/*
				// NOT CURRENTLY WORKING, USE HTTP/1.0 ONLY UNTIL THIS IS FIXED!
				*/

				// Get body of HTTP response:
				// Handle chunked encoding:
				/*
						length := 0
						read chunk-size, chunk-extension (if any) and CRLF
						while (chunk-size > 0) {
						   read chunk-data and CRLF
						   append chunk-data to entity-body
						   length := length + chunk-size
						   read chunk-size and CRLF
						}
				*/

				// Used to count total of all chunk lengths, the content-length:
				$chunkLength=0;

				// Get first chunk size:
				$chunkSize = hexdec(trim(fgets($this->socket)));

				// 0 size chunk indicates end of content:
				while ((!$info['timed_out']) && ($chunkSize > 0)) {
					// Read in up to $chunkSize chars:
					$line = @fgets($this->socket, $chunkSize);

					// Discard crlf after current chunk:
					fgets($this->socket);

					// Append chunk to response body:
					$this->responseBody .= $line;

					// Keep track of total chunk/content length:
					$chunkLength += $chunkSize;

					// Read next chunk size:
					$chunkSize = hexdec(trim(fgets($this->socket)));

					// meta-data
					$info = stream_get_meta_data($this->socket);
				}
				$this->responseHeaders["content-length"] = $chunkLength;
			} else {
				while ((!$info['timed_out']) && ($line = @fread($this->socket, 500000))) {
					$this->responseBody .= $line;
					// meta-data
					$info = stream_get_meta_data($this->socket);
				}
			}
			@fclose($this->socket); // Close our connection
		} else {
			return "Error fetching ".$this->url.".  PHP Error No=".$this->errno." . PHP Error String=".$this->errstr;
		}

		/*
		Check if we need to follow a redirect:
		http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html

		Each of these HTTP response status codes indicates a redirect and the
		content should be included in the Location field/header:

		300 Multiple Locations
		301 Moved Permanently
		302 Found (has a temp location somewhere else on server)
		303 See Other (should be fetched using GET, probably not relevant but won't hurt to include it)
		307 Temporary Redirect
		*/
		if( preg_match("/^30[0-37]$/D", $this->status) > 0 ){
			// Check we're not already over the max redirects limit:
			if ( $this->redirectCount > $this->redirectMax ) {
				$this->state = SIMPLEHTTP_STATE_ERROR;
				$msg = "Error fetching " . $this->url .".  The maximum number of allowed redirects ";
				$msg .="(" .$this->redirectMax. ") was exceeded.  Last followed URL was: " .$this->redirectUrl;
				array_push($this->messages , $msg);
				AuditAction($cfg["constants"]["error"], $msg);
				return($data="");
			} else {
				$this->redirectCount++;
				// Check we have a location to get redirected content:
				if( isset($this->responseHeaders["location"]) && !empty($this->responseHeaders["location"]) ){
					// 3 different cases for location header:
					// - full URL (scheme://.../foobar) -- just go to that URL,
					// - absolute URL (/foobar) -- keep everything up to host/port,
					//                             and replace end of request,
					// - relative URL (foobar) -- keep everything up to last component of path,
					//                            and replace end of request.
					$redirectLocation = $this->responseHeaders["location"];
					if (preg_match('#^(ht|f)tp(s)?://#', $redirectLocation) > 0) {
						// Case 1: full URL. Just use it.
						$this->redirectUrl = $redirectLocation;
					} else {
						// Cases 2 or 3: partial URL.
						// Keep scheme/user/pass/host/port of current request.
						$redirectUrlBase =
							$domain['scheme'].'://'.
							((isset($domain['user']) || isset($domain['pass'])) ?
								((isset($domain['user']) ?     $domain['user'] : '').
								 (isset($domain['pass']) ? ':'.$domain['pass'] : '').'@') : '').
							$domain['host'].
							(isset($domain['port']) ? ':'.$domain['port'] : '');
						if ($redirectLocation[0] == '/') {
							// Case 2: absolute URL.
							// Append it to current request's base.
							$this->redirectUrl = $redirectUrlBase . $redirectLocation;
						} else {
							// Case 3: relative URL.
							// Append it to current request's base + path stripped of its last component.
							$domainPathAry = explode('/', $domain['path']);
							array_splice($domainPathAry, -1, 1, $redirectLocation);
							$domainPathNew = implode('/', $domainPathAry);
							$this->redirectUrl =
								$redirectUrlBase .
								((isset($domainPathNew) &&
								  strlen($domainPathNew) > 0 &&
								  $domainPathNew[0] == '/') ? '' : '/') .
								$domainPathNew;
						}
					}
				} else {
					$msg = "Error fetching " . $this->url .".  A redirect status code (" . $this->status . ")";
					$msg .= " was sent from the remote webserver, but no location header was set to obtain the redirected content from.";
					AuditAction($cfg["constants"]["error"], $msg);
					array_push($this->messages , $msg);
					return($data="");
				}
				$this->instance_getData($this->redirectUrl);
			}
		}

		// Trim any extraneous linefeed chars:
		$this->responseBody = trim($this->responseBody, "\r\n");

		// If a filename is associated with this content, assign it to $filename
		if (isset($this->responseHeaders["content-disposition"]) && !empty($this->responseHeaders["content-disposition"])) {
			// Content-disposition: attachment; filename="nameoffile":
			// Don't think single quotes can be used to escape filename here, but just in case check for ' and ":
			if (preg_match("/filename=(['\"])([^\\1]+)\\1/", $this->responseHeaders["content-disposition"], $matches)) {
				if(isset($matches[2]) && !empty($matches[2])){
					$file_name = $matches[2];
					// Only accept filenames, not paths:
					if (!preg_match("@/@", $file_name))
						$this->filename = $file_name;
				}
			}
		}

        // state
        $this->state = SIMPLEHTTP_STATE_OK;

		// return content
		return $this->responseBody;
	}

	/**
	 * get torrent from URL. Has support for specific sites
	 *
	 * @param $durl
	 * @return string
	 */
	function instance_getTorrent($durl) {
		global $cfg;

    	// (re)set state
    	$this->state = SIMPLEHTTP_STATE_NULL;

    	// (re)set redir-count
    	$this->redirectCount = 0;

		// Initialize file name:
		$this->filename = "";

		$domain	 = parse_url($durl);

		// Check we have a remote URL:
		if (!isset($domain["host"])) {
			// Not a remote URL:
			$msg = "The torrent requested for download (".$durl.") is not a remote torrent. Please enter a valid remote torrent URL such as http://example.com/example.torrent\n";
			AuditAction($cfg["constants"]["error"], $msg);
			array_push($this->messages , $msg);
			// state
        	$this->state = SIMPLEHTTP_STATE_ERROR;
			// return empty data:
			return ($data="");
		}

		if (strtolower(substr($domain["path"], -8)) != ".torrent") {
			/*
				In these cases below, we check for torrent URLs that have to be manipulated in some
				way to obtain the torrent content.  These are sites that perhaps use redirection or
				URL rewriting in some way.
			*/
			// Check known domain types
			// mininova
			if (strpos(strtolower($domain["host"]), "mininova") !== false) {
				// Sample (http://www.mininova.org/rss.xml):
				// http://www.mininova.org/tor/2254847
				// <a href="/get/2281554">FreeLinux.ISO.iso.torrent</a>
				// If received a /tor/ get the required information
				if (strpos($durl, "/tor/") !== false) {
					// Get the contents of the /tor/ to find the real torrent name
					$data = $this->instance_getData($durl);
					// Check for the tag used on mininova.org
					if (preg_match("/<a href=\"\/get\/[0-9].[^\"]+\">(.[^<]+)<\/a>/i", $data, $data_preg_match)) {
						// This is the real torrent filename
						$this->filename = $data_preg_match[1];
					}
					// Change to GET torrent url
					$durl = str_replace("/tor/", "/get/", $durl);
				}
				// Now fetch the torrent file
				$data = $this->instance_getData($durl);
			// demonoid
			} elseif (strpos(strtolower($domain["host"]), "demonoid") !== false) {
				// Sample (http://www.demonoid.com/rss/0.xml):
				// http://www.demonoid.com/files/details/241739/6976998/
				// <a href="/files/download/HTTP/241739/6976998">...</a>

				// If received a /details/ page url, change it to the download url
				if (strpos($durl, "/details/") !== false) {
					// Need to make it grab the torrent
					$durl = str_replace("/details/", "/download/HTTP/", $durl);
				}
				// Now fetch the torrent file
				$data = $this->instance_getData($durl);
			// isohunt
			} elseif (strpos(strtolower($domain["host"]), "isohunt") !== false) {
				// Sample (http://isohunt.com/js/rss.php):
				$treferer = "http://" . $domain["host"] . "/btDetails.php?id=";
				// http://isohunt.com/torrent_details/7591035/
				// http://isohunt.com/download/7591035/
				// If the url points to the details page, change it to the download url
				if (strpos($durl, "/torrent_details/") !== false) {
					// Need to make it grab the torrent
					$durl = str_replace("/torrent_details/", "/download/", $durl);
				}
				// old one, but still works:
				// http://isohunt.com/btDetails.php?ihq=&id=8464972
				// http://isohunt.com/download.php?mode=bt&id=8837938
				// If the url points to the details page, change it to the download url
				if (strpos(strtolower($durl), "/btdetails.php?") !== false) {
					// Need to make it grab the torrent
					$durl = str_replace("/btDetails.php?", "/download.php?", $durl) . "&mode=bt";
				}
				// Now fetch the torrent file
				$data = $this->instance_getData($durl, $treferer);
			// details.php
			} elseif (strpos(strtolower($durl), "details.php?") !== false) {
				// Sample (http://www.bitmetv.org/rss.php?passkey=123456):
				// http://www.bitmetv.org/details.php?id=18435&hit=1

				// Strip final &hit=1 if present, since it only ever returns a 302
				// redirect to the same URL without the &hit=1.
				$durl2 = preg_replace('/&hit=1$/', '', $durl);

				$treferer = "http://" . $domain["host"] . "/details.php?id=";
				$data = $this->instance_getData($durl2, $treferer);

				// Sample (http://www.bitmetv.org/details.php?id=18435)
				// download.php/18435/SpiderMan%20Season%204.torrent
				if (preg_match("/(download.php.[^\"]+)/i", $data, $data_preg_match)) {
					$torrent = substr($data_preg_match[0], 0, -1);
					$turl2 = "http://" . $domain["host"] . "/" . $torrent;
					$data = $this->instance_getData($turl2);
				} else {
					$msg = "Error: could not find link to torrent file in $durl";
					AuditAction($cfg["constants"]["error"], $msg);
					array_push($this->messages , $msg);
					// state
			    	$this->state = SIMPLEHTTP_STATE_ERROR;
					// return empty data:
					return($data="");
				}
			// torrentspy
			} elseif (strpos(strtolower($domain["host"]), "torrentspy") !== false) {
				// Sample (http://torrentspy.com/rss.asp):
				// http://www.torrentspy.com/torrent/1166188/gentoo_livedvd_i686_installer_2007_0
				$treferer = "http://" . $domain["host"] . "/download.asp?id=";
				$data = $this->instance_getData($durl, $treferer);
				// If received a /download.asp?, a /directory.asp?mode=torrentdetails
				// or a /torrent/, extract real torrent link
				if (
					strpos($durl, "/download.asp?") !== false ||
					strpos($durl, "/directory.asp?mode=torrentdetails") !== false ||
					strpos($durl, "/torrent/") !== false
					) {
					// Check for the tag used in download details page
					if (preg_match("#<a\s+id=\"downloadlink0\"[^>]*?\s+href=\"(http[^\"]+)\"#i", $data, $data_preg_match)) {
						// This is the real torrent download link
						$durl = $data_preg_match[1];
						// Follow it
						$data = $this->instance_getData($durl);
					}
				}
			// download.asp
			} elseif (strpos(strtolower($durl), "download.asp?") !== false) {
				$treferer = "http://" . $domain["host"] . "/download.asp?id=";
				$data = $this->instance_getData($durl, $treferer);
			// default
			} else {
				// Fallback case for any URL not ending in .torrent and not matching the above cases:
				$data = $this->instance_getData($durl);
			}
		} else {
			$data = $this->instance_getData($durl);
		}
		// Make sure we have a torrent file
		if (strpos($data, "d8:") === false)	{
			// We don't have a Torrent File... it is something else.  Let the user know about it:
			$msg = "Content returned from $durl does not appear to be a valid torrent.";
			AuditAction($cfg["constants"]["error"], $msg);
			array_push($this->messages , $msg);
			// Display the first part of $data if debuglevel higher than 1:
			if ($cfg["debuglevel"] > 1){
				if (strlen($data) > 0){
					array_push($this->messages , "Displaying first 1024 chars of output: ");
					array_push($this->messages , htmlentities(substr($data, 0, 1023)), ENT_QUOTES);
				} else {
					array_push($this->messages , "Output from $durl was empty.");
				}
			} else {
				array_push($this->messages , "Set debuglevel > 2 in 'Admin, Webapps' to see the content returned from $durl.");
			}
			$data = "";
			// state
			$this->state = SIMPLEHTTP_STATE_ERROR;
		} else {
			// If the torrent file name isn't set already, do it now:
			if ((!isset($this->filename)) || (strlen($this->filename) == 0)) {
				// Get the name of the torrent, and make it the filename
				if (preg_match("/name([0-9][^:]):(.[^:]+)/i", $data, $data_preg_match)) {
					$filelength = $data_preg_match[1];
					$file_name = $data_preg_match[2];
					$this->filename = substr($file_name, 0, $filelength).".torrent";
				} else {
					require_once('inc/classes/BDecode.php');
				    $btmeta = @BDecode($data);
				    $this->filename = ((is_array($btmeta)) && (!empty($btmeta['info'])) && (!empty($btmeta['info']['name'])))
				    	? trim($btmeta['info']['name']).".torrent"
				    	: "";
					}
			}
	        // state
	        $this->state = SIMPLEHTTP_STATE_OK;
		}
		return $data;
	}

	/**
	 * get nzb from URL
	 *
	 * @param $durl
	 * @return string
	 */
	function instance_getNzb($durl) {
		global $cfg;

    	// (re)set state
    	$this->state = SIMPLEHTTP_STATE_NULL;

    	// (re)set redir-count
    	$this->redirectCount = 0;

		// Initialize file name:
		$this->filename = "";

		$domain	 = parse_url($durl);

		// Check we have a remote URL:
		if (!isset($domain["host"])) {
			// Not a remote URL:
			$msg = "The nzb requested for download (".$durl.") is not a remote nzb. Please enter a valid remote nzb URL such as http://example.com/example.nzb\n";
			AuditAction($cfg["constants"]["error"], $msg);
			array_push($this->messages , $msg);
			// state
        	$this->state = SIMPLEHTTP_STATE_ERROR;
			// return empty data:
			return ($data="");
		}

		if (strtolower(substr($domain["path"], -4)) != ".nzb") {
			/*
				In these cases below, we check for URLs that have to be manipulated in some
				way to obtain the content.  These are sites that perhaps use redirection or
				URL rewriting in some way.
			*/
			// details.php
			if (strpos(strtolower($durl), "details.php?") !== false) {
				// Sample (http://www.bitmetv.org/rss.php?passkey=123456):
				// http://www.bitmetv.org/details.php?id=18435&hit=1

				// Strip final &hit=1 if present, since it only ever returns a 302
				// redirect to the same URL without the &hit=1.
				$durl2 = preg_replace('/&hit=1$/', '', $durl);

				$treferer = "http://" . $domain["host"] . "/details.php?id=";
				$data = $this->instance_getData($durl, $treferer);

				// Sample (http://www.bitmetv.org/details.php?id=18435)
				// download.php/18435/SpiderMan%20Season%204.torrent
				if (preg_match("/(download.php.[^\"]+)/i", $data, $data_preg_match)) {
					$tr = substr($data_preg_match[0], 0, -1);
					$turl2 = "http://" . $domain["host"] . "/" . $tr;
					$data = $this->instance_getData($turl2);
				} else {
					$msg = "Error: could not find link to nzb file in $durl";
					AuditAction($cfg["constants"]["error"], $msg);
					array_push($this->messages , $msg);
					// state
			    	$this->state = SIMPLEHTTP_STATE_ERROR;
					// return empty data:
					return($data="");
				}
			// download.asp
			} elseif (strpos(strtolower($durl), "download.asp?") !== false) {
				// Sample (TF's TorrentSpy Search):
				// http://www.torrentspy.com/download.asp?id=519793
				$treferer = "http://" . $domain["host"] . "/download.asp?id=";
				$data = $this->instance_getData($durl, $treferer);
			// default
			} else {
				// Fallback case for any URL not ending in .nzb and not matching the above cases:
				$data = $this->instance_getData($durl);
			}
		} else {
			$data = $this->instance_getData($durl);
		}
		// Make sure we have a nzb file
		if (strpos($data, "nzb") === false)	{
			// We don't have a nzb File... it is something else.  Let the user know about it:
			$msg = "Content returned from $durl does not appear to be a valid nzb.";
			AuditAction($cfg["constants"]["error"], $msg);
			array_push($this->messages , $msg);
			// Display the first part of $data if debuglevel higher than 1:
			if ($cfg["debuglevel"] > 1){
				if (strlen($data) > 0){
					array_push($this->messages , "Displaying first 1024 chars of output: ");
					array_push($this->messages , htmlentities(substr($data, 0, 1023)), ENT_QUOTES);
				} else {
					array_push($this->messages , "Output from $durl was empty.");
				}
			} else {
				array_push($this->messages , "Set debuglevel > 2 in 'Admin, Webapps' to see the content returned from $durl.");
			}
			$data = "";
			// state
			$this->state = SIMPLEHTTP_STATE_ERROR;
		} else {
	        // state
	        $this->state = SIMPLEHTTP_STATE_OK;
		}
		return $data;
	}

	/**
	 * get size from URL.
	 *
	 * @param $durl
	 * @return string
	 */
	function instance_getRemoteSize($durl) {
		// set fields
		$this->url = $durl;
		$this->timeout = 8;
		$this->status = "";
		$this->errstr = "";
		$this->errno = 0;
		// domain
		$domain = parse_url($this->url);
		if (!isset($domain["port"]))
			$domain["port"] = 80;
		// check we have a remote URL:
		if (!isset($domain["host"]))
			return 0;
		// check we have a remote path:
		if (!isset($domain["path"]))
			return 0;
		// open socket
		$this->socket = @fsockopen($domain["host"], $domain["port"], $this->errno, $this->errstr, $this->timeout);
		if (!$this->socket)
			return 0;
		// send HEAD request
		$this->request  = "HEAD ".$domain["path"]." HTTP/1.0\r\nConnection: Close\r\n\r\n";
		@fwrite($this->socket, $this->request);
		// socket options
		stream_set_timeout($this->socket, $this->timeout);
		// meta data
		$info = stream_get_meta_data($this->socket);
		// read the response
		$this->responseBody = "";
		$ctr = 0;
		while ((!$info['timed_out']) && ($ctr < 25)) {
			$s = @fgets($this->socket, 4096);
			$this->responseBody .= $s;
			if (strcmp($s, "\r\n") == 0 || strcmp($s, "\n") == 0)
				break;
			// meta data
			$info = stream_get_meta_data($this->socket);
			// increment counter
			$ctr++;
		}
		// close socket
		@fclose($this->socket);
		// try to get Content-Length in response-body
		preg_match('/Content-Length:\s([0-9].+?)\s/', $this->responseBody, $matches);
		return (isset($matches[1]))
			? $matches[1]
			: 0;
	}

	/**
	 * Check whether PHP can do TLS.
	 *
	 * @return bool
	 */
	function _canTLS() {
		// Just check whether openssl extension is available.
		if (!isset($this->canTLS))
			$this->canTLS = extension_loaded('openssl');
		return $this->canTLS;
	}

	/**
	 * URL-encode all fragments of an URL, not re-encoding what is already encoded.
	 *
	 * @param $url
	 * @return string
	 */
	function _fullURLEncode($url) {
		# Split URL into fragments, delimiters are: '+', ':', '@', '/', '?', '=', '&' and /%[[:xdigit:]]{2}/.
		$fragments = preg_split('#[+:@/?=&]+|%[[:xdigit:]]{2}#', $url, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

		for ($i = count($fragments) - 1; $i >= 0; $i--) {
			$fragment = $fragments[$i];	# $fragment[0] is the fragment, $fragment[1] is its starting position.
			$url = substr_replace($url, rawurlencode($fragment[0]), $fragment[1], strlen($fragment[0]));
		}

		return $url;
	}

}

?>