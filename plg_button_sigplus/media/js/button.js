/**@license sigplus editor button
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2011-2012 Levente Hunyadi
 * @remarks Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
 * @see     http://hunyadi.info.hu/projects/sigplus
 **/

window.addEvent('domready', function () {
	var form = document.id('sigplus-settings-form');  // configuration settings form

	// selects all user controls
	var checkboxselector = 'input[type=checkbox]';
	var radioselector = 'input[type=radio]';
	var textselector = 'input[type=text]';
	var listselector = 'select';
	var ctrlselector = [checkboxselector,radioselector,textselector,listselector].join();
	
	// selects all user controls but omits checkboxes and radio buttons that are not checked
	var checkedselector = ':checked';
	var activectrlselector = [checkboxselector+checkedselector,radioselector+checkedselector,textselector,listselector].join();

	// extract the parameter name from a control
	function get_param_name(ctrl) {
		var name = ctrl.get('name');
		var matches = name.match(/^params\[(.*)\]$/);
		return matches ? matches[1] : name;
	}
	
	// initialize parameter values to those set on content plug-in configuration page
	if (window.parent.sigplus) {  // variable that holds configuration settings as JSON object with parameter names as keys
		form.getElements(ctrlselector).each(function (ctrl) {  // enumerate form controls in order of appearance
			var name = get_param_name(ctrl);
			var value = window.parent.sigplus[name];
			if (value) {  // has default value
				if (ctrl.match(checkboxselector)) {  // checkbox control
					ctrl.set('checked', value);
				} else if (ctrl.match(radioselector) && ctrl.value === '' + value) {  // related radio button (with value to assign matching button value)
					ctrl.set('checked', true);
				} else if (ctrl.match([textselector,listselector].join())) {  // text and list controls
					ctrl.value = value;
				}
			}
		});
	}	

	// bind event to make parameter value appear in generated activation code
	form.getElements('li').each(function (item) {
		// create marker control
		var updatebox = new Element('input', {
			'type': 'checkbox'
		});
		
		// check marker control when parameter value is to be edited
		item.getElements(ctrlselector).addEvent('focus', function () {
			updatebox.set('checked', true);
		});
		
		// insert marker control before parameter name label
		updatebox.inject(item, 'top');  // inject as first element
	});
	
	// process parameters when form is submitted
	document.id('sigplus-settings-submit').addEvent('click', function () {
		var params = '';  // activation code to insert
		form.getElements('li').each(function (item) {
			var updatebox = item.getFirst(checkboxselector);  // retrieve as first element
			var ctrl = item.getElements(activectrlselector).erase(updatebox).pick();
			if (ctrl && updatebox && updatebox.get('checked')) {  // verify whether parameter value has changed
				var name = get_param_name(ctrl);
				var value = ctrl.value;
				if (value) {  // omit missing values
					if (/color$/.test(name) || !/^(0|[1-9]\d*)$/.test(value)) {  // quote color codes but not integer values
						value = '"' + value + '"';
					}
					params += ' ' + name + '=' + value;
				}
			}
		});

		if (window.parent) {  // trigger insert event in parent window
			window.parent.sigplusOnInsertTag(params);
		}
	});
});