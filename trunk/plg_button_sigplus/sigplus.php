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

jimport('joomla.plugin.plugin');
jimport('joomla.form.form');
jimport('joomla.html.parameter');

/**
* Triggered when the sigplus content plug-in is unavailable or there is a version mismatch.
*/
class SIGPlusEditorDependencyException extends Exception {
	/**
	* Creates a new exception instance.
	* @param {string} $key Error message language key.
	*/
    public function __construct() {
		$key = 'SIGPLUS_EXCEPTION_EXTENSION';
		$message = '['.$key.'] '.JText::_($key);  // get localized message text
		parent::__construct($message);
    }
}

/**
* Editor button for sigplus.
*/
class plgButtonSIGPlus extends JPlugin {
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	* Displays the editor button.
	*/
	public function onDisplay($editorname) {
		try {
			// load sigplus content plug-in
			if (!JPluginHelper::importPlugin('content', 'sigplus')) {
				throw new SIGPlusEditorDependencyException();
			}
			$plugin = JPluginHelper::getPlugin('content', 'sigplus');
			$params = new JParameter($plugin->params);

			// load language file for internationalized labels
			$lang = JFactory::getLanguage();
			$lang->load('plg_content_sigplus', JPATH_ADMINISTRATOR);

			$xmlfile = JPATH_ROOT.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'sigplus.xml';
			$htmlfile = JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'plg_button_sigplus'.DIRECTORY_SEPARATOR.'button.'.$lang->getTag().'.html';
			
			// check for existence of content plug-in XML configuration file
			if (!file_exists($xmlfile)) {
				throw new SIGPlusAccessException($xmlfile);
			}
			
			// regenerate dialog form if content plug-in has been upgraded
			if (!file_exists($htmlfile) || !(filemtime($htmlfile) >= filemtime($xmlfile))) {
				// load configuration XML file
				$form = new JForm('sigplus');
				$form->loadFile($xmlfile, true, '/extension/config/fields');
				$fieldSets = $form->getFieldsets('params');

				// get permissible gallery parameters
				$vars = get_class_vars('SIGPlusGalleryParameters');
				
				// get full path to MooTools
				$mootools = JURI::root(true).'/media/system/js/mootools-core.js';

				ob_start();
				print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
				print '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$lang->getTag().'" lang="'.$lang->getTag().'">';
				print '<head>';
				print '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
				print '<link rel="stylesheet" href="css/button.css" type="text/css" />';
				print '<script type="text/javascript" src="'.$mootools.'"></script>';
				print '<script type="text/javascript" src="js/button.js"></script>';
				print '</head>';
				print '<body>';
				print '<form id="sigplus-settings-form">';
				print '<button id="sigplus-settings-submit" type="button">'.JText::_('JSUBMIT').'</button>';
				foreach ($fieldSets as $name => $fieldSet) {
					$fields = $form->getFieldset($name);

					$hasfields = false;
					foreach ($fields as $field) {
						if (isset($vars[$field->fieldname])) {
							$hasfields = true;
							break;
						}
					}
					if (!$hasfields) {
						continue;
					}

					// field group title
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_PLUGINS_'.$name.'_FIELDSET_LABEL';
					print '<h3>'.JText::_($label).'</h3>';
					if (isset($fieldSet->description) && trim($fieldSet->description)) {
						print '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					}

					// field group elements
					print '<fieldset class="panelform">';
					$hidden_fields = '';
					print '<ul>';
					foreach ($fields as $field) {
						if (!isset($vars[$field->fieldname])) {
							continue;
						}
						if (!$field->hidden) {
							print '<li class="formelm">';
							print $field->label;
							print $field->input;
							print '</li>';
						} else {
							$hidden_fields.= $field->input;
						}
					}
					print '</ul>';
					print $hidden_fields;
					print '</fieldset>';
				}
				print '</form>';
				print '<p>'.JText::_('SIGPLUS_EDITORBUTTON_DOCUMENTATION').'</p>';
				print '</body>';
				print '</html>';
				$html = ob_get_clean();
				if (file_put_contents($htmlfile, $html) === false) {
					throw new SIGPlusAccessException($htmlfile);
				}
			}

			// add javascript declaration
			$doc = JFactory::getDocument();
			$activationtag = $params->get('activationtag', 'gallery');
			$doc->addScriptDeclaration('function sigplusOnInsertTag(params) { SqueezeBox.close(); jInsertEditorText("{'.$activationtag.'" + params + "}myfolder{/'.$activationtag.'}", "'.$editorname.'"); }');

			// add modal window
			JHTML::_('behavior.modal');
			$button = new JObject;
			$button->set('modal', true);
			//$button->set('link', '#sigplus-settings-form');
			$app = JFactory::getApplication();
			if ($app->isAdmin()) {
				// Joomla expects a relative path, leave site folder "administrator"
				$button->set('link', '../media/plg_button_sigplus/button.'.$lang->getTag().'.html');
			} else {
				$button->set('link', 'media/plg_button_sigplus/button.'.$lang->getTag().'.html');
			}
			$button->set('text', 'sigplus');
			$button->set('name', 'image');
			//$button->set('options', "{handler: 'adopt', size: {x: 500, y: 400}, onClose: function () { document.id('sigplus-settings-container').adopt('sigplus-settings-form'); }}");
			$button->set('options', "{handler: 'iframe', size: {x: 500, y: 400}}");
			return $button;
		} catch (Exception $e) {
			$app = JFactory::getApplication();
			$app->enqueueMessage($e->getMessage(), 'error');
		}

		return null;
	}
}