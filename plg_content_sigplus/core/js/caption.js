/**
* @file
* @brief    sigplus Image Gallery Plus client-side template text assignment script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// apply the caption template to captions
(function () {
	var regexp = /\\?\{\$([^{}]+)\}/g;
	var imagetemplate = "{$caption_title_template}" || "{$text}";
	var anchortemplate = "{$caption_summary_template}" || "{$text}";
	var anchors = document.getElements("#{$id} a.sigplus-image");
	anchors.each(function (anchor, index) {
		var replacement = {  // template replacement rules
			filename: anchor.pathname,
			current: index + 1,  // index is zero-based but user interface needs one-based counter
			total: anchors.length
		};

		// apply template to "alt" attribute of image element wrapped in anchor
		var image = anchor.getElement("img");
		if (image) {
			image.set("alt", imagetemplate.substitute($merge({text: image.get("alt") || ""}, replacement), regexp));
		}

		// apply template to "title" attribute of anchor element
		anchor.set("title", anchortemplate.substitute($merge({text: anchor.get("title") || ""}, replacement), regexp));
	});
})();