/**@license captionplus mouse-over image caption engine
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2009-2011 Levente Hunyadi
 * @see     http://hunyadi.info.hu/projects/
 **/

(function ($) {
	'use strict';

	/**
	* @param {Array.<string>} cls An array of class name suffixes.
	* @return {string} A class annotation to be used as an Element "class" attribute value.
	*/
	function _class(cls) {
		return cls.map(function (item) {
			return 'captionplus-' + item;
		}).join(' ');
	}

	var captionplus = new Class({
		'Implements': Options,

		'options': {
			'overlay': true,
			/**
			* Determines the position of the caption relative to the image ['top'|'bottom']
			*/
			'position': 'bottom',
			/**
			* Determines when the caption is to be seen ['always'|'mouseover'].
			* @type {string}
			*/
			'visibility': 'mouseover',
			'horzalign': 'center',
			'vertalign': 'center'
		},

		/**
		* Adds a mouse-over image caption to a single image.
		*/
		'initialize': function (elem, options) {
			var self = this;
			self['setOptions'](options);
			options = self.options;

			var image = elem.getElement('img');
			var anchor = elem.getElement('a');
			if (image) {
				var caption = anchor && anchor.retrieve('title') || image.get('alt');
				var downloadurl = anchor && anchor.retrieve('download');
				if (caption || downloadurl) {
					var captionarea;
					
					elem.adopt(
						new Element('div', {
							'class': 'captionplus'
						}).adopt(elem.getChildren()).grab(
							new Element('div', {
								'class': _class([
									options['overlay'] ? 'overlay' : 'outside',
									options['position'],
									options['visibility']
								])
							}).grab(
								captionarea = new Element('div', {
									'class': _class([
										'align',
										'horizontal-' + options['horzalign'],
										'vertical-' + options['vertalign']
									]),
									'html': caption ? caption : ''  // text content
								})
							),
							options['position']
						)
					);
					
					if (downloadurl) {  // download button
						captionarea.adopt(
							new Element('a', {
								'class': _class(['button','download']),
								'href': downloadurl
							})
						);
					}
				}
			}
		}
	});
	
	captionplus['bind'] = function (elem, options) {
		// element existence test to ensure element is within DOM, some content management
		// systems may call the script even if the associated content is not on the page,
		// which is the case e.g. with Joomla category list layout or multi-page layout
		if (elem) {
			window.addEvent('domready', function () {
				elem.getChildren('li').each(function (item) {
					new captionplus(item, options);
				});
			});
		}
	}

	window['captionplus'] = captionplus;
})(document.id);