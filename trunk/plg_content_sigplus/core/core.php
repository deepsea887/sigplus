<?php
/**
* @file
* @brief    sigplus Image Gallery Plus plug-in for Joomla
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus plug-in for Joomla
* Copyright 2009-2014 Levente Hunyadi
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

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'version.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'exception.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'filesystem.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'params.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'imagegenerator.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'engines.php';

define('SIGPLUS_TEST', 0);
define('SIGPLUS_CREATE', 1);
define('SIGPLUS_CAPTION_CLIENT', true);  // apply template to caption text on client side

/**
* Interface for logging services.
*/
interface SIGPlusLoggingService {
	public function appendStatus($message);
	public function appendError($message);
	public function appendCodeBlock($message, $block);
	public function fetch();
}

/**
* A service that compiles a dynamic HTML-based log.
*/
class SIGPlusHTMLLogging implements SIGPlusLoggingService {
	/** Error log. */
	private $log = array();

	/**
	* Appends an informational message to the log.
	*/
	public function appendStatus($message) {
		$this->log[] = $message;
	}

	/**
	* Appends a critical error message to the log.
	*/
	public function appendError($message) {
		$this->log[] = $message;
	}

	/**
	* Appends an informational message to the log with a code block.
	*/
	public function appendCodeBlock($message, $block) {
		$this->log[] = $message."\n".'<pre class="sigplus-log">'.htmlspecialchars($block).'</pre>';
	}

	public function fetch() {
		$document = JFactory::getDocument();

		//$document->addScript(JURI::base(true).'/media/sigplus/js/log.js');  // language-neutral
		$script = file_get_contents(JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'log.js');
		if ($script !== false) {
			$script = str_replace(array("'Show'","'Hide'"), array("'".JText::_('JSHOW')."'","'".JText::_('JHIDE')."'"), $script);
			$document->addScriptDeclaration($script);
		}

		ob_start();
			print '<ul class="sigplus-log">';
			foreach ($this->log as $logentry) {
				print '<li>'.$logentry.'</li>';
			}
			print '</ul>';
			$this->log = array();
		return ob_get_clean();
	}
}

/**
* A service that does not perform any actual logging.
*/
class SIGPlusNoLogging implements SIGPlusLoggingService {
	public function appendStatus($message) {
	}

	public function appendError($message) {
	}

	public function appendCodeBlock($message, $block) {
	}

	public function fetch() {
		return null;
	}
}

/**
* Logging services.
*/
class SIGPlusLogging {
	/** Singleton instance. */
	private static $instance;

	public static function setService(SIGPlusLoggingService $service) {
		self::$instance = $service;
	}

	public static function appendStatus($message) {
		self::$instance->appendStatus($message);
	}

	public static function appendError($message) {
		self::$instance->appendError($message);
	}

	public static function appendCodeBlock($message, $block) {
		self::$instance->appendCodeBlock($message, $block);
	}

	public static function fetch() {
		return self::$instance->fetch();
	}
}
SIGPlusLogging::setService(new SIGPlusNoLogging());  // disable logging

class SIGPlusUser {
	/**
	* The normalized user group title for the currently logged-in user.
	*/
	public static function getCurrentUserGroup() {
		$user = JFactory::getUser();
		if ($user->guest) {
			return false;
		}

		// get all groups the user is member of, but not inherited groups
		$groups = JAccess::getGroupsByUser($user->id, false);
		if (count($groups) < 1) {
			return false;  // not a member of any group
		}

		// get first group out of all groups the user may be a member of
		$group = $groups[0];

		// get the group title from the database
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query
			->select('grp.title')
			->from('`#__usergroups` AS grp')
			->where('grp.id = '.$group)
		;
		$db->setQuery($query);
		$groupname = $db->loadResult();

		if ($groupname) {
			return $groupname;
		} else {
			return false;
		}
	}
}

/**
* Database layer.
*/
class SIGPlusDatabase {
	/**
	* Convert a wildcard pattern to an SQL LIKE pattern.
	*/
	public static function sqlpattern($pattern) {
		// replace "*" and "?" with LIKE expression equivalents "%" and "_"
		$pattern = str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $pattern);
		$pattern = str_replace(array('*','?'), array('%','_'), $pattern);
		return $pattern;
	}

	/**
	* Convert a timestamp to "yyyy-mm-dd hh:nn:ss" format.
	*/
	public static function sqldate($timestamp) {
		if (isset($timestamp)) {
			if (is_int($timestamp)) {
				return gmdate('Y-m-d H:i:s', $timestamp);
			} else {
				return $timestamp;
			}
		} else {
			return gmdate('Y-m-d H:i:s');
		}
	}

	/**
	* Quote column identifier names.
	*/
	private static function quoteColumns(array $cols) {
		$db = JFactory::getDbo();

		// quote identifier names
		foreach ($cols as &$col) {
			$col = $db->quoteName($col);
		}
		return $cols;
	}

	/**
	* Type-safe value quoting.
	*/
	public static function quoteValue($value) {
		if (is_string($value)) {
			$db = JFactory::getDbo();
			return $db->quote($value);
		} elseif (is_bool($value)) {
			return $value ? 1 : 0;
		} elseif (!is_numeric($value)) {
			return 'NULL';
		} else {
			return $value;
		}
	}

	private static function quoteValues(array $row) {
		$db = JFactory::getDbo();
		foreach ($row as &$entry) {
			if (is_string($entry)) {
				$entry = $db->quote($entry);
			} elseif (is_bool($entry)) {
				$entry = $entry ? 1 : 0;
			} elseif (!is_numeric($entry)) {
				$entry = 'NULL';
			}
		}
		return $row;
	}

	/**
	* The database identifier that belongs to an ISO language code.
	*/
	public static function getLanguageId($language) {
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->quoteName('langid').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_language').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->quoteName('lang').' = '.$db->quote($language)
		);
		return $db->loadResult();
	}

	/**
	* The database identifier that belongs to an ISO country code.
	*/
	public static function getCountryId($country) {
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->quoteName('countryid').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_country').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->quoteName('country').' = '.$db->quote($country)
		);
		return $db->loadResult();
	}

	public static function getInsertBatchStatement($table, array $cols, array $rows, array $keys = null, array $constants = null) {
		$db = JFactory::getDbo();

		// quote identifier names
		if (isset($keys)) {
			$keys = self::quoteColumns($keys);
		}

		// build column name array and quote column names
		if (isset($constants)) {
			$cols = array_merge(array_values($cols), array_keys($constants));  // append constant value columns
		}
		$cols = self::quoteColumns($cols);

		// build update closure
		$update = array();
		foreach ($cols as $col) {
			if (!isset($keys) || !in_array($col, $keys)) {  // there are no keys or column is not a key
				$update[] = $col.' = VALUES('.$col.')';
			}
		}

		// build insert closure
		foreach ($rows as &$row) {
			$row = self::quoteValues($row);

			if (isset($constants)) {
				foreach ($constants as $constant) {  // append constants
					$row[] = $constant;
				}
			}

			$row = '('.implode(',',$row).')';
		}
		unset($row);

		if (!empty($rows)) {
			return
				'INSERT INTO '.$db->quoteName($table).' ('.implode(',',$cols).')'.PHP_EOL.
				'VALUES '.implode(',',$rows).PHP_EOL.
				'ON DUPLICATE KEY UPDATE '.implode(', ',$update);
		} else {
			return false;
		}
	}

	/**
	* Insert multiple rows into the database in a batch with updates.
	*/
	public static function insertBatch($table, array $cols, array $rows, $keys = null, array $constants = null) {
		if (($statement = self::getInsertBatchStatement($table, $cols, $rows, $keys, $constants)) !== false) {
			$db = JFactory::getDbo();
			$db->setQuery($statement);
			if (version_compare(JVERSION, '3.0') >= 0) {
				$db->execute();
			} else {
				$db->query();
			}
		}
	}

	/**
	* Insert a single row into a table with unique key matching and duplicate update.
	* @param {string} $table The name of the table to update or insert the row into.
	* @param {array} $cols The name of the columns the values correspond to.
	* @param {array} $values The values to insert or overwrite existing values with.
	* @param {string} $lastkey The name of the auto-increment column.
	* @return {int} The auto-increment key for the updated or inserted row.
	*/
	public static function insertSingleUnique($table, array $cols, array $values, $lastkey = null) {
		$db = JFactory::getDbo();

		// quote identifier names
		$cols = self::quoteColumns($cols);
		if (isset($lastkey)) {
			$lastkey = $db->quoteName($lastkey);
		}

		// build update closure
		$update = array();
		if (isset($lastkey)) {
			$update[] = $lastkey.' = LAST_INSERT_ID('.$lastkey.')';
		}
		foreach ($cols as $col) {
			$update[] = $col.' = VALUES('.$col.')';
		}

		// build insert closure
		$values = self::quoteValues($values);
		$values = '('.implode(',',$values).')';

		$db->setQuery(
			'INSERT INTO '.$db->quoteName($table).' ('.implode(',',$cols).')'.PHP_EOL.
			'VALUES '.$values.PHP_EOL.
			'ON DUPLICATE KEY UPDATE '.implode(', ',$update)
		);
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
		if (isset($lastkey)) {
			$db->setQuery('SELECT LAST_INSERT_ID()');
			return (int) $db->loadResult();
		}
	}

	public static function replaceSingle($table, array $cols, array $values) {
		$db = JFactory::getDbo();

		// quote identifier names
		$cols = self::quoteColumns($cols);

		// build insert closure
		$values = self::quoteValues($values);
		$values = '('.implode(',',$values).')';

		$db->setQuery(
			'REPLACE INTO '.$db->quoteName($table).' ('.implode(',',$cols).')'.PHP_EOL.
			'VALUES '.$values
		);
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
		$db->setQuery('SELECT LAST_INSERT_ID()');
		return (int) $db->loadResult();
	}

	public static function executeAll(array $queries) {
		$db = JFactory::getDbo();

		// execute as one transaction
		$db->transactionStart();
		foreach ($queries as $query) {
			$db->setQuery($query);
			if (version_compare(JVERSION, '3.0') >= 0) {
				$db->execute();
			} else {
				$db->query();
			}
		}
		$db->transactionCommit();
	}
}

/**
* Measures execution time and prevents time-outs.
*/
class SIGPlusTimer {
	private static $timeout_count = 0;

	private static function getStartedTime() {
		return time();  // save current timestamp
	}

	private static function getMaximumDuration() {
		$duration = ini_get('max_execution_time');
		if ($duration) {
			$duration = (int)$duration;
		} else {
			$duration = 0;
		}

		if ($duration >= 10) {
			return $duration - 5;
		} else {
			return 10;  // a feasible guess
		}
	}

	/**
	* Short-circuit plug-in activation if allotted execution time has already been used up.
	*/
	public static function shortcircuit() {
		return SIGPlusTimer::$timeout_count > 0;
	}

	/**
	* Check whether execution time is within the allotted maximum limit.
	*/
	public static function checkpoint() {
		static $started_time;
		static $maximum_duration;

		// initialize static variables
		isset($started_time) || $started_time = SIGPlusTimer::getStartedTime();
		isset($maximum_duration) || $maximum_duration = SIGPlusTimer::getMaximumDuration();

		if (time() >= $started_time + $maximum_duration) {
			SIGPlusTimer::$timeout_count++;
			throw new SIGPlusTimeoutException();
		}
	}
}

class SIGPlusLabels {
	private $multilingual = false;
	private $caption_source = 'labels.txt';

	public function __construct(SIGPlusConfigurationParameters $config) {
		$this->multilingual = $config->service->multilingual;
		$this->caption_source = $config->gallery->caption_source;
	}

