/* $Id$ */

// fields
var ajax_fieldIds = new Array(
	"speedDown",
	"speedUp",
	"speedTotal",
	"cons",
	"freeSpace",
	"loadavg"
);
var ajax_idCount = ajax_fieldIds.length;
var ajax_fieldIdsXfer = new Array(
	"xferGlobalTotal",
	"xferGlobalMonth",
	"xferGlobalWeek",
	"xferGlobalDay",
	"xferUserTotal",
	"xferUserMonth",
	"xferUserWeek",
	"xferUserDay"
);
var ajax_idCountXfer = ajax_fieldIdsXfer.length;
//
var silentEnabled = 0;
var titleChangeEnabled = 0;
var pageTitle = "torrentflux-b4rt";
var goodLookingStatsEnabled = 0;
var goodLookingStatsSettings = null;
var bottomStatsEnabled = 0;
var queueActive = 0;
var xferEnabled = 0;
var usersEnabled = 0;
var usersHideOffline = 0;
var userList = "";
var transferListEnabled = 0;
var sortTableEnabled = 0;
var driveSpaceBarStyle = "tf";
var bandwidthBarsEnabled = 0;
var bandwidthBarsStyle = "tf";
var imgSrcDriveSpaceBlank = "themes/default/images/blank.gif";
var imgHeightDriveSpaceBlank = 12;
var indexTimer = null;
var updateTimeLeft = 0;

/**
 * ajax_initialize
 *
 * @param timer
 * @param delim
 * @param sEnabled
 * @param tChangeEnabled
 * @param pTitle
 * @param glsEnabled
 * @param glsSettings
 * @param bsEnabled
 * @param qActive
 * @param xEnabled
 * @param uEnabled
 * @param uHideOffline
 * @param tEnabled
 * @param sortEnabled
 * @param dsBarStyle
 * @param bwBarsEnabled
 * @param bwBarsStyle
 */
function ajax_initialize(timer, delim, sEnabled, tChangeEnabled, pTitle, glsEnabled, glsSettings, bsEnabled, qActive, xEnabled, uEnabled, uHideOffline, tEnabled, sortEnabled, dsBarStyle, bwBarsEnabled, bwBarsStyle) {
	ajax_updateTimer = timer;
	ajax_txtDelim = delim;
	silentEnabled = sEnabled;
	titleChangeEnabled = tChangeEnabled;
	pageTitle = pTitle;
	goodLookingStatsEnabled = glsEnabled;
	bottomStatsEnabled = bsEnabled;
	queueActive = qActive;
	xferEnabled = xEnabled;
	usersEnabled = uEnabled;
	usersHideOffline = uHideOffline;
	transferListEnabled = tEnabled;
	sortTableEnabled = sortEnabled;
	driveSpaceBarStyle = dsBarStyle;
	bandwidthBarsEnabled = bwBarsEnabled;
	bandwidthBarsStyle = bwBarsStyle;
	// url + params
	ajax_updateUrl = "index.php?iid=index";
	ajax_updateParams = "&ajax_update=1";
	if ((bottomStatsEnabled == 1) && (xferEnabled == 1))
		ajax_updateParams += '1';
	else
		ajax_updateParams += '0';
	ajax_updateParams += usersEnabled;
	ajax_updateParams += transferListEnabled;
	// gls
	if (goodLookingStatsEnabled == 1)
		goodLookingStatsSettings = glsSettings.split(":");
	// state
	ajax_updateState = 1;
	// http-request
	ajax_httpRequest = ajax_getHttpRequest();
	// start update-thread
	updateTimeLeft = ajax_updateTimer / 1000;
	ajax_pageUpdate();
}

/**
 * page ajax-update
 *
 */
