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

// class for the Fluxd-Service-module Maintenance
class FluxdMaintenance extends FluxdServiceMod
{

	// =========================================================================
	// public static methods
	// =========================================================================

    /**
     * accessor for singleton
     *
     * @return FluxdMaintenance
     */
    function getInstance() {
		global $instanceFluxdMaintenance;
		// initialize if needed
		if (!isset($instanceFluxdMaintenance))
			FluxdMaintenance::initialize();
		return $instanceFluxdMaintenance;
    }

    /**
     * initialize FluxdMaintenance.
     */
    function initialize() {
    	global $instanceFluxdMaintenance;
    	// create instance
    	if (!isset($instanceFluxdMaintenance))
    		$instanceFluxdMaintenance = new FluxdMaintenance();
    }

	/**
	 * getState
	 *
	 * @return state
	 */
    function getState() {
		global $instanceFluxdMaintenance;
		return (isset($instanceFluxdMaintenance))
			? $instanceFluxdMaintenance->state
			: FLUXDMOD_STATE_NULL;
    }

    /**
     * getMessages
     *
     * @return array
     */
    function getMessages() {
		global $instanceFluxdMaintenance;
		return (isset($instanceFluxdMaintenance))
			? $instanceFluxdMaintenance->messages
			: array();
    }

	/**
	 * getModState
	 *
	 * @return state
	 */
	function getModState() {
		global $instanceFluxdMaintenance;
		return (isset($instanceFluxdMaintenance))
			? $instanceFluxdMaintenance->modstate
			: FLUXDMOD_STATE_NULL;
	}

    /**
     * isRunning
     *
     * @return boolean
     */
    function isRunning() {
		global $instanceFluxdMaintenance;
		return (isset($instanceFluxdMaintenance))
			? ($instanceFluxdMaintenance->modstate == FLUXDMOD_STATE_RUNNING)
			: false;
    }

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function FluxdMaintenance() {
    	// name
        $this->moduleName = "Maintenance";
		// initialize
        $this->instance_initialize();
    }

}

?>