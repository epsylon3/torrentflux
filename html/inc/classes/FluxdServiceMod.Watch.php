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

// class for the Fluxd-Service-module Watch
class FluxdWatch extends FluxdServiceMod
{

	// private fields

	// jobs-delim
	var $_delimJobs = ";";

	// job-delim
	var $_delimJob = ":";

	// component-delim
	var $_delimComponent = "=";



	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return FluxdWatch
     */
    function getInstance() {
		global $instanceFluxdWatch;
		// initialize if needed
		if (!isset($instanceFluxdWatch))
			FluxdWatch::initialize();
		return $instanceFluxdWatch;
    }

    /**
     * initialize FluxdWatch.
     */
    function initialize() {
    	global $instanceFluxdWatch;
    	// create instance
    	if (!isset($instanceFluxdWatch))
    		$instanceFluxdWatch = new FluxdWatch();
    }

	/**
	 * getState
	 *
	 * @return state
	 */
    function getState() {
		global $instanceFluxdWatch;
		return (isset($instanceFluxdWatch))
			? $instanceFluxdWatch->state
			: FLUXDMOD_STATE_NULL;
    }

    /**
     * getMessages
     *
     * @return array
     */
    function getMessages() {
		global $instanceFluxdWatch;
		return (isset($instanceFluxdWatch))
			? $instanceFluxdWatch->messages
			: array();
    }

	/**
	 * getModState
	 *
	 * @return state
	 */
	function getModState() {
		global $instanceFluxdWatch;
		return (isset($instanceFluxdWatch))
			? $instanceFluxdWatch->modstate
			: FLUXDMOD_STATE_NULL;
	}

    /**
     * isRunning
     *
     * @return boolean
     */
    function isRunning() {
		global $instanceFluxdWatch;
		return (isset($instanceFluxdWatch))
			? ($instanceFluxdWatch->modstate == FLUXDMOD_STATE_RUNNING)
			: false;
    }

	/**
	 * get jobs-list
	 *
	 * @return jobs-list as array or false on error
	 */
	function jobsGetList() {
		global $instanceFluxdWatch;
		return $instanceFluxdWatch->instance_jobsGetList();
	}

	/**
	 * get job-content
	 *
	 * @param $jobnumber
	 * @return job as array or false on error
	 */
	function jobGetContent($jobnumber) {
		global $instanceFluxdWatch;
		return $instanceFluxdWatch->instance_jobGetContent($jobnumber);
	}

	/**
	 * add a job
	 *
	 * @param $watchdir
	 * @param $user
	 * @param $profile
	 * @param $checkdir
	 * @return boolean
	 */
	function jobAdd($watchdir, $user, $profile, $checkdir = false) {
		global $instanceFluxdWatch;
		return $instanceFluxdWatch->instance_jobAdd($watchdir, $user, $profile, $checkdir);
	}

	/**
	 * update a job
	 *
	 * @param $jobnumber
	 * @param $watchdir
	 * @param $user
	 * @param $profile
	 * @param $checkdir
	 * @return boolean
	 */
	function jobUpdate($jobnumber, $watchdir, $user, $profile, $checkdir = false) {
		global $instanceFluxdWatch;
		return $instanceFluxdWatch->instance_jobUpdate($jobnumber, $watchdir, $user, $profile, $checkdir);
	}

	/**
	 * delete a job
	 *
	 * @param $jobnumber
	 * @return boolean
	 */
	function jobDelete($jobnumber) {
		global $instanceFluxdWatch;
		return $instanceFluxdWatch->instance_jobDelete($jobnumber);
	}



	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function FluxdWatch() {
    	// name
        $this->moduleName = "Watch";
		// initialize
        $this->instance_initialize();
    }



	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * get jobs-list
	 *
	 * @return jobs-list as array or false on error
	 */
	function instance_jobsGetList() {
		global $cfg;
		// Jobs: job1;job2;job3
		// Job:  U=user:[P=profile:]D=watchdir
		if (isset($cfg["fluxd_Watch_jobs"])) {
			$joblist = array();
			$jobs = explode($this->_delimJobs, trim($cfg["fluxd_Watch_jobs"]));
			if (count($jobs) > 0) {
				$jobCount = 0;
				foreach ($jobs as $job) {
					$rest = trim($job);
					if (!isset($rest) || strlen($rest) == 0)
						continue;
					$jobCount++;

					$jobEntry = array();
					while (true) {
						if (preg_match('/^D'.$this->_delimComponent.'/', $rest) > 0) {
							// Dir: final component.
							$jobEntry['D'] = substr($rest, 2);
							break;
						} else {
							// Other component.
							$jobAry = explode($this->_delimJob, $rest, 2);
							if (count($jobAry) != 2 || preg_match('/^\s*[A-Z]'.$this->_delimComponent.'/', $jobAry[1]) == 0) {
								array_push($this->messages, "invalid format for job ".$jobCount.".");
								// Can't really return an error here... still load other jobs.
								//return false;
								$jobEntry['D'] = '';
								break;
							}
							$rest = trim(array_shift($jobAry));
							$jobEntry[$rest[0]] = substr($rest, 2);
							$rest = trim(array_shift($jobAry));
						}
					}

					if (
						isset($jobEntry['U']) && strlen($jobEntry['U']) > 0 &&
						isset($jobEntry['D']) && strlen($jobEntry['D']) > 0
					)
						array_push($joblist, $jobEntry);
				}
			}

			// An empty job list is not an error.
			return $joblist;
		}
		return false;
	}

