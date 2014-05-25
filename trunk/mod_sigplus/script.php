<?php
/**
* @file
* @brief    sigplus Image Gallery Plus installer script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus module for Joomla
* Copyright 2009-2014 Levente Hunyadi
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

if (!defined('SIGPLUS_PLUGIN_FOLDER')) {
	define('SIGPLUS_PLUGIN_FOLDER', 'sigplus');
}
if (!defined('SIGPLUS_MEDIA_FOLDER')) {
	define('SIGPLUS_MEDIA_FOLDER', 'sigplus');
}

class mod_SigPlusNovoInstallerScript {
	function __construct($parent) { }

	function install($parent) { }

	function uninstall($parent) { }

	function update($parent) { }

	function preflight($type, $parent) { }

	function postflight($type, $parent) {
		// copy language file
		$pluginlang = JPATH_ROOT.DIRECTORY_SEPARATOR.'administrator'.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR.'en-GB'.DIRECTORY_SEPARATOR.'en-GB.plg_content_'.SIGPLUS_PLUGIN_FOLDER.'.ini';
		$modulelang = JPATH_ROOT.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR.'en-GB'.DIRECTORY_SEPARATOR.'en-GB.mod_'.SIGPLUS_PLUGIN_FOLDER.'.ini';
		if (($data = file_get_contents($pluginlang)) !== false && ($handle = fopen($modulelang, 'a')) !== false) {
			fwrite($handle, "\n\n");
			fwrite($handle, $data);
			fclose($handle);
		}

		// copy back-end controls
		$sourcepath = JPATH_ROOT.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.SIGPLUS_PLUGIN_FOLDER.DIRECTORY_SEPARATOR.'fields';
		$targetpath = JPATH_ROOT.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'mod_'.SIGPLUS_PLUGIN_FOLDER.DIRECTORY_SEPARATOR.'fields';
		$fieldfiles = scandir($sourcepath);
		foreach ($fieldfiles as $fieldfile) {
			if (pathinfo($sourcepath.DIRECTORY_SEPARATOR.$fieldfile, PATHINFO_EXTENSION) == 'php') {
				@copy($sourcepath.DIRECTORY_SEPARATOR.$fieldfile, $targetpath.DIRECTORY_SEPARATOR.$fieldfile);
			}
		}
	}
}

class mod_SIGPlusInstallerScript extends mod_SigPlusNovoInstallerScript {

}
