<?php
/**
* @file
* @brief    sigplus Image Gallery Plus module for Joomla
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus module for Joomla
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
defined('_JEXEC') or die('Restricted access');

if (!defined('SIGPLUS_DEBUG')) {
	// Triggers debug mode. Debug uses uncompressed version of scripts rather than the bandwidth-saving minified versions.
	define('SIGPLUS_DEBUG', false);
}
if (!defined('SIGPLUS_LOGGING')) {
	// Triggers logging mode. Verbose status messages are printed to the output.
	define('SIGPLUS_LOGGING', false);
}

// include the helper file
require_once dirname(__FILE__).DS.'helper.php';

$galleryHTML = false;

try {
	// import dependencies
	if (($core = SIGPlusModuleHelper::import()) !== false) {
		$core->setParameterObject($params);  // get parameters from the module's configuration

		try {
			$imagesource = $params->get('source');
		
			// download image
			if ($core->downloadImage($imagesource)) {  // an image has been requested for download
				jexit();  // do not produce a page
			}

			// generate image gallery
			$galleryHTML = $core->getGalleryHTML($imagesource, $id);
			$core->addStyles($id);
			$core->addScripts($id);

			$core->resetParameters();
		} catch (Exception $e) {
			$core->resetParameters();
			throw $e;
		}
	}  // an error message has already been printed by another module instance
} catch (Exception $e) {
	$app = JFactory::getApplication();
	$app->enqueueMessage($e->getMessage(), 'error');
	$galleryHTML = $e->getMessage();
}

print $galleryHTML;