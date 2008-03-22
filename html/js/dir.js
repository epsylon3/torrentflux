/* $Id$ */

// fields
var popUpWin=0;

/**
 * MakeTorrent
 */
function MakeTorrent(name_file) {
	window.open (name_file,'_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=600,height=430')
}

/**
 * checkCheck
 */
function checkCheck(thisIn) {
	var form = thisIn.form, i = 0;
	for (i=0; i < form.length; i++) {
		if (form[i].type == 'checkbox' && form[i].name != 'checkall' && form[i].disabled == false) {
			form[i].checked = thisIn.checked;
		}
	}
}

/**
 * UncompDetails
 */
function UncompDetails(URL) {
	window.open (URL,'_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=600,height=300');
}

/**
 * CompressDetails
 */
function CompressDetails(URL) {
	window.open (URL,'_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=600,height=300');
}

/**
 * rename
 */
function rename(name_file) {
	if (popUpWin) {
		if (!popUpWin.closed) popUpWin.close();
	}
	popUpWin = open(name_file,'_blank','toolbar=no,location=0,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=640,height=430')
}

/**
 * moveFile
 */
function moveFile(name_file) {
	if (popUpWin) {
		if (!popUpWin.closed) popUpWin.close();
	}
	popUpWin = open(name_file,'_blank','toolbar=no,location=0,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=640,height=430');
}

/**
 * CheckSFV
 */
function CheckSFV(dir,file) {
	var width = screen.width/2-300;
	var height = screen.height/2-110;
	var InfoWin = window.open("index.php?iid=checkSFV&dir="+dir+"&file="+file, "CheckSFV", "status=no,toolbar=no,scrollbars=yes,resizable=yes,menubar=no,width=560,height=240,left="+width+",top="+height);
}

/**
 * StreamMultimedia
 */
function StreamMultimedia(name_file) {
	if (popUpWin) {
		if (!popUpWin.closed) popUpWin.close();
	}
	popUpWin = open(name_file,'_blank','toolbar=no,location=0,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=500,height=400')
}
