<?php
/**
* @file
* @brief    sigplus Image Gallery Plus installer script
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
defined( '_JEXEC' ) or die( 'Restricted access' );

class mod_sigplusInstallerScript {
	function __construct($parent) { }

	function install($parent) { }

	function uninstall($parent) { }

	function update($parent) { }

	function preflight($type, $parent) {
		/*
		if ((include_once JPATH_ROOT.DS.'plugins'.DS.'content'.DS.'sigplus'.DS.'core'.DS.'version.php') === false || SIGPLUS_VERSION !== '$__VERSION__$') {  // available since 1.5.0
			$message = 'Installing or upgrading the sigplus module requires a matching version $__VERSION__$ of the sigplus content plug-in to have been installed previously; please install or upgrade the sigplus content plug-in first.';
			$app = JFactory::getApplication();
			$app->enqueueMessage($message, 'error');

			return false;
		}
		*/
	}

	function postflight($type, $parent) {
		// copy language file
		$pluginlang = JPATH_ROOT.DS.'administrator'.DS.'language'.DS.'en-GB'.DS.'en-GB.plg_content_sigplus.ini';
		$modulelang = JPATH_ROOT.DS.'language'.DS.'en-GB'.DS.'en-GB.mod_sigplus.ini';
		if (($data = file_get_contents($pluginlang)) !== false && ($handle = fopen($modulelang, 'a')) !== false) {
			fwrite($handle, "\n\n");
			fwrite($handle, $data);
			fclose($handle);
		}

		// copy back-end controls
		$sourcepath = JPATH_ROOT.DS.'plugins'.DS.'content'.DS.'sigplus'.DS.'fields';
		$targetpath = JPATH_ROOT.DS.'modules'.DS.'mod_sigplus'.DS.'fields';
		$fieldfiles = scandir($sourcepath);
		foreach ($fieldfiles as $fieldfile) {
			if (pathinfo($sourcepath.DS.$fieldfile, PATHINFO_EXTENSION) == 'php') {
				@copy($sourcepath.DS.$fieldfile, $targetpath.DS.$fieldfile);
			}
		}
	}
}