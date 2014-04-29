<?php
/**
* @file
* @brief    sigplus Image Gallery Plus Slimbox2 lightweight pop-up window engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for jQuery-based Slimbox2 pop-up window engine.
* @see http://fancybox.net
*/
class SIGPlusSlimbox2LightboxEngine extends SIGPlusLightboxEngine {
	public function getIdentifier() {
		return 'slimbox2';
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
		$jsparams['loop'] = $params->loop;

		// add document loaded event script with parameters
		$script =
			'(function ($) {'.PHP_EOL.
			'	if (!/android|iphone|ipod|series60|symbian|windows ce|blackberry/i.test(navigator.userAgent)) {'.PHP_EOL.
			'		var items = $('.json_encode($selector).');'.PHP_EOL.
			'		items.slimbox('.json_encode($jsparams).', null, function(el) {'.PHP_EOL.
			'			return (this == el) || $.inArray(this, items);'.PHP_EOL.
			'		});'.PHP_EOL.
			'	}'.PHP_EOL.
			'})(jQuery);';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
}