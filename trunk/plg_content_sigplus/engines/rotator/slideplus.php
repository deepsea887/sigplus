<?php
/**
* @file
* @brief    sigplus Image Gallery Plus slideplus image rotator engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for MooTools-based slideplus rotator engine.
* @see http://hunyadi.info.hu/projects/slideplus/
*/
class SIGPlusSlidePlusRotatorEngine extends SIGPlusRotatorEngine {
	public function getIdentifier() {
		return 'slideplus';
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
		
		// add localization
		$language = JFactory::getLanguage();
		list($lang, $country) = explode('-', $language->getTag());
		//$instance->addScript('/media/sigplus/engines/'.$this->getIdentifier().'/js/'.$this->getIdentifier().'.lang?lang='.$lang.'-'.$country.'.js');

		// build rotator engine options
		$jsparams = $params->rotator_params;
		$jsparams['size'] = array(
			'rows' => $params->rows,
			'cols' => $params->cols
		);
		$jsparams['orientation'] = $params->rotator_orientation;
		$navigation = array();
		if ($params->rotator_buttons) {
			$navigation[] = 'over';
		}
		switch ($params->rotator_navigation) {
			case 'top': $navigation[] = 'top'; break;
			case 'bottom': $navigation[] = 'bottom'; break;
			case 'both': $navigation[] = 'top'; $navigation[] = 'bottom'; break;
		}
		$jsparams['navigation'] = $navigation;
		$jsparams['links'] = $params->rotator_links;
		$jsparams['trigger'] = $params->rotator_trigger;
		$jsparams['step'] = $params->rotator_step;
		$jsparams['duration'] = $params->rotator_duration;
		$jsparams['delay'] = $params->rotator_delay;
		switch ($params->rotator_alignment) {
			case 'w': case 'c': case 'e': $jsparams['horzalign'] = 'center'; break;
			case 'nw': case 'w': case 'sw': $jsparams['horzalign'] = 'start'; break;
			case 'ne': case 'e': case 'se': $jsparams['horzalign'] = 'end'; break;
		}
		switch ($params->rotator_alignment) {
			case 'w': case 'c': case 'e': $jsparams['vertalign'] = 'center'; break;
			case 'nw': case 'n': case 'ne': $jsparams['vertalign'] = 'start'; break;
			case 'sw': case 's': case 'se': $jsparams['vertalign'] = 'end'; break;
		}
		$jsparams['transition'] = $params->rotator_transition;
		$jsparams['loop'] = $params->loop;
		if ($params->sort_criterion == SIGPLUS_SORT_RANDOM) {
			$jsparams['random'] = true;
		}
		$jsparams['protection'] = $params->protection;

		if (preg_match('/^boxplus\b/', $params->lightbox)) {
			$script = 'new slideplus(document.getElement("'.$selector.'"), '.json_encode($jsparams).', lightbox);';
		} else {
			// add document loaded event script with parameters
			$script = 'new slideplus(document.getElement("'.$selector.'"), '.json_encode($jsparams).');';
		}
		$instance->addOnReadyScript($script);
	}
}