function ajax_pageUpdate() {
	var obj = document.getElementById("span_update");
	if (ajax_updateState == 1) {
		if (updateTimeLeft > 0) {
			if (silentEnabled == 0) {
				if (obj) obj.innerHTML = "Next AJAX-Update in " + String(updateTimeLeft) + " seconds";
			} else {
				if (obj) obj.innerHTML = "AJAX-Update enabled";
			}
			updateTimeLeft--;
		}
		else if (updateTimeLeft == 0) {
			updateTimeLeft = -1;
			if (silentEnabled == 0) {
				if (obj) obj.innerHTML = "Update in progress...";
			}
			if ((titleChangeEnabled == 1) && (silentEnabled == 0)) {
				document.title = "Update in progress... - "+ pageTitle;
			}
			setTimeout("ajax_update();", 100);
		}
		indexTimer = setTimeout("ajax_pageUpdate();", 1000);
	} else {
		if (obj) obj.innerHTML = "AJAX-Update disabled";
	}
}

/**
 * process XML-response
 *
 * @param content
 */
function ajax_processXML(content) {
	alert(content);
}

/**
 * process text-response
 *
 * @param content
 */
function ajax_processText(content) {
	var aryCount = 1;
	if ((bottomStatsEnabled == 1) && (xferEnabled == 1))
		aryCount++;
	if (usersEnabled == 1)
		aryCount++;
	if (transferListEnabled == 1)
		aryCount++;
	if (aryCount == 1) {
		// update
		ajax_updateContent(content, "", "", "");
	} else {
		var tempAry = content.split("|");
		// transfer-list
		var transferList = "";
		if (transferListEnabled == 1)
			transferList = tempAry.pop();
		// users
		var users = "";
		if (usersEnabled == 1)
			users = tempAry.pop();
		// xfer
		var statsXfer = "";
		if ((bottomStatsEnabled == 1) && (xferEnabled == 1))
			statsXfer = tempAry.pop();
		// update
		ajax_updateContent(tempAry.pop(), statsXfer, users, transferList);
	}
	// timer
	updateTimeLeft = ajax_updateTimer / 1000;
}

/**
 * update page contents from response
 *
 * @param statsServerStr
 * @param statsXferStr
 * @param usersStr
 * @param transferListStr
 */
