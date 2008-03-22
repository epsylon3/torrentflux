/* $Id$ */

/**
 * addRssadFilterEntry()
 */
function addRssadFilterEntry() {
    var filter = lrtrim(document.theForm.rssad_filter_entry.value);
    if (filter != "") {
	    for (var i = 0; i < document.theForm.rssad_filters.options.length; i++) {
	    	if ((lrtrim(document.theForm.rssad_filters.options[i].value)) == filter) {
	    		alert("Filter already exists");
	    		return false;
	    	}
	    }
	    var liststr = document.theForm.rssad_filtercontent;
	    var list = document.theForm.rssad_filters;
	    var newentry = document.createElement("option");
	    newentry.text = filter;
	    newentry.value = newentry.text;
		document.theForm.rssad_filter_entry.value = "";
	    if (navigator.appName == "Netscape")
	    	list.add(newentry, null);
	    else
	    	list.add(newentry);
	    if (liststr.value == "")
	    	liststr.value = newentry.value;
	    else
	    	liststr.value = liststr.value + "\n" + newentry.value;
    } else {
		alert("Please enter a Filter.");
	}
}

/**
 * removeRssadFilterEntry()
 */
function removeRssadFilterEntry() {
	if (document.theForm.rssad_filters.selectedIndex != -1) {
		var liststr = document.theForm.rssad_filtercontent;
		document.theForm.rssad_filters.remove(document.theForm.rssad_filters.selectedIndex);
		var newValue = "";
		for (var j = 0; j < document.theForm.rssad_filters.options.length; j++) {
            if (j > 0)
                newValue += "\n";
		    newValue += lrtrim(document.theForm.rssad_filters.options[j].value);
		}
		liststr.value = lrtrim(newValue);
	} else {
		alert("Please select a Filter first!");
	}
}
