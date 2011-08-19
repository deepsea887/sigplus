/**@license colorplus: a CSS color picker
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2010-2011 Levente Hunyadi
 * @remarks Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
 * @see     http://hunyadi.info.hu/projects/sigplus
 **/

/*
* colorplus: a lightweight HTML editor
* Copyright 2010-2011 Levente Hunyadi
*
* colorplus is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* colorplus is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with colorplus.  If not, see <http://www.gnu.org/licenses/>.
*/

(function ($) {
	var colorplus = {};

	function _class(cls) {
		return 'colorplus-' + cls;
	}

	colorplus.DropDownList = new Class({
		'Implements': Events,

		/**
		* The HTML <div> element whose children implement the drop-down list.
		*/
		//_control: null,

		'initialize': function (items, title) {
			var self = this;

			var control = new Element('div', {
				'class': _class('list')
			}).adopt(
				new Element('span', {
					'class': _class(title)
				}),
				new Element('span', {
					'class': _class('list')
				}),
				new Element('ul', {
					'class': _class('list')
				}).adopt(items.map(function (item) {
					return new Element('li').adopt(item).addEvent('click', function () {
						self.fireEvent('change', this);
					})
				}))
			).addEvents({
				'click': function () {
					if (!control.hasClass(_class('disabled'))) {
						control.toggleClass(_class('open'));
						return false;
					}
				},
				'mouseleave': function () {
					control.removeClass(_class('open'));
				}
			});
			control.getElements('*').set('unselectable', 'on');  // prevent selection in Internet Explorer
			self._control = control;
		},

		'toElement': function () {
			return this._control;
		},

		'setEnabled': function (state) {
			this._control.toggleClass(_class('disabled'), !state);
		}
	});

	colorplus.Dialog = new Class({
		'initialize': function () {
			var self = this;
			var dialog = new Element('div', {
				'class': _class('dialog')
			}).adopt(
				new Element('div', {
					'class': _class('titlebar')
				}).adopt(
					self._title = new Element('div', {
						'class': _class('title')
					}),
					new Element('div', {
						'class': _class('close'),
						'events': {
							'click': self.hide.bind(self)
						}
					})
				),
				new Element('div', {
					'class': _class('container')
				}).adopt(
					self._content = new Element('div', {
						'class': _class('content')
					})
				),
				new Element('div', {
					'class': _class('resize')
				})
			).inject(document.body);

			// enable drag & drop support
			if (window.Drag) {
				new Drag(dialog, {
					'handle': dialog.getElement('.colorplus-title')
				});
				new Drag(dialog, {
					'handle': dialog.getElement('.colorplus-resize'),
					'modifiers': {
						'x': 'width',
						'y': 'height'
					}
				});
			}

			self._dialog = dialog;
			self.center();
			dialog.addClass(_class('hidden'));
		},

		'toElement': function () {
			return this._dialog;
		},

		'show': function () {
			return this._dialog.removeClass(_class('hidden'));
		},

		'hide': function () {
			return this._dialog.addClass(_class('hidden'));
		},

		/**
		* Centers the dialog on the screen.
		*/
		'center': function () {
			var dlgsize = this._dialog.getSize();
			var winsize = window.getSize();
			this._dialog.setStyles({
				left: (winsize.x - dlgsize.x) / 2,
				top: (winsize.y - dlgsize.y) / 2
			});
		},

		'resize': function () {
			var self = this;
			self._content.getParent().setStyle('width', 'auto');
			self._dialog.setStyles({
				'width': self._content.getSize().x + 2,
				'height': self._content.getSize().y + 2 + 28
			});
			self._content.getParent().setStyle('width', '100%');
			self.center();
			return self;
		},

		'adopt': function (el) {
			this._content.adopt(el);
			return this;
		}
	});

	/**
	* A dialog associated with an editor.
	*/
	colorplus.EditorDialog = new Class({
		'Extends': colorplus.Dialog,

		'initialize': function (boundcontrol) {
			var self = this;
			self.parent();
			self.boundcontrol = boundcontrol;
		}
	});

	Array.implement('hsbToHex', function () {
		return this.hsbToRgb().rgbToHex();
	});

	var colornames = {
		'AliceBlue': 0xF0F8FF,
		'AntiqueWhite': 0xFAEBD7,
		'Aqua': 0x00FFFF,
		'Aquamarine': 0x7FFFD4,
		'Azure': 0xF0FFFF,
		'Beige': 0xF5F5DC,
		'Bisque': 0xFFE4C4,
		'Black': 0x000000,
		'BlanchedAlmond': 0xFFEBCD,
		'Blue': 0x0000FF,
		'BlueViolet': 0x8A2BE2,
		'Brown': 0xA52A2A,
		'BurlyWood': 0xDEB887,
		'CadetBlue': 0x5F9EA0,
		'Chartreuse': 0x7FFF00,
		'Chocolate': 0xD2691E,
		'Coral': 0xFF7F50,
		'CornflowerBlue': 0x6495ED,
		'Cornsilk': 0xFFF8DC,
		'Crimson': 0xDC143C,
		'Cyan': 0x00FFFF,
		'DarkBlue': 0x00008B,
		'DarkCyan': 0x008B8B,
		'DarkGoldenRod': 0xB8860B,
		'DarkGray': 0xA9A9A9,
		'DarkGrey': 0xA9A9A9,
		'DarkGreen': 0x006400,
		'DarkKhaki': 0xBDB76B,
		'DarkMagenta': 0x8B008B,
		'DarkOliveGreen': 0x556B2F,
		'DarkOrange': 0xFF8C00,
		'DarkOrchid': 0x9932CC,
		'DarkRed': 0x8B0000,
		'DarkSalmon': 0xE9967A,
		'DarkSeaGreen': 0x8FBC8F,
		'DarkSlateBlue': 0x483D8B,
		'DarkSlateGray': 0x2F4F4F,
		'DarkSlateGrey': 0x2F4F4F,
		'DarkTurquoise': 0x00CED1,
		'DarkViolet': 0x9400D3,
		'DeepPink': 0xFF1493,
		'DeepSkyBlue': 0x00BFFF,
		'DimGray': 0x696969,
		'DimGrey': 0x696969,
		'DodgerBlue': 0x1E90FF,
		'FireBrick': 0xB22222,
		'FloralWhite': 0xFFFAF0,
		'ForestGreen': 0x228B22,
		'Fuchsia': 0xFF00FF,
		'Gainsboro': 0xDCDCDC,
		'GhostWhite': 0xF8F8FF,
		'Gold': 0xFFD700,
		'GoldenRod': 0xDAA520,
		'Gray': 0x808080,
		'Grey': 0x808080,
		'Green': 0x008000,
		'GreenYellow': 0xADFF2F,
		'HoneyDew': 0xF0FFF0,
		'HotPink': 0xFF69B4,
		'IndianRed': 0xCD5C5C,
		'Indigo': 0x4B0082,
		'Ivory': 0xFFFFF0,
		'Khaki': 0xF0E68C,
		'Lavender': 0xE6E6FA,
		'LavenderBlush': 0xFFF0F5,
		'LawnGreen': 0x7CFC00,
		'LemonChiffon': 0xFFFACD,
		'LightBlue': 0xADD8E6,
		'LightCoral': 0xF08080,
		'LightCyan': 0xE0FFFF,
		'LightGoldenRodYellow': 0xFAFAD2,
		'LightGray': 0xD3D3D3,
		'LightGrey': 0xD3D3D3,
		'LightGreen': 0x90EE90,
		'LightPink': 0xFFB6C1,
		'LightSalmon': 0xFFA07A,
		'LightSeaGreen': 0x20B2AA,
		'LightSkyBlue': 0x87CEFA,
		'LightSlateGray': 0x778899,
		'LightSlateGrey': 0x778899,
		'LightSteelBlue': 0xB0C4DE,
		'LightYellow': 0xFFFFE0,
		'Lime': 0x00FF00,
		'LimeGreen': 0x32CD32,
		'Linen': 0xFAF0E6,
		'Magenta': 0xFF00FF,
		'Maroon': 0x800000,
		'MediumAquaMarine': 0x66CDAA,
		'MediumBlue': 0x0000CD,
		'MediumOrchid': 0xBA55D3,
		'MediumPurple': 0x9370D8,
		'MediumSeaGreen': 0x3CB371,
		'MediumSlateBlue': 0x7B68EE,
		'MediumSpringGreen': 0x00FA9A,
		'MediumTurquoise': 0x48D1CC,
		'MediumVioletRed': 0xC71585,
		'MidnightBlue': 0x191970,
		'MintCream': 0xF5FFFA,
		'MistyRose': 0xFFE4E1,
		'Moccasin': 0xFFE4B5,
		'NavajoWhite': 0xFFDEAD,
		'Navy': 0x000080,
		'OldLace': 0xFDF5E6,
		'Olive': 0x808000,
		'OliveDrab': 0x6B8E23,
		'Orange': 0xFFA500,
		'OrangeRed': 0xFF4500,
		'Orchid': 0xDA70D6,
		'PaleGoldenRod': 0xEEE8AA,
		'PaleGreen': 0x98FB98,
		'PaleTurquoise': 0xAFEEEE,
		'PaleVioletRed': 0xD87093,
		'PapayaWhip': 0xFFEFD5,
		'PeachPuff': 0xFFDAB9,
		'Peru': 0xCD853F,
		'Pink': 0xFFC0CB,
		'Plum': 0xDDA0DD,
		'PowderBlue': 0xB0E0E6,
		'Purple': 0x800080,
		'Red': 0xFF0000,
		'RosyBrown': 0xBC8F8F,
		'RoyalBlue': 0x4169E1,
		'SaddleBrown': 0x8B4513,
		'Salmon': 0xFA8072,
		'SandyBrown': 0xF4A460,
		'SeaGreen': 0x2E8B57,
		'SeaShell': 0xFFF5EE,
		'Sienna': 0xA0522D,
		'Silver': 0xC0C0C0,
		'SkyBlue': 0x87CEEB,
		'SlateBlue': 0x6A5ACD,
		'SlateGray': 0x708090,
		'SlateGrey': 0x708090,
		'Snow': 0xFFFAFA,
		'SpringGreen': 0x00FF7F,
		'SteelBlue': 0x4682B4,
		'Tan': 0xD2B48C,
		'Teal': 0x008080,
		'Thistle': 0xD8BFD8,
		'Tomato': 0xFF6347,
		'Turquoise': 0x40E0D0,
		'Violet': 0xEE82EE,
		'Wheat': 0xF5DEB3,
		'White': 0xFFFFFF,
		'WhiteSmoke': 0xF5F5F5,
		'Yellow': 0xFFFF00,
		'YellowGreen': 0x9ACD32
	}

	colorplus.RGBControl = new Class({
		'Implements': Events,

		'initialize': function () {
			var self = this;

			var ctrl = new Element('input', {
				'type': 'text',
				'size': 4
			});
			self._control = new Element('div').adopt([
				ctrl.clone(), ctrl.clone(), ctrl.clone()
			]);

			self._control.getChildren().addEvents({
				'change': function () {
					self.fireEvent('change', self._control.getChildren().map(function (item) {
						return $pick(Number.from(item.get('value')), 0).limit(0, 255);
					}).rgbToHex());
				}
			});
		},

		'toElement': function () {
			return this._control;
		},

		'update': function (color) {
			this._control.getChildren().each(function (ctrl, index) {
				var s = '' + color[index];
				if (ctrl.get('value') != s) {  // update text box only if value in box differs from value to set
					ctrl.set('value', color[index]);
				}
			});
		}
	});

	colorplus.ColorPicker = new Class({
		'Implements': Events,

		_hsb: [0,100,100],
		_mode: true,

		'initialize': function (mode) {
			var self = this;
			self._mode = mode;

			/**
			* Get color at canvas pixel.
			* @param {HTMLCanvasElement} canvas A 2D canvas.
			* @param event An event object with mouse coordinates (x,y) expressed in page coordinates.
			*/
			function _getCanvasColor(canvas, event) {
				var pos = canvas.getPosition();
				return Array.from(canvas.getContext('2d').getImageData(event.page.x - pos.x, event.page.y - pos.y, 1, 1).data);
			}

			function _fireChange(color) {
				self.fireEvent('change', color.rgbToHex());
			}

			new Element('div', {
				'class': _class('colors-picker')
			}).adopt(
				self._line = new Element('canvas', {
					'class': _class('colors-bar'),
					'events': {
						'click': function (event) {
							var color = _getCanvasColor(self._line, event);
							if (mode) {
								self._hsb[0] = color.rgbToHsb()[0];
								self.update();
							} else {
								_fireChange(color);
							}
						}
					}
				}),
				self._plane = new Element('canvas', {
					'class': _class('colors-plane'),
					'events': {
						'click': function (event) {
							var color = _getCanvasColor(self._plane, event);
							if (mode) {
								_fireChange(color);
							} else {
								var hsb = color.rgbToHsb();
								self._hsb = [hsb[0], hsb[1], self._hsb[2]];
								self.update();
							}
						}
					}
				})
			);

			// force canvas size to match CSS size
			self._setCanvasSize(self._line);
			self._setCanvasSize(self._plane);

			if (mode) {  // paint hue line and saturation/brightness plane, plane selects color
				// color line
				var line = self._line;
				var lineheight = line.height;
				var linecontext = line.getContext('2d');
				linecontext.save();

				// hue line
				for (var k = 0; k < 360; k++) {  // iterate over hue in range [0,359]
					var hexcolor = [k,100,100].hsbToHex();
					linecontext.strokeStyle = hexcolor;
					linecontext.fillStyle = hexcolor;  // hexadecimal representation of color as a string
					linecontext.fillRect(0, (lineheight * k / 360).toInt(), line.width, lineheight);
				}

				linecontext.restore();
			} else {  // paint hue/saturation plane and brightness line, line selects color
				// color plane
				var plane = self._plane;
				var planeheight = plane.height;
				var planewidth = plane.width;
				var planecontext = plane.getContext('2d');
				planecontext.save();

				// hue/saturation plane
				for (var i = 0; i < 360; i++) {  // iterate over hue in range [0,359]
					for (var j = 0; j <= 100; j++) {  // iterate over saturation in range [0,100]
						var hexcolor = [i,j,100].hsbToHex();
						planecontext.strokeStyle = hexcolor;
						planecontext.fillStyle = hexcolor;
						planecontext.fillRect((planewidth * i / 359).toInt(), (planeheight * j / 100).toInt(), planewidth, planeheight);
					}
				}

				planecontext.restore();
			}

			self.update();
		},

		'toElement': function () {
			return this._line.getParent();
		},

		_setCanvasSize: function (canvas) {
			canvas.height = canvas.getStyle('height').toInt();
			canvas.width = canvas.getStyle('width').toInt();
		},

		/**
		* Updates the color selected in the color picker.
		*/
		'update': function (rgb) {
			var self = this;

			// set color currently shown
			var hsb = self._hsb = rgb ? rgb.rgbToHsb() : self._hsb;

			if (self._mode) {  // paint hue line and saturation/brightness plane, plane selects color
				// color plane
				var plane = self._plane;
				self._setCanvasSize(plane);
				var planeheight = plane.height;
				var planewidth = plane.width;
				var planecontext = plane.getContext('2d');
				planecontext.save();

				// saturation/brightness plane
				for (var k = 0; k <= 100; k++) {  // iterate over brightness in range [0,100]
					// set up gradient
					var grad = planecontext.createLinearGradient(0, 0, planewidth, 0);
					grad.addColorStop(0, [hsb[0], 0, k].hsbToHex());    // zero saturation
					grad.addColorStop(1, [hsb[0], 100, k].hsbToHex());  // full saturation

					// draw gradient
					planecontext.fillStyle = grad;
					planecontext.fillRect(0, planeheight * k / 100, planewidth, planeheight);
				}

				planecontext.restore();
			} else {  // paint hue/saturation plane and brightness line, line selects color
				// color line
				var line = self._line;
				self._setCanvasSize(line);
				var lineheight = line.height;
				var linewidth = line.width;
				var linecontext = line.getContext('2d');
				linecontext.save();

				// brightness line
				var grad = linecontext.createLinearGradient(0, 0, linewidth, lineheight);  // set up gradient
				grad.addColorStop(0, [hsb[0], hsb[1], 0].hsbToHex());    // zero brightness
				grad.addColorStop(1, [hsb[0], hsb[1], 100].hsbToHex());  // full brightness
				linecontext.fillStyle = grad;
				linecontext.fillRect(0, 0, linewidth, lineheight);

				linecontext.restore();
			}
		}
	});

	colorplus.ColorsDialog = new Class({
		'Extends': colorplus.EditorDialog,

		'initialize': function (boundcontrol) {
			var self = this;
			self.parent(boundcontrol);

			self._title.adopt(
				new Element('span', {
					'class': _class('colors-title')
				})
			);

			var hascanvas = new Element('canvas').getContext;  // test for browser <canvas> support
			var named, rgb, hex, sample, picker1, picker2;

			/**
			* @param {string} color A valid CSS color string.
			*/
			function _updateColor(color) {
				if (color) {
					var hexcolor = /^#/.test(color) ? color : color.rgbToHex();  // convert rgb(255,255,255) or [255,255,255] to #ffffff
					if (hexcolor) {
						var rgbcolor = hexcolor.hexToRgb(true);  // convert #ffffff to [255,255,255]

						rgb.update(rgbcolor);
						hex.set('value', hexcolor);
						sample.setStyle('background-color', hexcolor);

						if (hascanvas) {
							picker1.update(rgbcolor);
							picker2.update(rgbcolor);
						}
					}
				}
			}

			var rows = [
				[
					new Element('span', {
						'class': _class('colors-named')
					}),
					named = new colorplus.DropDownList(Object.keys(colornames).map(function (colorname) {
						var colorhex = colornames[colorname].toString(16);
						return [
							new Element('div', {
								'class': _class('colors-sample'),
								'styles': {
									'background-color': '#'+ '000000'.substr(colorhex.length) + colorhex  // force 6 hexadecimal digits
								}
							}),
							new Element('span', {
								'html': colorname
							})
						];
					})).addEvent('change', function (listitem) {
						_updateColor(listitem.getElement('.colorplus-colors-sample').getStyle('background-color'));
					})
				],
				[
					new Element('span', {
						'class': _class('colors-rgb')
					}),
					rgb = new colorplus.RGBControl().addEvent('change', _updateColor)
				],
				[
					new Element('span', {
						'class': _class('colors-hex')
					}),
					hex = new Element('input', {
						'type': 'text',
						'size': 8
					}).addEvent('change', function () {
						var value = hex.get('value');
						/^#([0-9a-f]{3}){1,2}$/i.test(value) && _updateColor(value);  // only set valid color codes
					})
				],
				[
					new Element('span', {
						'class': _class('colors-sample')
					}),
					sample = new Element('div', {
						'class': _class('colors-sample')
					})
				]
			];

			if (window.Color && hascanvas) {
				rows.push([
					new Element('span', {
						'class': _class('colors-picker')
					}),
					[
						picker1 = new colorplus.ColorPicker(true).addEvent('change', _updateColor),
						picker2 = new colorplus.ColorPicker(false).addEvent('change', _updateColor)
					]
				]);
			}

			self._content.adopt(
				new Element('table').adopt(
					rows.map(function (cells) {
						return new Element('tr').adopt(
							cells.map(function (contents) {
								return new Element('td').adopt(contents);
							})
						)
					})
				),
				new Element('button', {
					'type': 'button',
					'html': 'OK',
					'events': {
						'click': function () {
							self.boundcontrol.set('value', sample.getStyle('background-color'));

							// close pop-up window
							self.hide();
						}
					}
				})
			)
		}
	});

	colorplus['bind'] = function (ctrl, button) {
		var dlg = new colorplus.ColorsDialog(ctrl);

		if (button) {
			button.addEvent('click', function () {
				// show dialog with size fit to contents
				dlg.show();
				dlg.resize();
			});
		}
	};

	window['colorplus'] = colorplus;
})(document.id);