/* $Id$ */
var actionInProgress = false;
var varRefresh;
function initRefresh(refresh) {
	varRefresh = refresh;
	setTimeout("updateRefresh();", 1000);
}
function updateRefresh() {
	varRefresh--;
	if (varRefresh >= 0) {
	    document.getElementById("span_refresh").innerHTML = String(varRefresh);
	    setTimeout("updateRefresh();", 1000);
	}
}
function bulkCheck(thisIn) {
	ajax_updateState = 0;
	var form = thisIn.form, i = 0;
	for(i = 0; i < form.length; i++) {
		if (form[i].type == 'checkbox' && form[i].name != 'bulkBox' && form[i].disabled == false) {
			form[i].checked = thisIn.checked;
		}
	}
}
function showTransfer(name_file) {
	if (actionInProgress)
		return;
	window.open(name_file,'_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=600,height=400">')
}
function openServerMonitor() {
	if (actionInProgress)
		return;
	window.open('index.php?iid=servermon','_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=470,height=220')
}
function actionClick(showlabel,labeltext) {
	if (actionInProgress) {
		actionRequestError();
		return false;
	}
	actionRequest(showlabel,labeltext);
	return true;
}
function actionConfirm(question,labeltext) {
	if (actionInProgress) {
		actionRequestError();
		return false;
	} else {
		var confirmResult = confirm(question);
		if (confirmResult)
			actionRequest(true,labeltext);
		return confirmResult;
	}
}
function actionSubmit(labeltext) {
	if (actionInProgress) {
		actionRequestError();
		return false;
	}
	actionRequest(true,labeltext);
	return true;
}
function actionRequest(showlabel,labeltext) {
	actionInProgress = true;
	ajax_updateState = 0;
	if(window.ajax_unload) ajax_unload();
	if (showlabel) {
		var label = document.getElementById("action_in_progress");
		var gray_layer = document.getElementById("grey_out");
		if (label != null) {	
			if(labeltext != null)
			{
				var label_span = document.getElementById("progress_label");
				if(label_span != null) 
					label_span.innerHTML = labeltext;
			}
			if(gray_layer != null)
			{
				if(gray_layer.className == 'hidden')
					gray_layer.className = 'active';
			}
			if(label.className == 'hidden')
			{
				//only if using style.visibility (not style.display!!) we can center the div exactly
				label.className = 'active';
				center_div("action_in_progress",document.getElementById("action_in_progress").offsetWidth,document.getElementById("action_in_progress").offsetHeight);
			}
			else 
				label.style.display = 'block';
		}
	}
}
function actionRequestError() {
	alert("Another Request in progress...");
}

function flux_clientWidth() {
	return flux_filterResults (
		window.innerWidth ? window.innerWidth : 0,
		document.documentElement ? document.documentElement.clientWidth : 0,
		document.body ? document.body.clientWidth : 0
	);
}

function flux_clientHeight() {
	return flux_filterResults (
		window.innerHeight ? window.innerHeight : 0,
		document.documentElement ? document.documentElement.clientHeight : 0,
		document.body ? document.body.clientHeight : 0
	);
}
function flux_scrollLeft() {
	return flux_filterResults (
		window.pageXOffset ? window.pageXOffset : 0,
		document.documentElement ? document.documentElement.scrollLeft : 0,
		document.body ? document.body.scrollLeft : 0
	);
}
function flux_scrollTop() {
	return flux_filterResults (
		window.pageYOffset ? window.pageYOffset : 0,
		document.documentElement ? document.documentElement.scrollTop : 0,
		document.body ? document.body.scrollTop : 0
	);
}
function flux_filterResults(n_win, n_docel, n_body) {
	var n_result = n_win ? n_win : 0;
	if (n_docel && (!n_result || (n_result > n_docel)))
		n_result = n_docel;
	return n_body && (!n_result || (n_result > n_body)) ? n_body : n_result;
}

function center_div(name,w,h){
	var div = document.getElementById(name);
	if(div == null)
		return;
	// If using IE hack, nothing to do here (centering is done in CSS).
	if (div.currentStyle && div.currentStyle.position == 'absolute')
		return;
	div.style.position = "fixed";
	div.style.top = (( flux_clientHeight()/2 ) - ( h/2 )) + 'px';
	div.style.left = (( flux_clientWidth()/2 ) - ( w/2 )) + 'px';
}

String.prototype.Trim = function () 
{
    return (this.replace(/\s+$/,"").replace(/^\s+/,""));
};