	/**
	* Finds language-specific labels files.
	* @param {string} $imagefolder An absolute path or URL to a directory with labels files.
	* @return {array} A list of full paths to the language-specific labels files.
	*/
	public function getLabelsFilePaths($imagefolder) {
		$sources = array();

		// get labels source file name components
		$labelsname = pathinfo($this->caption_source, PATHINFO_FILENAME);
		$labelsextn = pathinfo($this->caption_source, PATHINFO_EXTENSION);
		$labelssuff = '.'.( $labelsextn ? $labelsextn : 'txt' );

		// read default (language-neutral) labels file
		$file = $imagefolder.DIRECTORY_SEPARATOR.$labelsname.$labelssuff;  // filesystem path to labels file
		if (is_file($file)) {
			$lang = JFactory::getLanguage();
			$tag = $lang->getTag();  // use site default language
			$sources[$tag] = $file;  // language tag has format hu-HU or en-GB
		}

		if ($this->multilingual) {
			// look for language-specific labels files in folder
			$files = fsx::scandir($imagefolder);
			foreach ($files as $file) {
				if (preg_match('#'.preg_quote($labelsname, '#').'[.]([a-z]{2}-[A-Z]{2})'.preg_quote($labelssuff, '#').'$#Su', $file, $matches)) {
					$tag = $matches[1];
					$file = $imagefolder.DIRECTORY_SEPARATOR.$labelsname.'.'.$tag.$labelssuff;
					if (is_file($file)) {
						$sources[$tag] = $file;  // assignment may overwrite entry for default language
					}
				}
			}
		}

		return $sources;
	}

	/**
	* Extract short captions and descriptions attached to images from a "labels.txt" file.
	*/
	private function parseLabels($labelspath, &$entries, &$patterns) {
		$entries = array();
		$patterns = array();

		$imagefolder = dirname($labelspath);

		// read file contents
		$contents = file_get_contents($labelspath);
		if ($contents === false) {
			return false;
		}

		// verify file type
		if (!strcmp('{\rtf', substr($contents,0,5))) {  // file has type "rich text format" (RTF)
			throw new SIGPlusTextFormatException($labelspath);
		}

		// remove UTF-8 BOM and normalize line endings
		if (!strcmp("\xEF\xBB\xBF", substr($contents,0,3))) {  // file starts with UTF-8 BOM
			$contents = substr($contents, 3);  // remove UTF-8 BOM
		}
		$contents = str_replace("\r", "\n", $contents);  // normalize line endings

		// split into lines
		$matches = array();
		preg_match_all('/^([^|\n]+)(?:[|]([^|\n]*)(?:[|]([^\n]*))?)?$/mu', $contents, $matches, PREG_SET_ORDER);
		switch (preg_last_error()) {
			case PREG_BAD_UTF8_ERROR:
				throw new SIGPlusTextFormatException($labelspath);
		}

		// parse individual entries
		$priority = 0;
		$index = 0;  // counter for entry order
		foreach ($matches as $match) {
			$imagefile = $match[1];
			$title = count($match) > 2 ? $match[2] : null;
			$summary = count($match) > 3 ? $match[3] : null;

			if (strpos($imagefile, '*') !== false || strpos($imagefile, '?') !== false) {  // contains wildcard character
				$pattern = new stdClass;
				$pattern->match = SIGPlusDatabase::sqlpattern($imagefile);  // replace "*" and "?" with LIKE expression equivalents "%" and "_"
				$pattern->priority = ++$priority;
				$pattern->title = $title;
				$pattern->summary = $summary;
				$patterns[] = $pattern;
			} else {
				if (is_url_http($imagefile)) {  // a URL to a remote image
					$imagefile = safeurlencode($imagefile);
				} else {  // a local image
					$imagefile = str_replace('/', DIRECTORY_SEPARATOR, $imagefile);
					$imagefile = file_exists_case_insensitive($imagefolder.DIRECTORY_SEPARATOR.$imagefile);
					if ($imagefile === false) {  // check that image file truly exists
						continue;
					}
					$imagefile = $imagefolder.DIRECTORY_SEPARATOR.$imagefile;
				}

				// prepare data for injection into database
				$entry = new stdClass;
				$entry->file = $imagefile;
				$entry->index = ++$index;
				$entry->title = $title;
				$entry->summary = $summary;
				$entries[] = $entry;
			}
		}
		return true;
	}

	public function populate($imagefolder, $folderid) {
		$db = JFactory::getDbo();
		$queries = array();

		// force type to prevent SQL injection
		$folderid = (int)$folderid;

		// delete existing data
		$queries[] =
			'DELETE FROM '.$db->quoteName('#__sigplus_foldercaption').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->quoteName('folderid').' = '.$folderid
			;

		// invalidate existing labels data
		$queries[] =
			'DELETE c'.PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_caption').' AS c'.PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_image').' AS i'.PHP_EOL.
				'ON c.'.$db->quoteName('imageid').' = i.'.$db->quoteName('imageid').PHP_EOL.
			'WHERE'.PHP_EOL.
				'i.'.$db->quoteName('folderid').' = '.$folderid
			;

		$sources = $this->getLabelsFilePaths($imagefolder);
		foreach ($sources as $languagetag => $source) {
			// fetch language and country database identifier
			list($language, $country) = explode('-', $languagetag);
			$langid = (int)SIGPlusDatabase::getLanguageId($language);
			$countryid = (int)SIGPlusDatabase::getCountryId($country);
			if (!$langid || !$countryid) {  // language does not exist
				continue;
			}

			// extract captions and patterns from labels source
			$this->parseLabels($source, $entries, $patterns);
			SIGPlusLogging::appendStatus(count($entries).' caption(s) and '.count($patterns).' pattern(s) extracted from <code>'.$source.'</code>.');

			// update title and description patterns
			if (!empty($patterns)) {
				$rows = array();
				foreach ($patterns as $pattern) {
					$row = array(
						$folderid,
						$db->quote($pattern->match),
						$langid,
						$countryid,
						$pattern->priority,
						$db->quote($pattern->title),
						$db->quote($pattern->summary)
					);
					$rows[] = '('.implode(',',$row).')';
				}

				// add captions matched with patterns
				$queries[] =
					'INSERT INTO '.$db->quoteName('#__sigplus_foldercaption').' ('.
						$db->quoteName('folderid').','.
						$db->quoteName('pattern').','.
						$db->quoteName('langid').','.
						$db->quoteName('countryid').','.
						$db->quoteName('priority').','.
						$db->quoteName('title').','.
						$db->quoteName('summary').
					')'.PHP_EOL.
					'VALUES '.implode(',',$rows)
				;
			}

			// insert new labels data
			if (!empty($entries)) {
				$rows = array();
				foreach ($entries as $entry) {
					$row = array(
						'(SELECT '.$db->quoteName('imageid').' FROM '.$db->quoteName('#__sigplus_image').' WHERE '.$db->quoteName('fileurl').' = '.$db->quote($entry->file).')',  // look up image identifier that belongs to unique file URL
						$langid,
						$countryid,
						$entry->index,
						$db->quote($entry->title),
						$db->quote($entry->summary)
					);
					$rows[] = '('.implode(',',$row).')';
				}

				// add captions
				$queries[] =
					'INSERT INTO '.$db->quoteName('#__sigplus_caption').' ('.
						$db->quoteName('imageid').','.
						$db->quoteName('langid').','.
						$db->quoteName('countryid').','.
						$db->quoteName('ordnum').','.
						$db->quoteName('title').','.
						$db->quoteName('summary').
					')'.PHP_EOL.
					'VALUES '.implode(',',$rows)
				;
			}
		}

		SIGPlusDatabase::executeAll($queries);
	}
}

class SIGPlusImageMetadata {
	private $imagepath;
	private $metadata;

	/**
	* Fetches metadata associated with an image.
	*/
	public function __construct($imagepath) {
		$this->imagepath = $imagepath;

		require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'metadata.php';
		$this->metadata = SIGPlusMetadataServices::getImageMetadata($imagepath);
	}

	/**
	* Adds image metadata to the database.
	*/
	public function inject($imageid) {
		// insert image metadata
		if ($this->metadata !== false) {
			SIGPlusLogging::appendStatus('Metadata available in image <code>'.$this->imagepath.'</code> [id='.$imageid.'].');
			$entries = array();

			foreach ($this->metadata as $key => $metavalue) {
				$keyid = SIGPlusMetadataServices::getPropertyNumericKey($key);
				if ($keyid) {  // key maps to a numeric identifier
					if (is_array($metavalue)) {
						$value = implode(';', $metavalue);
					} else {
						$value = (string) $metavalue;
					}
					$entries[] = array($keyid, $value);
				}
			}

			SIGPlusDatabase::insertBatch(
				'#__sigplus_data',
				array('propertyid','textvalue'),
				$entries,
				null,
				array('imageid' => $imageid)
			);
		}
	}
}

/**
* Base class for gallery generators.
*/
abstract class SIGPlusGalleryBase {
	protected $config;

	public function __construct(SIGPlusConfigurationParameters $config) {
		$this->config = $config;
	}

	public abstract function populate($url, $folderparams);

	/**
	* Query a folder identifier for a folder with matching parameters.
	*/
	private function getFolder($url, $folderparams) {
		$datetime = SIGPlusDatabase::sqldate($folderparams->time);

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select(array('folderid','foldertime','entitytag'))
			->from('#__sigplus_folder')
			->where('folderurl = '.$db->quote($url))
		;
		$db->setQuery($query);
		$row = $db->loadRow();
		if ($row !== false) {
			list($folderid, $foldertime, $entitytag) = $row;
			if ($datetime == $foldertime && $entitytag == $folderparams->entitytag) {  // no changes to folder
				return $folderid;
			}
		}
		return false;
	}

	/**
	* Insert or update data associated with a folder URL.
	*/
	private function updateFolder($url, $folderparams, $replace = false, array $ancestors = array()) {
		$datetime = SIGPlusDatabase::sqldate($folderparams->time);

		// insert folder data
		if ($replace) {
			// delete and insert data
			$folderid = SIGPlusDatabase::replaceSingle(
				'#__sigplus_folder',
				array('folderurl', 'foldertime', 'entitytag'),
				array($url, $datetime, $folderparams->entitytag)
			);
		} else {
			if (!($folderid = $this->getFolder($url, $folderparams))) {
				// insert folder data with replacement on duplicate key
				$folderid = SIGPlusDatabase::insertSingleUnique(
					'#__sigplus_folder',
					array('folderurl', 'foldertime', 'entitytag'),
					array($url, $datetime, $folderparams->entitytag),
					'folderid'
				);
			}
		}

		// insert folder hierarchy data
		$entries = array(
			array($folderid, 0)
		);
		$ancestors = array_values($ancestors);  // re-index array
		foreach ($ancestors as $depth => $ancestor) {
			$entries[] = array($ancestor, $depth + 1);
		}
		SIGPlusDatabase::insertBatch(
			'#__sigplus_hierarchy',
			array(
				'ancestorid',
				'depthnum'
			),
			$entries,
			null,
			array('descendantid' => $folderid)
		);

		return $folderid;
	}

	protected function insertFolder($url, $folderparams, array $ancestors = array()) {
		return $this->updateFolder($url, $folderparams, false, $ancestors);
	}

	protected function replaceFolder($url, $folderparams, array $ancestors = array()) {
		return $this->updateFolder($url, $folderparams, true, $ancestors);
	}

	protected function getViewHash($folderid) {
		return md5(
			$folderid.' '.
			$this->config->gallery->preview_width . ($this->config->gallery->preview_crop ? 'x' : 's') . $this->config->gallery->preview_height . ' ' .
			( $this->config->gallery->watermark_position !== false
			? $this->config->gallery->watermark_x . $this->config->gallery->watermark_position . $this->config->gallery->watermark_y . '@' . $this->config->gallery->watermark_source
			: ''
			),
			true
		);
	}

	protected function getView($folderid) {
		$db = JFactory::getDbo();
		$folderid = (int) $folderid;
		$hash = $this->getViewHash($folderid);

		// verify if preview image parameters for the folder have changed
		$query = $db->getQuery(true);
		$query
			->select('viewid')
			->from('#__sigplus_view')
			->where(
				array(
					'folderid = '.$folderid,
					'hash = '.$db->quote($hash)
				)
			)
		;
		$db->setQuery($query);
		return $db->loadResult();
	}

