/**@license sigplus Image Gallery Plus jQuery on-demand inclusion
 * @author  Levente Hunyadi
 * @version $__VERSION__$
 * @remarks Copyright (C) 2009-2011 Levente Hunyadi.
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
	var hasversion = haslibrary && jQuery_version_compare();
	
	// check if another, incompatible version of jQuery is present
	if (haslibrary && !hasversion) {
		alert('jQuery version '+ jQuery().jquery +' detected but sigplus requires '+ minversion +' or later; sigplus must overwrite the detected version, which might render scripts on the page inoperable. Make sure your page includes a single, most recent version of jQuery; it is recommended that you remove the reference to the other, older version.');
	}

	// load required version
	if (!hasversion) {
		google.load('jquery', '1');  // .js file will be loaded after this script terminates
	}
})('1.4');