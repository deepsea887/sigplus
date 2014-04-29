<?php
/**
* @file
* @brief    sigplus Image Gallery Plus captionplus image caption engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for MooTools-based captionplus engine.
* @see http://hunyadi.info.hu/projects/
*/
class SIGPlusCaptionPlusCaptionEngine extends SIGPlusCaptionEngine {
	public function getIdentifier() {
		return 'captionplus';
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

		// build rotator engine options
		$jsparams = $params->caption_params;
		switch ($params->caption_position) {
			case 'below': case 'above': $jsparams['overlay'] = false; break;
			default: $jsparams['overlay'] = true;
		}
		switch ($params->caption_position) {
			case 'overlay-top': case 'above': $jsparams['position'] = 'top'; break;
			default: $jsparams['position'] = 'bottom';
		}
		$jsparams['visibility'] = $params->caption_visibility;

		// add document loaded event script with parameters
		$script = 'captionplus.bind(document.getElement("'.$selector.'"), '.json_encode($jsparams).');';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
}