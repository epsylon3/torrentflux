function slider(type, action) {
	if (document.getElementById(type) != null) {
		if (action == "show") {
			document.getElementById(type).style.display = "";
			document.getElementById(type).style.height = "";
		}
		if (action == "hide") {
			document.getElementById(type).style.display = "none";
			document.getElementById(type).style.height = "0px";
		}
		if (action == "toggle") {
			if (document.getElementById(type).style.display == "none") {
				document.getElementById(type).style.display = "";
				document.getElementById(type).style.height = "";
			}
			else {
				document.getElementById(type).style.display = "none";
				document.getElementById(type).style.height = "0px";
			}
		}
	}
}