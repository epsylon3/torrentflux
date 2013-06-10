<?php

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

// class for the Fluxd-Service-module Fluxinet
class FluxdFluxinet extends FluxdServiceMod
{

	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * accessor for singleton
	 *
	 * @return FluxdFluxinet
	 */
	public static function getInstance()
	{
		global $instanceFluxdFluxinet;
		// initialize if needed
		if (!isset($instanceFluxdFluxinet))
			FluxdFluxinet::initialize();
		return $instanceFluxdFluxinet;
	}

	/**
	 * initialize FluxdFluxinet.
	 */
	public static function initialize()
	{
		global $instanceFluxdFluxinet;
		// create instance
		if (!isset($instanceFluxdFluxinet))
			$instanceFluxdFluxinet = new FluxdFluxinet();
	}

	/**
	 * getState
	 *
	 * @return state
	 */
	public static function getState()
	{
		global $instanceFluxdFluxinet;
		return (isset($instanceFluxdFluxinet))
			? $instanceFluxdFluxinet->state
			: FLUXDMOD_STATE_NULL;
	}

	/**
	 * getMessages
	 *
	 * @return array
	 */
	public static function getMessages()
	{
		global $instanceFluxdFluxinet;
		return (isset($instanceFluxdFluxinet))
			? $instanceFluxdFluxinet->messages
			: array();
	}

	/**
	 * getModState
	 *
	 * @return state
	 */
	public static function getModState()
	{
		global $instanceFluxdFluxinet;
		return (isset($instanceFluxdFluxinet))
			? $instanceFluxdFluxinet->modstate
			: FLUXDMOD_STATE_NULL;
	}

	/**
	 * isRunning
	 *
	 * @return boolean
	 */
	public static function isRunning()
	{
		global $instanceFluxdFluxinet;
		return (isset($instanceFluxdFluxinet))
			? ($instanceFluxdFluxinet->modstate == FLUXDMOD_STATE_RUNNING)
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
		$this->moduleName = "Fluxinet";
		// initialize
		$this->instance_initialize();
	}

}

