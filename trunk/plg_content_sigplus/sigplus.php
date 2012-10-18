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

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'core.php';

/**
* sigplus Image Gallery Plus plug-in.
*/
class plgContentSIGPlus extends JPlugin {
	/** Activation tag used to produce galleries with the plug-in. */
	private $tag_gallery = 'gallery';
	/** Activation tag used to produce a lightbox-powered link with the plug-in. */
	private $tag_lightbox = 'lightbox';
	/** Core service object. */
	private $core;

	function __construct(&$subject, $params) {
		parent::__construct($subject, $params);

		// set activation tag if well-formed
		$tag_gallery = $this->params->get('tag_gallery', $this->tag_gallery);
		if (is_string($tag_gallery) && ctype_alnum($tag_gallery)) {
			$this->tag_gallery = $tag_gallery;
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
		// skip plug-in activation when the content is being indexed
		if ($context === 'com_finder.indexer') {
			return;
		}

		$this->parseContent($article);  // replacements take effect
	}

	private function parseContent(&$article) {
		if (strpos($article->text, '{'.$this->tag_gallery) === false) {
			return false;  // short-circuit plugin activation, no replacements made
		}

		if (SIGPlusTimer::shortcircuit()) {
			return false;  // short-circuit plugin activation, allotted execution time expired, error message already printed
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

				if (SIGPLUS_LOGGING || $configuration->service->debug_server == 'verbose') {
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

			// find {gallery}...{/gallery} tags and emit code
			$tag_gallery = preg_quote($this->tag_gallery, '#');
			//$pattern = '#\{'.$tag_gallery.'([^{}]*)(?<!/)\}\s*((?:[^{]+|\{(?!/'.$tag_gallery.'))+)\s*\{/'.$tag_gallery.'\}#msSu';
			$pattern = '#\{'.$tag_gallery.'([^{}]*)(?<!/)\}(.+?)\{/'.$tag_gallery.'\}#msSu';
			//$article->text = preg_replace_callback($pattern, array($this, 'getGalleryReplacement'), $article->text, 1, $gallerycount);
			$gallerycount = $this->getGalleryReplacementAll($article->text, $pattern);

			// find {lightbox}...{/lightbox} tags wrapping HTML and emit code
			$tag_lightbox = preg_quote($this->tag_lightbox, '#');
			$pattern = '#\{'.$tag_lightbox.'([^{}]*)(?<!/)\}(.+?)\{/'.$tag_lightbox.'\}#msSu';
			$article->text = preg_replace_callback($pattern, array($this, 'getLightboxReplacement'), $article->text, -1, $lightboxcount);

			// find compact {lightbox/} tags and emit code
			$pattern = '#\{'.$tag_lightbox.'([^{}]*)/\}#msSu';
			$article->text = preg_replace_callback($pattern, array($this, 'getSelectorReplacement'), $article->text);

			// employ safety measure for excessively large galleries
			if (strlen($article->text) > 80000) {  // there is a risk of exhausting the backtrack limit and producing the "white screen of death"
				ini_set('pcre.backtrack_limit', 1000000);  // try to raise backtrack limit
				SIGPlusLogging::appendStatus('Generated HTML code is excessively large, consider splitting galleries. Regular expression matching backtrack limit has been increased.');
			}

			$log = SIGPlusLogging::fetch();
			if ($log) {
				$article->text = $log.$article->text;
			}

			return $gallerycount + $lightboxcount > 0;
		}
		return false;
	}

	/**
	* Replaces all occurrences of a gallery activation tag.
	*/
	private function getGalleryReplacementAll(&$text, $pattern) {
		$count = 0;
		$offset = 0;
		while (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
			if (SIGPlusTimer::shortcircuit()) {
				return $count;  // short-circuit plugin activation, allotted execution time expired, error message already printed
			}

			$count++;
			$start = $match[0][1];
			$end = $start + strlen($match[0][0]);

			try {
				$body = $this->getGalleryReplacementSingle($match[2][0], $match[1][0]);
				$text = substr($text, 0, $start).$body.substr($text, $end);
				$offset = $start + strlen($body);
			} catch (Exception $e) {
				$app = JFactory::getApplication();
				switch ($this->core->verbosityLevel()) {
					case 'laconic':
						// display a very general, uninformative message
						$message = JText::_('SIGPLUS_EXCEPTION_MESSAGE');

						// hide activation tag completely
						$text = substr($text, 0, $start).substr($text, $end);
						$offset = $start;
						break;
					case 'verbose':
					default:
						// display a specific, informative message
						$message = $e->getMessage();

						// leave activation tag as it appears
						$offset = $end;
				}
				$app->enqueueMessage($message, 'error');
			}
		}
		return $count;
	}

	/**
	* Replaces a single occurrence of a gallery activation tag.
	*/
	private function getGalleryReplacementSingle($source, $params) {
		$imagereference = html_entity_decode($source, ENT_QUOTES, 'utf-8');
		if (is_url_http($imagereference)) {
			$imagereference = safeurlencode($imagereference);
		}

		// the activation code {gallery key=value}myfolder{/gallery} translates into a source and a parameter string
		$this->core->setParameterString(self::strip_html($params));

		try {
			if (is_absolute_path($imagereference)) {  // do not permit an absolute path enclosed in activation tags
				throw new SIGPlusImageSourceException($imagereference);
			}

			// download image
			if ($this->core->downloadImage($imagereference)) {  // an image has been requested for download
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
	private function getGalleryReplacement($match) {
		try {
			$body = $this->getGalleryReplacementSingle($match[2], $match[1]);
		} catch (Exception $e) {
			$body = $match[0];  // no replacements
			$app = JFactory::getApplication();
			$app->enqueueMessage($e->getMessage(), 'error');
		}
		return $body;
	}

	/**
	* Replaces a single occurrence of a lightbox activation tag.
	*/
	private function getLightboxReplacement($match) {
		// extract parameter string
		$params = SIGPlusConfigurationBase::string_to_array(self::strip_html($match[1]));

		// extract or create identifier
		if (!isset($params['id'])) {
			$params['id'] = $this->core->getUniqueGalleryId();
		}

		if (isset($params['href']) || isset($params['link'])) {
			$this->core->setParameterArray($params);

			// build anchor components
			if (isset($params['link'])) {  // create link to gallery on the same page
				$this->core->addLightboxLinkScript($params['id'], $params['link']);
				unset($params['link']);
				$params['href'] = 'javascript:void(0);';  // artificial link target
			} elseif (isset($params['href']) && is_url_http($params['href'])) {  // create link to (external) image
				$params['href'] = safeurlencode($params['href']);

				// add lightbox scripts to page header
				$selector = '#'.$params['id'];  // build selector from the identifier of the anchor that links to a resource
				$this->core->addLightboxScripts($selector);
			}

			$this->core->resetParameters();

			// generate anchor HTML
			$anchor = '<a';
			foreach (array('id','href','rel','class','style','title') as $attr) {
				if (isset($params[$attr])) {
					$anchor .= ' '.$attr.'="'.$params[$attr].'"';
				}
			}
			$anchor .= '>'.$match[2].'</a>';
			return $anchor;
		} else {
			return $match[2];  // do not change text for unsupported combination of parameters
		}
	}

	private function getSelectorReplacement($match) {
		$replacement = $match[0];  // no replacements

		// extract parameter string
		$params = SIGPlusConfigurationBase::string_to_array(self::strip_html($match[1]));

		// apply lightbox to all items that satisfy the CSS selector
		if (isset($params['selector'])) {
			// add lightbox scripts to page header
			$this->core->setParameterArray($params);
			try {
				$this->core->addLightboxScripts($params['selector']);
				$this->core->resetParameters();
			} catch (Exception $e) {
				$this->core->resetParameters();
				throw $e;
			}
			$replacement = '';
		}

		return $replacement;
	}

	private static function strip_html($html) {
		$text = html_entity_decode($html, ENT_QUOTES, 'utf-8');  // translate HTML entities to regular characters
		$text = str_replace("\xc2\xa0", ' ', $text);  // translate non-breaking space to regular space
		return $text;
	}
}