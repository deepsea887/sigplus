<?php
/**
* @file
* @brief    sigplus Image Gallery Plus javascript engine service classes
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

/**
* Service class for JavaScript code management.
*/
class SIGPlusEngineServices {
	/** True if one of the engines uses the MooTools library. */
	private $mootools = false;
	/** True if one of the engines uses the jQuery library. */
	private $jquery = false;
	/** JavaScript snippets to run on HTML DOM ready event. */
	private $scripts = array();

	/** Engine directory. */
	private $engines = array();

	/** Content delivery network to use on a site that is publicly available (i.e. not an intranet network), 'none' or 'local'. */
	public $jsapi = 'default';
	/** Whether to use uncompressed versions of scripts. */
	public $debug = null;  // true = enabled, false = disabled, null = not set (disabled)

	/** Singleton instance. */
	private static $object = null;

	public static function initialize() {
		if (!isset(self::$object)) {
			self::$object = new SIGPlusEngineServices();
		}
	}

	public static function instance() {
		return self::$object;
	}

	/**
	* Adds MooTools support.
	*/
	public function addMooTools() {
		if ($this->mootools) {
			return;
		}
		switch ($this->jsapi) {
			case 'none':
				break;
			default:
				JHTML::_('behavior.framework');  // MooTools is native to Joomla, modify Joomla if you wish to load it from a CDN
		}
		$this->mootools = true;
	}

	/**
	* Adds jQuery support.
	*/
	public function addJQuery() {
		if ($this->jquery) {
			return;
		}

		$document = JFactory::getDocument();
		$uri = JFactory::getURI();
		$scheme = $uri->isSSL() ? 'https://' : 'http://';  // check for HTTPS

		switch ($this->jsapi) {
			case 'none':  // do not load jQuery, recommended when you have another extension (e.g. a system plug-in) that loads jQuery
				break;
			case 'local':  // use local copy of jQuery, recommended only for intranet sites
				if ($this->debug) {
					$document->addScript(JURI::base(true).'/media/sigplus/js/jquery.js');
				} else {
					$document->addScript(JURI::base(true).'/media/sigplus/js/jquery.min.js');
				}
				break;
			case 'cdn-google':  // use jQuery from Google AJAX library
				if ($this->debug) {
					$document->addScript($scheme.'ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.js');
				} else {
					$document->addScript($scheme.'ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js');
				}
				break;
			case 'cdn-microsoft':  // use jQuery from Microsoft Ajax Content Delivery Network
				if ($this->debug) {
					$document->addScript($scheme.'ajax.microsoft.com/ajax/jQuery/jquery-1.4.4.js');
				} else {
					$document->addScript($scheme.'ajax.microsoft.com/ajax/jQuery/jquery-1.4.4.min.js');
				}
				break;
			case 'cdn':
			case 'cdn-jquery':
				if ($this->debug) {
					$document->addScript('http://code.jquery.com/jquery-1.4.4.js');
				} else {
					$document->addScript('http://code.jquery.com/jquery-1.4.4.min.js');
				}
				break;
			default:  // use jQuery from Google AJAX library with on-demand inclusion
				$document->addScript($scheme.'www.google.com/jsapi');
				if ($this->debug) {
					$document->addScript(JURI::base(true).'/media/sigplus/js/jquery.jsapi.debug.js');
				} else {
					$document->addScript(JURI::base(true).'/media/sigplus/js/jquery.jsapi.min.js');
				}
		}
		if ($this->jsapi != 'none') {
			$document->addScript(JURI::base(true).'/media/sigplus/js/jquery.noconflict.js');
		}
		$this->jquery = true;
	}

	/**
	* Fetch an engine from the engine registry, adding a new instance if necessary.
	* @param {string} $enginetype Engine type (e.g. "lightbox" or "rotator").
	* @param {string} $engine A unique name used to instantiate the engine.
	*/
	private function getEngine($enginetype, $engine) {
		if (isset($this->engines[$engine])) {
			return $this->engines[$engine];
		} else {
			return $engines[$engine] = SIGPlusEngine::create($enginetype, $engine);
		}
	}

