/* $Id$ */

/**
 * lrtrim
 */
function lrtrim(value) {
	var l, r;
	for (l = 0;                l < value.length && value.charCodeAt(l) == 32; l++);
	for (r = value.length - 1; r > l            && value.charCodeAt(r) == 32; r--);
	return value.substring(l, r + 1);
}

/**
 * isNumber
 */
function isNumber(value) {
	return isValidString("-0123456789", value);
}

/**
 * isUnsignedNumber
 */
function isUnsignedNumber(value) {
	return isValidString("0123456789", value);
}

/**
 * isValidString
 */
function isValidString(validChars, value) {
	var length = value.length;
	if (length < 1)
		return false;
	for (i = 0; i < length; i++) {
		if (validChars.indexOf(value.charAt(i)) == -1)
			return false;
	}
	return true;
}
