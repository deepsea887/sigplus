/**
* @file
* @brief    sigplus Image Gallery Plus logging simple expand/collapse script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// <sigplus>
window.addEvent('domready', function () {
	$$('pre.sigplus-log').each(function (block) {
		var linkshow = new Element('a', {
			'href': '#',
			'class': 'sigplus-log-show',
			'html': 'Show',
			'styles': {
				'display': 'inline'
			}
		}).inject(block, 'before');
		var linkhide = new Element('a', {
			'href': '#',
			'class': 'sigplus-log-hide',
			'html': 'Hide',
			'styles': {
				'display': 'none'
			}
		}).inject(block, 'before');
		block.setStyle('display','none');
		linkshow.addEvent('click', function (event) {
			linkshow.setStyle('display','none');
			linkhide.setStyle('display','inline');
			block.setStyle('display','block');
			event.preventDefault();
		});
		linkhide.addEvent('click', function (event) {
			linkhide.setStyle('display','none');
			linkshow.setStyle('display','inline');
			block.setStyle('display','none');
			event.preventDefault();
		});
	});
});
// </sigplus>