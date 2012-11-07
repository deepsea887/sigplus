/**@license sigplus editor button
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2011 Levente Hunyadi
 * @remarks Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
 * @see     http://hunyadi.info.hu/projects/sigplus
 **/

window.addEvent('domready', function () {
	var form = document.id('sigplus-settings-form');  // get form
	var ctrls = form.getElements('input[type=text],input[type=radio],select');  // enumerate form controls in order of appearance

	// set initial values
	if (window.parent.sigplus) {
		ctrls.each(function (ctrl) {
			var name = ctrl.get('name');
			var matches = name.match(/^params\[(.*)\]$/);
			if (matches) {
				name = matches[1];
			}
			var value = window.parent.sigplus[name];
			if (value) {
				if (ctrl.get('type') != 'radio') {
					ctrl.set('value', value);
				} else if (ctrl.get('value') == value) {  // omit unrelated radio buttons
					ctrl.set('checked', true);
				}
			}
		});
	}	
	
	document.id('sigplus-settings-submit').addEvent('click', function () {
		var params = '';
		ctrls.each(function (ctrl) {
			if (ctrl.get('type') != 'radio' || ctrl.get('checked')) {  // omit radio buttons that are not checked
				var name = ctrl.get('name');
				var matches = name.match(/^params\[(.*)\]$/);
				if (matches) {
					name = matches[1];
				}
				var value = ctrl.get('value');
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