/**
* @file
* @brief    sigplus Image Gallery Plus settings export/import control
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2012 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

var SettingsExporter = new Class({
	'initialize': function (textarea, exportbtn, importbtn) {
		// get parent HTML form
		var form = textarea.getParents('form').pick();
		if (form) {
			function _getUserControls(items) {
				var selector = items.map(function (item) {
					return item + '[name^="jform[params]"]';  // settings have attribute "name" set to "jform[param][...]"
				}).join(',');
				return form.getElements(selector);
			}

			function _getUserControlKey(elem) {
				return elem.get('name').match(/^jform\[params\]\[(\w+)]$/)[1];  // settings have attribute "name" set to "jform[param][...]"
			}

			// register click event for export (save) function
			exportbtn.addEvent('click', function () {
				// traverse elements that store settings
				var str = _getUserControls(['input[type=text]','input[type=radio]','select','textarea']).map(function (elem) {
					if (elem.type != 'radio' || elem.checked) {  // omit radio buttons that are not checked
						// a single JSON object key/value pair
						return JSON.encode(_getUserControlKey(elem)) + ':' + JSON.encode(elem.get('value'));
					}
				}).clean().join(',\n');  // use line breaks for pretty output

				// show generated JSON object string in text area
				textarea.value = '{\n' + str + '\n}';
			});

			// register click event for import (restore) function
			importbtn.addEvent('click', function () {
				// decode JSON object string into parameter object
				var params = JSON.decode(textarea.value, true);
				if (params) {
					// traverse elements that store settings
					_getUserControls(['input[type=text]','select','textarea']).each(function (elem) {
						// update text field value or selected element
						elem.value = [params[_getUserControlKey(elem)], elem.value].pick();
					});
					_getUserControls(['input[type=radio]']).each(function (elem) {
						// check radio button if its value matches the value stored in the parameter object
						if (elem.value == params[_getUserControlKey(elem)]) {
							elem.checked = true;
						}
					});
				}
			});
		}
	}
});