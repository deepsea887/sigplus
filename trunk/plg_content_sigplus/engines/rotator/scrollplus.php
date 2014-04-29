<?php
/**
* @file
* @brief    sigplus Image Gallery Plus scrollplus manual slider engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
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

		// get engine helper
		$instance = SIGPlusEngineServices::instance();
		
		// add dependent MooTools framework script
		$instance->addScript('/media/sigplus/engines/'.$this->getIdentifier().'/js/mootools-more-1.4.0.1.js');

		// add document loaded event script
		$script = 'new scrollplus(document.getElement("'.$selector.'"));';
		$instance->addOnReadyScript($script);
	}
}