	public function getLightboxEngine($lightboxengine) {
		return $this->getEngine('lightbox', $lightboxengine);
	}

	public function getRotatorEngine($rotatorengine) {
		return $this->getEngine('rotator', $rotatorengine);
	}

	public function getCaptionEngine($captionengine) {
		return $this->getEngine('caption', $captionengine);
	}

	public function addCustomTag($tag) {
		/** Custom tags added to page header. */
		static $customtags = array();

		if (!in_array($tag, $customtags)) {
			$document = JFactory::getDocument();
			$document->addCustomTag($tag);
			$customtags[] = $tag;
		}
	}

	public function getResourceRelativePath($relpath) {
		$basename = pathinfo($relpath, PATHINFO_BASENAME);  // e.g. "sigplus.css"
		$folder = pathinfo($relpath, PATHINFO_DIRNAME);  // e.g. "/plugins/content/sigplus/css"
		$p = strrpos($basename, '.');  // search from backwards
		if ($p !== false) {
			$filename = substr($basename, 0, $p);  // drop extension from filename
			$extension = substr($basename, $p);
		} else {
			$filename = $basename;
			$extension = '';
		}

		$path = JPATH_ROOT.str_replace('/', DS, $relpath);
		$dir = pathinfo($path, PATHINFO_DIRNAME);
		$original = $dir.DS.$basename;
		$minified = $dir.DS.$filename.'.min'.$extension;
		if (!$this->debug && (!file_exists($original) || file_exists($minified) && filemtime($minified) >= filemtime($original))) {
			return $folder.'/'.$filename.'.min'.$extension;
		} else {
			return $relpath;
		}
	}

	/**
	* Returns the minified version of a style or script file if available.
	*/
	public function getResourceURL($relpath) {
		return JURI::base(true).$this->getResourceRelativePath($relpath);
	}

	/**
	* Adds standard stylesheet references to the HTML head.
	*/
	public function addStandardStyles() {
		$document = JFactory::getDocument();
		$document->addStyleSheet($this->getResourceURL('/media/sigplus/css/sigplus.css'));
		$this->addConditionalStylesheet('/media/sigplus/css/sigplus.ie7.css', 8);
	}

	/**
	* Adds a stylesheet reference to the HTML head.
	*/
	public function addStylesheet($path, $attrs = null) {
		$url = $this->getResourceURL($path);
		$document = JFactory::getDocument();
		if (isset($attrs)) {
			$document->addStyleSheet($url, 'text/css', null, $attrs);
		} else {
			$document->addStyleSheet($url);
		}
	}

	public function addConditionalStylesheet($path, $version = 9, array $attrs = array()) {
		$attrstring = '';
		foreach ($attrs as $key => $value) {
			$attrstring .= ' '.$key.'="'.htmlspecialchars($value).'"';
		}
		$this->addCustomTag('<!--[if lt IE '.$version.']><link rel="stylesheet" href="'.$this->getResourceURL($path).'" type="text/css"'.$attrstring.' /><![endif]-->');
	}

	public function addStyles($selectors) {
		$css = '';
		foreach ($selectors as $selector => $rules) {
			if (!empty($rules)) {
				$css .= $selector." {\n";
				foreach ($rules as $name => $value) {
					$css .= $name.':'.$value.";\n";
				}
				$css .= "}\n";
			}
		}

		if (!empty($css)) {
			$document = JFactory::getDocument();
			$document->addStyleDeclaration($css);
		}
	}

	/**
	* Adds a script reference to the HTML head.
	*/
	public function addScript($path) {
		$document = JFactory::getDocument();
		$document->addScript($this->getResourceURL($path));
	}

