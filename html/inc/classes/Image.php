<?php

/* $Id$ */

/*******************************************************************************

 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html

*******************************************************************************/

// image-types
if (!defined("IMG_GIF")) define("IMG_GIF", 1);
if (!defined("IMG_JPG")) define("IMG_JPG", 2);
if (!defined("IMG_PNG")) define("IMG_PNG", 4);

// =============================================================================
// Image-class
// =============================================================================

/**
 * Image
 */
class Image
{
	// public fields

    // type
    var $type = 0;

    // dim
    var $width = 0;
    var $height = 0;

    // image
    var $image;

    // private fields

	// content-types
	var $_contentTypes = array(
		IMG_GIF => 'image/gif',
		IMG_JPG => 'image/png',
		IMG_PNG => 'image/jpeg');

    // imagetypes
    var $_imagetypes = 0;

	// =========================================================================
	// public static methods
	// =========================================================================

	/* factories */

    /**
     * getImage
     *
     * @param $t
     * @param $w
     * @param $h
     */
    function getImage($t = IMG_GIF, $w = 0, $h = 0) {
    	$img = new Image($t, $w, $h);
    	if (!$img)
    		return false;
    	$img->image = @imagecreate($w, $h);
		return (!$img->image)
			? false
			: $img;
    }

    /**
     * getImageFromRessource
     *
     * @param $t
     * @param $r
     */
    function getImageFromRessource($t = IMG_GIF, $r) {
    	$img = new Image($t);
    	if (!$img)
    		return false;
		switch ($t) {
			case IMG_GIF:
				$img->image = @imagecreatefromgif($r);
				return (!$img->image)
					? false
					: $img;
			case IMG_PNG:
				$img->image = @imagecreatefromjpeg($r);
				return (!$img->image)
					? false
					: $img;
				break;
			case IMG_JPG:
				$img->image = @imagecreatefrompng($r);
				return (!$img->image)
					? false
					: $img;
				break;
		}
    }

    /* generic static helper-meths */

	/**
	 * check image support of PHP + GD
	 *
	 * @return boolean
	 */
	function isSupported() {
		if (extension_loaded('gd')) {
			// gd is there but we also need support for at least one image-type
			$imageTypes = imagetypes();
			// gif
			if ($imageTypes & IMG_GIF)
				return true;
			// png
			if ($imageTypes & IMG_PNG)
			   return true;
			// jpg
			if ($imageTypes & IMG_JPG)
			   return true;
		}
		return false;
	}

	/**
	 * check image-type support of PHP + GD
	 *
	 * @return boolean
	 */
	function isTypeSupported($type) {
		return ((extension_loaded('gd')) && (imagetypes() & $type));
	}

	/**
	 * check referer
	 */
	function checkReferer() {
		if (!((isset($_SERVER["HTTP_REFERER"])) &&
			(stristr($_SERVER["HTTP_REFERER"], $_SERVER["SERVER_NAME"]) !== false)))
			Image::paintInvalidReferer();
	}

	/**
	 * convert a string (#RRGGBB or RRGGBB) to a array with R-G-B as decimals
	 *
	 * @param string $color
	 * @return array
	 */
	function stringToRGBColor($color) {
		$retVal = array();
		$color = str_replace('#', '', $color);
		$retVal['r'] = hexdec(substr($color, 0, 2));
		$retVal['g'] = hexdec(substr($color, 2, 2));
		$retVal['b'] = hexdec(substr($color, 4, 2));
		return $retVal;
	}

	/* static paint-image-methods */

	/**
	 * paint label-image created with a existing image-file
	 *
	 * @param $bgimage
	 * @param $label
	 * @param $font
	 * @param $x
	 * @param $y
	 * @param $r
	 * @param $g
	 * @param $b
	 */
	function paintLabelFromImage($bgimage, $label,
			$font = 1,
			$x = 0, $y = 0,
			$r = 0, $g = 0, $b = 0) {
		// img
		$imageObject = false;
		// gif
		$imageObject = Image::getImageFromRessource(IMG_GIF, $bgimage.".gif");
		// try png if failed
		if (!$imageObject)
			$imageObject = Image::getImageFromRessource(IMG_PNG, $bgimage.".png");
		// try jpg if failed
		if (!$imageObject)
			$imageObject = Image::getImageFromRessource(IMG_JPG, $bgimage.".jpg");
		// bail if no object
		if (!$imageObject)
			Image::paintNotSupported();
		// paint offscreen-image
		$textcolor = imagecolorallocate($imageObject->image, $r, $g, $b);
		imagestring($imageObject->image, $font, $x, $y, $label, $textcolor);
		// output
		$imageObject->paint();
	}

