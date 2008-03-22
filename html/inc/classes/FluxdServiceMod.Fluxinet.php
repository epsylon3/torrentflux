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
    function getInstance() {
		global $instanceFluxdFluxinet;
		// initialize if needed
		if (!isset($instanceFluxdFluxinet))
			FluxdFluxinet::initialize();
		return $instanceFluxdFluxinet;
    }

    /**
     * initialize FluxdFluxinet.
     */
    function initialize() {
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
    function getState() {
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
    function getMessages() {
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
	function getModState() {
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
    function isRunning() {
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
    function FluxdFluxinet() {
    	// name
        $this->moduleName = "Fluxinet";
		// initialize
        $this->instance_initialize();
    }

}

?>