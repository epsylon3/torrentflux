/* $Id$ */

// fields
var ajax_fieldIds = new Array(
	"speedDown",
	"speedUp",
	"speedTotal",
	"cons",
	"freeSpace",
	"loadavg",
	"running",
	"queued"
);
var ajax_idCount = ajax_fieldIds.length;
//
var pageTitle = "torrentflux-b4rt";

/**
 * ajax_initialize
 *
 * @param timer
 * @param delim
 * @param pTitle
 */
function ajax_initialize(timer, delim, pTitle) {
	ajax_updateTimer = timer;
	ajax_txtDelim = delim;
	pageTitle = pTitle;
	if (ajax_useXML)
		ajax_updateParams = '?t=server&f=xml';
	else
		ajax_updateParams = '?t=server&f=txt&h=0';
	// state
	ajax_updateState = 1;
	// http-request
	ajax_httpRequest = ajax_getHttpRequest();
	// start update-thread
	ajax_update();
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
	ajax_updateContent(content.split(ajax_txtDelim));
	// set timeout
	setTimeout("ajax_update();", ajax_updateTimer);
}

/**
 * update page contents from response
 *
 * @param content
 */
function ajax_updateContent(content) {
	// page-title
	var newTitle = "";
	for (i = 0; i < ajax_idCount; i++) {
		newTitle += content[i];
		if (i < ajax_idCount - 1)
			newTitle += "|";
	}
	newTitle += " - " + pageTitle;
	document.title = newTitle;
	// fields
	for (i = 0; i < ajax_idCount; i++) {
		document.getElementById(ajax_fieldIds[i]).innerHTML = content[i];
	}
	// download-bar
	currentPercentage = content[ajax_idCount];
	if (currentPercentage == 0)
		document.barImageSpeedDown1.width = 1;
	else
		document.barImageSpeedDown1.width = currentPercentage * 2;
	if (currentPercentage == 100)
		document.barImageSpeedDown2.width = 1;
	else
		document.barImageSpeedDown2.width = (100 - currentPercentage) * 2;
	// upload-bar
	currentPercentage = content[ajax_idCount + 1];
	if (currentPercentage == 0)
		document.barImageSpeedUp1.width = 1;
	else
		document.barImageSpeedUp1.width = currentPercentage * 2;
	if (currentPercentage == 100)
		document.barImageSpeedUp2.width = 1;
	else
		document.barImageSpeedUp2.width = (100 - currentPercentage) * 2;
	// drivespace-bar
	currentPercentage = content[ajax_idCount + 2];
	if (currentPercentage == 0)
		document.barImageDriveSpace1.width = 1;
	else
		document.barImageDriveSpace1.width = (100 - currentPercentage) * 2;
	if (currentPercentage == 100)
		document.barImageDriveSpace2.width = 1;
	else
		document.barImageDriveSpace2.width = currentPercentage * 2;
}
