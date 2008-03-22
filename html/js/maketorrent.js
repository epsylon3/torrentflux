/* $Id$ */

// fields
var trackerState = 1;
var anlst  = "(optional) announce_list = list of tracker URLs<BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;<i>url[,url...][|url[,url...]...]</i><BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;URLs separated by commas are tried first<BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;before URLs separated by the pipe is checked.<BR />\n";
	anlst += "Examples:<BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;<i>http://a.com<strong>|</strong>http://b.com<strong>|</strong>http://c.com</i><BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(tries <b>a-c</b> in order)<BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;<i>http://a.com<strong>,</strong>http://b.com<strong>,</strong>http://c.com</i><BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(tries <b>a-c</b> in a randomly selected order)<BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;<i>http://a.com<strong>|</strong>http://b.com<strong>,</strong>http://c.com</i><BR />\n";
	anlst += "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(tries <b>a</b> first, then tries <b>b-c</b> randomly)<BR />\n";

var annce  = "tracker announce URL.<BR /><BR />\n";
	annce += "Example:<BR />\n";
	annce += "&nbsp;&nbsp;&nbsp;&nbsp;<i>http://tracker.com/announce</i><BR />\n";

var tornt  = "torrent name to be saved as<BR /><BR />\n";
	tornt += "Example:<BR />\n";
	tornt += "&nbsp;&nbsp;&nbsp;&nbsp;<i>gnome-livecd-2.10.torrent</i><BR />\n";

var comnt  = "add a comment to your torrent file (optional)<BR />\n";
	comnt += "";

var piece  = "data piece size for torrent<BR />\n";
	piece += "power of 2 value to set the piece size to<BR />\n";
	piece += "(0 = automatic) (0 only option in this version)<BR />\n";

var prvte  = "private tracker support<BR />\n";
	prvte += "(disallows DHT if enabled)<BR />\n";

var dhtbl  = "DHT (Distributed Hash Table)<BR /><BR />\n";
	dhtbl += "can only be set abled if private flag is not set true<BR />\n";

/**
 * doSubmit
 */
function doSubmit(obj, client) {
	// Basic check to see if maketorrent is already running
	if (obj.value === "Creating...")
		return false;
	// Run some basic validation
	var valid = true;
	var tlength = document.maketorrent.torrent.value.length - 8;
	var torrent = document.maketorrent.torrent.value.substr(tlength);
	document.getElementById('output').innerHTML = "";
	document.getElementById('ttag').innerHTML	= "";
	// torrent
	if (torrent !== ".torrent") {
		document.getElementById('ttag').innerHTML = "<b style=\"color: #990000;\">*</b>";
		document.getElementById('output').innerHTML += "<b style=\"color: #990000;\">* Torrent file must end in .torrent</b><BR />";
		valid = false;
	}
	// client-specific checks
	if (client === "tornado") {
		// tornado-special-checks
		document.getElementById('atag').innerHTML = "";
		// announce-url
		if (document.maketorrent.announce.value === "http://") {
			document.getElementById('atag').innerHTML = "<b style=\"color: #990000;\">*</b>";
			document.getElementById('output').innerHTML += "<b style=\"color: #990000;\">* Please enter a valid announce URL.</b><BR />";
			valid = false;
		}
		// For safety reason, let's force the property to false if it's disabled (private tracker)
		if (document.maketorrent.DHT.disabled) {
			document.maketorrent.DHT.checked = false;
		}
	} else {
		// mainline-special-checks
		document.getElementById('trtag').innerHTML = "";
		/*
		due to bugs (?) in mainlines maketorrent, this is disabled.
		if (trackerState == 1) {
			if ((document.maketorrent.tracker_name.value === "http://") || (document.maketorrent.tracker_name.value === "")) {
				document.getElementById('trtag').innerHTML = "<b style=\"color: #990000;\">*</b>";
				document.getElementById('output').innerHTML += "<b style=\"color: #990000;\">* Please enter a valid Tracker Name.</b><BR />";
				valid = false;
			}
		} else {
			if (document.maketorrent.tracker_name.value === "http://") {
				document.getElementById('trtag').innerHTML = "<b style=\"color: #990000;\">*</b>";
				document.getElementById('output').innerHTML += "<b style=\"color: #990000;\">* Please enter a valid Node (&lt;ip&gt;:&lt;port&gt;) or an empty to pull some nodes from your routing table.</b><BR />";
				valid = false;
			}
		}
		*/
		if (trackerState == 1) {
			if ((document.maketorrent.tracker_name.value === "http://") || (document.maketorrent.tracker_name.value === "")) {
				document.getElementById('trtag').innerHTML = "<b style=\"color: #990000;\">*</b>";
				document.getElementById('output').innerHTML += "<b style=\"color: #990000;\">* Please enter a valid Tracker Name.</b><BR />";
				valid = false;
			}
		}
	}
	// If validation passed, submit form
	if (valid === true) {
		disableForm(client);
		toggleLayer('progress');
		document.getElementById('output').innerHTML += "<b>Creating torrent...</b><BR /><BR />";
		document.getElementById('output').innerHTML += "<i>* Note that larger folder/files will take some time to process,</i><BR />";
		document.getElementById('output').innerHTML += "<i>&nbsp;&nbsp;&nbsp;do not close the window until it has been completed.</i><BR /><BR />";
		document.getElementById('output').innerHTML += "&nbsp;&nbsp;&nbsp;When completed, the torrent will show in your list<BR />";
		document.getElementById('output').innerHTML += "&nbsp;&nbsp;&nbsp;and a download link will be provided.<BR />";
		return true;
	}
	return false;
}