	/**
	 * get job-content
	 *
	 * @param $jobnumber
	 * @return job as array or false on error
	 */
	function instance_jobGetContent($jobnumber) {
		$jobInt = intval($jobnumber);
		if ($jobInt > 0) {
			$jobs = $this->instance_jobsGetList();
			if ($jobs !== false && count($jobs) >= $jobInt)
				return $jobs[$jobInt - 1];
		}
		return false;
	}

	/**
	 * add a job
	 *
	 * @param $watchdir
	 * @param $user
	 * @param $profile
	 * @param $checkdir
	 * @return boolean
	 */
	function instance_jobAdd($watchdir, $user, $profile, $checkdir = false) {
		if (strlen($watchdir) > 0 && strlen($user) > 0) {
			$watchdir = checkDirPathString($watchdir);

			// Get current jobs and make sure new one is not a duplicate.
			$jobs = $this->instance_jobsGetList();
			if ($jobs !== false) {
				foreach ($jobs as $job)
					if (isset($job['D']) && $job['D'] == $watchdir) {
						array_push($this->messages, "dir ".$watchdir." is already begin watched.");
						return false;
					}

				// Check/create dir if needed.
				if ($checkdir && !checkDirectory($watchdir)) {
					array_push($this->messages, "dir ".$watchdir." does not exist and could not be created.");
					return false;
				}

				// Create new job.
				$job = array(
					'U' => $user,
					'D' => $watchdir
				);
				if (isset($profile))
					$job['P'] = $profile;
				array_push($jobs, $job);

				// Update settings.
				return $this->_jobsUpdateSetting($jobs);
			}
		}
		return false;
	}

	/**
	 * update a job
	 *
	 * @param $jobnumber
	 * @param $watchdir
	 * @param $user
	 * @param $profile
	 * @param $checkdir
	 * @return boolean
	 */
	function instance_jobUpdate($jobnumber, $watchdir, $user, $profile, $checkdir = false) {
		$jobInt = intval($jobnumber);
		if ($jobInt > 0 && strlen($watchdir) > 0 && strlen($user) > 0) {
			$watchdir = checkDirPathString($watchdir);

			// Get current jobs and make sure modified one is not a duplicate.
			$jobs = $this->instance_jobsGetList();
			if ($jobs !== false && count($jobs) >= $jobInt) {
				$jobs[$jobInt - 1] = array();
				foreach ($jobs as $job)
					if (isset($job['D']) && $job['D'] == $watchdir) {
						array_push($this->messages, "dir ".$watchdir." is already begin watched.");
						return false;
					}

				// Check/create dir if needed.
				if ($checkdir && !checkDirectory($watchdir)) {
					array_push($this->messages, "dir ".$watchdir." does not exist and could not be created.");
					return false;
				}

				// Create new job.
				$job = array(
					'U' => $user,
					'D' => $watchdir
				);
				if (isset($profile))
					$job['P'] = $profile;
				$jobs[$jobInt - 1] = $job;

				// Update settings.
				return $this->_jobsUpdateSetting($jobs);
			}
		}
		return false;
	}

	/**
	 * delete a job
	 *
	 * @param $jobnumber
	 * @return boolean
	 */
	function instance_jobDelete($jobnumber) {
		$jobInt = intval($jobnumber);
		if ($jobInt > 0) {
			$jobs = $this->instance_jobsGetList();
			if ($jobs !== false && count($jobs) >= $jobInt) {
				array_splice($jobs, $jobInt - 1, 1);
				return $this->_jobsUpdateSetting($jobs);
			}
		}
		return false;
	}



	// =========================================================================
	// private methods
	// =========================================================================

	/**
	 * build new jobs-list string from jobs-list array
	 *
	 * @param $jobs jobs-list as array (each item being a job as array)
	 * @return string
	 */
	function _jobsSerialize($jobs) {
		$return = '';

		foreach ($jobs as $job) {
			if (
				!isset($job['U']) || strlen(trim($job['U'])) <= 0 ||
				!isset($job['D']) || strlen(trim($job['D'])) <= 0
			)
				continue;

			// New format: U= component must be first.
			$jobstr = 'U' . $this->_delimComponent . $job['U'] . $this->_delimJob;

			foreach ($job as $k => $v)
				if ($k != 'U' && $k != 'D' && strlen($k) > 0 && strlen($v) > 0)
					$jobstr .= $k . $this->_delimComponent . $v . $this->_delimJob;

			// D= component must be last -- make sure it has a trailing slash.
			$jobstr .= 'D' . $this->_delimComponent . checkDirPathString($job['D']);

			$return .= (strlen($return) == 0 ? '' : $this->_delimJobs) . $jobstr;
		}

		return $return;
	}

	/**
	 * store new jobs-list setting
	 *
	 * @param $jobs jobs-list as array
	 * @return boolean
	 */
	function _jobsUpdateSetting($jobs) {
		global $cfg;

		// build setting value
		$setting = $this->_jobsSerialize($jobs);
		if ($setting === false)
			return false;

		// update setting
		updateSetting("tf_settings", "fluxd_Watch_jobs", $setting);

		// log
		AuditAction($cfg["constants"]["fluxd"], "Watch Jobs Saved : \n".$setting);

		return true;
	}

}

?>