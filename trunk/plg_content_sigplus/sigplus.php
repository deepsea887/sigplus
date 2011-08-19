<?php
/**
* @file
* @brief    sigplus Image Gallery Plus plug-in for Joomla
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus plug-in for Joomla
* Copyright 2009-2011 Levente Hunyadi
*
* sigplus is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* sigplus is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

if (!defined('SIGPLUS_VERSION_PLUGIN')) {
	define('SIGPLUS_VERSION_PLUGIN', '$__VERSION__$');
}

if (!defined('SIGPLUS_DEBUG')) {
	/**
	* Triggers debug mode.
	* In debug mode, the extension uses uncompressed versions of scripts rather than the bandwidth-saving minified versions.
	*/
	define('SIGPLUS_DEBUG', false);
}
if (!defined('SIGPLUS_LOGGING')) {
	/**
	* Triggers logging mode.
	* In logging mode, the extension prints verbose status messages to the output.
	*/
	define('SIGPLUS_LOGGING', false);
}

// import library dependencies
jimport('joomla.event.plugin');

require_once dirname(__FILE__).DS.'core'.DS.'core.php';

/**
* sigplus Image Gallery Plus plug-in.
*/
class plgContentSIGPlus extends JPlugin {
	/** Activation tag used to invoke the plug-in. */
	private $activationtag = 'gallery';
	/** Core service object. */
	private $core;

	function __construct(&$subject, $params) {
		parent::__construct($subject, $params);

		// set activation tag if well-formed
		$activationtag = $this->params->get('activationtag', $this->activationtag);
		if (is_string($activationtag) && ctype_alnum($activationtag)) {
			$this->activationtag = $activationtag;
		}
	}

	/**
	* Fired before article contents are to be processed by the plug-in.
	* @param $article The article that is being rendered by the view.
	* @param $params An associative array of relevant parameters.
	* @param $limitstart An integer that determines the "page" of the content that is to be generated.
	* @param
	*/
	function onContentAfterTitle($context, &$article, &$params, $limitstart) {

	}

	/**
	* Fired immediately after content has been saved.
	* @param {string} $context The context of the content passed to the plug-in.
	* @param $article The content object.
	* @param {bool} $isNew True if the content has just been created.
	*/
	public function onContentAfterSave($context, &$article, $isNew) {
		$this->parseContent($article);  // replacements will not be saved
		return true;
	}

	/**
	* Fired when content are to be processed by the plug-in.
	* Recommended usage syntax:
	* a) POSIX fully portable file names
	*    Folder name characters are in [A-Za-z0-9._-])
	*    Regular expression: [/\w.-]+
	*    Example: {gallery rows=1 cols=1}  /sigplus/birds/  {/gallery}
	* b) URL-encoded absolute URLs
	*    Regular expression: (?:[0-9A-Za-z!"$&\'()*+,.:;=@_-]|%[0-9A-Za-z]{2})+
	*    Example: {gallery} http://example.com/image.jpg {/gallery}
	*/
	public function onContentPrepare($context, &$article, &$params, $limitstart) {
		$this->parseContent($article);  // replacements take effect
	}