	/**
	 * paint a 3d-pie
	 *
	 * @param int $w
	 * @param int $h
	 * @param int $cx
	 * @param int $cy
	 * @param int $sx
	 * @param int $sy
	 * @param int $sz
	 * @param array $bg []
	 * @param array $values []
	 * @param array $colors [][]
	 * @param array $legend []
	 * @param int $legendX
	 * @param int $legendY
	 * @param int $legendFont
	 * @param int $legendSpace
	 */
	function paintPie3D(
		$w,
		$h,
		$cx,
		$cy,
		$sx,
		$sy,
		$sz,
		$bg,
		$values,
		$colors,
		$legend = false,
		$legendX = 0,
		$legendY = 0,
		$legendFont = 2,
		$legendSpace = 14) {
		// img
		$imageObject = false;
		// gif
		$imageObject = Image::getImage(IMG_GIF, $w, $h);
		// try png if failed
		if (!$imageObject)
			$imageObject = Image::getImage(IMG_PNG, $w, $h);
		// try jpg if failed
		if (!$imageObject)
			$imageObject = Image::getImage(IMG_JPG, $w, $h);
		// bail if no object
		if (!$imageObject)
			Image::paintNotSupported();
		// background
		$background = imagecolorallocate($imageObject->image, $bg['r'], $bg['g'], $bg['b']);
		// convert to angles.
		$valueCount = count($values);
		$valueSum = array_sum($values);
		for ($i = 0; $i < $valueCount; $i++) {
			$angle[$i] = (($values[$i] / $valueSum) * 360);
			$angle_sum[$i] = array_sum($angle);
		}
		// colors.
		for ($i = 0; $i < $valueCount; $i++) {
			$col_s[$i] = imagecolorallocate($imageObject->image, $colors[$i]['r'], $colors[$i]['g'], $colors[$i]['b']);
			$col_d[$i] = imagecolorallocate($imageObject->image, ($colors[$i]['r'] >> 1), ($colors[$i]['g'] >> 1), ($colors[$i]['b'] >> 1));
		}
		// 3D effect.
		for ($z = 1; $z <= $sz; $z++) {
			for ($i = 0; $i < $valueCount; $i++) {
				imagefilledarc($imageObject->image,
					$cx,
					($cy + $sz) - $z,
					$sx,
					$sy,
					$angle_sum[($i != 0) ? $i - 1 : $valueCount - 1],
					$angle_sum[$i],
					$col_d[$i],
					IMG_ARC_PIE);
			}
		}
		// top pie.
		for ($i = 0; $i < $valueCount; $i++) {
			imagefilledarc($imageObject->image,
				$cx,
				$cy,
				$sx,
				$sy,
				$angle_sum[($i != 0) ? $i - 1 : $valueCount - 1],
				$angle_sum[$i],
				$col_s[$i],
				IMG_ARC_PIE);
		}
		// legend
		if ($legend) {
			$curY = $legendY;
			for ($i = 0; $i < $valueCount; $i++) {
				$textcolor = imagecolorallocate($imageObject->image, $colors[$i]['r'], $colors[$i]['g'], $colors[$i]['b']);
				imagestring($imageObject->image, $legendFont, $legendX, $curY, $legend[$i], $textcolor);
				$curY += $legendSpace;
			}
		}
		// output
		$imageObject->paint();
	}

	/* static paint-methods "drawing" inline-media */

	/**
	 * output image not supported image
	 */
	function paintNotSupported() {
		$d = "";
		$d .= 'R0lGODlhXAAkAIcAAAAAAP8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAACH5BAMAAAIALAAAAABcACQAAAj/AAUIHEiwoMGDCBMqXMiw'
		    . 'ocAAAQ5CdEgQosWJFDNqxDjQokGODi+K1EiyIUgBFwueZHhyZcmXHSNW9DgzJk2J'
		    . 'MiuizPkwokiOP3nuvBl05M6jMXsa/Xgzac2iSpcGjQoVKVCfQl0OJZoVq0qvT7si'
		    . 'dUoW48SWQmemHNuT7VG0bs+Kran07dyFHuHqFbv3q9+kcv+yBAuYr2G/cOnaJNyW'
		    . 'KWKeeSEHthmXcWWmMqfi/Lk4ZdWqW2milYy5M961neWiDp1WamvRad3CRDjZ8cbY'
		    . 'WmfD1Jpbt2/fvGP/Hj47OPHjyJObXK28eUnOL6E7By78NvPp0auT7I2dInTX1xuz'
		    . 'g8baVPp406kle/6+VHBR9vCNagYtvu9psmzNPt4fVqX7w/eJl19OU9lXWFOKGZjQ'
		    . 'VYpZVaBh87VXH4AKMYhfbW3tpRlV//FXIYGkFabWgJYteNd5stk2YFgRxreeeZVx'
		    .'9eF6DV4F3mqcbYifiTNGVqNwxnUnZI8IDmlkakcmqeSSSQYEADs=';
	    Image::paintData('image/gif', base64_decode($d), 1190);
	}

