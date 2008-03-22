/* $Id$ */

// fields
var formContent = '<br /><input type="file" name="upload_files[]" size="40" /><span id="fileUploadSpan"></span>';

/**
 * addUploadField
 */
function addUploadField() {
	element = document.getElementById("fileUploadSpan");
	if (typeof(element.outerHTML) != 'undefined') {
		element.outerHTML = formContent;
	} else {
		var range = document.createRange();
		range.setStartBefore(element);
		element.parentNode.replaceChild(range.createContextualFragment(formContent), element);
	}
}