	private function parseContent(&$article) {
		if (strpos($article->text, '{'.$this->activationtag) === false) {
			return false;  // short-circuit plugin activation, no replacements made
		}

		// load language file for internationalized labels and error messages
		$lang = JFactory::getLanguage();
		$lang->load('plg_content_sigplus', JPATH_ADMINISTRATOR);

		if (!isset($this->core)) {
			$this->core = false;
			try {
				// create configuration parameter objects
				$configuration = new SIGPlusConfigurationParameters();
				$configuration->service = new SIGPlusServiceParameters();
				$configuration->service->setParameters($this->params);
				$configuration->gallery = new SIGPlusGalleryParameters();
				$configuration->gallery->setParameters($this->params);

				if (SIGPLUS_LOGGING || $configuration->service->debug_server) {
					SIGPlusLogging::setService(new SIGPlusHTMLLogging());
				} else {
					SIGPlusLogging::setService(new SIGPlusNoLogging());
				}

				$this->core = new SIGPlusCore($configuration);
			} catch (Exception $e) {
				$app = JFactory::getApplication();
				$app->enqueueMessage($e->getMessage(), 'error');
			}
		}

		if ($this->core !== false) {
			if (SIGPLUS_LOGGING) {
				SIGPlusLogging::appendStatus(JText::_('SIGPLUS_STATUS_LOGGING'));
			}

			// find gallery tags and emit code
			$activationtag = preg_quote($this->activationtag, '#');
			$count = 0;
			$pattern = '#\{'.$activationtag.'([^{}]*)(?<!/)\}\s*((?:[^{]+|\{(?!/'.$activationtag.'))+)\s*\{/'.$activationtag.'\}#msSu';
			//$pattern = '#\{'.$activationtag.'([^{}]*)(?<!/)\}(.+?)\{/'.$activationtag.'\}#msSu';

			//$article->text = preg_replace_callback($pattern, array($this, 'getGalleryPlaceholderReplacement'), $article->text, 1, $count);
			$offset = 0;
			while (preg_match($pattern, $article->text, $match, PREG_OFFSET_CAPTURE, $offset)) {
				$start = $match[0][1];
				$end = $start + strlen($match[0][0]);

				try {
					$body = $this->getGalleryReplacement($match[2][0], $match[1][0]);
					$article->text = substr($article->text, 0, $start).$body.substr($article->text, $end);
					$offset = $start + strlen($body);
				} catch (Exception $e) {
					$app = JFactory::getApplication();
					$app->enqueueMessage($e->getMessage(), 'error');
					$offset = $end;
				}
			}

			// employ safety measure for excessively large galleries
			if (strlen($article->text) > 80000) {  // there is a risk of exhausting the backtrack limit and producing the "white screen of death"
				ini_set('pcre.backtrack_limit', 1000000);  // try to raise backtrack limit
				SIGPlusLogging::appendStatus('Generated HTML code is excessively large, consider splitting galleries. Regular expression matching backtrack limit has been increased.');
			}

			$log = SIGPlusLogging::fetch();
			if ($log) {
				$article->text = $log.$article->text;
			}

			return $count > 0;
		}
		return false;
	}

	private function getGalleryReplacement($source, $params) {
		$imagereference = html_entity_decode($source, ENT_QUOTES, 'utf-8');
		if (is_url_http($imagereference)) {
			$imagereference = safeurlencode($imagereference);
		}

		// the activation code {gallery key=value}myfolder{/gallery} translates into a source and a parameter string
		$this->core->setParameterString(html_entity_decode($params, ENT_QUOTES, 'utf-8'));

		try {
			if (is_absolute_path($imagereference)) {  // do not permit an absolute path enclosed in activation tags
				throw new SIGPlusImageSourceException($imagereference);
			}

			// download image
			$imageid = JRequest::getInt('sigplus', 0);
			if ($imageid > 0 && $this->core->downloadImage($imagereference, $imageid)) {  // an image has been requested for download
				jexit();  // do not produce a page
			}

			// generate image gallery
			$body = $this->core->getGalleryHTML($imagereference, $id);
			$this->core->addStyles($id);
			$this->core->addScripts($id);

			$this->core->resetParameters();
			return $body;
		} catch (Exception $e) {
			$this->core->resetParameters();
			throw $e;
		}
	}

	/**
	* Generates image thumbnails with alternate text, title and lightbox pop-up activation on mouse click.
	* This method is to be called as a regular expression replace callback.
	* Any error messages are printed to screen.
	* @param $match A regular expression match.
	*/
	private function getGalleryPlaceholderReplacement($match) {
		try {
			$body = $this->getGalleryReplacement($match[2], $match[1]);
		} catch (Exception $e) {
			$body = $match[0];  // no replacements
			$app = JFactory::getApplication();
			$app->enqueueMessage($e->getMessage(), 'error');
		}
		return $body;
	}
}