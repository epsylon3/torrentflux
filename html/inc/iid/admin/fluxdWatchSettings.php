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

// Watch
FluxdServiceMod::initializeServiceMod('Watch');

// init template-instance
tmplInitializeInstance($cfg["theme"], "page.admin.fluxdWatchSettings.tmpl");

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
// * addJob
// * editJob
// * saveJob
// * deleteJob
//

$with_profiles = $cfg['transfer_profiles'] >= 1 ? 1 : 0;
$tmpl->setvar('with_profiles', $with_profiles);


$wasWatchError = false;
function setWatchError($msg) {
	global $tmpl, $wasWatchError;
	$wasWatchError = true;
	$tmpl->setvar('new_msg', 1);
	$tmpl->setvar('message', $msg);
}


$pageop = tfb_getRequestVar('pageop');
$pageop2 = $pageop = (empty($pageop)) ? "default" : $pageop;
$tmpl->setvar('pageop', $pageop);

// op-switch
switch ($pageop) {

	default:
	case "default":
		// jobs
		$jobs = FluxdWatch::jobsGetList();
		if ($jobs === false)
			setWatchError('Error : could not load jobs.');
		else
			$tmpl->setloop('watch_jobs', $jobs);
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Watch Settings");
		break;

	case "addJob":
	case "editJob":
	case "saveJob":
		$jobNumber = trim(tfb_getRequestVarRaw('job'));
		$isNew = empty($jobNumber);

		$watchdir = trim(tfb_getRequestVarRaw('watchdir'));
		$user = trim(tfb_getRequestVarRaw('user'));
		$profile = trim(tfb_getRequestVarRaw('profile'));
		$checkdir = trim(tfb_getRequestVarRaw('checkdir'));

		$postback = tfb_getRequestVarRaw('postback');
		$isPostback = $postback == '1';
		$refresh = tfb_getRequestVarRaw('refresh');
		$isRefresh = $refresh == '1';
		$isSave = $isPostback && !$isRefresh;
		$checkdir = ($checkdir == '1' || $checkdir == 'on' || $checkdir == 'true');

		if ($isSave) {	// saving (a new one or an existing one)
			$pageop2 = "saveJob";

			$paramErrors = 0;
			if (strlen($watchdir) == 0)
				$paramErrors++;
			if (strlen($user) == 0)
				$paramErrors++;
			if ($paramErrors != 0)
				setWatchError('Error : Argument-Error.');
			else {
				if ($isNew) {
					$result = FluxdWatch::jobAdd($watchdir, $user, $profile, $checkdir);
					if ($result !== false)
						$tmpl->setvar('watch_job_message', 'Job added.');
				} else {
					$result = FluxdWatch::jobUpdate($jobNumber, $watchdir, $user, $profile, $checkdir);
					if ($result !== false)
						$tmpl->setvar('watch_job_message', 'Job updated.');
				}
				if ($result === false) {
					$wasWatchError = true;
					$messages = array();
					$msgs = FluxdWatch::getMessages();
					foreach ($msgs as $msg)
						array_push($messages, array('msg' => $msg));
					$tmpl->setloop('messages', $messages);
				}
			}

			$tmpl->setvar('watch_job_saved', !$wasWatchError);

			// title-bar
			tmplSetTitleBar("Administration - Fluxd Watch - Save Job");
		} else {	// initial display, or refresh (of a new one or of an existing one)
			$pageop2 = "addJobOReditJob";

			// job number
			$tmpl->setvar('jobnumber', $jobNumber);

			// initial display of an existing job: load its contents
			if (!$isNew && !$isRefresh) {
				$job = FluxdWatch::jobGetContent($jobNumber);
				if ($job === false)
					$wasWatchError = true;
				else {
					$watchdir = $job['D'];
					$user = $job['U'];
					$profile = isset($job['P']) ? $job['P'] : '';
				}
			}

			if (!$wasWatchError) {
				// job number
				$tmpl->setvar('jobnumber', $jobNumber);
				// watchdir
				$tmpl->setvar('watchdir', $watchdir);
				// users
				$watchusers = array();
				$userCount = count($cfg['users']);
				$foundSel = false;
				for ($i = 0; $i < $userCount; $i++) {
					$tmp = $cfg['users'][$i];
					$sel = ((!$isNew || $isRefresh) && $user == $tmp) ? 1 : 0;
					if ($sel)
						$foundSel = true;
					array_push($watchusers, array(
						'name'        => $tmp,
						'is_selected' => $sel
					));
				}
				if (!$foundSel) {
					// no or invalid user, just set superadmin by default
					$user = GetSuperAdmin();
					foreach ($watchusers as $k => $watchuser)
						if ($user == $watchuser['name'])
							$watchusers[$k]['is_selected'] = 1;
				}
				$tmpl->setloop('watch_users', $watchusers);
				// profiles
				if ($with_profiles) {
					$profiles = GetProfilesByUserName($user, $profile);
					$public_profiles = GetPublicProfiles($profile);
					$tmpl->setloop('profiles', $profiles);
					$tmpl->setloop('public_profiles', $public_profiles);
				}
				// checkdir
				$tmpl->setvar('checkdir', (!$isRefresh || $checkdir) ? 1 : 0);
			}

			$tmpl->setvar('watch_job_loaded', !$wasWatchError);

			// title-bar
			tmplSetTitleBar("Administration - Fluxd Watch - ".($isNew ? "Add" : "Edit")." Job");
		}
		break;

	case "deleteJob":
		$jobNumber = trim(tfb_getRequestVar('job'));
		if (empty($jobNumber)) {
			setWatchError('Error : No Job-Number.');
			$tmpl->setvar('watch_job_deleted', 0);
		} else {
			$tmpl->setvar('watch_job_deleted', (FluxdWatch::jobDelete($jobNumber) === true) ? 1 : 0);
		}
		// title-bar
		tmplSetTitleBar("Administration - Fluxd Watch - Delete Job");
		break;
}

$tmpl->setvar('pageop2', $pageop2);
//
$tmpl->setvar('enable_dereferrer', $cfg["enable_dereferrer"]);
//
tmplSetAdminMenu();
tmplSetFoot();
tmplSetIidVars();

// parse template
$tmpl->pparse();

?>