	protected function insertView($folderid) {
		$folderid = (int) $folderid;
		if ($viewid = $this->getView($folderid)) {
			return $viewid;
		} else {
			return SIGPlusDatabase::insertSingleUnique(
				'#__sigplus_view',
				array('folderid', 'hash', 'preview_width', 'preview_height', 'preview_crop'),
				array($folderid, $this->getViewHash($folderid), $this->config->gallery->preview_width, $this->config->gallery->preview_height, $this->config->gallery->preview_crop),
				'viewid'
			);
		}
	}

	protected function replaceView($folderid) {
		return SIGPlusDatabase::replaceSingle(
			'#__sigplus_view',
			array('folderid', 'hash', 'preview_width', 'preview_height', 'preview_crop'),
			array($folderid, $this->getViewHash($folderid), $this->config->gallery->preview_width, $this->config->gallery->preview_height, $this->config->gallery->preview_crop)
		);
	}

	private function unlinkGeneratedImage($path, $filetime) {
		if ($path && file_exists($path) && $filetime == fsx::filemdate($path)) {
			unlink($path);
		}
	}

	/**
	* Removes an image from the file system that has been obsoleted by updated configuration settings.
	*/
	protected function cleanGeneratedImages($imageid, $viewid = null) {
		$db = JFactory::getDbo();
		$imageid = (int) $imageid;

		if (isset($viewid)) {
			$viewid = (int) $viewid;
			$cond = ' AND '.$db->quoteName('viewid').' = '.$viewid;
		} else {
			$cond = '';
		}

		// verify if preview image parameters for the folder have changed
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->quoteName('preview_fileurl').','.PHP_EOL.
				$db->quoteName('preview_filetime').','.PHP_EOL.
				$db->quoteName('thumb_fileurl').','.PHP_EOL.
				$db->quoteName('thumb_filetime').','.PHP_EOL.
				$db->quoteName('watermark_fileurl').','.PHP_EOL.
				$db->quoteName('watermark_filetime').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_imageview').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->quoteName('imageid').' = '.$imageid.$cond
		);
		$rows = $db->loadRowList();

		if (!empty($rows)) {
			foreach ($rows as $row) {
				list($preview_path, $preview_filetime, $thumb_path, $thumb_filetime, $watermark_path, $watermark_filetime) = $row;

				// delete obsoleted images
				$this->unlinkGeneratedImage($preview_path, $preview_filetime);
				$this->unlinkGeneratedImage($thumb_path, $thumb_filetime);
				$this->unlinkGeneratedImage($watermark_path, $watermark_filetime);
			}

			// remove entries from the database
			$db->setQuery(
				'DELETE FROM '.$db->quoteName('#__sigplus_imageview').PHP_EOL.
				'WHERE'.PHP_EOL.
					$db->quoteName('imageid').' = '.$imageid.$cond
			);
			if (version_compare(JVERSION, '3.0') >= 0) {
				$db->execute();
			} else {
				$db->query();
			}
		}
	}

	/**
	* Cleans the database of image files that no longer exist.
	*/
	protected function purgeFolder($folderid) {
		// purge images
		$db = JFactory::getDbo();
		$folderid = (int) $folderid;
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->quoteName('imageid').','.PHP_EOL.
				$db->quoteName('fileurl').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_image').PHP_EOL.
			'WHERE '.$db->quoteName('folderid').' = '.$folderid
		);
		$rows = $db->loadRowList();

		if (!empty($rows)) {
			$missing = array();

			// find image entries that point to files that have been removed from the file system
			foreach ($rows as $row) {
				list($id, $url) = $row;

				if (is_absolute_path($url) && !file_exists($url)) {
					$this->cleanGeneratedImages($id);
					SIGPlusLogging::appendStatus('Image <code>'.$url.'</code> is about to be removed from the database.');
					$missing[] = $id;
				}
			}

			if (!empty($missing)) {
				$db->setQuery(
					'DELETE FROM '.$db->quoteName('#__sigplus_image').PHP_EOL.
					'WHERE '.$db->quoteName('imageid').' IN ('.implode(',',$missing).')'
				);
				if (version_compare(JVERSION, '3.0') >= 0) {
					$db->execute();
				} else {
					$db->query();
				}
			}
		}

		// purge deleted previews and thumbnails
		$db = JFactory::getDbo();
		$folderid = (int) $folderid;
		$db->setQuery(
			'SELECT'.PHP_EOL.
				'i.'.$db->quoteName('imageid').','.PHP_EOL.
				'i.'.$db->quoteName('viewid').','.PHP_EOL.
				'i.'.$db->quoteName('thumb_fileurl').','.PHP_EOL.
				'i.'.$db->quoteName('preview_fileurl').','.PHP_EOL.
				'i.'.$db->quoteName('watermark_fileurl').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_imageview').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_view').' AS f'.PHP_EOL.
				'ON i.'.$db->quoteName('viewid').' = f.'.$db->quoteName('viewid').PHP_EOL.
			'WHERE f.'.$db->quoteName('folderid').' = '.$folderid
		);
		$rows = $db->loadRowList();

		if (!empty($rows)) {
			SIGPlusLogging::appendStatus('Cleaning deleted preview and thumbnail images from database.');

			// find image entries that point to files that have been removed from the file system
			foreach ($rows as $row) {
				list($imageid, $viewid, $thumburl, $previewurl, $watermarkurl) = $row;

				if (is_absolute_path($thumburl) && !file_exists($thumburl) || is_absolute_path($previewurl) && !file_exists($previewurl) || is_absolute_path($watermarkurl) && !file_exists($watermarkurl)) {
					$this->cleanGeneratedImages($imageid, $viewid);
				}
			}
		}
	}

	/**
	* Remove image views that have been persisted in the cache but removed manually.
	*/
	protected function purgeCache() {
		if ($this->config->service->cache_image != 'cache') {
			return;  // images are not set to be generated in cache folder
		}

		$thumb_folder = JPATH_CACHE.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->config->service->folder_thumb);
		$preview_folder = JPATH_CACHE.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->config->service->folder_preview);
		if (file_exists($thumb_folder) && file_exists($preview_folder)) {
			return;  // thumb and preview folder not removed
		}

		SIGPlusLogging::appendStatus('Manual removal of cache folders detected.');
		$db = JFactory::getDbo();

		// escape special characters, append any character qualifier at end, quote string
		$thumb_pattern = $db->quote(str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $thumb_folder).'%');
		$preview_pattern = $db->quote(str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $preview_folder).'%');

		// remove views from database with deleted image files
		$db->setQuery(
			'DELETE FROM '.$db->quoteName('#__sigplus_imageview').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->quoteName('thumb_fileurl').' LIKE '.$thumb_pattern.' OR '.
				$db->quoteName('preview_fileurl').' LIKE '.$preview_pattern
		);
		if (version_compare(JVERSION, '3.0') >= 0) {
			$db->execute();
		} else {
			$db->query();
		}
	}
}

abstract class SIGPlusLocalBase extends SIGPlusGalleryBase {
	/**
	* Creates a thumbnail image, a preview image, and a watermarked image for an original.
	* Images are generated only if they do not already exist.
	* A separate thumbnail image is generated if the preview is too large to act as a thumbnail.
	* @param {string} $imagepath An absolute file system path to an image.
	*/
	private function getGeneratedImages($imagepath) {
		SIGPlusTimer::checkpoint();

		$previewparams = new SIGPlusPreviewParameters($this->config->gallery);  // current image generation parameters
		$thumbparams = new SIGPlusThumbParameters($this->config->gallery);

		$imagelibrary = SIGPlusImageLibrary::instantiate($this->config->service->library_image);

		// create watermarked image
		if ($this->config->gallery->watermark_position !== false && ($watermarkpath = $this->getWatermarkPath(dirname($imagepath))) !== false) {
			$watermarkedpath = $this->getWatermarkedPath($imagepath, SIGPLUS_TEST);
			if ($watermarkedpath === false || !(fsx::filemtime($watermarkedpath) >= fsx::filemtime($imagepath))) {  // watermarked image does not yet exist
				$watermarkedpath = $this->getWatermarkedPath($imagepath, SIGPLUS_CREATE);
				$watermarkparams = array(
					'position' => $this->config->gallery->watermark_position,
					'x' => $this->config->gallery->watermark_x,
					'y' => $this->config->gallery->watermark_y,
					'quality' => $previewparams->quality  // GD cannot extract quality parameter from stored image, use quality set by user
				);
				$result = $imagelibrary->createWatermarked($imagepath, $watermarkpath, $watermarkedpath, $watermarkparams);
				if ($result) {
					SIGPlusLogging::appendStatus('Saved watermarked image to <code>'.$watermarkedpath.'</code>.');
				} else {
					SIGPlusLogging::appendError('Unable to save watermarked image to <code>'.$watermarkedpath.'</code>.');
				}
			}
		}

		// create preview image
		$previewpath = $this->getPreviewPath($imagepath, $previewparams, SIGPLUS_TEST);
		if ($previewpath === false || !(fsx::filemtime($previewpath) >= fsx::filemtime($imagepath))) {  // create image on-the-fly if does not exist
			$previewpath = $this->getPreviewPath($imagepath, $previewparams, SIGPLUS_CREATE);
			$result = $imagelibrary->createThumbnail($imagepath, $previewpath, $previewparams->width, $previewparams->height, $previewparams->crop, $previewparams->quality);
			if ($result) {
				SIGPlusLogging::appendStatus('Saved preview image to <code>'.$previewpath.'</code>');
			} else {
				SIGPlusLogging::appendError('Unable to save preview image to <code>'.$previewpath.'</code>');
			}
		}

		// create thumbnail image
		$thumbpath = $this->getThumbnailPath($imagepath, $thumbparams, SIGPLUS_TEST);
		if ($thumbpath === false || !(fsx::filemtime($thumbpath) >= fsx::filemtime($imagepath))) {  // separate thumbnail image is required
			$thumbpath = $this->getThumbnailPath($imagepath, $thumbparams, SIGPLUS_CREATE);
			$result = $imagelibrary->createThumbnail($imagepath, $thumbpath, $thumbparams->width, $thumbparams->height, $thumbparams->crop, $thumbparams->quality);
			if ($result) {
				SIGPlusLogging::appendStatus('Saved thumbnail to <code>'.$thumbpath.'</code>');
			} else {
				SIGPlusLogging::appendError('Unable to save thumbnail to <code>'.$thumbpath.'</code>');
			}
		}
	}

	/**
	* Creates a directory if it does not already exist.
	* @param {string} $directory The full path to the directory.
	*/
	private function createDirectoryOnDemand($directory) {
		if (!is_dir($directory)) {  // directory does not exist
			@mkdir($directory, 0755, true);  // try to create it
			if (!is_dir($directory)) {
				throw new SIGPlusFolderPermissionException($directory);
			}
			// create an index.html to prevent getting a web directory listing
			@file_put_contents($directory.DIRECTORY_SEPARATOR.'index.html', '<html><body></body></html>');
		}
	}

