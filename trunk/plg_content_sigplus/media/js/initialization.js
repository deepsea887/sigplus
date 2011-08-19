/**
* @file
* @brief    sigplus Image Gallery Plus client-side initialization script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

window.addEvent('domready', function () {
	// unwrap galleries from <noscript> elements
	$$('noscript.sigplus-gallery').each(function (item) {
		new Element('div', {
			'html': item.get('text')
		}).replaces(item);
		item.destroy();
	});

	// bind thumbnail images to anchors
	$$('.sigplus-gallery a').each(function (anchor) {
		var thumb = anchor.getElement('.sigplus-thumb');
		if (thumb) {
			anchor.store('thumb', thumb);
			thumb.dispose();
		}
	});

	// apply click prevention to galleries without lightbox
	$$('.sigplus-lightbox-none a.sigplus-image').each(function (anchor) {
		anchor.addEvent('click', function (event) {
			event.preventDefault();
		});
	});
});