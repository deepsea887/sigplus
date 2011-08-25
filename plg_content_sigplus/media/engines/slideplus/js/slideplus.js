/**@license slideplus image rotator
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2011 Levente Hunyadi
 * @see     http://hunyadi.info.hu/projects/slideplus
 **/

;
(function ($) {
	$extend(Element['NativeEvents'], {
		'dragstart': 2  // listen to browser-native drag-and-drop events
	});

	Elements.implement({
		/**
		* Circular indexing.
		* @type {number} k Index.
		* @return {Element} An element at the specified modulo index position.
		*/
		'at': function (k) {
			var len = this['length'];
			return this[(k % len + len) % len];
		}
	});

	/**
	* @param {Array.<string>} cls An array of class name suffixes.
	* @return {string} A class annotation to be used as an Element "class" attribute value.
	*/
	function _class(cls) {
		return cls.map(function (item) {
			return 'slideplus-' + item;
		}).join(' ');
	}

	function _dotclass(cls) {
		return '.slideplus-' + cls;
	}

	/**
	* Time between successive scroll animations [ms].
	* @type {number}
	* @const
	*/
	var SLIDEPLUS_SCROLL_INTERVAL = 10;

	var slideplus = new Class({
		'Implements': Options,

		// default configuration properties
		'options': {
			'size': {
				'rows': 1,  // number of rows per slider page
				'cols': 3   // number of columns per slider page
			},
			/**
			* Unit the slider advances in response to navigation buttons previous or next ['single'|'page'].
			* @type {string}
			*/
			'step': 'page',
			/**
			* Whether the rotator loops around in a circular fashion [false|true].
			* @type {boolean}
			*/
			'loop': true,
			/**
			* Orientation of sliding image strip ['horizontal'|'vertical'].
			* @type {string}
			*/
			'orientation': 'horizontal',
			/**
			* Horizontal alignment of current image in sliding image strip ['center'|'start'|'end'].
			* @type {string}
			*/
			'horzalign': 'center',
			/**
			* Vertical alignment of current image in sliding image strip ['center'|'start'|'end'].
			* @type {string}
			*/
			'vertalign': 'center',
			/**
			* Position where navigation controls are displayed, a combination of ['top'|'bottom'|'over'] or false.
			* @type {(Array.<string>|boolean)}
			*/
			'navigation': ['over','bottom'],
			/**
			* Action to trigger advancing the slider using navigation buttons previous or next ['click'|'mouseover'].
			* @type {string}
			*/
			'trigger': 'click',
			/**
			* Whether to show navigation links [true|false].
			* @type {boolean}
			*/
			'links': true,
			/**
			* Whether to show page counter [true|false].
			* @type {boolean}
			*/
			'counter': true,
			/**
			* Whether the context menu appears when right-clicking an image [true|false].
			* @type {boolean}
			*/
			'protection': false,
			/**
			* Duration for slide animation [ms], or one of ['slow'|'fast']
			* @type {(number|string)}
			*/
			'duration': 800,
			/**
			* Transition effect for slide animation, takes a string or an Fx.Transitions object.
			*/
			'transition': 'sine',
			/**
			* Time between successive automatic slide steps [ms], or false to disable automatic sliding.
			* @type {(number|boolean)}
			*/
			'delay': false,
			/**
			* Scroll speed [px/s].
			* @type {number}
			*/
			'scrollspeed': 200,
			/**
			* Acceleration factor, multiplier of scroll speed in fast scroll mode.
			* @type {number}
			*/
			'scrollfactor': 5
		},

		_index: 0,         // zero-based index of the currently active list item
		/*
		_stepsize: 0,      // number of items the slider advances when a navigation button is activated
		_gallery: null,    // DOM Element that encapsules list items, navigation controls, etc.
		_list: null,       // DOM Element that represents the sliding viewpane
		_listitems: null,  // list of DOM Elements the sliding viewpane can be populated with
		_paging: null,
		_quickaccess: null,
		_maxwidth: 0,      // maximum width of images
		_maxheight: 0,     // maximum height of images
		_scrollspeed: 0,
		_scrolltimer: null,
		*/

		/**
		* @param {Element} elem
		* @param {Object} options
		* @param {Object=} lightbox Lightbox engine associated with the slider. All animations stop while the lightbox is displayed.
		*        The engine must support the events "open" or "show", and the "close" or "hide".
		*/
		'initialize': function (elem, options, lightbox) {
			var self = this;
			self['setOptions'](options);
			options = self.options;

			// save list items
			var list = self._list = (self._gallery = elem).getElement('ul,ol');
			if (!list) {
				return;
			}
			var listitems = self._listitems = list.getChildren('li');
			// create a nesting <div><div class="slideplus"><ul>...</ul></div></div>
			var viewer = new Element('div', {
				'class': 'slideplus'
			}).inject(elem).grab(list);

			// set viewpane size
			var rows = options['size']['rows'];
			var cols = options['size']['cols'];
			if (rows > 0 && cols > 0) {
				// get maximum width and height of image slider items
				function _getMaxSize(items, dim) {
					return Math.max.attempt(items.getSize().map(function (item) {
						return item[dim];
					}));
				}

				$$(list, viewer).setStyles({
					'width': cols * (self._maxwidth = _getMaxSize(listitems, 'x')),
					'height': rows * (self._maxheight = _getMaxSize(listitems, 'y'))
				});
				listitems.setStyles({
					'width': self._maxwidth,
					'height': self._maxheight
				});
			} else {
				// resolve parameter incompatibilities
				options['counter'] = false;
			}

			listitems.each(function (listitem) {
				// center items horizontally and vertically
				listitem.adopt(
					new Element('div', {
						'class': _class([
							'align',
							'horizontal-' + options['horzalign'],
							'vertical-' + options['vertalign']
						])
					}).adopt(listitem.getChildren())
				);
			});

			// setup navigation and paging container
			var navigation = options['navigation'];  // e.g. ['over','bottom']
			var barnavigation = navigation.clone().erase('over');  // e.g. ['bottom']
			barnavigation.each(function (pos) {
				new Element('div', {
					'class': _class(['navbar'])
				}).setStyle('width', viewer.getStyle('width')).inject(elem, pos);
			});
			var quickaccess = self._quickaccess = elem.getElements(_dotclass(['navbar']));

			// setup overlay navigation controls
			if (navigation.contains('over')) {
				var isvertical = options['orientation'] == 'vertical';
				var listsize = list.getSize();
				var clsOrientation = isvertical ? 'vertical' : 'horizontal';
				var clsSize = (isvertical ? listsize.x : listsize.y) < 120 ? 'small' : 'large';
				viewer.adopt(
					new Element('div', {
						'class': _class(['prev',clsOrientation,clsSize])
					}),
					new Element('div', {
						'class': _class(['next',clsOrientation,clsSize])
					})
				);
			}

			/**
			* Adds buttons "Previous" and "Next" to navigation bar.
			*/
			function _addNavigation(dir,text) {
				quickaccess.each(function (bar) {
					bar.adopt(
						new Element('span', {
							'class': _class(['navbutton',dir]),
							'html': text
						})
					);
				});
			}

			// setup navigation bar controls
			if (barnavigation.length) {
				_addNavigation('prev', '&lt;');  // '\u21E6' or 'Previous'
				_addNavigation('next', '&gt;');  // '\u21E8' or 'Next'
				_addNavigation('first', '|&lt;');
				_addNavigation('last', '&gt;|');
			}

			// add navigation bar paging controls
			if (options['links']) {
				quickaccess.each(function (bar) {
					var paging = new Element('div', {
						'class': _class(['paging'])
					});

					var pagecount = options['step'] == 'page' ? ((listitems.length - 1) / (rows*cols)).floor() + 1 : listitems.length;
					pagecount > 1 && pagecount.times(function (index) {  // do not create paging controls for a single page
						paging.adopt(
							new Element('span', {
								'html': index + 1,
								'events': {
									'click': function () {
										self._index = index * (options['step'] == 'page' ? rows*cols : 1);
										self._layout();
									}
								}
							})
						);
					});
					bar.adopt(paging);
				});
			}
			self._paging = elem.getElements(_dotclass(['paging']));

			// postpone loading images
			listitems.dispose().each(function (listitem) {
				listitem.getElements('img').each(function (image) {
					image.setProperty('data-src', image.getProperty('src')).removeProperty('src');
				});
			});

			self._layout();

			// scroll actions
			function _bindClickAction(elem, fun) {
				elem.addEvent('click', fun.bind(self));
			}

			var prevbuttons = self._buttons('prev');
			var nextbuttons = self._buttons('next');
			if (options['trigger'] == 'mouseover') {
				self._stopScroll();  // reset scroll speed
				prevbuttons.addEvents({
					'mouseover': function () {
						self._startScroll(-1);
					},
					'mouseout': function () {
						self._stopScroll();
					}
				});
				nextbuttons.addEvents({
					'mouseover': function () {
						self._startScroll(1);
					},
					'mouseout': function () {
						self._stopScroll();
					}
				});
				$$([prevbuttons,nextbuttons].flatten()).addEvents({
					'mousedown': function () {
						self._accelerateScroll();
					},
					'mouseup': function () {
						self._decelerateScroll();
					}
				});
			} else {
				_bindClickAction(prevbuttons, self['prev']);
				_bindClickAction(nextbuttons, self['next']);
			}
			_bindClickAction(self._buttons('first'), self['first']);
			_bindClickAction(self._buttons('last'), self['last']);

			// suppress context menu and drag-and-drop
			if (options['protection']) {
				/**
				* Conditionally suppresses an event.
				*/
				function _uiProhibitedAction(event) {
					return !list.contains(event.target);
				}

				document.addEvents({  // subscribe to protected events
					'contextmenu': _uiProhibitedAction,  // prevent right-click on image
					'dragstart': _uiProhibitedAction     // prevent drag-and-drop of image
				});
			}

			// slider animation
			var delay = options['delay'];
			if (delay) {
				/** Shared interval timer. */
				var intervalID;
				/** Whether to suppress animation when mouse is not over rotator. */
				var stopAnimation = false;

				/**
				* Sets the animation delay.
				*/
				function _setInterval() {
					intervalID = window.setInterval(function () {
						self._slide(1);
					}, delay);
				}

				/**
				* Clears the animation delay.
				*/
				function _clearInterval() {
					window.clearInterval(intervalID);
				}

				// stop animation when mouse mover over an image
				viewer.addEvents({
					'mouseover': function () {
						stopAnimation || _clearInterval();  // do not keep animating if lightbox pop-up window is open
					},
					'mouseout': function () {
						stopAnimation || _setInterval();
					}
				});

				if (lightbox) {
					lightbox.addEvents({
						'open': function () {
							stopAnimation = true;
							_clearInterval();
						},
						'close': function () {
							stopAnimation = false;
							_setInterval();
						}
					});
				}

				_setInterval();
			}
		},

		'prev': function () {
			this._slide(-1);
		},

		'next': function () {
			this._slide(1);
		},

		'first': function () {
			var self = this;
			self._index = 0;
			self._layout();
		},

		'last': function () {
			var self = this;
			var options = self['options'];
			var rows = options['size']['rows'];
			var cols = options['size']['cols'];
			var len = self._listitems.length;
			self._index = options['step'] == 'page' ? ((len - 1) / (rows*cols)).floor() * (rows*cols) : len - 1;
			self._layout();
		},

		_buttons: function (cls) {
			return this._gallery.getElements(_dotclass(cls));
		},

		/**
		* Arrange items on sliding image strip.
		*/
		_layout: function () {
			var self = this;
			var options = self['options'];
			var rows = options['size']['rows'];
			var cols = options['size']['cols'];
			var maxwidth = self._maxwidth;
			var maxheight = self._maxheight;
			var length = self._listitems.length;

			// extract part of array with loop semantics
			var listitems = $$([]);
			var lowest = self._index - rows*cols;     // start index
			var highest = self._index + 2*rows*cols;  // end index
			if (!options['loop']) {
				highest = highest.min(length);
			}

			for (var k = lowest; k < highest; k++) {
				var listitem = self._listitems['at'](k);  // call class extension "at" for Elements

				// schedule images for loading
				listitem.getElements('img').each(function (image) {
					var src = image.getProperty('data-src');
					if (src) {
						image.removeProperty('data-src').setProperty('src', src);
					}
				});

				// add item to the group of those shown or about to be shown
				if (length > 3*rows*cols) {  // sufficient number of images available to fill each position
					listitems.push(listitem);
				} else {  // not enough images to occupy each position, create duplicates (might be unsafe with other script libraries, e.g. jQuery)
					/**
					* Copies a set of events from an element and all of its descendants to another element.
					* @param {Element} to The element to copy events to.
					* @param {Element} from The element to copy events from.
					* @param {string} type The event to copy, e.g. "click".
					*/
					function _cloneEventsDeep(to, from, type) {
						var toChildren = to.cloneEvents(from, type).getChildren();
						var fromChildren = from.getChildren();
						for (var k = 0; k < toChildren.length; k++) {
							_cloneEventsDeep(toChildren[k], fromChildren[k], type);
						}
					}

					var cloneitem = listitem.clone();
					_cloneEventsDeep(cloneitem, listitem);
					listitems.push(cloneitem);
				}
			}

			// remove previous event bindings
			self._list.getChildren().each(function (listitem) {
				// remove (fade-in and fade-out) animation effects
				listitem.removeEvents(listitem.retrieve(_class(['events'])));  // remove events set exclusively by this script
			});

			// clear items currently displayed
			self._list.empty();

			// add new items displayed
			listitems.each(function (listitem, index) {
				// arrange list items on sliding canvas
				var left, top;
				if (options['orientation'] == 'vertical') {
					left = (index%cols) * maxwidth;
					top = (index/cols).toInt() * maxheight - rows * maxheight;
				} else {
					left = (index/rows).toInt() * maxwidth - cols * maxwidth;
					top = (index%rows) * maxheight;
				}
				self._list.adopt(listitem.setStyles({
					'left': left,
					'top': top
				}));

				// add (fade-in and fade-out) animation effects
				var effect = new Fx.Morph(listitem, {
					link: 'cancel'
				});
				effect.set('.slideplus-inactive');
				var events = {
					'mouseenter': function () {
						effect.start(_dotclass('active'));
					},
					'mouseleave': function () {
						effect.start(_dotclass('inactive'));
					}
				};

				// associate events object with element storage
				listitem.store(_class(['events']), events).addEvents(events);
			});

			// show or hide navigation buttons previous and next (for non-looping mode)
			self._buttons('prev').toggleClass(_class(['hidden']), !options['loop'] && self._index <= 0);
			self._buttons('next').toggleClass(_class(['hidden']), !options['loop'] && self._index >= length - (options['step'] == 'page' ? 1 : rows*cols));

			// update current page
			self._paging.each(function (paging) {
				// remove and set active page marker (if paging controls are present)
				var activeitem = paging.getChildren().removeClass(_class(['active']))['at'](self._index / (options['step'] == 'page' ? rows*cols : 1)).addClass(_class(['active']));

				// scroll active page into view
				var position_x = activeitem.getPosition(paging).x;  // ranges between 0 and container width if left edge is in view
				if (position_x + activeitem.getSize().x > paging.getSize().x || position_x < 0) {
					paging.scrollTo(paging.getScroll().x + position_x, 0);  // align left edge with container left edge
				}
			});
		},

		_advance: function (increment) {
			var self = this;
			self._index += increment;
			self._layout();  // reset layout when animation is complete
			self._list.setStyles({
				'left': 0,
				'top': 0
			});
		},

		_increment: function (dir) {
			var self = this;
			var options = self['options']
			var rows = options['size']['rows'];
			var cols = options['size']['cols'];
			return dir * (options['step'] == 'page' ? rows*cols : (options['orientation'] == 'vertical' ? cols : rows));
		},

		/**
		* Advance the sliding image strip.
		* @param dir Specify -1 to slide backwards, 1 to slide forwards.
		*/
		_slide: function (dir) {
			var self = this;
			var options = self['options']
			var rows = options['size']['rows'];
			var cols = options['size']['cols'];
			var increment = self._increment(dir);

			// disallow circular repeat when looping is disabled; option "move by page" allows empty slots at the end
			if (!options['loop'] && (self._index + increment >= self._listitems.length - (options['step'] == 'page' ? 0 : rows*cols-1) || self._index + increment < 0)) {
				return;
			}

			// find source and target positions
			var source = self._list.getChildren()[rows*cols];
			var sourcepos = source.getPosition();
			var target = self._list.getChildren()[rows*cols + increment];
			var targetpos = target.getPosition();

			// launch animation
			var effect = new Fx.Morph(self._list, {
				'transition': options['transition'],
				'onComplete': function () {
					self._advance(increment);
				}
			});
			effect.start({
				'left': sourcepos.x - targetpos.x,  // slide from source to target
				'top': sourcepos.y - targetpos.y
			});
		},

		/**
		* Starts scrolling the sliding strip either forward or backward.
		* @param {number} dir -1 to scroll backward, 1 to scroll forward, 0 to initialize controls (no scrolling)
		*/
		_startScroll: function (dir) {
			var self = this;
			var options = self['options'];

			// navigation bar to scroll
			var navbar = self._list;

			// current left/top offset of sliding strip w.r.t. left edge of viewer
			var property = options['orientation'] == 'vertical' ? 'top' : 'left';
			var x = navbar.getStyle(property).toInt();  // 0 > x > minpos
			x = isNaN(x) ? 0 : x;

			if (x == 0 && dir < 0) {
				self._advance(self._increment(dir));
			}

			// reference item size, which causes a layout update when scrolls to its final position
			var size = self._list.getChildren()[options['size']['rows']*options['size']['cols']].getSize();
			if (x == 0 && dir < 0) {
				x = -size.x;
				navbar.setStyle(property, x + 'px');
			}

			// minimum and maximum value permitted as left/top offset w.r.t. left/top edge of viewer
			var minpos = -size.x;

			// assign scroll function, avoid complex operations
			var func = function () {
				// whether to reset offsets and update layout
				var reset = false;

				x -= dir * self._scrollspeed;
				if (x <= minpos) {
					x = minpos;
					reset = true;
				}
				if (x >= 0) {
					x = 0;
					reset = true;
				}

				navbar.setStyle(property, x + 'px');

				if (reset) {
					window.clearInterval(self._scrolltimer);
					self._scrolltimer = null;
					if (dir > 0) {
						self._advance(self._increment(dir));
					}
					self._startScroll(dir);
				}
			};

			self._scrolltimer = window.setInterval(func, SLIDEPLUS_SCROLL_INTERVAL);
		},

		_stopScroll: function () {
			var self = this;
			self._decelerateScroll();
			if (self._scrolltimer) {
				window.clearInterval(self._scrolltimer);
				self._scrolltimer = null;
			}
		},

		_accelerateScroll: function () {
			var self = this;
			var options = self['options'];
			self._scrollspeed = options['scrollspeed'] * SLIDEPLUS_SCROLL_INTERVAL * options['scrollfactor'] / 1000;
		},

		_decelerateScroll: function () {
			this._scrollspeed = this['options']['scrollspeed'] * SLIDEPLUS_SCROLL_INTERVAL / 1000;
		}
	});

	/**
	* Automatically discovers static image sliders wrapped in an HTML <noscript> element and transforms them into a rotating gallery.
	*
	* Example HTML source code:
	* <noscript class="slideplus">
	* <ul>
	* <li><a href="images/example1.jpg"><img width="150" height="100" alt="First sample image" src="thumbs/example1.jpg" /></a></li>
	* <li><img width="150" height="100" alt="Second sample image" src="thumbs/example2.jpg" /></li>
	* </ul>
	* </noscript>
	*/
	slideplus['autodiscover'] = function () {
		window.addEvent('domready', function () {
			$$('noscript.slideplus').each(function (item) {
				// extracts the contents of a <noscript> element, and build a rotating gallery
				new slideplus(new Element('div', {
					'class': 'slideplus',
					'html': item.get('text')  // <noscript> elements are not parsed when javascript is enabled
				}).inject(item, 'after'));
				item.destroy();
			});
		});
	};

	window['slideplus'] = slideplus;
})(document.id);