	/**
	* The full file system path to a high-resolution image version.
	* @param {string} $imagepath An absolute path to an image file.
	*/
	private function getFullsizeImagePath($imagepath) {
		if (!$this->config->service->folder_fullsize) {
			return $imagepath;
		}
		$fullsizepath = dirname($imagepath).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->config->service->folder_fullsize).DIRECTORY_SEPARATOR.basename($imagepath);
		if (!is_file($fullsizepath)) {
			return $imagepath;
		}
		return $fullsizepath;
	}

	/**
	* The full path to an image used for watermarking.
	* @param {string} $imagedirectory The full path to a directory where images to watermark are to be found.
	* @return {string} The full path to a watermark image, or false if none is found.
	*/
	private function getWatermarkPath($imagedirectory) {
		$watermark_image = $this->config->gallery->watermark_source;
		// look inside image gallery folder (e.g. "images/stories/myfolder")
		$watermark_in_gallery = $imagedirectory.DIRECTORY_SEPARATOR.$watermark_image;
		// look inside watermark subfolder of image gallery folder (e.g. "images/stories/myfolder/watermark")
		$watermark_in_subfolder = $imagedirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->config->service->folder_watermarked).DIRECTORY_SEPARATOR.$watermark_image;
		// look inside base path (e.g. "images/stories")
		$watermark_in_base = $this->config->service->base_folder.DIRECTORY_SEPARATOR.$watermark_image;

		if (is_file($watermark_in_gallery)) {
			return $watermark_in_gallery;
		} elseif (is_file($watermark_in_subfolder)) {
			return $watermark_in_subfolder;
		} elseif (is_file($watermark_in_base)) {
			return $watermark_in_base;
		} else {
			return false;
		}
	}

	/**
	* Test or create full path to a generated image (e.g. preview image or thumbnail) based on configuration settings.
	* @param {string} $generatedfolder The folder where generated images are to be stored.
	* @return {bool|string} The path to the generated image, or false if it does not exist.
	*/
	private function getGeneratedImagePath($generatedfolder, $imagepath, SIGPlusImageParameters $params, $action = SIGPLUS_TEST) {
		switch ($this->config->service->cache_image) {
			case 'cache':  // images are set to be generated in the Joomla cache folder
				$directory = JPATH_CACHE.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $generatedfolder);
				$path = $directory.DIRECTORY_SEPARATOR.$params->getHash($imagepath);  // hash original image file paths to avoid name conflicts
				break;
			case 'media':  // images are set to be generated in the Joomla media folder
				$directory = JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $generatedfolder);
				$path = $directory.DIRECTORY_SEPARATOR.$params->getHash($imagepath);  // hash original image file paths to avoid name conflicts
				break;
			case 'source':  // images are set to be generated inside folders within the directory where the images are
				$directory = dirname($imagepath).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $generatedfolder);
				$subfolder = $params->getNamingPrefix();
				if ($subfolder) {
					$directory .= DIRECTORY_SEPARATOR.$subfolder;
				}
				$path = $directory.DIRECTORY_SEPARATOR.basename($imagepath);
				break;
		}
		switch ($action) {
			case SIGPLUS_TEST:
				if (is_file($path)) {
					return $path;
				}
				break;
			case SIGPLUS_CREATE:
				$this->createDirectoryOnDemand($directory);
				return $path;
		}
		return false;
	}

	/**
	* Test or create the full path to a watermarked image based on configuration settings.
	* @param {string} $imagepath Absolute path to an image file.
	* @return The full path to a watermarked image, or false on error.
	*/
	private function getWatermarkedPath($imagepath, $action = SIGPLUS_TEST) {
		$params = new SIGPlusPreviewParameters();
		$params->width = 0;  // special values for watermarked image
		$params->height = 0;
		$params->crop = false;
		$params->quality = 0;
		return $this->getGeneratedImagePath($this->config->service->folder_watermarked, $imagepath, $params, $action);
	}

	/**
	* Test or create the full path to a preview image based on configuration settings.
	* @param {string} $imagepath Absolute path to an image file.
	* @return The full path to a preview image, or false on error.
	*/
	private function getPreviewPath($imagepath, SIGPlusPreviewParameters $params, $action = SIGPLUS_TEST) {
		return $this->getGeneratedImagePath($this->config->service->folder_preview, $imagepath, $params, $action);
	}

	/**
	* Test or create the full path to an image thumbnail based on configuration settings.
	* @param {string} $imageref Absolute path to an image file.
	* @return The full path to an image thumbnail, or false on error.
	*/
	private function getThumbnailPath($imagepath, SIGPlusThumbParameters $params, $action = SIGPLUS_TEST) {
		return $this->getGeneratedImagePath($this->config->service->folder_thumb, $imagepath, $params, $action);
	}

	protected function populateImage($imagepath, $folderid) {
		// check if file has been modified since its data have been injected into the database
		$db = JFactory::getDbo();
		$db->setQuery('SELECT '.$db->quoteName('filetime').' FROM '.$db->quoteName('#__sigplus_image').' WHERE '.$db->quoteName('fileurl').' = '.$db->quote($imagepath));
		$time = $db->loadResult();
		$filetime = fsx::filemdate($imagepath);
		if ($time == $filetime) {
			SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> has <em>not</em> changed.');
			return false;
		}

		if ($this->config->gallery->watermark_position !== false && $this->config->gallery->watermark_source == basename($imagepath)) {
			SIGPlusLogging::appendStatus('Skipping image <code>'.$imagepath.'</code>, which acts as a watermark image.');
			return false;
		}

		// extract image metadata from file
		$metadata = new SIGPlusImageMetadata($imagepath);

		// image size
		$width = 0;
		$height = 0;
		$imagedims = fsx::getimagesize($imagepath);
		if ($imagedims !== false) {
			$width = $imagedims[0];
			$height = $imagedims[1];
		}
		SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> ['.$width.'x'.$height.'] has been added or updated.');

		// image filename and size
		$filename = basename($imagepath);
		$filesize = fsx::filesize($imagepath);

		// insert main image data into database
		$imageid = SIGPlusDatabase::replaceSingle(  // deletes rows related via foreign key constraints
			'#__sigplus_image',
			array('folderid','fileurl','filename','filetime','filesize','width','height'),
			array($folderid, $imagepath, $filename, $filetime, $filesize, $width, $height)
		);
		SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> [id='.$imageid.', folder='.$folderid.'] has been recorded in the database.');

		$metadata->inject($imageid);

		return $imageid;
	}

	private function getImageData($path) {
		$time = null;
		$width = null;
		$height = null;
		if (isset($path) && $path !== false && file_exists($path)) {
			$time = fsx::filemdate($path);
			$imagedims = fsx::getimagesize($path);
			if ($imagedims !== false) {
				list($width, $height) = $imagedims;
			}
		} else {
			$path = null;
		}
		return array($path, $time, $width, $height);
	}

	protected function populateImageView($imagepath, $imageid, $viewid) {
		// generate missing images
		$this->getGeneratedImages($imagepath);

		// image thumbnail path and parameters
		$thumbparams = new SIGPlusThumbParameters($this->config->gallery);
		list($thumbpath, $thumbtime, $thumbwidth, $thumbheight) = $this->getImageData($this->getThumbnailPath($imagepath, $thumbparams, SIGPLUS_TEST));

		// image preview path and parameters
		$previewparams = new SIGPlusPreviewParameters($this->config->gallery);
		list($previewpath, $previewtime, $previewwidth, $previewheight) = $this->getImageData($this->getPreviewPath($imagepath, $previewparams, SIGPLUS_TEST));

		// watermarked image
		list($watermarkedpath, $watermarkedtime) = $this->getImageData($this->getWatermarkedPath($imagepath, SIGPLUS_TEST));

		// handle special value NULL when thumbnail or preview image could not be generated
		if (!isset($thumbwidth) || !isset($thumbheight)) {
			$thumbwidth = 0;
			$thumbheight = 0;
		}
		if (!isset($previewwidth) || !isset($previewheight)) {
			$previewwidth = 0;
			$previewheight = 0;
		}

		// insert image view
		SIGPlusDatabase::insertSingleUnique(
			'#__sigplus_imageview',
			array(
				'imageid','viewid',
				'thumb_fileurl','thumb_filetime','thumb_width','thumb_height',
				'preview_fileurl','preview_filetime','preview_width','preview_height',
				'watermark_fileurl','watermark_filetime'
			),
			array(
				$imageid, $viewid,
				$thumbpath, $thumbtime, $thumbwidth, $thumbheight,
				$previewpath, $previewtime, $previewwidth, $previewheight,
				$watermarkedpath, $watermarkedtime
			)
		);
	}

	/**
	* Finds images that have no preview or thumbnail image.
	*/
	protected function getMissingImageViews($folderid, $viewid) {
		// add depth condition
		if ($this->config->gallery->depth >= 0) {
			$depthcond = ' AND depthnum <= '.((int) $this->config->gallery->depth);
		} else {
			$depthcond = '';
		}

		$folderid = (int) $folderid;
		$viewid = (int) $viewid;
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT'.PHP_EOL.
				'i.'.$db->quoteName('fileurl').','.PHP_EOL.
				'i.'.$db->quoteName('imageid').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->quoteName('folderid').' = f.'.$db->quoteName('folderid').PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->quoteName('folderid').' = h.'.$db->quoteName('descendantid').PHP_EOL.
			'WHERE h.'.$db->quoteName('ancestorid').' = '.$folderid.' AND NOT EXISTS (SELECT * FROM '.$db->quoteName('#__sigplus_imageview').' AS v WHERE i.'.$db->quoteName('imageid').' = v.'.$db->quoteName('imageid').' AND v.'.$db->quoteName('viewid').' = '.$viewid.')'.$depthcond
		);
		return $db->loadRowList();
	}

	/**
	* Get last modified time of folder with consideration of changes to labels file.
	* @param {string} $folder A folder in which the labels file is to be found.
	* @param {int} $lastmod A base value for the last modified time, typically obtained with a recursive scan of descendant folders.
	*/
	protected function getLabelsLastModified($folder, $lastmod) {
		// get last modified time of labels file
		$labels = new SIGPlusLabels($this->config);  // get labels file manager
		$sources = $labels->getLabelsFilePaths($folder);

		// update last modified time if labels file has been changed
		foreach ($sources as $source) {
			$lastmod = max($lastmod, fsx::filemtime($source));
		}
		return gmdate('Y-m-d H:i:s', $lastmod);  // use SQL DATE format "yyyy-mm-dd hh:nn:ss"
	}
}

/**
* A gallery hosted in the file system.
*/
class SIGPlusLocalGallery extends SIGPlusLocalBase {
	/**
	* True if the file extension indicates a recognized image format.
	*/
	protected static function is_image_file($file) {
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'jpg': case 'jpeg': case 'JPG': case 'JPEG':
			case 'gif': case 'GIF':
			case 'png': case 'PNG':
				return true;
			default:
				return false;
		}
	}

	/**
	* True if the file extension indicates a recognized video format.
	*/
	protected static function is_video_file($file) {
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'avi': case 'AVI':
			case 'flv': case 'FLV':
			case 'mov': case 'MOV':
			case 'mp4': case 'mpeg': case 'MP4': case 'MPEG':
				return true;
			default:
				return false;
		}
	}

	/**
	* Removes all images and related generated images associated with a folder that has been deleted.
	*/
	private function purgeLocalFolder($url) {
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT '.$db->quoteName('folderid').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_folder').PHP_EOL.
			'WHERE '.$db->quoteName('folderurl').' = '.$db->quote($url)
		);
		$folderid = $db->loadResult();
		if ($folderid) {
			$this->purgeFolder($folderid);
		}
	}

	/**
	* Populates a database equivalent of a folder with images in the folder.
	*/
	public /*private*/ function populateFolder($path, $files, $folders, $ancestors) {
		// add folder
		$folderparams = new SIGPlusFolderParameters();
		$folderparams->time = fsx::filemtime($path);  // directory timestamp
		$folderid = $this->insertFolder($path, $folderparams, $ancestors);

		// remove entries that correspond to non-existent images
		$this->purgeFolder($folderid);

		// scan list of files
		$entries = array();
		foreach ($files as $file) {
			if (self::is_image_file($path.DIRECTORY_SEPARATOR.$file)) {
				$entry = $this->populateImage($path.DIRECTORY_SEPARATOR.$file, $folderid);
				if ($entry !== false) {
					$entries[] = $entry;
				}
			}
		}

		return $folderid;
	}

	/**
	* Populates the view of a database equivalent of a folder.
	*/
	protected function populateFolderViews($folderid) {
		// add folder view
		$viewid = (int) $this->insertView($folderid);

		// collect images that have no preview or thumbnail image
		$rows = $this->getMissingImageViews($folderid, $viewid);
		if (!empty($rows)) {
			foreach ($rows as $row) {
				list($path, $imageid) = $row;

				$this->populateImageView($path, $imageid, $viewid);
			}
		} else {
			SIGPlusLogging::appendStatus('Folder view [id='.$viewid.'] has not changed.');
		}
		return $viewid;
	}

	/**
	* Generate an image gallery whose images come from the local file system.
	*/
	public function populate($imagefolder, $folderparams) {
		// check whether cache folder has been removed manually by user
		$this->purgeCache();

		if (!file_exists($imagefolder)) {
			$this->purgeLocalFolder($imagefolder);
			return null;
		}

		// get last modified time of folder
		$lastmod = $this->getLabelsLastModified($imagefolder, get_folder_last_modified($imagefolder, $this->config->gallery->depth));

		if (!isset($folderparams->time) || strcmp($lastmod, $folderparams->time) > 0) {
			// get list of direct child and indirect descendant folders and files inside root folder
			$exclude = array(
				$this->config->service->folder_thumb,
				$this->config->service->folder_preview,
				$this->config->service->folder_watermarked,
				$this->config->service->folder_fullsize
			);
			$exclude = array_filter($exclude);  // remove null values from array
			walkdir($imagefolder, $exclude, $this->config->gallery->depth, array($this, 'populateFolder'), array());

			// update folder entry with last modified date
			$folderparams->time = $lastmod;
			$folderid = $this->insertFolder($imagefolder, $folderparams);

			// populate labels from external file
			$labels = new SIGPlusLabels($this->config);  // get labels file manager
			$labels->populate($imagefolder, $folderid);
		} else {
			$folderid = $folderparams->id;
			SIGPlusLogging::appendStatus('Folder <code>'.$imagefolder.'</code> has not changed.');
		}

		return $this->populateFolderViews($folderid);
	}
}

