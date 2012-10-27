/**@license scrollplus manual image slider
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2011 Levente Hunyadi
 * @see     http://hunyadi.info.hu/projects/sigplus
 **/

;
(function ($) {
	function _class(cls) {
		return 'scrollplus-' + cls;
	}

	var scrollplus = new Class({
		/**
		* @param {Element} elem
		*/
		'initialize': function (elem) {
			// element existence test to ensure element is within DOM, some content management
			// systems may call the script even if the associated content is not on the page,
			// which is the case e.g. with Joomla category list layout or multi-page layout
			if (!elem) {
				return;
			}
		
			var list = elem.getElement('ul,ol');
			if (!list) {
				return;
			}
			list.addClass('scrollplus');

			// create slider scrollbar and knob
			var knob = new Element('div', {
				'class': _class('knob')
			});
			var container = new Element('div', {  // container for scrollbar and knob
				'class': _class('slider')
			}).adopt(new Element('div', {  // bar along which knob moves
				'class': _class('bar')
			})).adopt(knob).inject(elem, 'before');

			// add slider actions
			var slider = new Slider(container, knob, {  // maximum value for slider is (by default) 100
				'onChange': function (value) {
					list.scrollTo((list.getScrollSize().x - list.getSize().x) * value / 100, 0);
				}
			});

			// resize automatically if browser window size changes
			window.addEvent('resize', function() {
				// set slider value based on new scroll position of sliding gallery
				slider.set(100 * list.getScroll().x / (list.getScrollSize().x - list.getSize().x));
				
				// re-adjust slider dimensions and knob position
				slider.autosize();
			});
		}
	});

	scrollplus['autodiscover'] = function () {
		window.addEvent('domready', function () {
			$$('.scrollplus').each(function (item) {
				new scrollplus(item);
			});
		});
	};

	window['scrollplus'] = scrollplus;
})(document.id);