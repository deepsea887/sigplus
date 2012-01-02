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
	$$('.sigplus-gallery').removeClass('sigplus-noscript');

	// bind thumbnail images to anchors
	$$('.sigplus-gallery a').each(function (anchor) {
		var elem;
		var link;

		if (elem = anchor.getElement('img')) {
			anchor.store('title', elem.get('alt'));
		}
		
		// assign thumbnail image
		if (elem = anchor.getElement('.sigplus-thumb')) {
			anchor.store('thumb', elem);  // copy to MooTools element storage
			elem.dispose();
		}

		// assign summary text (with HTML support)
		if (elem = anchor.getNext('.sigplus-summary')) {
			anchor.store('summary', elem.get('html')).setProperty('title', elem.get('text'));
			if (link = elem.getElement('a')) {  // summary contains an anchor, which should be set as a preferred target for the image
				anchor.store('link', link);
			}
			elem.destroy();
		}

		// assign download URL
		if (elem = anchor.getNext('.sigplus-download')) {
			anchor.store('download', elem.get('href'));  // copy to MooTools element storage
			elem.destroy();
		}
	});

	// apply click prevention to galleries without lightbox
	$$('.sigplus-lightbox-none a.sigplus-image').each(function (anchor) {
		anchor.addEvent('click', function (event) {
			var link = anchor.retrieve('link');
			if (link) {  // there is a preferred target for the image
				anchor.href = link.href;
				anchor.target = link.target;
			} else {
				event.preventDefault();
			}
		});
	});
});

// apply template to caption text
function __sigplusCaption(id, titletemplate, summarytemplate) {
	var anchors = document.getElements('#' + id + ' a.sigplus-image');
	titletemplate = titletemplate || '{$text}';
	summarytemplate = summarytemplate || '{$text}';
	anchors.each(function (anchor, index) {
		var replacement = {  // template replacement rules
			filename: (anchor.pathname || '').match(/([^\/]*)$/)[1],  // keep only file name component from path
			current: index + 1,  // index is zero-based but user interface needs one-based counter
			total: anchors.length
		};

		function _subs(template, text) {
			return template.substitute($merge({'text': text || ''}, replacement), /\\?\{\$([^{}]+)\}/g);
		}
		
		function _subsattr(elem, attr, template) {
			elem.set(attr, _subs(template, elem.get(attr)));
		}
		
		function _subsstore(elem, key, template) {
			elem.store(key, _subs(template, elem.retrieve(key)));
		}

		// apply template to element store data
		_subsstore(anchor, 'title', titletemplate);
		_subsstore(anchor, 'summary', summarytemplate);

		// apply template to "alt" attribute of image element wrapped in anchor
		var image = anchor.getElement('img');
		if (image) {
			_subsattr(image, 'alt', titletemplate);
		}

		// apply template to "title" attribute of anchor element
		_subsattr(anchor, 'title', summarytemplate);
	});
}