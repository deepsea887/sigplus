<?php
/**
* @file
* @brief    sigplus Image Gallery Plus installer script
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus plug-in for Joomla
* Copyright 2009-2011 Levente Hunyadi
*
* sigplus is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* sigplus is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

class plgContentSIGPlusInstallerScript {
	function __construct($parent) { }

	function install($parent) { }

	function uninstall($parent) {
		self::removeCacheFolder();
	}

	function update($parent) { }

	function preflight($type, $parent) {
		switch ($type) {
			case 'install':
			case 'discover_install':
			case 'update':
				$message = 'This version of the sigplus extension is experimental, contains only a subset of the features a future stable release will, and is strictly for evaluation purposes only, no backward or forward compatibility with previous versions or the future stable release is guaranteed. Consequently, using this version on production sites is <b>NOT recommended</b>, install one of the stable versions from <a href="http://joomlacode.org/gf/project/sigplus/frs/">JoomlaCode</a> instead. If you run into any problems with this distribution or would like to make a feature request, do not contact the developer directly but <a href="http://code.google.com/p/sigplus/issues/list">submit an issue report</a>. You find preliminary documentation on the project <a href="http://code.google.com/p/sigplus/w/list">wiki pages</a>.';
				$app = JFactory::getApplication();
				$app->enqueueMessage($message, 'warning');

				$required = '1.5';  // minimum version required for upgrade installation to succeed
				if ((include_once JPATH_ROOT.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'version.php') !== false) {  // available since 1.5.0
					$current = SIGPLUS_VERSION;  // version number of installed plug-in
					$supported = $current === '$__'.'VERSION'.'__$' || version_compare($current, $required) >= 0;  // allow upgrading experimental versions
				} elseif (file_exists(JPATH_ROOT.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'core.php')) {  // available since 1.4.x
					$current = SIGPLUS_VERSION;
					$supported = false;
				} else {  // not yet installed
					$current = '';
					$supported = true;
				}

				if (!$supported) {
					//$string = JText::_('SIGPLUS_INSTALLER_VERSION_UPGRADE');
					$string = 'This distribution cannot upgrade the current version {$current} of the extension, version {$required} or later is required for an upgrade installation. Alternatively, you might wish to remove the previous version before installing this distribution.';
					$message = str_replace(array('{$current}','{$required}'), array($current, $required), $string);

					$app = JFactory::getApplication();
					$app->enqueueMessage($message, 'error');

					return false;
				}

				self::updateDatabase();
				break;
		}
	}

	function postflight($type, $parent) {
		switch ($type) {
			case 'update':
				self::removeCacheFolder(false);
			case 'install':  // runs after installation is complete
				self::checkDependencies();
				self::copyScriptLibrary();
				self::minifyAllStylesheets();
				self::populateDatabase();
				break;
		}
	}

	private static function checkDependencies() {
		require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'librarian.php';

		$app = JFactory::getApplication();
		if (!is_gd_supported() && !is_imagick_supported()) {
			$app->enqueueMessage(JText::_('SIGPLUS_INSTALLER_LIBRARY_IMAGE'), 'warning');
		}
		if (!is_xml_supported()) {
			$app->enqueueMessage(JText::_('SIGPLUS_INSTALLER_LIBRARY_XML'), 'warning');
		}
	}

	private static function copyScriptLibrary() {
		$targetpath = JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'jquery.js';
		$sourcepaths = array(
			'http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js',
			'http://ajax.microsoft.com/ajax/jquery/jquery-1.4.4.min.js',
			'http://code.jquery.com/jquery-1.4.4.min.js'
		);
		foreach ($sourcepaths as $sourcepath) {
			if (copy($sourcepath, $targetpath)) {
				return true;  // jQuery library successfully copied from a CDN source
			}
		}

		// jQuery library not copied from any CDN source
		$app = JFactory::getApplication();
		$app->enqueueMessage(sprintf(JText::_('SIGPLUS_INSTALLER_JQUERY'), '<pre>'.implode("\n", $sourcepaths).'</pre>', '<kbd>'.dirname($targetpath).'</kbd>'), 'warning');
		return false;
	}

	/**
	* Minifies a group of stylesheets in the specified folder.
	* To be used as a callback function to walkdir.
	*/
	public static function minifyStylesheets($path, $files, $folders) {
		foreach ($files as $file) {
			if (preg_match('/(?<!\.min)\.css$/', $file)) {
				// minify stylesheet files
				$stylesheet = Minify_CSS_Compressor::process(file_get_contents($path.DIRECTORY_SEPARATOR.$file));

				// substitute image URLs with data URIs
				$stylesheet = SIGPlusUriSubstitution::replace($stylesheet, $path.DIRECTORY_SEPARATOR.dirname($file), $count);

				// write file
				file_put_contents($path.DIRECTORY_SEPARATOR.basename($file,'.css').'.min.css', $stylesheet);
			}
		}
	}

	/**
	* Minifies stylesheets.
	*/
	private static function minifyAllStylesheets() {
		require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'filesystem.php';

		walkdir(JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus', array(), -1, array(__CLASS__, 'minifyStylesheets'));
	}

	/**
	* Cleans a cache folder.
	* @param {string} $folder The name of the folder whose contents to remove from the cache.
	*/
	private static function removeCacheFolder($complete = true) {
		$folder = JPATH_ROOT.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'sigplus';  // use site cache folder, not administrator cache folder
		if (file_exists($folder)) {
			$files = scandir($folder);
			if ($files !== false) {
				foreach ($files as $file) {
					if ($file[0] != '.') {  // skip parent directory entries and hidden files
						unlink($folder.DIRECTORY_SEPARATOR.$file);
					}
				}
				if ($complete) {
					rmdir($folder);
				}
			}
		}
	}

	/**
	* Drops and re-creates all tables in the database.
	*/
	private static function updateDatabase() {
		self::executeQueryInFile(dirname(__FILE__).DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'uninstall'.DIRECTORY_SEPARATOR.'mysql.sql');
		self::executeQueryInFile(dirname(__FILE__).DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'install'.DIRECTORY_SEPARATOR.'mysql.sql');
	}

	private static function executeQueryInFile($file) {
		$db = JFactory::getDBO();
		$contents = file_get_contents($file);
		if ($contents !== false) {
			$queries = $db->splitSql($contents);
			foreach ($queries as $query) {
				$query = trim($query);
				if ($query) {  // skip empty queries
					$db->setQuery($query);
					if (version_compare(JVERSION, '3.0') >= 0) {
						$db->execute();
					} else {
						$db->query();
					}
				}
			}
		}
	}

	private static function populateTable($values, $table, $field) {
		$db = JFactory::getDBO();
		foreach ($values as &$item) {
			$item = '('.$db->quote($item).')';  // e.g. ('Title')
		}
		$db->setQuery('INSERT INTO '.$db->quoteName($table).' ('.$db->quoteName($field).') VALUES '.implode(',', $values));
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
	}

	/**
	* Populates the database.
	*/
	private static function populateDatabase() {
		$db = JFactory::getDBO();

		// language codes
		$languages = array(
			'aa','ab','ae','af','ak','am','an','ar','as','av','ay','az','ba','be','bg','bh','bi','bm','bn','bo',
			'br','bs','ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv','dz','ee','el','en','eo','es',
			'et','eu','fa','ff','fi','fj','fo','fr','fy','ga','gd','gl','gn','gu','gv','ha','he','hi','ho','hr',
			'ht','hu','hy','hz','ia','id','ie','ig','ii','ik','io','is','it','iu','ja','jv','ka','kg','ki','kj',
			'kk','kl','km','kn','ko','kr','ks','ku','kv','kw','ky','la','lb','lg','li','ln','lo','lt','lu','lv',
			'mg','mh','mi','mk','ml','mn','mr','ms','mt','my','na','nb','nd','ne','ng','nl','nn','no','nr','nv',
			'ny','oc','oj','om','or','os','pa','pi','pl','ps','pt','qu','rm','rn','ro','ru','rw','sa','sc','sd',
			'se','sg','si','sk','sl','sm','sn','so','sq','sr','ss','st','su','sv','sw','ta','te','tg','th','ti',
			'tk','tl','tn','to','tr','ts','tt','tw','ty','ug','uk','ur','uz','ve','vi','vo','wa','wo','xh','yi',
			'yo','za','zh','zu'
		);
		self::populateTable($languages, '#__sigplus_language', 'lang');

		// country codes
		$countries = array(
			'AD','AE','AF','AG','AL','AM','AO','AQ','AR','AT','AU','AW','AZ','BA','BB','BD','BE','BF','BG','BH',
			'BI','BJ','BM','BN','BO','BR','BS','BT','BW','BY','BZ','CA','CD','CF','CG','CH','CI','CL','CM','CN',
			'CO','CR','CU','CV','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI',
			'FJ','FM','FO','FR','GA','GB','GD','GE','GF','GH','GM','GN','GP','GQ','GR','GT','GW','GY','HN','HR',
			'HT','HU','ID','IE','IL','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP','KE','KG','KH','KI','KM',
			'KN','KP','KR','KW','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','ME',
			'MG','MK','ML','MM','MN','MO','MQ','MR','MS','MU','MV','MW','MX','MY','MZ','NA','NE','NG','NI','NL',
			'NO','NP','NZ','OM','PA','PE','PG','PH','PK','PL','PS','PT','PW','PY','QA','RE','RO','RS','RU','RW',
			'SA','SB','SC','SD','SE','SH','SI','SK','SL','SM','SN','SO','SR','ST','SV','SY','SZ','TD','TF','TG',
			'TH','TJ','TL','TM','TN','TR','TT','TW','TZ','UA','UG','US','UY','UZ','VC','VE','VN','VU','YE','ZA',
			'ZM','ZW'
		);
		self::populateTable($countries, '#__sigplus_country', 'country');

		// discard existing metadata
		$db->setQuery('DELETE FROM '.$db->quoteName('#__sigplus_data'));
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
		$db->setQuery('DELETE FROM '.$db->quoteName('#__sigplus_property'));
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
		$db->setQuery('ALTER TABLE '.$db->quoteName('#__sigplus_property').' AUTO_INCREMENT = 1');

		// populate metadata store with properties
		require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'metadata.php';
		$properties = SIGPlusMetadataServices::getProperties();
		self::populateTable($properties, '#__sigplus_property', 'propertyname');
	}
}

