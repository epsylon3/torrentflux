/* $Id$ */

/**
 * addDirEntry
 */
function addDirEntry () {
    var val = lrtrim(document.theForm.resdirentry.value);
 	if (val != "") {
  		if (val.indexOf('/') != -1) {
 			alert("No slashes allowed");
	    	return false;
 		}
 		for (var i = 0; i < document.theForm.resdirlist.options.length; i++) {
	    	if ((lrtrim(document.theForm.resdirlist.options[i].text)) == val) {
	    		alert("Entry already exists.");
	    		return false;
	    	}
	    }
	    var resliststr = document.theForm.dir_restricted;
	    var reslist = document.theForm.resdirlist;
 	    var newentry = document.createElement("option");
	    newentry.text = val;
        document.theForm.resdirentry.value = "";
        newentry.value = reslist.length;
        if (navigator.appName == "Netscape") {
        	reslist.add(newentry, null);
        } else {
        	reslist.add(newentry);
        }
        if (resliststr.value == "") {
        	resliststr.value = newentry.text;
        } else {
        	resliststr.value = resliststr.value + ":" + newentry.text;
        }
  	} else {
		alert("Please enter a Entry first!");
	}
}

/**
 * removeDirEntry
 */
function removeDirEntry() {
	var resliststr = document.theForm.dir_restricted;
	if (document.theForm.resdirlist.selectedIndex != -1) {
		document.theForm.resdirlist.remove(document.theForm.resdirlist.selectedIndex);
		var newValue = "";
		for (var j = 0; j < document.theForm.resdirlist.options.length; j++) {
            if (j > 0) {
                newValue += ":";
            }
		    newValue += lrtrim(document.theForm.resdirlist.options[j].text);
		}
		resliststr.value = lrtrim(newValue);
	} else {
		alert("Please select an entry first!");
	}
}
