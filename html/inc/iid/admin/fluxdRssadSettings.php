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

// prevent direct invocation
if ((!isset($cfg['user'])) || (isset($_REQUEST['cfg']))) {
	@ob_end_clean();
	@header("location: ../../../index.php");
	exit();
}

/******************************************************************************/

// Rssad
FluxdServiceMod::initializeServiceMod('Rssad');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.fluxdRssadSettings.tmpl");

// message section
$message = tfb_getRequestVar('m');
if ((isset($message)) && ($message != "")) {
	$tmpl->setvar('new_msg', 1);
	$tmpl->setvar('message', urldecode($message));
} else {
	$tmpl->setvar('new_msg', 0);
}

// pageop
//
// * default
//
// * addFilter
// * editFilter
// * saveFilter
// * deleteFilter
//
// * addJob
// * editJob
// * saveJob
// * deleteJob
//
$pageop = tfb_getRequestVar('pageop');
$tmpl->setvar('pageop', (empty($pageop)) ? "default" : $pageop);
// op-switch
switch ($pageop) {

	default:
	case "default":
		// filters
		$filters = FluxdRssad::filterGetList();
		if ($filters !== false) {
			$filterlist = array();
			foreach ($filters as $filter) {
				$filt = trim($filter);
				if (strlen($filt) > 0)
					array_push($filterlist, array("filtername" => $filt));
			}
			$tmpl->setloop('rssad_filters', $filterlist);
		}
		// jobs
		$jobs = FluxdRssad::jobsGetList();
		if ($jobs !== false)
			$tmpl->setloop('rssad_jobs', $jobs);
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad Settings");
		break;

	case "addFilter":
		$filtername = tfb_getRequestVar('filtername');
		if (empty($filtername)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Filtername.");
		} else {
			if (FluxdRssad::filterIdCheck($filtername, true) === true) {
				$filterstring = $filtername;
				$maxFiles = 100;
				$noMatch = true;
				$idx = 1;
				while ($noMatch) {
					if (FluxdRssad::filterExists($filtername) === false) {
						$tmpl->setvar('filtername', $filtername);
						$tmpl->setvar('rssad_filtercontent', "");
						$noMatch = false;
					} else {
						$filtername = $filterstring."_".$idx;
					}
					$idx++;
					if ($idx >= $maxFiles) {
						$noMatch = false;
						$tmpl->setvar('new_msg', 1);
						$tmpl->setvar('message', "Error : Invalid Filtername.");
					}
				}
			} else {
				$tmpl->setvar('new_msg', 1);
				$tmpl->setvar('message', "Error : Invalid Filtername.");
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Add Filter");
		break;

	case "editFilter":
		$filtername = tfb_getRequestVar('filtername');
		if (empty($filtername)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Filtername.");
		} else {
			if (FluxdRssad::filterIdCheck($filtername, false) === true) {
				// create the filter
				if (FluxdRssad::filterExists($filtername) === true) {
					$tmpl->setvar('filtername', $filtername);
					$content = trim(FluxdRssad::filterGetContent($filtername));
					$tmpl->setvar('rssad_filtercontent', tfb_htmlencode($content));
					$filterlines = explode("\n", $content);
					if (count($filterlines) > 0) {
						$filterlist = array();
						foreach ($filterlines as $filterline) {
							$filt = trim($filterline);
							if (strlen($filt) > 0)
								array_push($filterlist, array("filter" => tfb_htmlencodekeepspaces($filt)));
						}
						$tmpl->setloop('rssad_filter_list', $filterlist);
					}
				} else {
					$tmpl->setvar('new_msg', 1);
					$tmpl->setvar('message', "Error : Filter does not exist.");
				}
			} else {
				$tmpl->setvar('new_msg', 1);
				$tmpl->setvar('message', "Error : Invalid Filtername.");
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Edit Filter");
		break;

	case "saveFilter":
		$filtername = tfb_getRequestVar('filtername');
		$filtercontent = tfb_getRequestVarRaw('rssad_filtercontent');
		$new = tfb_getRequestVar('new');
		if (empty($filtername)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Filtername.");
		} else {
			$isnew = false;
			if ($new == "true") {
				$isnew = true;
				$tmpl->setvar('rssad_filter_message', "Filter ".$filtername." added.");
			} else {
				$isnew = false;
				$tmpl->setvar('rssad_filter_message', "Filter ".$filtername." updated.");
			}
			if (FluxdRssad::filterIdCheck($filtername, $isnew) === true) {
				// save the filter
				$tmpl->setvar('filtername', $filtername);
				if ((FluxdRssad::filterSave($filtername, $filtercontent)) === true) {
					$tmpl->setvar('filter_saved', 1);
					$tmpl->setvar('filtercontent', tfb_htmlencode($filtercontent));
				} else {
					$tmpl->setvar('filter_saved', 0);
					$messages = array();
					$msgs = FluxdRssad::getMessages();
					foreach ($msgs as $msg)
						array_push($messages, array('msg' => $msg));
					$tmpl->setloop('messages', $messages);
				}
			} else {
				$tmpl->setvar('new_msg', 1);
				$tmpl->setvar('message', "Error : Invalid Filtername.");
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Save Filter");
		break;

	case "deleteFilter":
		$filtername = tfb_getRequestVar('filtername');
		if (empty($filtername)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Filtername.");
		} else {
			if (FluxdRssad::filterIdCheck($filtername, false) === true) {
				// delete the filter
				$tmpl->setvar('filtername', $filtername);
				if ((FluxdRssad::filterDelete($filtername)) === true) {
					$tmpl->setvar('filter_deleted', 1);
				} else {
					$tmpl->setvar('filter_deleted', 0);
					$messages = array();
					$msgs = FluxdRssad::getMessages();
					foreach ($msgs as $msg)
						array_push($messages, array('msg' => $msg));
					$tmpl->setloop('messages', $messages);
				}
			} else {
				$tmpl->setvar('new_msg', 1);
				$tmpl->setvar('message', "Error : Invalid Filtername.");
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Delete Filter");
		break;

	case "addJob":
		// filters
		$filters = FluxdRssad::filterGetList();
		if ($filters !== false) {
			$filterlist = array();
			foreach ($filters as $filter) {
				$filt = trim($filter);
				if (strlen($filt) > 0)
					array_push($filterlist, array("filtername" => $filt));
			}
			$tmpl->setloop('rssad_filters', $filterlist);
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Add Job");
		break;

	case "editJob":
		$jobNumber = trim(tfb_getRequestVar('job'));
		if (empty($jobNumber)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Job-Number.");
			$tmpl->setvar('rssad_job_loaded', 0);
		} else {
			$job = FluxdRssad::jobGetContent($jobNumber);
			if ($job !== false) {
				$tmpl->setvar('rssad_job_loaded', 1);
				$tmpl->setvar('jobnumber', $jobNumber);
				$tmpl->setvar('rssad_savedir', $job['savedir']);
				$tmpl->setvar('rssad_url', $job['url']);
				$tmpl->setvar('rssad_filtername', $job['filtername']);
				// filters
				$filters = FluxdRssad::filterGetList();
				if ($filters !== false) {
					$filterlist = array();
					foreach ($filters as $filter) {
						$filt = trim($filter);
						if (strlen($filt) > 0)
							array_push($filterlist, array(
								"filtername" => $filt,
								"selected" => ($filt == $job['filtername']) ? " selected" : ""
								)
							);
					}
					$tmpl->setloop('rssad_filters', $filterlist);
				}
			} else {
				$tmpl->setvar('rssad_job_loaded', 0);
				$tmpl->setvar('messages', $jobNumber);
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Edit Job");
		break;

	case "saveJob":
		$jobNumber = trim(tfb_getRequestVar('job'));
		$savedir = tfb_getRequestVar('savedir');
		$url = tfb_getRequestVarRaw('url');
		$filtername = tfb_getRequestVar('filtername');
		$checkdir = tfb_getRequestVar('checkdir');
		$isNew = (empty($jobNumber)) ? true : false;
		$doCheckdir = ($checkdir == "true") ? true : false;
		$paramErrors = 0;
		if (empty($savedir))
			$paramErrors++;
		if (empty($url))
			$paramErrors++;
		if (empty($filtername))
			$paramErrors++;
		if ($paramErrors != 0) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : Argument-Error.");
		} else {
			if ($isNew) {
				if (FluxdRssad::jobAdd($savedir, $url, $filtername, $doCheckdir) === true) {
					$tmpl->setvar('rssad_job_saved', 1);
					$tmpl->setvar('rssad_job_message', "Job added.");
				} else {
					$tmpl->setvar('rssad_job_saved', 0);
					$messages = array();
					$msgs = FluxdRssad::getMessages();
					foreach ($msgs as $msg)
						array_push($messages, array('msg' => $msg));
					$tmpl->setloop('messages', $messages);
				}
			} else {
				if (FluxdRssad::jobUpdate($jobNumber, $savedir, $url, $filtername, $doCheckdir) === true) {
					$tmpl->setvar('rssad_job_saved', 1);
					$tmpl->setvar('rssad_job_message', "Job updated.");
				} else {
					$tmpl->setvar('rssad_job_saved', 0);
					$messages = array();
					$msgs = FluxdRssad::getMessages();
					foreach ($msgs as $msg)
						array_push($messages, array('msg' => $msg));
					$tmpl->setloop('messages', $messages);
				}
			}
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Save Job");
		break;

	case "deleteJob":
		$jobNumber = trim(tfb_getRequestVar('job'));
		if (empty($jobNumber)) {
			$tmpl->setvar('new_msg', 1);
			$tmpl->setvar('message', "Error : No Job-Number.");
			$tmpl->setvar('rssad_job_deleted', 0);
		} else {
			$tmpl->setvar('rssad_job_deleted', (FluxdRssad::jobDelete($jobNumber) === true) ? 1 : 0);
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Rssad - Delete Job");
		break;
}

//
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
//
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>