class SIGPlusUriSubstitution {
	/**
	* The root path w.r.t. relative URLs are to be interpreted.
	*/
	private $root;

	private function __construct($root) {
		$this->root = $root;
	}

	public static function replace($contents, $path, &$count) {
		return preg_replace_callback(
			'#url\(("(?!\w+:)[^"]+"|\'(?!\w+:)[^\']+\'|(?!\w+:)[^()]+)\)#',  // (?!\w+:) is a negative lookahead assertion to skip absolute URLs
			array(new self($path), 'preg_replace'),
			$contents, -1, $count
		);
	}

	/**
	* Replaces an image URL reference with a data URI.
	* @param {array} $match A CSS url() function match.
	* This function is to be passed to preg_replace_callback.
	*/
	public function preg_replace($match) {
		$url = $match[1];
		if ($url[0] == '"' || $url == "'") {  // unquote quoted strings
			$url = trim($url, $url[0]);
		}
		$path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $url);
		if (file_exists($path) && ($imagedata = getimagesize($path)) !== false) {
			return 'url("'.self::datauri($imagedata['mime'], file_get_contents($path)).'")';
		} else {
			return $match[0];
		}
	}

	/**
	* Converts raw data to a data URI.
	* @param {string} $mime Content MIME type.
	* @param {string} $data Content raw data.
	*/
	private static function datauri($mime, $data) {
		return 'data:' . $mime . ';base64,' . base64_encode($data);
	}
}

