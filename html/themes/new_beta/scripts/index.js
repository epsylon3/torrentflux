function more_info(type) {
	if (document.getElementById(type) != null) {
		slider(type, 'toggle');
		if (document.getElementById(type).style.display == "none") {
			document.getElementById("more_Info_" + type).src = "themes/new/pics/moreInfo_closed.jpg";
		}
		else {
			document.getElementById("more_Info_" + type).src = "themes/new/pics/moreInfo_open.jpg";
		}
	}
}