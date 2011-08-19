/**@license hoverplus lightweight pop-up window on mouse-over
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2011 Levente Hunyadi
 * @see     http://hunyadi.info.hu/projects/hoverplus
 **/

(function ($) {
	function _class(cls) {
		return 'hoverplus-' + cls;
	}

	function _dotclass(cls) {
		return '.' + _class(cls);
	}

	function _hide(elem) {
		elem.addClass(_class('hidden'));
	}

	function _show(elem) {
		elem.removeClass(_class('hidden'));
	}

	var hoverplusDialog = new Class({
		/**
		* Appends the pop-up window HTML code to the document body.
		* The HTML code inserted is
			<div id="hoverplus">
				<div class="hoverplus-viewer"></div>
				<div class="hoverplus-caption">
					<p class="hoverplus-title"></p>
					<p class="hoverplus-text"></p>
				</div>
			</div>
		*/
		'initialize': function () {
			var self = this;
			self._dialog = new Element('div', {
				'id': 'hoverplus'
			}).adopt(
				self._viewer = new Element('div', {
					'class': _class('viewer')
				}),
				self._caption = new Element('div', {
					'class': _class('caption')
				}).adopt(
					self._title = new Element('p', {
						'class': _class('title')
					}),
					self._text = new Element('p', {
						'class': _class('text')
					})
				)
			).inject(document.body);
			_hide($$([self._dialog, self._viewer, self.caption]));
		},

		/*
		_dialog: null,
		_viewer: null,
		_caption: null,
		_title: null,
		_text: null,
		*/

		/**
		* Shows an image in the dialog with the given URL, title and text.
		* @param {Element} elem An element the pop-up window is associated with, the window tries not to cover the element.
		* @param {string} url URL of image to show in the pop-up window.
		* @param {string} title Title text of image to display.
		* @param {string} text Additional text to display.
		*/
		show: function (elem, url, title, text) {
			var self = this;

			/**
			* Smart placement for the lightweight window not to cover the element that has triggered displaying the window.
			*/
			function _position() {
				var size = self._dialog.getSize();
				var coord = elem.getCoordinates();  // dimensions and offset w.r.t. browser window edges

				var winscroll = window.getScroll();
				var arealeft = coord['left'] - winscroll.x;
				var areatop = coord['top'] - winscroll.y;
				var areawidth = coord['width'];
				var areaheight = coord['height'];

				var winsize = window.getSize();
				var error = [
					arealeft + areawidth + size.x - winsize.x,  // position to the right of area
					areatop + areaheight + size.y - winsize.y,  // position to the bottom of area
					size.x - arealeft                           // position to the left of area
				];

				// find positioning with minimum error
				var index = -1;
				var min = Infinity;
				for (var k in error) {
					if (error[k] < min) {
						index = k;
						min = error[k];
					}
				}

				var x = (winsize.x - size.x) / 2;
				var y = (winsize.y - size.y) / 2;
				var pad = 20;  // keeps distance
				var left = [
					arealeft + areawidth + pad,
					x,
					arealeft - size.x - pad
				];
				var top = [
					y,
					areatop + areaheight + pad,
					y
				];

				return {
					'left': winscroll.x + left[index],
					'top': winscroll.y + top[index]
				};
			}

			// set window to initial size and position
			new Fx.Morph(self._dialog).set(_dotclass('dialog'));  // set width and height
			self._dialog.setStyles(_position());  // set position

			// show dialog but hide descendants
			_show(self._dialog);
			_hide($$([self._viewer,self._caption]));

			// prepare an image for display in the viewer using a preloaded image
			var preloader = $(new Image()).addEvents({
				'load': function () {  // display image when image has been loaded
					// set image viewer dimensions
					var w = preloader['width'];
					var h = preloader['height'];
					var dims = {
						'width': w,
						'height': h
					};
					self._viewer.setStyles($extend({
						'background-image': 'url("' + url + '")'
					}, dims));
					self._caption.setStyle('width', w);

					// get dialog width and height
					var target = $extend(_position(), dims);

					// morph dialog to new size and position
					new Fx.Morph(self._dialog, {
						'duration': 200,
						'onComplete': function () {
							// show image
							_show(self._viewer);

							// add caption text
							self._title.empty().set('html', title);
							self._text.empty().set('html', text);

							if (title || text) {
								// resize dialog to show caption text
								dims.height += self._caption.getSize().y;
								new Fx.Morph(self._dialog, {
									'duration': 100,
									'onComplete': function () {
										// display the image caption text
										_show(self._caption);
									}
								}).start(dims);
							}
						}
					}).start(target);
				},
				'error': function () {
					self.hide();
				}
			}).setProperty('src', url);
		},

		/**
		* Hides the lightweight pop-up window.
		*/
		'hide': function () {
			_hide(this._dialog);
		}
	});

	var dialog;
	window.addEvent('domready', function () {
		dialog = new hoverplusDialog();  // inject pop-up HTML to document
	});

	var hoverplus = new Class({
		'Implements': Options,

		'options': {
		},

		/**
		* Title text that belongs an anchor.
		* @param {Element} anchor A mootools Element object representing the anchor.
		* @return {?string} Raw HTML data as a string.
		*/
		_getTitle: function (anchor) {
			var image = anchor.getElement('img');
			return image ? image.getProperty('alt') : '';
		},
		/**
		* Description text that belongs to an anchor.
		* @param {Element} anchor A mootools Element object representing the anchor.
		* @return {string} Raw HTML data as a string.
		*/
		_getText: function (anchor) {
			return anchor.getProperty('title');
		},

		'initialize': function (elem, url, options) {
			var self = this;
			self['setOptions'](options);
			options = self['options'];

			self._getTitle = $pick(options['getTitle'], self._getTitle);
			self._getText = $pick(options['getText'], self._getText);

			/**
			* Stores an active timer.
			*/
			var timer;

			/**
			* Renews a timer.
			* Fired when the mouse pointer is moved while over an image.
			*/
			function _timerfun() {
				if (timer) {
					clearTimeout(timer);  // clear current timer (if any)
				}
				timer = _show.delay(100, self);  // set a new timer
			};

			/**
			* Shows the lightweight pop-up window.
			*/
			function _show() {
				var self = this;

				// cancel mouse move event that would reset timer
				if (timer) {
					clearTimeout(timer);  // clear current timer (if any)
				}
				elem.removeEvent('mousemove', _timerfun);  // cancel the timer for mouse move events

				dialog.show(elem, url, self._getTitle(elem), self._getText(elem));
			}

			elem.addEvents({
				'mouseenter': function () {  // sets a timer that will show the pop-up window as soon as the mouse pointer comes to a stop
					elem.addEvent('mousemove', _timerfun);
					_timerfun();
				},
				'mouseleave': function () {
					elem.removeEvent('mousemove', _timerfun);
					if (timer) {
						clearTimeout(timer);  // clear current timer (if any)
					}
					dialog.hide();
				}
			});
		}
	});

	/**
	* Binds the lightweight window to appear when the mouse pointer is positioned over images in a gallery.
	* A gallery should be specified as a list (ul or ol with li as children), each of whose elements wraps an individual image.
	*/
	hoverplus['bind'] = function (elem, options) {
		function _bindAnchors(anchors) {
			anchors.each(function (item) {
				if (/\.(jpe?g|png|gif)$/.test(item.getProperty('href'))) {
					new hoverplus(item, item.getProperty('href'), options);
				}
			});
		}

		if (elem.length && elem.get('tag').every(function (item) { return item == 'a'; })) {  // is a collection of anchors
			_bindAnchors(elem);
		} else {
			switch (elem.get('tag')) {
				case 'ol':
				case 'ul':
					elem.getChildren('li').each(function (item) {
						_bindAnchors(item.getChildren('a[href]'));
						item.getChildren('img').each(function (item) {
							new hoverplus(item, item.getProperty('src'), options);
						});
					});
					break;
			}
		}
	};

	window['hoverplus'] = hoverplus;
})(document.id);