abstract class SIGPlusAtomFeedGallery extends SIGPlusGalleryBase {
	public function __construct(SIGPlusConfigurationParameters $config) {
		parent::__construct($config);

		// check for presence of XML parser
		if (!function_exists('simplexml_load_file')) {
			throw new SIGPlusLibraryUnavailableException('SimpleXML');
		}
	}

	protected function getFolderView($url, &$folderparams) {
		// create folder if it does not yet exist
		$folderparams->id = $this->insertFolder($url, $folderparams);

		// get view identifier but do not create one if it does not already exist
		return $this->getView($folderparams->id);
	}

	protected function requestFolder($feedurl, &$folderparams, $url, $viewid) {
		// determine whether gallery needs new view
		if ($viewid) {
			$entitytag = $folderparams->entitytag;
		} else {  // no coresponding view available, force retrieval by discarding HTTP entity tag
			SIGPlusLogging::appendStatus('<a href="'.$url.'">Web album</a> view is to be re-populated.');
			$entitytag = null;
		}

		// read data from URL only if modified
		$feeddata = http_get_modified($feedurl, $folderparams->time, $entitytag);
		if ($feeddata === true) {  // same HTTP ETag
			SIGPlusLogging::appendStatus('<a href="'.$url.'">Web album</a> with ETag <code>'.$folderparams->entitytag.'</code> has not changed.');
			return false;
		} elseif ($feeddata === false) {  // retrieval failure
			throw new SIGPlusRemoteException($url);
		}

		// get XML file of list of photos in an album
		$sxml = simplexml_load_string($feeddata);
		if ($sxml === false) {
			throw new SIGPlusXMLFormatException($url);
		}

		// update folder data (if necessary)
		if ($entitytag != $folderparams->entitytag) {  // update folder data
			$folderparams->entitytag = $entitytag;
			$folderparams->id = $this->replaceFolder($url, $folderparams);  // clears related image data as a side effect
			SIGPlusLogging::appendStatus('<a href="'.$url.'">Web album</a> feed XML has been retrieved, new ETag is <code>'.$folderparams->entitytag.'</code>.');
		} else {
			SIGPlusLogging::appendStatus('<a href="'.$url.'">Web album</a> feed XML has not changed.');
		}

		return $sxml;
	}
}

class SIGPlusFlickrGallery extends SIGPlusAtomFeedGallery {
	public function populate($url, $folderparams) {
		// parse album feed URL
		$urlparts = parse_url($url);
		if (!preg_match('"^/services/feeds/photos_public.gne"', $urlparts['path'])) {
			SIGPlusLogging::appendError('Invalid Flickr Web Album feed URL <code>'.$url.'</code>.');
			return false;
		}

		// extract Flickr user identifier from feed URL
		$urlquery = array();
		if (isset($urlparts['query'])) {
			parse_str($urlparts['query'], $urlquery);
		}
		$userid = $urlquery['id'];

		$viewid = $this->getFolderView($url, $folderparams);

		// build URL query string to fetch list of photos in album
		$feedquery = array(
			'id' => $userid
		);

		// build URL to fetch list of photos in album
		$uri = JFactory::getURI();
		$feedurl = 'http://api.flickr.com/services/feeds/photos_public.gne?'.http_build_query($feedquery, '', '&');

		// send request
		if (($sxml = $this->requestFolder($feedurl, $folderparams, $url, $viewid)) === false) {  // has not changed
			return $viewid;
		}

		// parse XML response
		$entries = array();
		foreach ($sxml->entry as $entry) {  // enumerate album entries with XPath "/feed/entry"
			$time = $entry->updated;
		}
	}
}

class SIGPlusPicasaGallery extends SIGPlusAtomFeedGallery {
	/**
	* Generates an image gallery whose images come from Picasa Web Albums.
	* @see http://picasaweb.google.com
	* @param {string} $url The Picasa album RSS feed URL.
	*/
	public function populate($url, $folderparams) {
		// parse album feed URL
		$urlparts = parse_url($url);

		// extract Picasa user identifier and album identifier from feed URL
		$urlpath = $urlparts['path'];
		$match = array();
		if (!preg_match('"^/data/feed/(?:api|base)/user/([^/?#]+)/albumid/([^/?#]+)"', $urlpath, $match)) {
			throw new SIGPlusFeedURLException($url);
		}
		$userid = $match[1];
		$albumid = $match[2];

		$viewid = $this->getFolderView($url, $folderparams);

		// extract feed URL parameters (including authorization key if any)
		$urlquery = array();
		if (isset($urlparts['query'])) {
			parse_str($urlparts['query'], $urlquery);
		}

		// define fixed thumbnail sizes provided by Picasa
		$sizes_cropped = array(32, 48, 64, 72, 104, 144, 150, 160);
		$sizes_uncropped = array_merge($sizes_cropped, array(94, 110, 128, 200, 220, 288, 320, 400, 512, 576, 640, 720, 800, 912, 1024, 1152, 1280, 1440, 1600));
		sort($sizes_uncropped);

		// set preferred width and height
		$prefwidth = max(100, $this->config->gallery->preview_width);
		$prefheight = max(100, $this->config->gallery->preview_height);

		// choose cropped vs. uncropped
		if ($this->config->gallery->preview_crop) {
			$sizes = $sizes_cropped;
			$crop = 'c';
		} else {
			$sizes = $sizes_uncropped;
			$crop = 'u';
		}

		// get thumbnail size(s) that best match(es) expected preview image dimensions
		$mindim = min($prefwidth, $prefheight);  // smaller dimension
		$minsize = $sizes[0];
		for ($k = 0; $k < count($sizes) && $mindim >= $sizes[$k]; $k++) {  // smaller than both width and height
			$minsize = $sizes[$k];
		}
		$preferred = array($minsize);
		$maxdim = max($prefwidth, $prefheight);  // larger dimension
		for ($k = 0; $k < count($sizes) && $maxdim >= $sizes[$k]; $k++) {
			$preferred[] = $sizes[$k];
		}
		sort($preferred, SORT_REGULAR);
		$preferred = array_unique($preferred, SORT_REGULAR);

		// build URL query string to fetch list of photos in album
		$feedquery = array(
			'v' => '2.0',  // use Google Data Protocol v2.0
			// 'prettyprint' => 'true',  // for debugging purposes only
			'kind' => 'photo',
			'thumbsize' => implode($crop.',', $preferred).$crop,  // preferred thumb sizes
			'fields' => 'id,updated,entry(id,updated,media:group)'  // fetch only the listed XML elements
		);
		if ($this->config->gallery->maxcount > 0) {
			$feedquery['max-results'] = $this->config->gallery->maxcount;
		}
		if (isset($urlquery['authkey'])) {  // pass on authorization key
			$feedquery['authkey'] = $urlquery['authkey'];
		}

		// build URL to fetch list of photos in album
		$uri = JFactory::getURI();
		$scheme = $uri->isSSL() ? 'https:' : 'http:';
		$feedurl = $scheme.'//picasaweb.google.com/data/feed/api/user/'.$userid.'/albumid/'.$albumid.'?'.http_build_query($feedquery, '', '&');

		// send request
		if (($sxml = $this->requestFolder($feedurl, $folderparams, $url, $viewid)) === false) {  // has not changed
			return $viewid;
		}

		// parse XML response
		$entries = array();
		foreach ($sxml->entry as $entry) {  // enumerate album entries with XPath "/feed/entry"
			$time = $entry->updated;

			$media = $entry->children('http://search.yahoo.com/mrss/');  // children with namespace "media"
			$mediagroup = $media->group;

			// get image title and description
			$title = (string) $mediagroup->title;  // TODO: image title currently unused
			$summary = (string) $mediagroup->description;  // TODO: image summary currently unused

			// get image URL
			$attrs = $mediagroup->content->attributes();
			$imageurl = (string) $attrs['url'];  // <media:content url='...' height='...' width='...' type='image/jpeg' medium='image' />
			$width = (int) $attrs['width'];
			$height = (int) $attrs['height'];

			// get preview image URL
			$thumburl = null;
			$thumbwidth = 0;
			$thumbheight = 0;
			foreach ($mediagroup->thumbnail as $thumbnail) {
				$attrs = $thumbnail->attributes();
				$curwidth = (int) $attrs['width'];
				$curheight = (int) $attrs['height'];

				// update thumbnail to use if it fits in image bounds
				if ($prefwidth >= $curwidth && $prefheight >= $curheight && ($curwidth > $thumbwidth || $curheight > $thumbheight)) {
					$thumburl = (string) $attrs['url'];  // <media:thumbnail url='...' height='...' width='...' />
					$thumbwidth = $curwidth;
					$thumbheight = $curheight;
				}
			}

			// insert image data
			$imageid = SIGPlusDatabase::insertSingleUnique(
				'#__sigplus_image',
				array(
					'folderid',
					'fileurl',
					'filetime',
					'filesize',
					'width',
					'height'
				),
				array(
					$folderparams->id,
					$imageurl,
					$time,
					0,  // information not available for Picasa albums
					$width,
					$height
				),
				'imageid'
			);

			$entries[] = array(
				$imageid,
				$thumburl,
				$thumbwidth,
				$thumbheight,
				$thumburl,
				$thumbwidth,
				$thumbheight
			);
		}

		// update folder view data
		$viewid = (int) $this->replaceView($folderparams->id);  // clears all entries related to the folder as a side effect

		// insert image data
		SIGPlusDatabase::insertBatch(
			'#__sigplus_imageview',
			array(
				'imageid',
				'thumb_fileurl',
				'thumb_width',
				'thumb_height',
				'preview_fileurl',
				'preview_width',
				'preview_height'
			),
			$entries,
			array('imageid'),
			array('viewid' => $viewid)
		);

		return $viewid;
	}
}

