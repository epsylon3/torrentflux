<?php
/*
This is a simple Javascript files combiner to reduce number of http requests
no need for crc or md5 checks, will be slower to check if files are modified

call it like that in html templates :
<script type="text/javascript" src="themes/<tmpl_var name='theme'>/scripts/jspack.php?p=layout-header"></script>
*/

header('Content-Type: text/javascript');

$packages = array (

"jquery-ui" => array(
	"../../../js/jquery.min.js",
	"jquery-ui.min.js"
	),
"droplist" => array(
	"jquery.mousewheel.js",
	"jquery.jScrollPane.js",
	"jquery.droplist.js"
	),
"progressbar" => array(
	"jquery.progressbar.min.js"
	),

"layout-header" => array(
	//packages
	"jquery-ui",
	"droplist",
	//single files
	"linkControl.js",
	"common.js",
	"jquery.documentready.js",
	"jquery.documentready.user.js",
	//common
	"../../../js/jquery.jgrowl.js",
	"../../../js/common.js"
	)
);

if (!empty($_REQUEST['p'])) {
	
	$package = $_REQUEST['p'];
	
	//build javascript file list from packages
	$filelist = array();
	foreach($packages[$package] as $jsfile) {
		if (strpos($jsfile,'.js') === false) {
			//subpackage (one level only)
			foreach($packages[$jsfile] as $jsfile) {
				$filelist[$jsfile] = $jsfile;
			}
		} else
			$filelist[$jsfile] = $jsfile;
	}

	if (count($filelist) == 1) {
		header('Location: '.array_pop($filelist));
		exit;
	}

	//combine js files and save cache
	$packed = '';
	foreach($filelist as $jsfile) {
		if (!empty($jsfile)) {
			$data = file_get_contents($jsfile);
			$packed .= $data."\n";
		}
	}

	//write package if changed
	if (@ file_get_contents('pack/'.$package.'.js') != $packed) {
		file_put_contents('pack/'.$package.'.js',$packed);
	}
	
	header('Location: pack/'.$package.'.js');
	
	exit(0);
}

?>