<?php
/**
* @file
* @brief    sigplus Image Gallery Plus boxplus lightweight pop-up window engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for MooTools-based boxplus lightweight pop-up window engine.
* @see http://hunyadi.info.hu/projects/boxplus/
*/
class SIGPlusBoxPlusLightboxEngine extends SIGPlusLightboxEngine {
	private $theme = 'lightsquare';

	public function getIdentifier() {
		return 'boxplus';
	}

	public function getLibrary() {
		return 'mootools';
	}

	public function __construct($params = false) {
		parent::__construct($params);
		if (isset($params['theme'])) {
			$this->theme = $params['theme'];
		}
	}

	/**
	* Adds style sheet references to the HTML head element.
	*/
	public function addStyles($selector, SIGPlusGalleryParameters $params) {
		// add main stylesheet
		parent::addStyles($selector, $params);
		$instance = SIGPlusEngineServices::instance();
		$instance->addConditionalStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.ie8.css', 9);
		$instance->addConditionalStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.ie7.css', 8);

		// add theme stylesheet
		$instance->addStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.'.$this->theme.'.css', array('title'=>$this->getIdentifier().'-'.$this->theme));
		$instance->addConditionalStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.'.$this->theme.'.ie8.css', 9, array('title'=>$this->getIdentifier().'-'.$this->theme));
	}

	/**
	* Adds script references to the HTML head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addScripts($selector, SIGPlusGalleryParameters $params) {
		// add main script
		parent::addScripts($selector, $params);

		// add localization
		$instance = SIGPlusEngineServices::instance();
		$language = JFactory::getLanguage();
		list($lang, $country) = explode('-', $language->getTag());
		$instance->addScript('/media/sigplus/engines/'.$this->getIdentifier().'/js/'.$this->getIdentifier().'.lang.js?lang='.$lang.'-'.$country.'.js');

		// build lightbox engine options
		$jsparams = $params->lightbox_params;
		$jsparams['autocenter'] = $params->lightbox_autocenter;
		$jsparams['autofit'] = $params->lightbox_autofit;
		$jsparams['thumbs'] = $params->lightbox_thumbs;
		$jsparams['slideshow'] = $params->lightbox_slideshow;
		$jsparams['autostart'] = $params->lightbox_autostart;
		$jsparams['transition'] = $params->lightbox_transition;
		$jsparams['loop'] = $params->loop;
		$jsparams['protection'] = $params->protection;

		// add document loaded event script with parameters
		$script = 'var lightbox = new boxplus(document.getElements('.json_encode($selector).'), '.json_encode($jsparams).');';
		$instance->addOnReadyScript($script);

		// persist lightbox instance in elements store
		$instance->storeLightbox($selector);
	}
}