/**
 * disableForm
 */
function disableForm(client) {
	// Because of IE issue of disabling the submit button,
	// we change the text and don't allow resubmitting
	document.maketorrent.tsubmit.value = "Creating...";
	document.maketorrent.torrent.readOnly = true;
	if (client === "tornado")
		document.maketorrent.announce.readOnly = true;
}

/**
 * toggleDHT
 */
function toggleDHT(dhtstatus) {
	document.maketorrent.DHT.disabled = dhtstatus;
}

/**
 * toggleTracker
 */
function toggleTracker(tState) {
	/*
	trackerState = tState;
	if (trackerState == 1) {
		document.maketorrent.tracker_name.value = "http://";
	} else {
		document.maketorrent.tracker_name.value = "";
	}
	*/
	trackerState = 1;
}

/**
 * toggleLayer
 */
function toggleLayer(whichLayer) {
	if (document.getElementById) {
		// This is the way the standards work
		var style2 = document.getElementById(whichLayer).style;
		style2.display = style2.display ? "" : "block";
	} else if (document.all) {
		// This is the way old msie versions work
		var style2 = document.all[whichLayer].style;
		style2.display = style2.display ? "" : "block";
	} else if (document.layers) {
		// This is the way nn4 works
		var style2 = document.layers[whichLayer].style;
		style2.display = style2.display ? "" : "block";
	}
}

/**
 * completed
 */
function completed(downpath, alertme, timetaken) {
	document.getElementById('output').innerHTML	 = "<b style='color: #005500;'>Creation completed!</b><BR />";
	document.getElementById('output').innerHTML += "Time taken: <i>" + timetaken + "</i><BR />";
	document.getElementById('output').innerHTML += "The new torrent has been added to your list.<BR /><BR />"
	document.getElementById('output').innerHTML += "You can download the <a style='font-weight: bold;' href='dispatcher.php?action=metafileDownload&transfer=" + downpath + "'>torrent here</a><BR />";
	if(alertme === 1)
		alert('Creation of torrent completed!');
}

/**
 * failed
 */
function failed(downpath, alertme) {
	document.getElementById('output').innerHTML	 = "<b style='color: #AA0000;'>Creation failed!</b><BR /><BR />";
	document.getElementById('output').innerHTML += "An error occurred while trying to create the torrent.<BR />";
	if(alertme === 1)
		alert('Creation of torrent failed!');
}
