<?php
/**
* @file
* @brief    sigplus Image Gallery Plus Fancybox lightweight pop-up window engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for jQuery-based Fancybox pop-up window engine.
* @see http://fancybox.net
*/
class SIGPlusFancyboxLightboxEngine extends SIGPlusLightboxEngine {
	public function getIdentifier() {
		return 'fancybox';
	}

	public function getLibrary() {
		return 'jquery';
	}

	/**
	* Adds script references to the HTML head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addScripts($selector, SIGPlusGalleryParameters $params) {
		// add main script
		parent::addScripts($selector, $params);

		// build lightbox engine options
		$jsparams = $params->lightbox_params;
		$jsparams['cyclic'] = $params->loop;
		$jsparams['autoScale'] = $params->lightbox_autofit;
		$jsparams['centerOnScroll'] = $params->lightbox_autocenter;

		// add document loaded event script with parameters
		$script = 'jQuery('.json_encode($selector).').fancybox('.json_encode($jsparams).');';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
}