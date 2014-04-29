/**@license sigplus Image Gallery Plus jQuery on-demand inclusion
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2009-2014 Levente Hunyadi.
 * @remarks Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
 * @see     http://hunyadi.info.hu/projects/sigplus
 **/

(function (minversion) {  // minimum jQuery version required
	function jQuery_version_compare() {
		function version_compare(current, required) {
			var cur = current.split('.');
			var req = required.split('.');
			for (var k = 0; k < req.length; k++) {
				var c = parseInt(cur[k]);
				var r = parseInt(req[k]);
				if (c == r) {  // check next component (equality fails on NaN)
					continue;
				}
				return c > r;  // returns false if required version is less than current version or one of the components is NaN
			}
			return true;
		}
		return version_compare(jQuery().jquery, minversion);
	}

	var haslibrary = typeof(jQuery) != 'undefined';

	// load required version
	if (!haslibrary || !jQuery_version_compare()) {
		google.load('jquery', '1');  // .js file will be loaded after this script terminates
	}
})('1.4');