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

// states
define('FLUXDMOD_STATE_NULL', 0);                                        // null
define('FLUXDMOD_STATE_RUNNING', 1);                                  // running
define('FLUXDMOD_STATE_ERROR', -1);                                     // error

// base class for a Fluxd-Service-module
class FluxdServiceMod
{
	// public fields

	// module-name
	public $moduleName = "";

	// state
	public $state = FLUXDMOD_STATE_NULL;

	// messages-array
	public $messages = array();

	// modstate
	public $modstate = FLUXDMOD_STATE_NULL;

	// =========================================================================
	// public static methods
	// =========================================================================

	/**
	 * accessor for singleton
	 *
	 * @return FluxdServiceMod
	 */
	public static function getInstance() {}

	/**
	 * initialize FluxdServiceMod.
	 */
	public static function initialize() {}

	/**
	 * getState
	 *
	 * @return state
	 */
	public static function getState() {}

	/**
	 * getMessages
	 *
	 * @return array
	 */
	public static function getMessages() {}

	/**
	 * getModState
	 *
	 * @return state
	 */
	public static function getModState() {}

	/**
	 * isRunning
	 *
	 * @return boolean
	 */
	public static function isRunning() {}

	/**
	 * initialize a Fluxd-Service-mod.
	 *
	 * @param $type
	 */
	public static function initializeServiceMod($type)
	{
		global $cfg;
		if (in_array($type, $cfg['fluxdServiceModList'])) {
			require_once('inc/classes/FluxdServiceMod.'.$type.'.php');
			eval('Fluxd'.$type.'::initialize();');
		}
	}

	// =========================================================================
	// ctor
	// =========================================================================

	/**
	 * ctor
	 */
	public function __construct()
	{
		die('base class -- dont do this');
	}

	// =========================================================================
	// public methods
	// =========================================================================

	/**
	 * initialize the FluxdServiceMod.
	 */
	public function instance_initialize()
	{
		// modstate-init
		$this->modstate = Fluxd::modState($this->moduleName);
	}
}

