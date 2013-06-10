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

// class for the Fluxd-Service-module Trigger
class FluxdTrigger extends FluxdServiceMod
{

	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * accessor for singleton
	 *
	 * @return FluxdTrigger
	 */
	public static function getInstance()
	{
		global $instanceFluxdTrigger;
		// initialize if needed
		if (!isset($instanceFluxdTrigger))
			FluxdTrigger::initialize();
		return $instanceFluxdTrigger;
	}

	/**
	 * initialize FluxdTrigger.
	 */
	public static function initialize() {
		global $instanceFluxdTrigger;
		// create instance
		if (!isset($instanceFluxdTrigger))
			$instanceFluxdTrigger = new FluxdTrigger();
	}

	/**
	 * getState
	 *
	 * @return state
	 */
	public static function getState()
	{
		global $instanceFluxdTrigger;
		return (isset($instanceFluxdTrigger))
			? $instanceFluxdTrigger->state
			: FLUXDMOD_STATE_NULL;
	}

	/**
	 * getMessages
	 *
	 * @return array
	 */
	public static function getMessages() {
		global $instanceFluxdTrigger;
		return (isset($instanceFluxdTrigger))
			? $instanceFluxdTrigger->messages
			: array();
	}

	/**
	 * getModState
	 *
	 * @return state
	 */
	public static function getModState()
	{
		global $instanceFluxdTrigger;
		return (isset($instanceFluxdTrigger))
			? $instanceFluxdTrigger->modstate
			: FLUXDMOD_STATE_NULL;
	}

	/**
	 * isRunning
	 *
	 * @return boolean
	 */
	public static function isRunning()
	{
		global $instanceFluxdTrigger;
		return (isset($instanceFluxdTrigger))
			? ($instanceFluxdTrigger->modstate == FLUXDMOD_STATE_RUNNING)
			: false;
	}

	// =========================================================================
	// ctor
	// =========================================================================

	/**
	 * ctor
	 */
	public function __construct()
	{
		// name
		$this->moduleName = "Trigger";
		// initialize
		$this->instance_initialize();
	}

}

