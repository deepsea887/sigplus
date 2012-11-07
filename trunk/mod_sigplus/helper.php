<?php
/**
* @file
* @brief    sigplus Image Gallery Plus module for Joomla
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2010 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus module for Joomla
* Copyright 2009-2010 Levente Hunyadi
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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

if (!defined('SIGPLUS_VERSION_MODULE')) {
	define('SIGPLUS_VERSION_MODULE', '$__VERSION__$');
}

/**
* Triggered when a mandatory dependency is missing or there is a version mismatch.
*/
class SIGPlusModuleDependencyException extends Exception {
	protected $version_module;
	protected $version_plugin;

	/**
	* Creates a new exception instance.
	* @param {string} $key Error message language key.
	*/
    public function __construct($key, $version = null) {
		$this->version_module = SIGPLUS_VERSION_MODULE;
		$this->version_plugin = isset($version) ? $version : 'SIGPLUS_UNKNOWN';

		$message = '['.$key.'] '.JText::_($key);  // get localized message text
		$search = array();
		$replace = array();
		foreach (get_object_vars($this) as $property => $value) {
			$search[] = '{$'.$property.'}';  // replace placeholders in message text
			$text = (string) $this->$property;
			if (preg_match('/^[A-Z][0-9A-Z_]*$/', $text)) {  // could be a language key
				$text = JText::_($text);
			}
			$replace[] = htmlspecialchars($text);
		}
		$message = str_replace($search, $replace, $message);
		parent::__construct($message);
    }
}

class SIGPlusModuleHelper {
	private static $core;

	/**
	* Imports module dependencies.
	*/
	public static function import() {
		if (isset(self::$core)) {
			return self::$core;
		}

		self::$core = false;

		// load sigplus content plug-in
		if (!JPluginHelper::importPlugin('content', 'sigplus')) {
			throw new SIGPlusModuleDependencyException('SIGPLUS_EXCEPTION_DEPENDENCY_MISSING');
		}

		if (!defined('SIGPLUS_VERSION')) {
			throw new SIGPlusModuleDependencyException('SIGPLUS_EXCEPTION_DEPENDENCY_MISMATCH');
		}

		if (SIGPLUS_VERSION_MODULE !== '$__'.'VERSION'.'__$' && SIGPLUS_VERSION_MODULE !== SIGPLUS_VERSION) {
			throw new SIGPlusModuleDependencyException('SIGPLUS_EXCEPTION_DEPENDENCY_MISMATCH', SIGPLUS_VERSION);
		}

		// load sigplus content plug-in parameters
		$plugin = JPluginHelper::getPlugin('content', 'sigplus');
		$params = json_decode($plugin->params);

		// create configuration parameter objects
		$configuration = new SIGPlusConfigurationParameters();
		$configuration->service = new SIGPlusServiceParameters();
		$configuration->service->setParameters($params);
		$configuration->gallery = new SIGPlusGalleryParameters();
		$configuration->gallery->setParameters($params);

		if (SIGPLUS_LOGGING || $configuration->service->debug_server) {
			SIGPlusLogging::setService(new SIGPlusHTMLLogging());
		} else {
			SIGPlusLogging::setService(new SIGPlusNoLogging());
		}

		self::$core = new SIGPlusCore($configuration);

		return self::$core;
	}
}