	/**
	* Appends a JavaScript snippet to the code to be run on the HTML DOM ready event.
	* This method is usually invoked by engines.
	*/
	public function addOnReadyScript($script) {
		$this->scripts[] = $script;
	}

	/**
	* Appends the contents of a JavaScript file to the code to be run on the HTML DOM ready event.
	*/
	public function addOnReadyScriptFile($path, array $map = array()) {
		$path = str_replace('/', DS, $this->getResourceRelativePath($path));
		if ($contents = file_get_contents(JPATH_BASE.DS.$path)) {
			$searchmap = array();
			foreach ($map as $key => $value) {
				$searchmap['{$__'.$key.'__$}'] = addslashes($value);
				$searchmap['$__'.$key.'__$'] = '"'.addslashes($value).'"';  // wrap into string
			}
			$this->addOnReadyScript(str_replace(array_keys($searchmap), array_values($searchmap), $contents));
		}
	}

	/**
	* Adds all HTML DOM ready event scripts to the page in an HTML script block.
	* This method is usually invoked in the HTML content generation phase.
	*/
	public function addOnReadyEvent() {
		if (!empty($this->scripts)) {
			// build script block to execute when DOM is loaded
			$onready = '// <!--'."\n";
			if ($this->jquery) {  // jQuery
				$onready .= 'jQuery(document).ready(';
			} else {  // MooTools
				$onready .= 'window.addEvent("domready",';
			}
			$onready .= 'function () {'."\n".implode("\n", $this->scripts)."\n".'});';
			$onready .= "\n".'// -->';

			// add script block to page
			$document = JFactory::getDocument();
			$document->addScriptDeclaration($onready);

			// clear "on ready" event scripts
			$this->scripts = array();  // clear scripts added to document
		}
	}
	
	/**
	* Saves the JavaScript variable "lightbox" in the current scope to the elements storage.
	* @see self::activateLightbox
	*/
	public function storeLightbox($selector) {
		$this->addMooTools();
		$script = 'document.id(document.body).store("'.$selector.'", lightbox);';  // assign to variable and persist in elements storage
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
	}
	
	/**
	* Adds JavaScript code subscribed to an anchor click event to programmatically activate a gallery.
	* @param {int} $index The index of the image within the gallery to show.
	* @see self::storeLightbox
	*/
	public function activateLightbox($linkid, $selector, $index = 0) {
		$this->addMooTools();
		$script =
			'document.id("'.$linkid.'").addEvent("click", function () {'.PHP_EOL.
			'	var lightbox;'.PHP_EOL.
			'	if (lightbox = document.id(document.body).retrieve("'.$selector.'")) {'.PHP_EOL.
			'		lightbox.show && lightbox.show(document.getElement("'.$selector.':nth('.$index.')"));'.PHP_EOL.
			'	}'.PHP_EOL.
			'});';
		$instance = SIGPlusEngineServices::instance();
		$instance->addOnReadyScript($script);
		return false;
	}
}
SIGPlusEngineServices::initialize();

/**
* Base class for engines based on a javascript framework.
*/
abstract class SIGPlusEngine {
	abstract public function getIdentifier();
	abstract public function getLibrary();

	/**
	* Adds style sheet references to the HTML @c head element.
	*/
	public function addStyles($selector, SIGPlusGalleryParameters $params) {
		$instance = SIGPlusEngineServices::instance();
		$instance->addStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.css');

		// add right-to-left reading order stylesheet (if available)
		$language = JFactory::getLanguage();
		if ($language->isRTL() && file_exists(JPATH_ROOT.DS.'media'.DS.'sigplus'.DS.'engines'.DS.$this->getIdentifier().DS.'css'.$this->getIdentifier().'.rtl.css')) {
			$instance->addStylesheet('/media/sigplus/engines/'.$this->getIdentifier().'/css/'.$this->getIdentifier().'.rtl.css');
		}
	}