function ajax_updateContent(statsServerStr, statsXferStr, usersStr, transferListStr) {
	var statsServer = statsServerStr.split(ajax_txtDelim);
	// page-title
	if (titleChangeEnabled == 1) {
		var newTitle = "";
		for (i = 0; i < 5; i++) {
			newTitle += statsServer[i] + "|";
		}
		newTitle += statsServer[5]+ " - " + pageTitle;
		document.title = newTitle;
	}
	// good looking stats
	if (goodLookingStatsEnabled == 1) {
		for (i = 0; i < ajax_idCount; i++) {
			if (goodLookingStatsSettings[i] == 1)
				document.getElementById("g_" + ajax_fieldIds[i]).innerHTML = statsServer[i];
		}
	}
	// drivespace-bar
	var dSpace = statsServer[10];
	document.getElementById("barFreeSpace").innerHTML = statsServer[4];
	document.getElementById("barDriveSpacePercent").innerHTML = (100 - dSpace);
	document.getElementById("barDriveSpace1").style.width = dSpace + "%";
	document.getElementById("barDriveSpace2").style.width = (100 - dSpace) + "%";
	if (driveSpaceBarStyle == "xfer") {
		// set color
		var dsbCol = 'rgb(';
		dsbCol += parseInt(255 - 255 * ((100 - dSpace) / 100));
		dsbCol += ',' + parseInt(255 * ((100 - dSpace) / 100));
		dsbCol += ',0)';
		document.getElementById("barDriveSpace2").style.backgroundcolor = dsbCol;
	}
	// bandwidth-bars
	if (bandwidthBarsEnabled == 1) {
		// up
		var upPer = statsServer[9];
		document.getElementById("barSpeedUpPercent").innerHTML = upPer;
		document.getElementById("barSpeedUp").innerHTML = statsServer[1];
		
		document.getElementById("barSpeedUp1").style.width = upPer + "%";

		document.getElementById("barSpeedUp2").style.width = (100 - upPer) + "%";
		// down
		var downPer = statsServer[8];
		document.getElementById("barSpeedDownPercent").innerHTML = downPer;
		document.getElementById("barSpeedDown").innerHTML = statsServer[0];
		document.getElementById("barSpeedDown2").style.width = (100 - downPer) + "%";
			
		document.getElementById("barSpeedDown1").style.width = downPer + "%";
		if (bandwidthBarsStyle == "xfer") {
			// set color
			// up
			var bwbCol  = 'rgb(';
			bwbCol += parseInt(255 - 255 * ((100 - upPer) / 150));
			bwbCol += ',' + parseInt(255 * ((100 - upPer) / 150));
			bwbCol += ',0)';
			document.getElementById("barSpeedUp1").style.backgroundcolor = bwbCol;

			// down
			bwbCol  = 'rgb(';
			bwbCol += parseInt(255 - 255 * ((100 - downPer) / 150));
			bwbCol += ',' + parseInt(255 * ((100 - downPer) / 150));
			bwbCol += ',0)';
			document.getElementById("barSpeedDown1").style.backgroundcolor = bwbCol;
		}
	}
	// bottom stats
	if (bottomStatsEnabled == 1) {
		for (i = 0; i < ajax_idCount; i++) {
			document.getElementById("b_" + ajax_fieldIds[i]).innerHTML = statsServer[i];
		}
		// running + queued
		if (queueActive == 1) {
			document.getElementById("running").innerHTML = statsServer[ajax_idCount];
			document.getElementById("queued").innerHTML = statsServer[ajax_idCount + 1];
		}
		// xfer
		if (xferEnabled == 1) {
			var statsXfer = statsXferStr.split(ajax_txtDelim);
			for (i = 0; i < ajax_idCountXfer; i++) {
				document.getElementById(ajax_fieldIdsXfer[i]).innerHTML = statsXfer[i];
			}
		}
	}
	// users
	if (usersEnabled == 1) {
		if (userList != usersStr) {
			userList = usersStr;
			if (usersHideOffline == 0) {
				var allUsers = usersStr.split("+");
				// online
				var onlineUsers = allUsers[0].split(ajax_txtDelim);
				var onlineUsersCount = onlineUsers.length;
				var htmlString = "";
				for (i = 0; i < onlineUsersCount; i++) {
					htmlString += '<a href="index.php?iid=message&to_user='+onlineUsers[i]+'">'+onlineUsers[i]+'</a><br>';
				}
				document.getElementById("usersOnline").innerHTML = htmlString;
				// offline
				var offlineUsers = allUsers[1].split(ajax_txtDelim);
				var offlineUsersCount = offlineUsers.length;
				htmlString = "";
				for (i = 0; i < offlineUsersCount; i++) {
					htmlString += '<a href="index.php?iid=message&to_user='+offlineUsers[i]+'">'+offlineUsers[i]+'</a><br>';
				}
				document.getElementById("usersOffline").innerHTML = htmlString;
			} else {
				// online
				var onlineUsers = usersStr.split(ajax_txtDelim);
				var onlineUsersCount = onlineUsers.length;
				var htmlString = "";
				for (i = 0; i < onlineUsersCount; i++) {
					htmlString += '<a href="index.php?iid=message&to_user='+onlineUsers[i]+'">'+onlineUsers[i]+'</a><br>';
				}
				document.getElementById("usersOnline").innerHTML = htmlString;
			}
		}
	}
	// transfer-list
	if (transferListEnabled == 1) {
		// update content
		document.getElementById("transferList").innerHTML = transferListStr;
		// re-init sort-table
		if (sortTableEnabled == 1)
			sortables_init();
	}
}


/**
 * unload
 */
function ajax_unload() {
	if(indexTimer) window.clearTimeout(indexTimer);
}
