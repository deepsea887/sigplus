<?php
/**
* @file
* @brief    sigplus Image Gallery Plus Bootstrap lightweight pop-up window engine
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
* Support class for jQuery-based Bootstrap lightweight pop-up window engine, integrated into Joomla 3.0.
* @see http://twitter.github.com/bootstrap/javascript.html#transitions
*/
class SIGPlusBootstrapLightboxEngine extends SIGPlusLightboxEngine {
	public function getIdentifier() {
		return 'bootstrap';
	}

	public function getLibrary() {
		return 'jquery';
	}

	/**
	* Adds style sheet references to the HTML head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addStyles($selector, SIGPlusGalleryParameters $params) {
		// do not call parent::addStyles($selector, $params), Bootstrap is integrated into Joomla 3.0.
	}

	/**
	* Adds script references to the HTML head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addScripts($selector, SIGPlusGalleryParameters $params) {
		static $html;

		// do not call parent::addScripts($selector, $params), Bootstrap is integrated into Joomla 3.0.

		// check version, Bootstrap is available only in Joomla 3.0 or later
		if (version_compare(JVERSION, '3.0') < 0) {
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('SIGPLUS_EXCEPTION_BOOTSTRAP'), 'error');
			return;
		}

		// import jQuery in page <head> section
		JHtml::_('behavior.framework', true);
		JHtml::_('bootstrap.framework');

		// get reference to sigplus engine services
		$instance = SIGPlusEngineServices::instance();

		// append Bootstrap dialog box HTML
		if (!isset($html)) {
			$html = ''
				.'<div id="sigplus-bootstrap" class="modal fade hide" tabindex="-1" role="dialog" aria-labelledby="sigplus-modal-label" aria-hidden="true">'
				.  '<div class="modal-header">'
				.    '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>'
				.    '<h3 id="sigplus-modal-label">sigplus</h3>'
				.  '</div>'
				.  '<div class="modal-body"><img id="sigplus-bootstrap-image" src=""></div>'
				.  '<div class="modal-footer">'
				.    '<button class="btn btn-primary" data-dismiss="modal" aria-hidden="true">Close</button>'
				.  '</div>'
				.'</div>'
			;
			$script = 'jQuery("body").append('.json_encode($html).');';
			$instance->addOnReadyScript($script);
		}

		// wire Bootstrap with sigplus gallery
		$jsparams = $params->lightbox_params;
		$jsparams['show'] = false;  // suppress showing modal box upon initialization
		$script  = 'jQuery("#sigplus-bootstrap").modal('.json_encode($jsparams).');';  // initialize modal box
		$script .= 'jQuery('.json_encode($selector).').click(function (event) {';  // subscribe to click event
		$script .=   'jQuery("#sigplus-bootstrap-image").attr("src", this.href);';  // set image
		$script .=   'jQuery("#sigplus-modal-label").html(jQuery(this).attr("title"));';  // set image title
		$script .=   'jQuery("#sigplus-bootstrap").modal("show");';  // show modal box
		$script .=   'event.preventDefault();';  // prevent click event on anchor triggering default browser behavior
		$script .= '});';
		$instance->addOnReadyScript($script);
	}
}
