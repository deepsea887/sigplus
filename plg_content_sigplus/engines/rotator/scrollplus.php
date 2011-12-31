<?php
/**
* @file
* @brief    sigplus Image Gallery Plus scrollplus manual slider engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for MooTools-based scrollplus manual slider engine.
*/
class SIGPlusScrollPlusRotatorEngine extends SIGPlusRotatorEngine {
	public function getIdentifier() {
		return 'scrollplus';
	}

	public function getLibrary() {
		return 'mootools';
	}
	
	/**
	* Adds script references to the HTML head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addScripts($selector, SIGPlusGalleryParameters $params) {
		// add main script
		parent::addScripts($selector, $params);

		// add document loaded event script
		$script = 'new scrollplus(document.getElement("'.$selector.'"));';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
}