/* $Id$ */

/**
 * validateSettings
 */
function validateSettings(type) {
	var msg = "";
	switch (type) {

		case 'torrent':
			if (isNumber(document.theForm.max_upload_rate.value) == false) {
				msg = msg + "* Max Upload Rate must be a valid number.\n";
				document.theForm.max_upload_rate.focus();
			}
			if (isNumber(document.theForm.max_download_rate.value) == false) {
				msg = msg + "* Max Download Rate must be a valid number.\n";
				document.theForm.max_download_rate.focus();
			}
			if (isNumber(document.theForm.max_uploads.value) == false) {
				msg = msg + "* Max # Uploads must be a valid number.\n";
				document.theForm.max_uploads.focus();
			}
			if (isNumber(document.theForm.maxcons.value) == false) {
				msg = msg + "* Max Cons must be a valid number.\n" ;
			}
			if (isNumber(document.theForm.sharekill.value) == false) {
				msg = msg + "* Keep seeding until Sharing % must be a valid number.\n";
				document.theForm.sharekill.focus();
			}
			if (isNumber(document.theForm.rerequest.value) == false) {
				msg = msg + "* Rerequest Interval must have a valid number.\n";
				document.theForm.rerequest.focus();
			}
			if (document.theForm.rerequest.value < 10) {
				msg = msg + "* Rerequest Interval must be 10 or greater.\n";
				document.theForm.rerequest.focus();
			}
			if ((isNumber(document.theForm.minport.value) == false) || (isNumber(document.theForm.maxport.value) == false)) {
				msg = msg + "* Port Range must have valid numbers.\n";
				document.theForm.minport.focus();
			}
			if ((document.theForm.maxport.value > 65535) || (document.theForm.minport.value > 65535)) {
				msg = msg + "* Port can not be higher than 65535.\n";
				document.theForm.minport.focus();
			}
			if ((document.theForm.maxport.value < 0) || (document.theForm.minport.value < 0)) {
				msg = msg + "* Can not have a negative number for port value.\n";
				document.theForm.minport.focus();
			}
			if (document.theForm.maxport.value < document.theForm.minport.value) {
				msg = msg + "* Port Range is not valid.\n";
				document.theForm.minport.focus();
			}
			break;

		case 'wget':
			if (isNumber(document.theForm.max_download_rate.value) == false) {
				msg = msg + "* Max Download Rate must be a valid number.\n";
				document.theForm.max_download_rate.focus();
			}
			break;

		case 'nzb':
			if (isNumber(document.theForm.max_download_rate.value) == false) {
				msg = msg + "* Max Download Rate must be a valid number.\n";
				document.theForm.max_download_rate.focus();
			}
			if (isNumber(document.theForm.maxcons.value) == false) {
				msg = msg + "* Max Cons must be a valid number.\n" ;
			}
			break;

	}
	if (msg != "") {
		alert("Please check the following:\n\n" + msg);
		return false;
	} else {
		return true;
	}
}