	/**
	 * output invalid referer image
	 */
	function paintInvalidReferer() {
		$d = "";
		$d .= 'R0lGODlhEAAQAOZpAJJzRlFFOU4vKOjPNOzTNOvTMkowJ0k5MmU3J1k6OEo8NO3S'
		    . 'KvDUM4VFKuLEMjciIe7WKz4iHVUxJNPAbUgwNKBTLY1HLPTbK/PaLezRMu3TMIVw'
		    . 'X8K5q+bOM75nL6yJO0QtM+7RN8+ILm86KeXLLYduZaCpyuXZWOrMMt+1MsFqMWEy'
		    . 'Ir/Ej4iEakM6RmxHNufiiO3PMdyqMdeZMOvSMqiusezPT3dEL7a2t+7gUKmFUHpK'
		    . 'OT8pJmQ3KFoxJz8rKPXcLpJiUu7QMXhIObJkLujQMpWBU+rSM+rLMbTApfHjQtCH'
		    . 'MWxWN+3VM5uPhkIrJuXGM+bIbEEqJ5SivsemMOrPL+W/LsO5nGlVQst5Mu3QMZ50'
		    . 'MdWZLF44M+3TNdK8POK7M8CTMUssJ3tWN8/Qin5XR725ofDVNFk4KwAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAACH5BAEAAGkALAAAAAAQABAAAAeSgGmCAAoHWIKIiYMAg0yK'
		    . 'ik6KRo+IjIkBV5QtG4oTXzCUAYJRNiFAF0pmjwEvKlxWQmdeEGQcimgPFllgWgwE'
		    . 'VTk1j08+HjJIMRoFCyw4ihI8FTMoGTRFJCdTijc/I0tQRwMdAxhJJolBAmJEKU0E'
		    . 'DlQfOo89Ug0iYVtjCSARZYo7BCAY0oWCC0qCSqwwgLAhwkAAOw==';
	    Image::paintData('image/gif', base64_decode($d), 565);
	}

	/**
	 * output no-op image
	 */
	function paintNoOp() {
		$d = "";
		$d .= 'R0lGODlhDQAMAOZeAP///44LDoYLDWMJC18XGKNmZ6cNEXQJDFsHCsUQFbwPFLkP'
		    . 'FKQNEaENEZMMEIoLDokLD4cLD34KDXIJDHAJDG4JDGYIC7YPFJ0NEZoNEYILDncK'
		    . 'DXMKDWcJDMgXHpAaHmwXGlokJsRpbK5maKxqbdmoqrkaIr4hLOHQ0cYkMc0sO7kp'
		    . 'NswxQkoTGXYhKtuvtOvh4lAWHdE6TmgdJ4YtOeSxt784S8Y+UkQYH5o9TLxAVdNK'
		    . 'YkgaIk0cJdy2vdZPaVQfKtZQal0jMMZPadhaecpYdtpggZhFXOW8x9tlh9ZjhHs6'
		    . 'Tttpjd5vlYVJYYtTbrV2nplliLqAqqJzmZltkqh9pquEqpyrxp2tx196o6e3zr3J'
		    . '28bQ393Q0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAACH5BAEAAF4ALAAAAAANAAwAAAeHgF6CXlZVU1RRg4NSTUxJ'
		    . 'REE7MkeDUEpFSEM7NjUrKTSCRpgAPjcvACUmCV5RPzo5AAAwsR8GF0JPMiwrLrMw'
		    . 'IBgGDEJOKiknJLEABRgNGUBLHgoisSGxIxEQPV4LDA8oIAcEXQMTG4IuDQEUBxoW'
		    . 'CBYVMYMzDhACEhwVHS2Kgj09eODAoSgQADs=';
		Image::paintData('image/gif', base64_decode($d), 554);
	}

	/**
	 * output spacer image
	 */
	function paintSpacer() {
		$d = "";
		$d .= 'R0lGODlhAQABAIcAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		    . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
			. 'AAAAAAAAAAAAAAAAACH5BAMAAAEALAAAAAABAAEAAAgEAAMEBAA7';
		Image::paintData('image/gif', base64_decode($d), 807);
	}

	/**
	 * paint image-data
	 *
	 * @param $type
	 * @param $data
	 * @param $len
	 */
	function paintData($type, $data, $len) {
		@header('Accept-Ranges: bytes');
	    @header('Content-Length: '.$len);
	    @header('Content-Type: '.$type);
	    echo $data;
	    exit();
	}

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * do not use direct, use the factory-methods !
     *
     * @return Image
     */
    function Image($t = IMG_GIF, $w = 0, $h = 0) {

    	// GD required
		if (extension_loaded('gd')) {

	    	// types Supported
			$this->_imagetypes = imagetypes();

	    	// type
	    	if ($this->_imagetypes & $t)
	    		$this->type = $t;
	    	else
	    		return false;

	    	// dim
	    	$this->width = $w;
	    	$this->height = $h;

		} else {
			// return false
			return false;
		}

    }

	// =========================================================================
	// public methods
	// =========================================================================

    /**
     * paint
     */
    function paint() {
    	@header("Content-type: ".$this->_contentTypes[$this->type]);
		switch ($this->type) {
			case IMG_GIF:
				imagegif($this->image);
				break;
			case IMG_PNG:
				imagepng($this->image);
				break;
			case IMG_JPG:
				imagejpeg($this->image);
				break;
		}
		imagedestroy($this->image);
		exit();
    }

}

?>