/**
* Class Minify_CSS_Compressor
* @package Minify
*/

/**
* Compress CSS
*
* This is a heavy regex-based removal of whitespace, unnecessary
* comments and tokens, and some CSS value minimization, where practical.
* Many steps have been taken to avoid breaking comment-based hacks,
* including the ie5/mac filter (and its inversion), but expect tricky
* hacks involving comment tokens in 'content' value strings to break
* minimization badly. A test suite is available.
*
* @package Minify
* @author Stephen Clay <steve@mrclay.org>
* @author http://code.google.com/u/1stvamp/ (Issue 64 patch)
*/
class Minify_CSS_Compressor {

	/**
	* Minify a CSS string
	*
	* @param string $css
	*
	* @param array $options (currently ignored)
	*
	* @return string
	*/
	public static function process($css, $options = array())
	{
		$obj = new Minify_CSS_Compressor($options);
		return $obj->_process($css);
	}

	/**
	* @var array
	*/
	protected $_options = null;

	/**
	* Are we "in" a hack? I.e. are some browsers targetted until the next comment?
	*
	* @var bool
	*/
	protected $_inHack = false;


	/**
	* Constructor
	*
	* @param array $options (currently ignored)
	*/
	private function __construct($options) {
		$this->_options = $options;
	}

	/**
	* Minify a CSS string
	*
	* @param string $css
	*
	* @return string
	*/
	protected function _process($css)
	{
		$css = str_replace("\r\n", "\n", $css);

		// preserve empty comment after '>'
		// http://www.webdevout.net/css-hacks#in_css-selectors
		$css = preg_replace('@>/\\*\\s*\\*/@', '>/*keep*/', $css);

		// preserve empty comment between property and value
		// http://css-discuss.incutio.com/?page=BoxModelHack
		$css = preg_replace('@/\\*\\s*\\*/\\s*:@', '/*keep*/:', $css);
		$css = preg_replace('@:\\s*/\\*\\s*\\*/@', ':/*keep*/', $css);

		// apply callback to all valid comments (and strip out surrounding ws
		$css = preg_replace_callback('@\\s*/\\*([\\s\\S]*?)\\*/\\s*@'
			,array($this, '_commentCB'), $css);

		// remove ws around { } and last semicolon in declaration block
		$css = preg_replace('/\\s*{\\s*/', '{', $css);
		$css = preg_replace('/;?\\s*}\\s*/', '}', $css);

		// remove ws surrounding semicolons
		$css = preg_replace('/\\s*;\\s*/', ';', $css);

		// remove ws around urls
		$css = preg_replace('/
				url\\(      # url(
				\\s*
				([^\\)]+?)  # 1 = the URL (really just a bunch of non right parenthesis)
				\\s*
				\\)         # )
			/x', 'url($1)', $css);

		// remove ws between rules and colons
		$css = preg_replace('/
				\\s*
				([{;])              # 1 = beginning of block or rule separator
				\\s*
				([\\*_]?[\\w\\-]+)  # 2 = property (and maybe IE filter)
				\\s*
				:
				\\s*
				(\\b|[#\'"-])        # 3 = first character of a value
			/x', '$1$2:$3', $css);

		// remove ws in selectors
		$css = preg_replace_callback('/
				(?:              # non-capture
					\\s*
					[^~>+,\\s]+  # selector part
					\\s*
					[,>+~]       # combinators
				)+
				\\s*
				[^~>+,\\s]+      # selector part
				{                # open declaration block
			/x'
			,array($this, '_selectorsCB'), $css);

		// minimize hex colors
		$css = preg_replace('/([^=])#([a-f\\d])\\2([a-f\\d])\\3([a-f\\d])\\4([\\s;\\}])/i'
			, '$1#$2$3$4$5', $css);

		// remove spaces between font families
		$css = preg_replace_callback('/font-family:([^;}]+)([;}])/'
			,array($this, '_fontFamilyCB'), $css);

		$css = preg_replace('/@import\\s+url/', '@import url', $css);

		// replace any ws involving newlines with a single newline
		$css = preg_replace('/[ \\t]*\\n+\\s*/', "\n", $css);

		// separate common descendent selectors w/ newlines (to limit line lengths)
		$css = preg_replace('/([\\w#\\.\\*]+)\\s+([\\w#\\.\\*]+){/', "$1\n$2{", $css);

		// Use newline after 1st numeric value (to limit line lengths).
		$css = preg_replace('/
			((?:padding|margin|border|outline):\\d+(?:px|em)?) # 1 = prop : 1st numeric value
			\\s+
			/x'
			,"$1\n", $css);

		// prevent triggering IE6 bug: http://www.crankygeek.com/ie6pebug/
		$css = preg_replace('/:first-l(etter|ine)\\{/', ':first-l$1 {', $css);

		return trim($css);
	}

	/**
	* Replace what looks like a set of selectors
	*
	* @param array $m regex matches
	*
	* @return string
	*/
	protected function _selectorsCB($m)
	{
		// remove ws around the combinators
		return preg_replace('/\\s*([,>+~])\\s*/', '$1', $m[0]);
	}

	/**
	* Process a comment and return a replacement
	*
	* @param array $m regex matches
	*
	* @return string
	*/
	protected function _commentCB($m)
	{
		$hasSurroundingWs = (trim($m[0]) !== $m[1]);
		$m = $m[1];
		// $m is the comment content w/o the surrounding tokens,
		// but the return value will replace the entire comment.
		if ($m === 'keep') {
			return '/**/';
		}
		if ($m === '" "') {
			// component of http://tantek.com/CSS/Examples/midpass.html
			return '/*" "*/';
		}
		if (preg_match('@";\\}\\s*\\}/\\*\\s+@', $m)) {
			// component of http://tantek.com/CSS/Examples/midpass.html
			return '/*";}}/* */';
		}
		if ($this->_inHack) {
			// inversion: feeding only to one browser
			if (preg_match('@
					^/               # comment started like /*/
					\\s*
					(\\S[\\s\\S]+?)  # has at least some non-ws content
					\\s*
					/\\*             # ends like /*/ or /**/
				@x', $m, $n)) {
				// end hack mode after this comment, but preserve the hack and comment content
				$this->_inHack = false;
				return "/*/{$n[1]}/**/";
			}
		}
		if (substr($m, -1) === '\\') { // comment ends like \*/
			// begin hack mode and preserve hack
			$this->_inHack = true;
			return '/*\\*/';
		}
		if ($m !== '' && $m[0] === '/') { // comment looks like /*/ foo */
			// begin hack mode and preserve hack
			$this->_inHack = true;
			return '/*/*/';
		}
		if ($this->_inHack) {
			// a regular comment ends hack mode but should be preserved
			$this->_inHack = false;
			return '/**/';
		}
		// Issue 107: if there's any surrounding whitespace, it may be important, so
		// replace the comment with a single space
		return $hasSurroundingWs // remove all other comments
			? ' '
			: '';
	}

	/**
	* Process a font-family listing and return a replacement
	*
	* @param array $m regex matches
	*
	* @return string
	*/
	protected function _fontFamilyCB($m)
	{
		// Issue 210: must not eliminate WS between words in unquoted families
		$pieces = preg_split('/(\'[^\']+\'|"[^"]+")/', $m[1], null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$out = 'font-family:';
		while (null !== ($piece = array_shift($pieces))) {
			if ($piece[0] !== '"' && $piece[0] !== "'") {
				$piece = preg_replace('/\\s+/', ' ', $piece);
				$piece = preg_replace('/\\s?,\\s?/', ',', $piece);
			}
			$out .= $piece;
		}
		return $out . $m[2];
	}
}