/**
* A single image hosted on a remote server.
* The image is downloaded to a temporary file for metadata extraction. Properly assembled HTTP
* headers ensure the image is downloaded only if the remote file has been modified.
*/
class SIGPlusRemoteImage extends SIGPlusGalleryBase {
	public function populate($url, $folderparams) {
		// update image data only if remote image has been modified
		$imagedata = http_get_modified($url, $folderparams->time, $etag);
		if ($imagedata === true) {  // not modified since specified date
			SIGPlusLogging::appendStatus('<a href="'.$url.'">Remote image</a> not modified since <code>'.$folderparams->time.'</code>.');

			if ($viewid = $this->getView($folderparams->id)) {  // preview image is available for remote image
				return $viewid;
			}

			// preview image not available, retrieve image from remote server
			$imagedata = http_get_modified($url);
			if ($imagedata === true || $imagedata === false) {  // unexpected response or retrieval failure
				throw new SIGPlusRemoteException($url);
			}

			SIGPlusLogging::appendStatus('<a href="'.$url.'">Remote image</a> retrieved again as gallery parameters had changed.');
		} elseif ($imagedata === false) {  // retrieval failure
			throw new SIGPlusRemoteException($url);
		}

		// update folder entry with last modified date
		SIGPlusLogging::appendStatus('<a href="'.$url.'">Remote image</a> was last changed on <code>'.$folderparams->time.'</code>.');
		$folderid = $this->insertFolder($url, $folderparams);

		$metadata = null;
		$filesize = 0;
		$width = null;
		$height = null;

		// create temporary image file and extract metadata
		if ($imagepath = tempnam(JPATH_CACHE, 'sigplus')) {
			if (file_put_contents($imagepath, $imagedata)) {
				SIGPlusLogging::appendStatus('Image data has been saved to temporary file <code>'.$imagepath.'</code>.');

				// extract image metadata from file
				$metadata = new SIGPlusImageMetadata($imagepath);

				// image file size and dimensions
				$filesize = fsx::filesize($imagepath);
				$imagedims = fsx::getimagesize($imagepath);
				if ($imagedims !== false) {
					$width = $imagedims[0];
					$height = $imagedims[1];
					SIGPlusLogging::appendStatus('<a href="'.$url.'">Remote image</a> has MIME type '.$imagedims['mime'].' and dimensions '.$width.'x'.$height.'.');
				} else {
					throw new SIGPlusImageFormatException($url);
				}
			}
			unlink($imagepath);  // "tempnam", if succeeds, always creates the file
		}

		// insert image data into database
		$imageid = SIGPlusDatabase::replaceSingle(  // deletes rows related via foreign key constraints
			'#__sigplus_image',
			array('folderid','fileurl','filename','filetime','filesize','width','height'),
			array($folderid, $url, basename($url), $folderparams->time, $filesize, $width, $height)
		);

		if (isset($metadata)) {
			$metadata->inject($imageid);
		}

		$viewid = (int) $this->insertView($folderid);
		// insert image view
		SIGPlusDatabase::insertSingleUnique(
			'#__sigplus_imageview',
			array(
				'imageid','viewid',
				'preview_fileurl','preview_filetime','preview_width','preview_height'
			),
			array(
				$imageid, $viewid,
				$url, $folderparams->time, $width, $height
			)
		);

		return $viewid;
	}
}

/**
* Exposes the sigplus public services.
*/
class SIGPlusCore {
	/**
	* Global service configuration.
	*/
	private $config;
	/**
	* Stack of local gallery configurations.
	*/
	private $paramstack;

	public function __construct(SIGPlusConfigurationParameters $config) {
		// set global service parameters
		SIGPlusLogging::appendCodeBlock('Service parameters are:', print_r($config->service, true));
		$this->config = $config->service;
		$instance = SIGPlusEngineServices::instance();
		$instance->jsapi = $this->config->library_jsapi;
		$instance->debug = $this->config->debug_client;

		// set default parameters for image galleries
		SIGPlusLogging::appendCodeBlock('Default gallery parameters are:', print_r($config->gallery, true));
		$this->paramstack = new SIGPlusParameterStack();
		$this->paramstack->push($config->gallery);
	}

	public function verbosityLevel() {
		return $this->config->debug_server;
	}

