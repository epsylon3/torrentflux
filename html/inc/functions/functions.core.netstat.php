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
 * netstatConnectionsSum
 *
 * @return int
 */
function netstatConnectionsSum() {
	global $cfg;
	switch ($cfg["_OS"]) {
		case 1: // linux
			return intval(trim(shell_exec($cfg['bin_netstat']." -e -p --tcp -n 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." -v 127.0.0.1 | ".$cfg['bin_grep']." -cE '.*(python|transmissionc|wget|nzbperl|java).*'")));
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
			return intval(trim(shell_exec($cfg['bin_sockstat']." -c -4 | ".$cfg['bin_grep']." -v 127.0.0.1 | ".$cfg['bin_grep']." -cE '".$webserverUser.".+(python|transmissi|wget|nzbperl|java)'")));
	}
	return 0;
}

/**
 * netstatConnections
 *
 * @param $transfer
 * @return int
 */
function netstatConnections($transfer) {
	return netstatConnectionsByPid(getTransferPid($transfer));
}

/**
 * netstatConnectionsByPid
 *
 * @param $transferPid
 * @return int
 */
function netstatConnectionsByPid($transferPid) {
	global $cfg;
	switch ($cfg["_OS"]) {
		case 1: // linux
			return trim(shell_exec($cfg['bin_netstat']." -e -p --tcp --numeric-hosts --numeric-ports 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." -v 127.0.0.1 | ".$cfg['bin_grep']." -c \"".$transferPid ."/\""));
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
			return intval(trim(shell_exec($cfg['bin_sockstat']." -c | ".$cfg['bin_grep']." -cE ".$webserverUser.".+".$transferPid)));
	}
}

/**
 * netstatPortList
 *
 * @return string
 */
function netstatPortList() {
	global $cfg;
	$retStr = "";
	switch ($cfg["_OS"]) {
		case 1: // linux
			// not time-critical (only used on allServices-page), use the
			// generic and correct way :
			// array with all clients
			$clients = array('tornado', 'transmission', 'wget', 'nzbperl', 'azureus');
			// get informations
			foreach ($clients as $client) {
				$ch = ClientHandler::getInstance($client);
				$retStr .= shell_exec($cfg['bin_netstat']." -e -l -p --tcp --numeric-hosts --numeric-ports 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." ". $ch->binSocket ." | ".$cfg['bin_awk']." '{print \$4}' | ".$cfg['bin_awk']." 'BEGIN{FS=\":\"}{print \$2}'");
			}
			break;
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
			$retStr .= shell_exec($cfg['bin_sockstat']." -4 -l | ".$cfg['bin_awk']." '/(python|transmissi|wget|nzbperl|java).+\*:[0-9]/ {split(\$6, a, \":\");print a[2]}'");
			break;
	}
	return $retStr;
}

/**
 * netstatPort
 *
 * @param $transfer
 * @return int
 */
function netstatPort($transfer) {
	return netstatPortByPid(getTransferPid($transfer));
}

/**
 * netstatPortByPid
 *
 * @param $transferPid
 * @return int
 */
function netstatPortByPid($transferPid) {
	global $cfg;
	switch ($cfg["_OS"]) {
		case 1: // linux
			return trim(shell_exec($cfg['bin_netstat']." -l -e -p --tcp --numeric-hosts --numeric-ports 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." \"".$transferPid ."/\" | ".$cfg['bin_awk']." '{print \$4}' | ".$cfg['bin_awk']." 'BEGIN{FS=\":\"}{print \$2}'"));
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
	//delete	return shell_exec($cfg['bin_sockstat']." | ".$cfg['bin_awk']." '/".$webserverUser.".*".$transferPid.".*tcp4 .*\*:[0-9]/ {split(\$6, a, \":\");print a[2];nextfile}'");
			return shell_exec($cfg['bin_sockstat']." -4 -l | ".$cfg['bin_awk']." '/".$webserverUser.".+".$transferPid.".+:[0-9]/ {split(\$6, a, \":\"); if (a[2] != 80) {print a[2]; nextfile}}'");
	}
}

/**
 * netstatHostList
 *
 * @return string
 */
function netstatHostList() {
	global $cfg;
	$retStr = "";
	switch ($cfg["_OS"]) {
		case 1: // linux
			// not time-critical (only used on allServices-page), use the
			// generic and correct way :
			// array with all clients
			$clients = array('tornado', 'transmission', 'wget', 'nzbperl', 'azureus');
			// get informations
			foreach($clients as $client) {
				$ch = ClientHandler::getInstance($client);
				$retStr .= shell_exec($cfg['bin_netstat']." -e -p --tcp --numeric-hosts --numeric-ports 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." -v 127.0.0.1 | ".$cfg['bin_grep']." ". $ch->binSocket ." | ".$cfg['bin_awk']." '{print \$5}'");
			}
			break;
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
			$retStr .= shell_exec($cfg['bin_sockstat']." -4 -c | ".$cfg['bin_awk']." '/".$webserverUser.".+(python|transmissi|nzbperl|wget|java)/ {split(\$7, a, \":\"); print a[1]}'");
			break;
	}
	return $retStr;
}

/**
 * netstatHosts
 *
 * @param $transfer
 * @return array
 */
function netstatHosts($transfer) {
	return netstatHostsByPid(getTransferPid($transfer));
}

/**
 * netstatHostsByPid
 *
 * @param $transferPid
 * @return array
 */
function netstatHostsByPid($transferPid) {
	global $cfg;
	switch ($cfg["_OS"]) {
		case 1: // linux
			$hostList = shell_exec($cfg['bin_netstat']." -e -p --tcp --numeric-hosts --numeric-ports 2> /dev/null | ".$cfg['bin_grep']." -v root | ".$cfg['bin_grep']." -v 127.0.0.1 | ".$cfg['bin_grep']." \"".$transferPid."/\" | ".$cfg['bin_awk']." '{print \$5}'");
			break;
		case 2: // bsd
			$processUser = posix_getpwuid(posix_geteuid());
			$webserverUser = $processUser['name'];
			$hostList = shell_exec($cfg['bin_sockstat']." -4 -c | ".$cfg['bin_awk']." '/".$webserverUser.".+".$transferPid."/ {print \$7}'");
			break;
	}
	$retVal = array();
	$hostAry = explode("\n", $hostList);
	foreach ($hostAry as $hostLine) {
		$hostLineAry = explode(':', trim($hostLine));
		$retVal[$hostLineAry[0]] = isset($hostLineAry[1]) ? $hostLineAry[1] : "";
	}
	return $retVal;
}

?>
