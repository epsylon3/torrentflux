/* $Id$ */

/**
 * loginSubmit
 */
function loginSubmit(useImageCode) {
	msg = "";
	// user
	user = document.theForm.username.value;
	if (user.length < 1) {
		msg = msg + "* Username is required\n";
		document.theForm.username.focus();
	}
	// pass
	pass = document.theForm.iamhim.value;
	if (pass.length < 1) {
		msg = msg + "* Password is required\n";
		if (user.length > 0)
			document.theForm.iamhim.focus();
	}
	// image-code
	if (useImageCode == 1) {
		imageCode = document.theForm.security.value;
		if (imageCode.length != 6) {
			msg = msg + "* Security-Code is required\n";
			if ((user.length > 0) && (pass.length > 0))
				document.theForm.security.focus();
		}
	}
	if (msg != "") {
		alert("Check the following:\n\n" + msg);
		return false;
	}
	var loginDivForm = document.getElementById("login_form");
	if (loginDivForm != null)
		loginDivForm.style.display = "none";
		
	var loginDivAction = document.getElementById("login_in_progress");
	if (loginDivAction != null) 
	{
		if(loginDivAction.className == 'hidden')
			loginDivAction.className = '';
		else
			loginDivAction.style.display = 'block';
	}
}