	/**
	* Maps an image folder to a full file system path.
	* @param {string} $entry A simple directory entry (file or folder).
	*/
	private function getImageGalleryPath($entry) {
		$root = $this->config->base_folder;
		if (!is_absolute_path($this->config->base_folder)) {
			$root = JPATH_ROOT.DIRECTORY_SEPARATOR.$root;
		}
		if ($entry) {
			return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry);  // replace '/' with platform-specific directory separator
		} else {
			return $root;
		}
	}

	private function getFilterExpression(SIGPlusFilter $filter) {
		$db = JFactory::getDbo();
		$expr = array();
		foreach ($filter->items as $item) {
			if ($item instanceof SIGPlusFilter && !$item->is_empty()) {
				// add filter subexpression, e.g. "b or c" in "a and (b or c)"
				$expr[] = self::getFilterExpression($item);
			} elseif (is_string($item)) {
				// add a simple filter, e.g. "b" in "a and b and c"
				$expr[] = $db->quoteName('filename').' LIKE '.$db->quote(SIGPlusDatabase::sqlpattern($item));
			}
		}
		return '('.implode(' '.$filter->rel.' ', $expr).')';
	}

	private static function getFormattedSize($size) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor(log($size, 1000)) : 0;
		return number_format($size / pow(1000, $power), 2, '.', '') . ' ' . $units[$power];
	}

	/**
	* Get an image label with placeholder and default value substitutions.
	*/
	private static function getSubstitutedLabel($text, $default, $template, $filename, $index, $total, $filesize) {
		// use default text if no text is explicitly given
		if (!isset($text) && isset($default)) {
			$text = $default;
		}

		// replace placeholders for file name, current image number and total image count with actual values in template
		if (isset($text) && isset($template)) {
			$text = str_replace(
				array('{$text}','{$filename}','{$current}','{$total}','{$filesize}'),
				array($text, $filename, (string) ($index+1), (string) $total, self::getFormattedSize($filesize)),
				$template
			);
		}

		return $text;
	}

	/**
	* Returns whether the label depends on server data not available on the client side.
	*/
	private static function isServerDependentLabel($template) {
		// check if placeholders for server-dependent values are present in template string
		if (isset($template)) {
			return strpos($template, '{$filesize}') !== false;
		} else {
			return false;
		}
	}

	/**
	* Get an image label with placeholder and default substitutions as plain text with double quote escapes.
	*/
	private static function getLabel($text, $default, $template, $url, $index, $total, $filesize) {
		return self::getSubstitutedLabel($text, $default, $template, basename($url), $index, $total, $filesize);
	}

	/**
	* Ensures that a gallery identifier is unique across the page.
	* A gallery identifier is specified by the user or generated from a counter. Some extensions
	* may duplicate article content on the page (e.g. show a short article extract in a module
	* position), making an identifier no longer unique. This function adds an ordinal to prevent
	* conflicts when the same gallery would occur multiple times on the page, causing scripts
	* not to function properly.
	* @param {string} $galleryid A preferred identifier, or null to have a new identifier generated.
	*/
	public function getUniqueGalleryId($galleryid = false) {
		static $counter = 1000;
		static $galleryids = array();

		if (!$galleryid || in_array($galleryid, $galleryids)) {  // look for identifier in script-lifetime container
			do {
				$counter++;
				$gid = 'sigplus_'.$counter;
			} while (in_array($gid, $galleryids));
			$galleryid = $gid;
		}
		$galleryids[] = $galleryid;
		return $galleryid;
	}

	private function getGalleryStyle() {
		$curparams = $this->paramstack->top();

		$style = 'sigplus-gallery';

		// add custom class annotation
		if ($curparams->classname) {
			$style .= ' '.$curparams->classname;
		}

		if ($curparams->layout == 'fixed') {  // imitate fixed layout in <noscript> mode
			$style .= ' sigplus-noscript';  // "sigplus-noscript" is automatically removed when javascript is detected
		}
		switch ($curparams->alignment) {
			case 'left': case 'left-clear': case 'left-float': $style .= ' sigplus-left'; break;
			case 'center': $style .= ' sigplus-center'; break;
			case 'right': case 'right-clear': case 'right-float': $style .= ' sigplus-right'; break;
		}
		switch ($curparams->alignment) {
			case 'left': case 'left-float': case 'right': case 'right-float': $style .= ' sigplus-float'; break;
			case 'left-clear': case 'right-clear': $style .= ' sigplus-clear'; break;
		}

		if ($curparams->lightbox !== false) {
			$instance = SIGPlusEngineServices::instance();
			$lightbox = $instance->getLightboxEngine($curparams->lightbox);
			$style .= ' sigplus-lightbox-'.$lightbox->getIdentifier();
		} else {
			$style .= ' sigplus-lightbox-none';
		}

		return $style;
	}

	/**
	* Transforms a file system path into a URL.
	* @param {string} $make_absolute Build absolute URL address with scheme, host and port.
	*/
	public function makeURL($url, $make_absolute = false) {
		if (is_absolute_path($url)) {
			if (strpos($url, JPATH_CACHE.DIRECTORY_SEPARATOR) === 0) {  // file is inside cache folder
				$path = substr($url, strlen(JPATH_CACHE.DIRECTORY_SEPARATOR));
				$url = JURI::base(true).'/cache/'.pathurlencode($path);
			} elseif (strpos($url, JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR) === 0) {  // file is inside media folder
				$path = substr($url, strlen(JPATH_ROOT.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR));
				$url = JURI::base(true).'/media/sigplus/'.pathurlencode($path);
			} elseif (strpos($url, $this->config->base_folder.DIRECTORY_SEPARATOR) === 0) {  // file is inside base folder
				$path = substr($url, strlen($this->config->base_folder.DIRECTORY_SEPARATOR));
				$url = $this->config->base_url.'/'.pathurlencode($path);
			} else {
				return false;
			}

			// transform relative URLs into absolute URLs if necessary
			if ($make_absolute && strpos($url, JURI::base(true).'/') === 0) {
				$url = JURI::base(false).substr($url, strlen(JURI::base(true)) + 1);
			}
		}
		return $url;
	}

	private function getDownloadAuthorization() {
		$curparams = $this->paramstack->top();

		$user = JFactory::getUser();
		if (in_array($curparams->download, $user->getAuthorisedViewLevels())) {  // user is not authorized to download image
			return true;
		} else {
			return false;  // access forbidden to user
		}
	}

	/**
	* Image download URL.
	*/
	private function getImageDownloadUrl($imageid) {
		if (!$this->getDownloadAuthorization()) {
			return false;
		}

		$uri = clone JFactory::getURI();  // URL of current page
		$uri->setVar('sigplus', $imageid);  // add query parameter "sigplus"
		return $uri->toString();
	}

	public function downloadImage($imagesource) {
		$imageid = (int) JRequest::getInt('sigplus', 0);
		if ($imageid <= 0) {
			return false;
		}

		// get active set of parameters from the top of the stack
		$curparams = $this->paramstack->top();

		// test user access level
		if (!$this->getDownloadAuthorization()) {  // authorization is required
			SIGPlusLogging::appendStatus('User is not authorized to download image.');
			throw new SIGPlusImageDownloadAccessException();
		}

		// translate image source into full source specification
		if (is_url_http($imagesource) || is_absolute_path($imagesource)) {
			$source = $imagesource;
		} else {
			$source = $this->getImageGalleryPath(trim($imagesource, '/\\'));  // remove leading and trailing slash and backslash
		}

		// add depth condition
		if ($curparams->depth >= 0) {
			$depthcond = ' AND depthnum <= '.((int) $curparams->depth);
		} else {
			$depthcond = '';
		}

		// test if source contains wildcard character
		if (strpos($source, '*') !== false) {  // contains wildcard character
			// remove file name component of path
			$source = dirname($source);
		}

		// test whether image is part of the gallery
		$db = JFactory::getDbo();
		$imageid = (int) $imageid;
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->quoteName('fileurl').','.PHP_EOL.
				$db->quoteName('filename').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->quoteName('folderid').' = f.'.$db->quoteName('folderid').PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->quoteName('folderid').' = h.'.$db->quoteName('ancestorid').PHP_EOL.
			'WHERE '.$db->quoteName('folderurl').' = '.$db->quote($source).PHP_EOL.
				'AND '.$db->quoteName('imageid').' = '.$imageid.$depthcond
		);
		$row = $db->loadRow();
		if (!$row) {
			SIGPlusLogging::appendStatus('Image to download is not found in gallery database.');
			return false;
		}

		list($fileurl, $filename) = $row;
		if (headers_sent($file, $line)) {
			SIGPlusLogging::appendStatus('Unable to make browser download image, HTTP headers have already been sent in file "'.$file.'" line '.$line.'.');
			throw new SIGPlusImageDownloadHeadersSentException($fileurl);
		}

		// produce HTTP response
		header('Content-Description: File Transfer');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		if (is_absolute_path($fileurl)) {
			// return image as HTTP payload
			$size = fsx::getimagesize($fileurl);
			if ($size !== false) {
				header('Content-Type: '.$size['mime']);
			}
			$filesize = fsx::filesize($fileurl);
			if ($filesize !== false) {
				header('Content-Length: '.$filesize);
			}
			header('Content-Disposition: attachment; filename="'.$filename.'"');

			// discard internal buffer content used for output buffering
			ob_clean();
			flush();

			@fsx::readfile($fileurl);
		} else {
			// redirect to image URL
			header('Location: '.$fileurl);

			// discard internal buffer content used for output buffering
			ob_clean();
			flush();
		}
		return true;
	}

	/**
	* Generates image thumbnails with alternate text, title and lightbox pop-up activation on mouse click.
	* This method is typically called by the class plgContentSIGPlus, which represents the sigplus Joomla plug-in.
	* The method may modify the top of the parameter stack; the caller must provide a discardable copy.
	* @param {string|boolean} $imagesource A string that defines the gallery source. Relative paths are interpreted
	* w.r.t. the image base folder, which is passed in a configuration object to the class constructor.
	*/
	public function getGalleryHTML($imagesource, &$galleryid) {
		SIGPlusTimer::checkpoint();

		// get active set of parameters from the top of the stack
		$curparams = $this->paramstack->top();  // current gallery parameters

		$config = new SIGPlusConfigurationParameters();
		$config->gallery = $curparams;
		$config->service = $this->config;

		if ($imagesource === false) {  // use base folder as source if not set
			$imagesource = $this->config->base_folder;
		}

		// make placeholder replacement for {$username}
		if (strpos($imagesource, '{$username}') !== false) {
			$user = JFactory::getUser();
			if ($user->guest) {
				throw new SIGPlusLoginRequiredException();
			} else {
				$imagesource = str_replace('{$username}', $user->username, $imagesource);
			}
		}

		// make placeholder replacement for {$group}
		if (strpos($imagesource, '{$group}') !== false) {
			$user = JFactory::getUser();
			if ($user->guest) {
				throw new SIGPlusLoginRequiredException();
			} else {
				$groupname = SIGPlusUser::getCurrentUserGroup();
				if ($groupname) {
					$groupname = str_replace(' ', '', $groupname);  // normalize whitespace
				} else {
					$groupname = '.';  // no group, use current directory
				}
				$imagesource = str_replace('{$group}', $groupname, $imagesource);
			}
		}

		// set gallery identifier
		$galleryid = $this->getUniqueGalleryId($curparams->id);

		// show current set of parameters for image galleries
		SIGPlusLogging::appendCodeBlock('Local gallery parameters for "'.$galleryid.'" are:', print_r($curparams, true));

		// instantiate image generator
		$generator = null;
		if (strip_tags($imagesource) != $imagesource) {
			throw new SIGPlusHTMLCodeException($imagesource);
		} else if (is_url_http($imagesource) ) {  // test for Picasa galleries
			$source = $imagesource;
			SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from URL: <code>'.$source.'</code>');
			if (preg_match('"^https?://picasaweb.google.com/"', $source)) {
				$generator = new SIGPlusPicasaGallery($config);
			} elseif (preg_match('"^http://api.flickr.com/services/feeds/photos_public.gne"', $source)) {
				$generator = new SIGPlusFlickrGallery($config);
			} else {
				$generator = new SIGPlusRemoteImage($config);
				$curparams->maxcount = 1;
			}
		} else {
			if (is_absolute_path($imagesource)) {
				$source = $imagesource;
			} else {
				$source = $this->getImageGalleryPath(trim($imagesource, '/\\'));  // remove leading and trailing slash and backslash
			}

			// parse wildcard patterns in file name component
			if (strpos($source, '*') !== false || strpos($source, '?') !== false) {  // contains wildcard character
				// add implicit include filter on file name component of path
				$filter = $curparams->filter_include;  // save current filter
				$curparams->filter_include = new SIGPlusFilter('and');
				$curparams->filter_include->items[] = basename($source);  // add wildcard name to include filter
				$curparams->filter_include->items[] = $filter;  // add current filter as sub-filter

				// remove file name component of path
				$source = dirname($source);

				if (is_dir($source)) {
					// set up gallery populator
					SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from filtered folder: <code>'.$source.'</code>');
					$generator = new SIGPlusLocalGallery($config);
				}
			} elseif (is_dir($source)) {
				SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from folder: <code>'.$source.'</code>');
				$generator = new SIGPlusLocalGallery($config);
			} elseif (is_file($source)) {
				// set implicit filter to filter exact file name
				$filter = $curparams->filter_include;  // save current filter
				$curparams->filter_include = new SIGPlusFilter('and');
				$curparams->filter_include->items[] = basename($source);
				$curparams->filter_include->items[] = $filter;  // add current filter as sub-filter

				// activate single image mode
				$curparams->maxcount = 1;

				// remove file name component of path
				$source = dirname($source);

				SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from file: <code>'.$source.'</code>');
				$generator = new SIGPlusLocalGallery($config);
			}
		}
		if (!isset($generator)) {
			throw new SIGPlusImageSourceException($imagesource);
		}
		$curparams->validate();  // re-validate parameters to resolve inconsistencies (e.g. rotator with a single image)

		// set image gallery alignment (left, center or right) and text wrap (float or clear)
		$gallerystyle = $this->getGalleryStyle();

		// get properties of folder stored in the database
		$db = JFactory::getDbo();
		$db->setQuery('SELECT '.$db->quoteName('folderid').', '.$db->quoteName('foldertime').', '.$db->quoteName('entitytag').' FROM '.$db->quoteName('#__sigplus_folder').' WHERE '.$db->quoteName('folderurl').' = '.$db->quote($source));
		$result = $db->loadRow();

		$folderparams = new SIGPlusFolderParameters();
		if ($result) {
			list($folderparams->id, $folderparams->time, $folderparams->entitytag) = $result;
		}

		// populate image database
		$viewid = $generator->populate($source, $folderparams);

		// apply sort criterion and sort order
		switch ($curparams->sort_criterion) {
			case SIGPLUS_SORT_LABELS_OR_FILENAME:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						// entries with smallest ordnum are shown first, entries without ordnum shown last
						$sortorder = '-ordnum DESC, filename ASC'; break;  // unary minus inverts sort order, NULL values presented last when doing ORDER BY ... DESC
					case SIGPLUS_SORT_DESCENDING:
						// entries with largest ordnum are shown first, entries without ordnum shown last
						$sortorder = 'ordnum DESC, filename DESC'; break;
				}
				break;
			case SIGPLUS_SORT_LABELS_OR_MTIME:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = '-ordnum DESC, filetime ASC'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'ordnum DESC, filetime DESC'; break;
				}
				break;
			case SIGPLUS_SORT_LABELS_OR_FILESIZE:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = '-ordnum DESC, filesize ASC'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'ordnum DESC, filesize DESC'; break;
				}
				break;
			case SIGPLUS_SORT_LABELS_OR_RANDOM:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = '-ordnum DESC, RAND()'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'ordnum DESC, RAND()'; break;
				}
				break;
			case SIGPLUS_SORT_MTIME:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = 'filetime ASC'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'filetime DESC'; break;
				}
				break;
			case SIGPLUS_SORT_FILESIZE:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = 'filesize ASC'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'filesize DESC'; break;
				}
				break;
			case SIGPLUS_SORT_RANDOM:
				$sortorder = 'RAND()';
				break;
			default:  // case SIGPLUS_SORT_FILENAME:
				switch ($curparams->sort_order) {
					case SIGPLUS_SORT_ASCENDING:
						$sortorder = 'filename ASC'; break;
					case SIGPLUS_SORT_DESCENDING:
						$sortorder = 'filename DESC'; break;
				}
		}
		$sortorder = 'depthnum ASC, '.$sortorder;  // keep descending from topmost to bottommost in hierarchy, do not mix entries from different levels

		// determine current site language
		$lang = JFactory::getLanguage();
		list($language, $country) = explode('-', $lang->getTag());  // site current language
		$langid = (int)SIGPlusDatabase::getLanguageId($language);
		$countryid = (int)SIGPlusDatabase::getCountryId($country);

		// build SQL condition for depth
		if ($curparams->depth >= 0) {
			$depthcond = ' AND depthnum <= '.$curparams->depth;
		} else {
			$depthcond = '';
		}

		// build SQL condition for file match pattern
		$patterncond = '';
		if (!$curparams->filter_include->is_empty()) {
			$patterncond .= ' AND '.self::getFilterExpression($curparams->filter_include);
		}
		if (!$curparams->filter_exclude->is_empty()) {
			$patterncond .= ' AND NOT '.self::getFilterExpression($curparams->filter_exclude);
		}

		// build and execute SQL query
		$viewid = (int) $viewid;
		$query =
			'SELECT'.PHP_EOL.
				'i.'.$db->quoteName('imageid').','.PHP_EOL.
				'IFNULL(v.'.$db->quoteName('watermark_fileurl').', i.'.$db->quoteName('fileurl').') AS '.$db->quoteName('url').','.PHP_EOL.
				'i.'.$db->quoteName('width').','.PHP_EOL.
				'i.'.$db->quoteName('height').','.PHP_EOL.
				'i.'.$db->quoteName('filesize').','.PHP_EOL.
				'IFNULL('.PHP_EOL.
					// use image title if set
					'IFNULL(c.'.$db->quoteName('title').','.PHP_EOL.
						// or use meta-data field "Headline" if no image title has been set explicitly
						'('.PHP_EOL.
							'SELECT md.'.$db->quoteName('textvalue').''.PHP_EOL.
							'FROM #__sigplus_property AS mp'.PHP_EOL.
							'INNER JOIN #__sigplus_data AS md'.PHP_EOL.
							'ON mp.'.$db->quoteName('propertyid').' = md.'.$db->quoteName('propertyid').PHP_EOL.
							'WHERE mp.'.$db->quoteName('propertyname').' = '.$db->quote('Headline').' AND md.'.$db->quoteName('imageid').' = i.'.$db->quoteName('imageid').''.PHP_EOL.
							'LIMIT 1'.PHP_EOL.
						')'.PHP_EOL.
					'),'.PHP_EOL.
					// or use the best wild-card match for the image
					'('.PHP_EOL.
						'SELECT p.'.$db->quoteName('title').PHP_EOL.
						'FROM '.$db->quoteName('#__sigplus_foldercaption').' AS p'.PHP_EOL.
						'WHERE'.PHP_EOL.
							'p.'.$db->quoteName('langid').' = '.$langid.' AND '.PHP_EOL.
							'p.'.$db->quoteName('countryid').' = '.$countryid.' AND '.PHP_EOL.
							'i.'.$db->quoteName('filename').' LIKE p.'.$db->quoteName('pattern').' AND '.PHP_EOL.
							'i.'.$db->quoteName('folderid').' = p.'.$db->quoteName('folderid').PHP_EOL.
						'ORDER BY p.'.$db->quoteName('priority').' LIMIT 1'.PHP_EOL.
					')'.PHP_EOL.
				') AS '.$db->quoteName('title').','.PHP_EOL.
				'IFNULL('.PHP_EOL.
					// use image summary if set
					'IFNULL(c.'.$db->quoteName('summary').','.PHP_EOL.
						// or use meta-data field "Caption-Abstract" if no image summary has been set explicitly
						'('.PHP_EOL.
							'SELECT md.'.$db->quoteName('textvalue').''.PHP_EOL.
							'FROM #__sigplus_property AS mp'.PHP_EOL.
							'INNER JOIN #__sigplus_data AS md'.PHP_EOL.
							'ON mp.'.$db->quoteName('propertyid').' = md.'.$db->quoteName('propertyid').PHP_EOL.
							'WHERE mp.'.$db->quoteName('propertyname').' = '.$db->quote('Caption-Abstract').' AND md.'.$db->quoteName('imageid').' = i.'.$db->quoteName('imageid').''.PHP_EOL.
							'LIMIT 1'.PHP_EOL.
						')'.PHP_EOL.
					'),'.PHP_EOL.
					// or use the best wild-card match for the image
					'('.PHP_EOL.
						'SELECT p.'.$db->quoteName('summary').PHP_EOL.
						'FROM '.$db->quoteName('#__sigplus_foldercaption').' AS p'.PHP_EOL.
						'WHERE'.PHP_EOL.
							'p.'.$db->quoteName('langid').' = '.$langid.' AND '.PHP_EOL.
							'p.'.$db->quoteName('countryid').' = '.$countryid.' AND '.PHP_EOL.
							'i.'.$db->quoteName('filename').' LIKE p.'.$db->quoteName('pattern').' AND '.PHP_EOL.
							'i.'.$db->quoteName('folderid').' = p.'.$db->quoteName('folderid').PHP_EOL.
						'ORDER BY p.'.$db->quoteName('priority').' LIMIT 1'.PHP_EOL.
					')'.PHP_EOL.
				') AS '.$db->quoteName('summary').','.PHP_EOL.
				$db->quoteName('preview_fileurl').','.PHP_EOL.
				$db->quoteName('preview_width').','.PHP_EOL.
				$db->quoteName('preview_height').','.PHP_EOL.
				$db->quoteName('thumb_fileurl').','.PHP_EOL.
				$db->quoteName('thumb_width').','.PHP_EOL.
				$db->quoteName('thumb_height').PHP_EOL.
			'FROM '.$db->quoteName('#__sigplus_image').' AS i'.PHP_EOL.
				// folder "f" in which image is to be found
				'INNER JOIN '.$db->quoteName('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->quoteName('folderid').' = f.'.$db->quoteName('folderid').PHP_EOL.
				// couple folders related to folder "f" in the folder hierarchy
				'INNER JOIN '.$db->quoteName('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->quoteName('folderid').' = h.'.$db->quoteName('descendantid').PHP_EOL.
				// topmost folder "a" in the folder hierarchy, which the user selects
				'INNER JOIN '.$db->quoteName('#__sigplus_folder').' AS a'.PHP_EOL.
				'ON a.'.$db->quoteName('folderid').' = h.'.$db->quoteName('ancestorid').PHP_EOL.
				'INNER JOIN '.$db->quoteName('#__sigplus_imageview').' AS v'.PHP_EOL.
				'ON i.'.$db->quoteName('imageid').' = v.'.$db->quoteName('imageid').PHP_EOL.
				'LEFT JOIN '.$db->quoteName('#__sigplus_caption').' AS c'.PHP_EOL.
				'ON i.'.$db->quoteName('imageid').' = c.'.$db->quoteName('imageid').PHP_EOL.
			'WHERE'.PHP_EOL.
				// no caption belongs to image or caption language matches site language
				'(ISNULL(c.'.$db->quoteName('langid').') OR c.'.$db->quoteName('langid').' = '.$langid.') AND '.PHP_EOL.
				'(ISNULL(c.'.$db->quoteName('countryid').') OR c.'.$db->quoteName('countryid').' = '.$countryid.') AND '.PHP_EOL.
				// condition to match folder URL with (activation tag or module) source folder
				'a.'.$db->quoteName('folderurl').' = '.$db->quote($source).' AND '.PHP_EOL.
				// condition to match folder view with activation tag or module instance
				$db->quoteName('viewid').' = '.$viewid.PHP_EOL.
				// include and exclude filters or single image selection
				$patterncond.PHP_EOL.
				// limit on hierarchical listing
				$depthcond.PHP_EOL.
			'ORDER BY '.$sortorder
		;
		$db->setQuery($query);
		if (version_compare(JVERSION, '3.0') >= 0) {
			$cursor = $db->execute();
		} else {
			$cursor = $db->query();
		}
		if ($cursor) {
			$total = $db->getNumRows();  // get number of images in gallery
		} else {
			$total = 0;
		}
		if ($total > 0) {
			$images = $db->loadRowList();
		} else {
			$images = array();
			$galleryid = null;
		}
		$limit = $curparams->maxcount > 0 ? min($curparams->maxcount, $total) : $total;

		// add images to be used on social network sites
		$this->addOpenGraphProperties($images);

		// generate HTML code for each image
		ob_start();  // start output buffering
		$this->printGallery($galleryid, $gallerystyle, $images, $limit, $total);
		$body = ob_get_clean();  // fetch output buffer

		return $body;
	}

	private function printGallery($galleryid, $gallerystyle, array $images, $limit, $total) {
		$curparams = $this->paramstack->top();  // current gallery parameters

		if (version_compare(JVERSION, '3.1') >= 0) {
			$layout_path = JPluginHelper::getLayoutPath('content', 'sigplus', 'default');
		} else {
			$layout_path = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tmpl'.DIRECTORY_SEPARATOR.'default.php';
		}
		require($layout_path);
	}

	private function printImage($image, $index, $total, $style = null) {
		$curparams = $this->paramstack->top();  // current gallery parameters

		list($imageid, $source, $width, $height, $filesize, $title, $summary, $preview_url, $preview_width, $preview_height, $thumb_url, $thumb_width, $thumb_height) = $image;

		// translate paths into URLs
		$url = $this->makeURL($source);
		$preview_url = $this->makeURL($preview_url);
		$thumb_url = $this->makeURL($thumb_url);
		$download_url = $this->getImageDownloadUrl($imageid);

		$properties = array();
		if (SIGPLUS_CAPTION_CLIENT) {  // client-side template replacement
			$title = $title ? $title : $curparams->caption_title;
			$summary = $summary ? $summary : $curparams->caption_summary;
			if (self::isServerDependentLabel($curparams->caption_title_template) || self::isServerDependentLabel($curparams->caption_summary_template)) {
				$property = new stdClass;
				$property->key = 'image-file-size';
				$property->value = $filesize;
				$properties[] = $property;
			}
		} else {  // server-side template replacement
			$title = self::getSubstitutedLabel($title, $curparams->caption_title, $curparams->caption_title_template, $url, $index, $total, $filesize);
			$summary = self::getSubstitutedLabel($summary, $curparams->caption_summary, $curparams->caption_summary_template, $url, $index, $total, $filesize);
		}

		if (version_compare(JVERSION, '3.1') >= 0) {
			$layout_path = JPluginHelper::getLayoutPath('content', 'sigplus', 'item');
		} else {
			$layout_path = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tmpl'.DIRECTORY_SEPARATOR.'item.php';
		}
		require($layout_path);
	}

	/**
	* Add Open Graph meta tags to tell social network sites (e.g. Facebook) which images to use as representative images for the page when the page is shared.
	*/
	private function addOpenGraphProperties(array $images) {
		if (empty($images)) {
			return;
		}

		$document = JFactory::getDocument();
		if ($document->getType() != 'html') {  // custom tags are supported by HTML document type only
			return;
		}

		$limit = min(count($images), 3);
		for ($index = 0; $index < $limit; $index++) {
			$image = $images[$index];
			list($imageid, $source, $width, $height, $filesize, $title, $summary, $preview_url, $preview_width, $preview_height, $thumb_url, $thumb_width, $thumb_height) = $image;

			// translate paths into absolute URLs
			$url = $this->makeURL($source, true);

			// add Open Graph meta tag
			$document->addCustomTag('<meta property="og:image" content="'.$url.'"/>');
		}
	}

	public function addStyles($id = null) {
		$curparams = $this->paramstack->top();  // current gallery parameters

		$instance = SIGPlusEngineServices::instance();
		$instance->addStandardStyles();
		if (isset($id)) {
			// add custom style declaration based on back-end and inline settings
			$cssrules = array();
			$captionrules = array();
			if ($curparams->preview_margin !== false) {
				$cssrules['margin'] = $curparams->preview_margin.' !important';
				$captionrules[$curparams->caption_position == 'overlay-top' ? 'top' : 'bottom'] = $curparams->preview_margin.' !important';
				$captionrules['left'] = $curparams->preview_margin.' !important';
				$captionrules['right'] = $curparams->preview_margin.' !important';
			}
			if ($curparams->preview_border_width !== false && $curparams->preview_border_style !== false && $curparams->preview_border_color !== false) {
				$cssrules['border'] = $curparams->preview_border_width.' '.$curparams->preview_border_style.' '.$curparams->preview_border_color.' !important';
			} else {
				if ($curparams->preview_border_width !== false) {
					$cssrules['border-width'] = $curparams->preview_border_width.' !important';
				}
				if ($curparams->preview_border_style !== false) {
					$cssrules['border-style'] = $curparams->preview_border_style.' !important';
				}
				if ($curparams->preview_border_color !== false) {
					$cssrules['border-color'] = $curparams->preview_border_color.' !important';
				}
			}
			if ($curparams->preview_padding !== false) {
				$cssrules['padding'] = $curparams->preview_padding.' !important';
			}
			$selectors = array(
				'#'.$id.' ul > li img' => $cssrules,
				'#'.$id.' .captionplus-overlay' => $captionrules
			);
			$instance->addStyles($selectors);
		}
	}

	public function addScripts($id = null) {
		if (isset($id)) {
			$curparams = $this->paramstack->top();  // current gallery parameters

			$instance = SIGPlusEngineServices::instance();
			$instance->addMooTools();
			$instance->addScript('/media/sigplus/js/initialization.js');  // unwrap all galleries from protective <noscript> container

			if (SIGPLUS_CAPTION_CLIENT) {  // client-side template replacement
				$instance->addOnReadyScript('__sigplusCaption('.json_encode($id).', '.json_encode($curparams->caption_title_template).', '.json_encode($curparams->caption_summary_template).');');
			}

			if ($curparams->lightbox !== false) {
				$lightbox = $instance->getLightboxEngine($curparams->lightbox);
				$selector = '#'.$id.' a.sigplus-image';
				$lightbox->addStyles($selector, $curparams);
				$lightbox->addScripts($selector, $curparams);
			}
			if ($curparams->caption !== false) {
				$caption = $instance->getCaptionEngine($curparams->caption);
				$selector = '#'.$id.' ul';
				$caption->addStyles($selector, $curparams);
				$caption->addScripts($selector, $curparams);
			}
			if ($curparams->rotator !== false) {
				$rotator = $instance->getRotatorEngine($curparams->rotator);
				$selector = '#'.$id;
				$rotator->addStyles($selector, $curparams);
				$rotator->addScripts($selector, $curparams);
			}
			$instance->addOnReadyEvent();
		}
	}

	/**
	* Subscribes to the "click" event of an anchor to pop up the associated lightbox window.
	* @param {string} $linkid The HTML identifier of the anchor whose "click" event to subscribe to.
	* @param {string} $galleryid The identifier of the gallery to open in the lightbox window.
	*/
	public function addLightboxLinkScript($linkid, $galleryid) {
		$curparams = $this->paramstack->top();  // current gallery parameters
		$instance = SIGPlusEngineServices::instance();
		$instance->activateLightbox($linkid, '#'.$galleryid.' a.sigplus-image', $curparams->index);  // selector should be same as above
		$instance->addOnReadyEvent();
	}

	/**
	* Adds lightbox styleheet and script references to the page header.
	* This method is typically invoked to bind a lightbox to an external URL not part of a gallery.
	*/
	public function addLightboxScripts($selector) {
		$curparams = $this->paramstack->top();  // current gallery parameters

		if ($curparams->lightbox !== false) {
			$instance = SIGPlusEngineServices::instance();

			$lightbox = $instance->getLightboxEngine($curparams->lightbox);
			$lightbox->addStyles($selector, $curparams);
			$lightbox->addScripts($selector, $curparams);

			$instance->addOnReadyEvent();
		}
	}

	public function getParameters() {
		return $this->paramstack->top();
	}

	public function setParameterObject($object) {
		$this->paramstack->setObject($object);
	}

	/**
	* Pushes a new set of gallery parameters on the parameter stack.
	* If used as a plug-in, these would normally appear as the attribute list of the activation start tag.
	*/
	public function setParameterString($string) {
		$this->paramstack->setString($string);
	}

	/**
	* Pushes an array of gallery parameter key-value pairs on the parameter stack.
	*/
	public function setParameterArray($array) {
		$this->paramstack->setArray($array);
	}

	/**
	* Pops a set of gallery parameters from the parameter stack.
	*/
	public function resetParameters() {
		$this->paramstack->pop();
	}
}