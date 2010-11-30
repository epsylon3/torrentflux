/* $Id$ */

// fields
var ajax_fieldIds = new Array(
	"running",		//0
	"speedDown",	//1
	"speedUp",		//2
	"downCurrent",	//3
	"upCurrent",	//4
	"downTotal",	//5
	"upTotal",		//6
	"percentDone",	//7
	"sharing",		//8
	"eta",			//9
	"seeds",		//10
	"peers",		//11
	"cons"			//12
);
var ajax_idCount = ajax_fieldIds.length;
var ajax_transferName = "";

/**
 * ajax_initialize
 *
 * @param timer
 * @param delim
 * @param name
 */
function ajax_initialize(timer, delim, name) {
	ajax_updateTimer = timer;
	ajax_txtDelim = delim;
	ajax_transferName = name;
	if (ajax_useXML)
		ajax_updateParams = '?t=transfer&f=xml&i=' + name;
	else
		ajax_updateParams = '?t=transfer&f=txt&h=0&i=' + name;
	// state
	ajax_updateState = 1;
	// http-request
	ajax_httpRequest = ajax_getHttpRequest();
	// start update-thread
	setTimeout("ajax_update();", ajax_updateTimer);
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
function ajax_updateContent(content) 
{
	// progress-bar
	var currentPercentage = parseFloat(content[7]);
	var barImage1 = document.getElementById('barImage1');
	if (barImage1 !== null) {
		if (typeof(jQuery) != 'undefined') {
			jQuery('#barImage1').progressBar(currentPercentage);
		}
		else {
			barImage1.style.width = Math.round(currentPercentage) + '%';
			if (document.getElementById('barImage2') !== null)
				document.getElementById('barImage2').style.width = Math.round(100.0 - currentPercentage) + '%';
		}
	}
	//
	// fields
	for (i = 0; i < ajax_idCount; i++) {
		if (document.getElementById(ajax_fieldIds[i]) === null)
			continue;
		if ((ajax_fieldIds[i] == 'eta') && (content[i] == '-'))
			document.getElementById(ajax_fieldIds[i]).innerHTML = '&#8734';
		else
			document.getElementById(ajax_fieldIds[i]).innerHTML = content[i];
	}
}
