<?php
/**
* @file
* @brief    sigplus Image Gallery Plus color selection control
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

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

/**
* Renders a control for choosing CSS border parameters.
* This class implements a user-defined control in the administration backend.
*/
class JFormFieldColor extends JFormField {
	protected $type = 'Color';

	public function getInput() {
		$class = ( isset($this->element['class']) ? (string)$this->element['class'] : 'inputbox' );
		$ctrlid = str_replace(array('][','[',']'), array('_','_',''), $this->name);
		$html = '<input type="text" class="'. $class .'" name="'. $this->name .'" id="'. $ctrlid .'" value="'. $this->value .'" />';
		
		$scriptpath = JPATH_ROOT.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'fields';
		if (file_exists($scriptpath.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'colorplus.min.js') || file_exists($scriptpath.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'colorplus.js')) {
			$buttonid = $ctrlid . '-button';

			// add script declaration to header
			JHTML::_('behavior.framework', true);  // include MooTools Core and MooTools More
			$document = JFactory::getDocument();
			$document->addStylesheet(JURI::root(true).'/plugins/content/sigplus/fields/css/colorplus.css');
			if (file_exists($scriptpath.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'colorplus.min.js') && filemtime($scriptpath.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'colorplus.min.js') >= filemtime($scriptpath.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'colorplus.js')) {
				$document->addScript(JURI::root(true).'/plugins/content/sigplus/fields/js/colorplus.min.js');
			} else {
				$document->addScript(JURI::root(true).'/plugins/content/sigplus/fields/js/colorplus.js');
			}
			$document->addScriptDeclaration('window.addEvent("domready", function () { colorplus.bind(document.id("'.$ctrlid.'"), document.id("'.$buttonid.'")); });');
			
			$html .= '<button type="button" id="'. $buttonid .'">'.JText::_('SIGPLUS_CHOOSE').'</button>';
		}

		// add control to page
		return $html;
	}
}