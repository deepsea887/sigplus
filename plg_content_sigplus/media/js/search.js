/**
* @file
* @brief    sigplus Image Gallery Plus index image population for search results script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

function __sigplusSearch(url, preview_url, width, height) {
	var elem = document.getElement('.result-title > a[href="' + url + '"]');
	if (elem) {
		new Element('img', {
			'src': preview_url,
			'width': width,
			'height': height
		}).inject(elem.getParent().getNext('.result-text'), 'top');
	}
}