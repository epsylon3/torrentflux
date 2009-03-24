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
    function getInstance() {
    global $instanceFluxdTrigger;
		// initialize if needed
		if (!isset($instanceFluxdTrigger))
			FluxdTrigger::initialize();
		return $instanceFluxdTrigger;
    }

    /**
     * initialize FluxdTrigger.
     */
    function initialize() {
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
    function getState() {
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
    function getMessages() {
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
	function getModState() {
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
    function isRunning() {
		global $instanceFluxdTrigger;
		return (isset($instanceFluxdTrigger))
			? ($instanceFluxdTrigger->modstate == FLUXDMOD_STATE_RUNNING)
			: false;
    }
    
    /**
     * listJobs
     *
     * @return string
     */
     function listJobs() {
     global $instanceFluxdTrigger;
     return (isset($instanceFluxdTrigger))
       ? ($instanceFluxdTrigger->instance_listJobs()
       : "";
     }
     
    /**
     *
     * addJob
     *
     * @params transfer
     * @param event
     * @param action
     */
     function addjob($transfer, $event, $action) {
         global $instanceFluxdTrigger;
         if (isset($instanceFluxdTrigger) {
           $instanceFluxdTrigger->instance_addjob($transfer, $event, $action);
         }
     }
     
    /**
     *
     * removeJob
     *
     * @params transfer
     * @params event
     * @event action
     */
     function removeJob($transfer, $event, $action) {
         global $instanceFluxdTrigger;
         if (isset($instanceFluxdTrigger) {
           $instanceFluxdTrigger->instance_removeJob($transfer, $event, $action);
         }
    }
         

	// =========================================================================
	// ctor
	// =========================================================================

    /**
     * ctor
     */
    function FluxdTrigger() {
    	// name
        $this->moduleName = "Trigger";
		// initialize
        $this->instance_initialize();
    }

  // ===========================================================================
  // public methods
  // ===========================================================================
  
    /**
     *
     * instance_listJobs
     * 
     * @return string
     */
     function instance_listJobs() {
         return ($this->modstate == FLUXMOD_STATE_RUNNING)
           ? Fluxd::sendServiceCommand($this->moduleName, 'listJobs', 1)
           : "";
     }
     
    /**
     *
     * instance_addJob
     * 
     * @param transfer
     * @param event
     * @param action
     * @return string
     */
     function instance_addJob($transfer, $event, $action) {
         global $cfg;
         if ($this->modstate == FLUXMOD_STATE_RUNNING) {
            // send command
            $result = Fluxd::sendServiceCommand($this->moduleName, 'addJob;' . $transfer . ';' . $event . ';' . $action, 1)
            
            // log it
            AuditAction($cfg["constants"]["fluxd"], $result);
            // sleep
            sleep(2);
        }
    }
    
     /**
      *
      * instance_removeJob
      *
      * @param transfer
      * @param event
      * @param action
      * @return string
      */
      function instance_removeJob($transfer, $event, $action) {
        global $cfg
        if ($this->modstate == FLUXMOD_STATE_RUNNING) {
            // send command
            $result = Fluxd::sendServiceCommand($this->moduleName, 'removeJob;' . $transfer . ';' . $event . ';' . $action, 1)
            
            // log it
            AuditAction($cfg["constants"]["fluxd"], $result);
            // sleep
            sleep(2)
        }
      }

}

?>