	/**
	* Adds script references to the HTML @c head element.
	* @param {string} $selector A CSS selector.
	* @param $params Gallery parameters.
	*/
	public function addScripts($selector, SIGPlusGalleryParameters $params) {
		$instance = SIGPlusEngineServices::instance();

		// add script library dependency
		switch ($this->getLibrary()) {
			case 'mootools': $instance->addMooTools(); break;
			case 'jquery':   $instance->addJQuery();   break;
		}

		$instance->addScript('/media/sigplus/engines/'.$this->getIdentifier().'/js/'.$this->getIdentifier().'.js');
	}

	/**
	* Factory method for engine instantiation.
	*/
	public static function create($enginetype, $engine) {
		// check for parameters passed to engine
		$pos = strpos($engine, '/');
		if ($pos !== false) {
			$params = array('theme'=>substr($engine, $pos+1));
			$engine = substr($engine, 0, $pos);
		} else {
			$params = array();
		}

		if (!ctype_alnum($engine)) {  // simple name required
			throw new SIGPlusEngineUnavailableException($engine, $enginetype);  // naming failure
		}

		$engineclass = 'SIGPlus'.$engine.$enginetype.'Engine';
		$enginedir = dirname(__FILE__).DS.'..'.DS.'engines';
		if (is_file($enginefile = $enginedir.DS.$enginetype.DS.$engine.'.php') || is_file($enginefile = $enginedir.DS.$enginetype.DS.$engine.'.php')) {
			require_once $enginefile;
		}
		if (class_exists($engineclass)) {
			return new $engineclass($params);
		} else {
			throw new SIGPlusEngineUnavailableException($engine, $enginetype);  // inclusion failure
		}
	}
}

/**
* Base class for pop-up window (lightbox-clone) support.
*/
abstract class SIGPlusLightboxEngine extends SIGPlusEngine {
	/**
	* A default constructor that ignores all optional arguments.
	*/
	public function __construct($params = false) { }

	/**
	* Whether the pop-up window supports displaying arbitrary HTML content.
	* @return True if the pop-up window is not restricted to displaying images only.
	* @deprecated
	*/
	public function isInlineContentSupported() {
		return false;
	}

	/**
	* Whether the pop-up window supports fast navigation by displaying a ribbon of thumbnails
	* the user can click and jump to a particular image.
	* @deprecated
	*/
	public function isQuickNavigationSupported() {
		return false;
	}

	/**
	* Adds script references to the HTML head to bind the click event to lightbox pop-up activation.
	* @deprecated
	*/
	public function addInitializationScripts($selector, $params) {
		$this->addScripts($selector, $params);
		$instance = SIGPlusEngineServices::instance();
		$instance->addScript('/plugins/content/sigplus/engines/'.$this->getIdentifier().'/js/initialization.js');
	}

	/**
	* Adds script references to the HTML head to support fully customized gallery initialization.
	* @remark When overriding this method, the base method should normally NOT be called.
	* @deprecated
	*/
	public function addActivationScripts($selector, $params) {
		$this->addScripts($selector, $params);
		$instance = SIGPlusEngineServices::instance();
		$instance->addScript('/plugins/content/sigplus/engines/'.$this->getIdentifier().'/js/activation.js');
	}

	/**
	* The value to use in the attribute "rel" of anchor elements to bind the lightbox-clone.
	* @param gallery The unique identifier for the image gallery. Images in the same gallery are grouped together.
	* @return A valid value for the attribute "rel" of an element "a".
	* @deprecated
	*/
	public function getLinkAttribute($gallery = false) {
		if ($gallery !== false) {
			return $this->getIdentifier().'-'.$gallery;
		} else {
			return $this->getIdentifier();
		}
	}
}

/**
* Base class for image rotator support.
*/
abstract class SIGPlusRotatorEngine extends SIGPlusEngine {

}

/**
* Base class for image caption support.
*/
abstract class SIGPlusCaptionEngine extends SIGPlusEngine {

}