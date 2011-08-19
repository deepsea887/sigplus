/**@license captionplus mouse-over image caption engine
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2009-2011 Levente Hunyadi
 * @see     http://hunyadi.info.hu/projects/
 **/

(function ($) {
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
			if (image) {
				var caption = image.get('alt');
				if (caption) {
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
							}).adopt(
								new Element('div', {
									'class': _class([
										'align',
										'horizontal-' + options['horzalign'],
										'vertical-' + options['vertalign']
									]),
									'html': caption
								})
							),
							options['position']
						)
					);
				}
			}
		}
	});

	captionplus['bind'] = function (elem, options) {
		window.addEvent('domready', function () {
			elem.getChildren('li').each(function (item) {
				new captionplus(item, options);
			});
		});
	}

	window['captionplus'] = captionplus;
})(document.id);