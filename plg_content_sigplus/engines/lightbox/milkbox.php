<?php
/**
* @file
* @brief    sigplus Image Gallery Plus Milkbox lightweight pop-up window engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for MooTools-based Milkbox pop-up window engine.
* @see http://reghellin.com/milkbox/
*/
class SIGPlusMilkboxLightboxEngine extends SIGPlusLightboxEngine {
	public function getIdentifier() {
		return 'milkbox';
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
		static $counter = 0;

		// add main script
		parent::addScripts($selector, $params);

		// build lightbox engine options
		$jsparams = $params->lightbox_params;
		$jsparams['autoSize'] = $params->lightbox_autofit;
		$jsparams['centered'] = $params->lightbox_autocenter;
		$jsparams['resizeTransition'] = $params->lightbox_transition;
		$autoplay = $params->lightbox_slideshow > 0;
		$autoplaydelay = $params->lightbox_slideshow/1000;

		// get unique identifier
		$galleryid = sprintf('gallery-%03d', ++$counter);

		// add document loaded event script with parameters
		$script =
			'milkbox.addGalleries([{'.PHP_EOL.
			'	name:"'.$galleryid.'",'.PHP_EOL.
			'	autoplay:'.json_encode($autoplay).','.PHP_EOL.
			'	autoplay_delay:'.json_encode($autoplaydelay).','.PHP_EOL.
			'	files:document.getElements("'.$selector.'").map(function (anchor) {'.PHP_EOL.
			'		return {'.PHP_EOL.
			'			href: anchor.get("href"),'.PHP_EOL.
			'			title: anchor.get("title")'.PHP_EOL.
			'		};'.PHP_EOL.
			'	})'.PHP_EOL.
			'}]);'.PHP_EOL.
			'document.getElements('.json_encode($selector).').each(function (anchor,index) {'.PHP_EOL.
			'	anchor.addEvent("click", function (event) {'.PHP_EOL.
			'		event.preventDefault();'.PHP_EOL.
			'		milkbox.refreshDisplay('.json_encode($jsparams).');'.PHP_EOL.
			'		milkbox.open("'.$galleryid.'",index);'.PHP_EOL.
			'	});'.PHP_EOL.
			'});';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
}