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
 * Get Languages in an array
 *
 * @return array
 */
function GetLanguages() {
	$arLanguages = array();
	$dir = "inc/language/";
	$handle = opendir($dir);
	while($entry = readdir($handle)) {
		if (is_file($dir.$entry) && (strcmp(substr($entry, strlen($entry)-4, 4), ".php") == 0))
			array_push($arLanguages, $entry);
	}
	closedir($handle);
	sort($arLanguages);
	return $arLanguages;
}

/**
 * Get Language name from file name
 *
 * @param $inFile
 * @return string
 */
function GetLanguageFromFile($inFile) {
	$rtnValue = "";
	$rtnValue = str_replace("lang-", "", $inFile);
	$rtnValue = str_replace(".php", "", $rtnValue);
	return $rtnValue;
}

/**
 * loads a language-file and sets string-vars.
 *
 * @param $language
 */
function loadLanguageFile($language) {
	global $cfg;
	// load language
	require_once("inc/language/".$language);
	// set vars
	$cfg['_CHARSET'] = _CHARSET;
	$cfg['_SELECTFILE'] = _SELECTFILE;
	$cfg['_URLFILE'] = _URLFILE;
	$cfg['_UPLOAD'] = _UPLOAD;
	$cfg['_GETFILE'] = _GETFILE;
	$cfg['_LINKS'] = _LINKS;
	$cfg['_ONLINE'] = _ONLINE;
	$cfg['_OFFLINE'] = _OFFLINE;
	$cfg['_STORAGE'] = _STORAGE;
	$cfg['_DRIVESPACE'] = _DRIVESPACE;
	$cfg['_SERVERSTATS'] = _SERVERSTATS;
	$cfg['_DIRECTORYLIST'] = _DIRECTORYLIST;
	$cfg['_ALL'] = _ALL;
	$cfg['_PAGEWILLREFRESH'] = _PAGEWILLREFRESH;
	$cfg['_SECONDS'] = _SECONDS;
	$cfg['_TURNONREFRESH'] = _TURNONREFRESH;
	$cfg['_TURNOFFREFRESH'] = _TURNOFFREFRESH;
	$cfg['_WARNING'] = _WARNING;
	$cfg['_DRIVESPACEUSED'] = _DRIVESPACEUSED;
	$cfg['_ADMINMESSAGE'] = _ADMINMESSAGE;
	$cfg['_TORRENTS'] = _TORRENTS;
	$cfg['_UPLOADHISTORY'] = _UPLOADHISTORY;
	$cfg['_MYPROFILE'] = _MYPROFILE;
	$cfg['_ADMINISTRATION'] = _ADMINISTRATION;
	$cfg['_SENDMESSAGETO'] = _SENDMESSAGETO;
	$cfg['_TRANSFERFILE'] = _TRANSFERFILE;
	$cfg['_FILESIZE'] = _FILESIZE;
	$cfg['_STATUS'] = _STATUS;
	$cfg['_ADMIN'] = _ADMIN;
	$cfg['_BADFILE'] = _BADFILE;
	$cfg['_DATETIMEFORMAT'] = _DATETIMEFORMAT;
	$cfg['_DATEFORMAT'] = _DATEFORMAT;
	$cfg['_ESTIMATEDTIME'] = _ESTIMATEDTIME;
	$cfg['_DOWNLOADSPEED'] = _DOWNLOADSPEED;
	$cfg['_UPLOADSPEED'] = _UPLOADSPEED;
	$cfg['_SHARING'] = _SHARING;
	$cfg['_USER'] = _USER;
	$cfg['_DONE'] = _DONE;
	$cfg['_INCOMPLETE'] = _INCOMPLETE;
	$cfg['_NEW'] = _NEW;
	$cfg['_TRANSFERDETAILS'] = _TRANSFERDETAILS;
	$cfg['_STOPTRANSFER'] = _STOPTRANSFER;
	$cfg['_RUNTRANSFER'] = _RUNTRANSFER;
	$cfg['_SEEDTRANSFER'] = _SEEDTRANSFER;
	$cfg['_DELETE'] = _DELETE;
	$cfg['_ABOUTTODELETE'] = _ABOUTTODELETE;
	$cfg['_NOTOWNER'] = _NOTOWNER;
	$cfg['_MESSAGETOALL'] = _MESSAGETOALL;
	$cfg['_TRYDIFFERENTUSERID'] = _TRYDIFFERENTUSERID;
	$cfg['_HASBEENUSED'] = _HASBEENUSED;
	$cfg['_RETURNTOEDIT'] = _RETURNTOEDIT;
	$cfg['_ADMINUSERACTIVITY'] = _ADMINUSERACTIVITY;
	$cfg['_ADMIN_MENU'] = _ADMIN_MENU;
	$cfg['_ACTIVITY_MENU'] = _ACTIVITY_MENU;
	$cfg['_LINKS_MENU'] = _LINKS_MENU;
	$cfg['_NEWUSER_MENU'] = _NEWUSER_MENU;
	$cfg['_BACKUP_MENU'] = _BACKUP_MENU;
	$cfg['_ALLUSERS'] = _ALLUSERS;
	$cfg['_NORECORDSFOUND'] = _NORECORDSFOUND;
	$cfg['_SHOWPREVIOUS'] = _SHOWPREVIOUS;
	$cfg['_SHOWMORE'] = _SHOWMORE;
	$cfg['_ACTIVITYSEARCH'] = _ACTIVITYSEARCH;
	$cfg['_FILE'] = _FILE;
	$cfg['_ACTION'] = _ACTION;
	$cfg['_SEARCH'] = _SEARCH;
	$cfg['_ACTIVITYLOG'] = _ACTIVITYLOG;
	$cfg['_DAYS'] = _DAYS;
	$cfg['_IP'] = _IP;
	$cfg['_TIMESTAMP'] = _TIMESTAMP;
	$cfg['_USERDETAILS'] = _USERDETAILS;
	$cfg['_HITS'] = _HITS;
	$cfg['_UPLOADACTIVITY'] = _UPLOADACTIVITY;
	$cfg['_JOINED'] = _JOINED;
	$cfg['_LASTVISIT'] = _LASTVISIT;
	$cfg['_USERSACTIVITY'] = _USERSACTIVITY;
	$cfg['_NORMALUSER'] = _NORMALUSER;
	$cfg['_ADMINISTRATOR'] = _ADMINISTRATOR;
	$cfg['_SUPERADMIN'] = _SUPERADMIN;
	$cfg['_EDIT'] = _EDIT;
	$cfg['_USERADMIN'] = _USERADMIN;
	$cfg['_EDITUSER'] = _EDITUSER;
	$cfg['_UPLOADPARTICIPATION'] = _UPLOADPARTICIPATION;
	$cfg['_UPLOADS'] = _UPLOADS;
	$cfg['_PERCENTPARTICIPATION'] = _PERCENTPARTICIPATION;
	$cfg['_PARTICIPATIONSTATEMENT'] = _PARTICIPATIONSTATEMENT;
	$cfg['_TOTALPAGEVIEWS'] = _TOTALPAGEVIEWS;
	$cfg['_THEME'] = _THEME;
	$cfg['_USERTYPE'] = _USERTYPE;
	$cfg['_NEWPASSWORD'] = _NEWPASSWORD;
	$cfg['_CONFIRMPASSWORD'] = _CONFIRMPASSWORD;
	$cfg['_HIDEOFFLINEUSERS'] = _HIDEOFFLINEUSERS;
	$cfg['_UPDATE'] = _UPDATE;
	$cfg['_USERIDREQUIRED'] = _USERIDREQUIRED;
	$cfg['_PASSWORDLENGTH'] = _PASSWORDLENGTH;
	$cfg['_PASSWORDNOTMATCH'] = _PASSWORDNOTMATCH;
	$cfg['_PLEASECHECKFOLLOWING'] = _PLEASECHECKFOLLOWING;
	$cfg['_NEWUSER'] = _NEWUSER;
	$cfg['_PASSWORD'] = _PASSWORD;
	$cfg['_CREATE'] = _CREATE;
	$cfg['_ADMINEDITLINKS'] = _ADMINEDITLINKS;
	$cfg['_FULLURLLINK'] = _FULLURLLINK;
	$cfg['_BACKTOPARRENT'] = _BACKTOPARRENT;
	$cfg['_DOWNLOADDETAILS'] = _DOWNLOADDETAILS;
	$cfg['_PERCENTDONE'] = _PERCENTDONE;
	$cfg['_RETURNTOTRANSFERS'] = _RETURNTOTRANSFERS;
	$cfg['_DATE'] = _DATE;
	$cfg['_WROTE'] = _WROTE;
	$cfg['_SENDMESSAGETITLE'] = _SENDMESSAGETITLE;
	$cfg['_TO'] = _TO;
	$cfg['_FROM'] = _FROM;
	$cfg['_YOURMESSAGE'] = _YOURMESSAGE;
	$cfg['_SENDTOALLUSERS'] = _SENDTOALLUSERS;
	$cfg['_FORCEUSERSTOREAD'] = _FORCEUSERSTOREAD;
	$cfg['_SEND'] = _SEND;
	$cfg['_PROFILE'] = _PROFILE;
	$cfg['_PROFILEUPDATEDFOR'] = _PROFILEUPDATEDFOR;
	$cfg['_REPLY'] = _REPLY;
	$cfg['_MESSAGE'] = _MESSAGE;
	$cfg['_MESSAGES'] = _MESSAGES;
	$cfg['_RETURNTOMESSAGES'] = _RETURNTOMESSAGES;
	$cfg['_COMPOSE'] = _COMPOSE;
	$cfg['_LANGUAGE'] = _LANGUAGE;
	$cfg['_CURRENTDOWNLOAD'] = _CURRENTDOWNLOAD;
	$cfg['_CURRENTUPLOAD'] = _CURRENTUPLOAD;
	$cfg['_SERVERLOAD'] = _SERVERLOAD;
	$cfg['_FREESPACE'] = _FREESPACE;
	$cfg['_UPLOADED'] = _UPLOADED;
	$cfg['_QMANAGER_MENU'] = _QMANAGER_MENU;
	$cfg['_FLUXD_MENU'] = _FLUXD_MENU;
	$cfg['_SETTINGS_MENU'] = _SETTINGS_MENU;
	$cfg['_SEARCHSETTINGS_MENU'] = _SEARCHSETTINGS_MENU;
	$cfg['_ERRORSREPORTED'] = _ERRORSREPORTED;
	$cfg['_STARTED'] = _STARTED;
	$cfg['_ENDED'] = _ENDED;
	$cfg['_QUEUED'] = _QUEUED;
	$cfg['_DELQUEUE'] = _DELQUEUE;
	$cfg['_FORCESTOP'] = _FORCESTOP;
	$cfg['_STOPPING'] = _STOPPING;
	$cfg['_COOKIE_MENU'] = _COOKIE_MENU;
	$cfg['_TOTALXFER'] = _TOTALXFER;
	$cfg['_MONTHXFER'] = _MONTHXFER;
	$cfg['_WEEKXFER'] = _WEEKXFER;
	$cfg['_DAYXFER'] = _DAYXFER;
	$cfg['_XFERTHRU'] = _XFERTHRU;
	$cfg['_REMAINING'] = _REMAINING;
	$cfg['_TOTALSPEED'] = _TOTALSPEED;
	$cfg['_SERVERXFERSTATS'] = _SERVERXFERSTATS;
	$cfg['_YOURXFERSTATS'] = _YOURXFERSTATS;
	$cfg['_OTHERSERVERSTATS'] = _OTHERSERVERSTATS;
	$cfg['_TOTAL'] = _TOTAL;
	$cfg['_DOWNLOAD'] = _DOWNLOAD;
	$cfg['_MONTHSTARTING'] = _MONTHSTARTING;
	$cfg['_WEEKSTARTING'] = _WEEKSTARTING;
	$cfg['_DAY'] = _DAY;
	$cfg['_XFER'] = _XFER;
	$cfg['_XFER_USAGE'] = _XFER_USAGE;
	$cfg['_QUEUEMANAGER'] = _QUEUEMANAGER;
	$cfg['_MULTIPLE_UPLOAD'] = _MULTIPLE_UPLOAD;
	$cfg['_TDDU'] = _TDDU;
	$cfg['_FULLSITENAME'] = _FULLSITENAME;
	$cfg['_MOVE_STRING'] = _MOVE_STRING;
	$cfg['_DIR_MOVE_LINK'] = _DIR_MOVE_LINK;
	$cfg['_MOVE_FILE'] = _MOVE_FILE;
	$cfg['_MOVE_FILE_TITLE'] = _MOVE_FILE_TITLE;
	$cfg['_REN_STRING'] = _REN_STRING;
	$cfg['_DIR_REN_LINK'] = _DIR_REN_LINK;
	$cfg['_REN_FILE'] = _REN_FILE;
	$cfg['_REN_DONE'] = _REN_DONE;
	$cfg['_REN_ERROR'] = _REN_ERROR;
	$cfg['_REN_ERR_ARG'] = _REN_ERR_ARG;
	$cfg['_REN_TITLE'] = _REN_TITLE;
	$cfg['_ID_PORT'] = _ID_PORT;
	$cfg['_ID_PORTS'] = _ID_PORTS;
	$cfg['_ID_CONNECTIONS'] = _ID_CONNECTIONS;
	$cfg['_ID_HOST'] = _ID_HOST;
	$cfg['_ID_HOSTS'] = _ID_HOSTS;
	$cfg['_ID_IMAGES'] = _ID_IMAGES;
}

?>