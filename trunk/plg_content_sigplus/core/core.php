<?php
/**
* @file
* @brief    sigplus Image Gallery Plus plug-in for Joomla
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

require_once dirname(__FILE__).DS.'version.php';
require_once dirname(__FILE__).DS.'exception.php';
require_once dirname(__FILE__).DS.'params.php';
require_once dirname(__FILE__).DS.'imagegenerator.php';
require_once dirname(__FILE__).DS.'engines.php';

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
		$script = file_get_contents(JPATH_ROOT.DS.'media'.DS.'sigplus'.DS.'js'.DS.'log.js');
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
			$col = $db->nameQuote($col);
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
			if (!isset($keys) || !in_array($col, $keys)) {  // there are not keys or column is not a key
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
				'INSERT INTO '.$db->nameQuote($table).' ('.implode(',',$cols).')'.PHP_EOL.
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
			$db->query();  // execute query
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
			$lastkey = $db->nameQuote($lastkey);
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
			'INSERT INTO '.$db->nameQuote($table).' ('.implode(',',$cols).')'.PHP_EOL.
			'VALUES '.$values.PHP_EOL.
			'ON DUPLICATE KEY UPDATE '.implode(', ',$update)
		);
		$db->query();
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
			'REPLACE INTO '.$db->nameQuote($table).' ('.implode(',',$cols).')'.PHP_EOL.
			'VALUES '.$values
		);
		$db->query();
		$db->setQuery('SELECT LAST_INSERT_ID()');
		return (int) $db->loadResult();
	}

	public static function executeAll(array $queries) {
		$db = JFactory::getDbo();
		$db->setQuery(implode('; ', $queries));
		$db->queryBatch(true, true);  // execute as one transaction
	}
}

/**
* Measures execution time and prevents time-outs.
*/
class SIGPlusTimer {
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

	public static function checkpoint() {
		static $hit_count = 0;
		static $started_time;
		static $maximum_duration;

		// initialize static variables
		isset($started_time) || $started_time = SIGPlusTimer::getStartedTime();
		isset($maximum_duration) || $maximum_duration = SIGPlusTimer::getMaximumDuration();

		if (time() >= $started_time + $maximum_duration) {
			throw new SIGPlusTimeoutException();
		}

		$hit_count++;
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
	* Finds the language-specific labels file.
	* @param {string} $imagefolder An absolute path or URL to a directory with a labels file.
	* @return The full path to the language-specific labels file.
	*/
	public function getLabelsFilePath($imagefolder) {
		$labelsname = pathinfo($this->caption_source, PATHINFO_FILENAME);
		$labelsextn = pathinfo($this->caption_source, PATHINFO_EXTENSION);
		$labelsextn = '.'.( $labelsextn ? $labelsextn : 'txt' );
		if ($this->multilingual) {  // check for language-specific labels file
			$lang = JFactory::getLanguage();
			$file = $imagefolder.DS.$labelsname.'.'.$lang->getTag().$labelsextn;
			if (is_file($file)) {
				return $file;
			}
		}

		// default to language-neutral labels file
		$file = $imagefolder.DS.$labelsname.$labelsextn;  // filesystem path to labels file
		if (is_file($file)) {
			return $file;
		}
		return false;
	}

	/**
	* Extract short captions and descriptions attached to images from a "labels.txt" file.
	*/
	private function parseLabels($imagefolder, &$entries = array(), &$patterns = array()) {
		$labelspath = $this->getLabelsFilePath($imagefolder);
		if ($labelspath === false) {
			return false;
		}
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

			if (strpos($imagefile, '*') !== false) {  // contains wildcard character
				// replace "*" and "?" with LIKE expression equivalents "%" and "_"
				$patterns[] = array(SIGPlusDatabase::sqlpattern($imagefile), ++$priority, $title, $summary);
			} else {
				if (is_url_http($imagefile)) {  // a URL to a remote image
					$imagefile = safeurlencode($imagefile);
				} else {  // a local image
					$imagefile = str_replace('/', DS, $imagefile);
					$imagefile = file_exists_case_insensitive($imagefolder.DS.$imagefile);
					if ($imagefile === false) {  // check that image file truly exists
						continue;
					}
					$imagefile = $imagefolder.DS.$imagefile;
				}

				// prepare data for injection into database
				$index++;
				$entries[] = array($imagefile, $index, $title, $summary);
			}
		}
		return true;
	}

	public function populate($imagefolder, $folderid) {
		$this->parseLabels($imagefolder, $entries, $patterns);

		// update and insert data
		$db = JFactory::getDbo();
		$queries = array();

		// update title and description patterns
		$queries[] =
			'DELETE FROM '.$db->nameQuote('#__sigplus_foldercaption').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('folderid').' = '.$folderid;
		if (!empty($patterns)) {
			$queries[] = SIGPlusDatabase::getInsertBatchStatement(
				'#__sigplus_foldercaption',
				array('pattern','priority','title','summary'),
				$patterns,
				null,
				array('folderid' => $folderid)
			);
		}

		// invalidate custom order
		$folderid = (int) $folderid;  // force type to prevent SQL injection
		$queries[] = 'UPDATE '.$db->nameQuote('#__sigplus_image').' SET '.$db->nameQuote('ordnum').' = NULL WHERE '.$db->nameQuote('folderid').' = '.$folderid;

		if (!empty($entries)) {
			// add and update entries
			$queries[] = SIGPlusDatabase::getInsertBatchStatement(
				'#__sigplus_image',
				array('fileurl','ordnum','title','summary'),
				$entries,
				array('fileurl'),
				array('folderid' => $folderid)
			);
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

		require_once dirname(__FILE__).DS.'metadata.php';
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
				if (is_array($metavalue)) {
					$value = implode(';', $metavalue);
				} else {
					$value = (string) $metavalue;
				}
				$entries[] = array($key, $value);
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
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->nameQuote('folderid').','.PHP_EOL.
				$db->nameQuote('foldertime').','.PHP_EOL.
				$db->nameQuote('entitytag').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_folder').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('folderurl').' = '.$db->quote($url)
		);
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
			$this->config->gallery->watermark_x . ($this->config->gallery->watermark_position !== false ? $this->config->gallery->watermark_position : '@') . $this->config->gallery->watermark_y,
			true
		);
	}

	protected function getView($folderid) {
		$db = JFactory::getDbo();
		$folderid = (int) $folderid;
		$hash = $this->getViewHash($folderid);

		// verify if preview image parameters for the folder have changed
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->nameQuote('viewid').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_view').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('folderid').' = '.$folderid.' AND '.PHP_EOL.
				$db->nameQuote('hash').' = '.$db->quote($hash)
		);
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
			$cond = ' AND '.$db->nameQuote('viewid').' = '.$viewid;
		} else {
			$cond = '';
		}

		// verify if preview image parameters for the folder have changed
		$db->setQuery(
			'SELECT'.PHP_EOL.
				$db->nameQuote('preview_fileurl').','.PHP_EOL.
				$db->nameQuote('preview_filetime').','.PHP_EOL.
				$db->nameQuote('thumb_fileurl').','.PHP_EOL.
				$db->nameQuote('thumb_filetime').','.PHP_EOL.
				$db->nameQuote('watermark_fileurl').','.PHP_EOL.
				$db->nameQuote('watermark_filetime').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_imageview').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('imageid').' = '.$imageid.$cond
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
				'DELETE FROM '.$db->nameQuote('#__sigplus_imageview').PHP_EOL.
				'WHERE'.PHP_EOL.
					$db->nameQuote('imageid').' = '.$imageid.$cond
			);
			$db->query();
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
				$db->nameQuote('imageid').','.PHP_EOL.
				$db->nameQuote('fileurl').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_image').PHP_EOL.
			'WHERE '.$db->nameQuote('folderid').' = '.$folderid
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
					'DELETE FROM '.$db->nameQuote('#__sigplus_image').PHP_EOL.
					'WHERE '.$db->nameQuote('imageid').' IN ('.implode(',',$missing).')'
				);
				$db->query();
			}
		}

		// purge deleted previews and thumbnails
		$db = JFactory::getDbo();
		$folderid = (int) $folderid;
		$db->setQuery(
			'SELECT'.PHP_EOL.
				'i.'.$db->nameQuote('imageid').','.PHP_EOL.
				'i.'.$db->nameQuote('viewid').','.PHP_EOL.
				'i.'.$db->nameQuote('thumb_fileurl').','.PHP_EOL.
				'i.'.$db->nameQuote('preview_fileurl').','.PHP_EOL.
				'i.'.$db->nameQuote('watermark_fileurl').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_imageview').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_view').' AS f'.PHP_EOL.
				'ON i.'.$db->nameQuote('viewid').' = f.'.$db->nameQuote('viewid').PHP_EOL.
			'WHERE f.'.$db->nameQuote('folderid').' = '.$folderid
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
		if (!$this->config->service->cache_image) {
			return;  // images are not set to be generated in cache folder
		}

		$thumb_folder = JPATH_CACHE.DS.str_replace('/', DS, $this->config->service->folder_thumb);
		$preview_folder = JPATH_CACHE.DS.str_replace('/', DS, $this->config->service->folder_preview);
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
			'DELETE FROM '.$db->nameQuote('#__sigplus_imageview').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('thumb_fileurl').' LIKE '.$thumb_pattern.' OR '.
				$db->nameQuote('preview_fileurl').' LIKE '.$preview_pattern
		);
		$db->query();
	}
}

abstract class SIGPlusLocalBase extends SIGPlusGalleryBase {
	/**
	* Creates a thumbnail image, a preview image, and a watermarked image for an original.
	* Images are generated only if they do not already exist.
	* A separate thumbnail image is generated if the preview is too large to act as a thumbnail.
	* @param string $imagepath An absolute file system path to an image.
	*/
	private function getGeneratedImages($imagepath) {
		SIGPlusTimer::checkpoint();

		$previewparams = new SIGPlusPreviewParameters($this->config->gallery);  // current image generation parameters
		$thumbparams = new SIGPlusThumbParameters($this->config->gallery);

		$imagelibrary = SIGPlusImageLibrary::instantiate($this->config->service->library_image);

		// create watermarked image
		if ($this->config->gallery->watermark_position !== false && ($watermarkpath = $this->getWatermarkPath($imagepath)) !== false) {
			$watermarkedpath = $this->getWatermarkedPath($imagepath, SIGPLUS_TEST);
			if ($watermarkedpath === false || !(fsx::filemtime($watermarkedpath) >= fsx::filemtime($imagepath))) {  // watermarked image does not yet exist
				$watermarkedpath = $this->getWatermarkedPath($imagepath, SIGPLUS_CREATE);
				$watermarkparams = $this->config->gallery->watermark_params;
				$watermarkparams['quality'] = $previewparams->quality;  // GD cannot extract quality parameter from stored image, use quality set by user
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
			@file_put_contents($directory.DS.'index.html', '<html><body></body></html>');
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
		$fullsizepath = dirname($imagepath).DS.str_replace('/', DS, $this->config->service->folder_fullsize).DS.basename($imagepath);
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
		$watermark_in_gallery = $imagedirectory.DS.$watermark_image;
		// look inside watermark subfolder of image gallery folder (e.g. "images/stories/myfolder/watermark")
		$watermark_in_subfolder = $imagedirectory.DS.str_replace('/', DS, $this->config->service->folder_watermark).DS.$watermark_image;
		// look inside base path (e.g. "images/stories")
		$watermark_in_base = $this->config->service->base_folder.DS.$watermark_image;

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
		if ($this->config->service->cache_image) {  // images are set to be generated in cache folder
			$directory = JPATH_CACHE.DS.str_replace('/', DS, $generatedfolder);
			$path = $directory.DS.$params->getHash($imagepath);  // hash original image file paths to avoid name conflicts
		} else {  // images are set to be generated inside folders within the directory where the images are
			$directory = dirname($imagepath).DS.str_replace('/', DS, $generatedfolder);
			$subfolder = $params->getNamingPrefix();
			if ($subfolder) {
				$directory .= DS.$subfolder;
			}
			$path = $directory.DS.basename($imagepath);
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
		$db->setQuery('SELECT '.$db->nameQuote('filetime').' FROM '.$db->nameQuote('#__sigplus_image').' WHERE '.$db->nameQuote('fileurl').' = '.$db->quote($imagepath));
		$time = $db->loadResult();
		$filetime = fsx::filemdate($imagepath);
		if ($time == $filetime) {
			SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> has <em>not</em> changed.');
			return false;
		}

		// extract image metadata from file
		$metadata = new SIGPlusImageMetadata($imagepath);

		// image size
		$width = 0;
		$height = 0;
		$imagedims = getimagesize($imagepath);
		if ($imagedims !== false) {
			$width = $imagedims[0];
			$height = $imagedims[1];
		}
		SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> ['.$width.'x'.$height.'] has been added or updated.');

		// image filename
		$filename = basename($imagepath);

		// insert main image data into database
		$imageid = SIGPlusDatabase::replaceSingle(  // deletes rows related via foreign key constraints
			'#__sigplus_image',
			array('folderid','fileurl','filename','filetime','width','height'),
			array($folderid, $imagepath, $filename, $filetime, $width, $height)
		);
		SIGPlusLogging::appendStatus('Image <code>'.$imagepath.'</code> [id='.$imageid.'] has been recorded in the database.');

		$metadata->inject($imageid);

		return $imageid;
	}

	private function getImageData($path) {
		$time = null;
		$width = null;
		$height = null;
		if (isset($path) && $path !== false && file_exists($path)) {
			$time = fsx::filemdate($path);
			$imagedims = getimagesize($path);
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
				'i.'.$db->nameQuote('fileurl').','.PHP_EOL.
				'i.'.$db->nameQuote('imageid').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->nameQuote('folderid').' = f.'.$db->nameQuote('folderid').PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->nameQuote('folderid').' = h.'.$db->nameQuote('ancestorid').PHP_EOL.
			'WHERE i.'.$db->nameQuote('folderid').' = '.$folderid.' AND NOT EXISTS (SELECT * FROM '.$db->nameQuote('#__sigplus_imageview').' AS v WHERE i.'.$db->nameQuote('imageid').' = v.'.$db->nameQuote('imageid').' AND v.'.$db->nameQuote('viewid').' = '.$viewid.')'.$depthcond
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
		$labelsfile = $labels->getLabelsFilePath($folder);

		// update last modified time if labels file has been changed
		if ($labelsfile !== false) {
			$lastmod = max($lastmod, fsx::filemtime($labelsfile));
		}
		return gmdate('Y-m-d H:i:s', $lastmod);  // use SQL DATE format "yyyy-mm-dd hh:nn:ss"
	}
}

class SIGPlusLocalImage extends SIGPlusLocalBase {
	/**
	* Populates a single-image pseudo-folder with the specified image.
	*/
	private function populatePseudoFolder($imagefile) {
		// add folder
		$folderparams = new SIGPlusFolderParameters();
		$folderparams->time = fsx::filemtime($imagefile);
		$folderid = $this->insertFolder($imagefile, $folderparams);

		// remove entries that correspond to non-existent images
		$this->purgeFolder($folderid);

		// check presence of image
		$entry = $this->populateImage($imagefile, $folderid);

		return $folderid;
	}

	/**
	* Populates the view of a single-image pseudo-folder.
	*/
	private function populatePseudoFolderView($folderid) {
		// add folder view
		$viewid = (int) $this->insertView($folderid);

		// check if image has no preview or thumbnail image
		$rows = $this->getMissingImageViews($folderid, $viewid);
		if (!empty($rows)) {  // 0 or 1 row
			$row = $rows[0];
			list($path, $imageid) = $row;
			$this->populateImageView($path, $imageid, $viewid);
		} else {
			SIGPlusLogging::appendStatus('Pseudo-folder view [id='.$viewid.'] has not changed.');
		}
		return $viewid;
	}

	/**
	* Generate output from a single image in the local file system.
	*/
	public function populate($imagefile, $folderparams) {
		// check whether cache folder has been removed manually by user
		$this->purgeCache();

		// get last modified time of file, also inspecting a related labels file
		$lastmod = $this->getLabelsLastModified(dirname($imagefile), fsx::filemtime($imagefile));

		if (!isset($folderparams->time) || strcmp($lastmod, $folderparams->time) > 0) {
			$this->populatePseudoFolder($imagefile);

			// update folder entry with last modified date
			$folderparams->time = $lastmod;
			$folderid = $this->insertFolder($imagefile, $folderparams);

			// add caption from external labels file
			$labels = new SIGPlusLabels($this->config);  // get labels file manager
			$labels->populate(dirname($imagefile), $folderid);
		} else {
			$folderid = $folderparams->id;
			SIGPlusLogging::appendStatus('File <code>'.$imagefile.'</code> has not changed.');
		}

		return $this->populatePseudoFolderView($folderid);
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

	public /*private*/ function populateFolder($path, $files, $folders, $ancestors) {
		// add folder
		$folderparams = new SIGPlusFolderParameters();
		$folderparams->time = fsx::filemtime($path);
		$folderid = $this->insertFolder($path, $folderparams, $ancestors);

		// remove entries that correspond to non-existent images
		$this->purgeFolder($folderid);

		// scan list of files
		$entries = array();
		foreach ($files as $file) {
			if (self::is_image_file($path.DS.$file)) {
				$entry = $this->populateImage($path.DS.$file, $folderid);
				if ($entry !== false) {
					$entries[] = $entry;
				}
			}
		}

		return $folderid;
	}

	protected function populateFolderViews($folderid) {
		// add folder view
		$viewid = (int) $this->insertView($folderid);

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

		// get last modified time of folder
		$lastmod = $this->getLabelsLastModified($imagefolder, get_folder_last_modified($imagefolder, $this->config->gallery->depth));

		if (!isset($folderparams->time) || strcmp($lastmod, $folderparams->time) > 0) {
			// get list of direct and indirect child folders and files inside root folder
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
		$feedurl = 'http://api.flickr.com/services/feeds/photos_public.gne?'.http_build_query($feedquery);

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
			SIGPlusLogging::appendError('Invalid Picasa Web Album feed URL <code>'.$url.'</code>.');
			return false;
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

		// choose cropped vs. uncropped
		if ($this->config->gallery->preview_crop) {
			$sizes = $sizes_cropped;
			$crop = 'c';
		} else {
			$sizes = $sizes_uncropped;
			$crop = 'u';
		}

		// get thumbnail size(s) that best match(es) expected preview image dimensions
		$mindim = min($this->config->gallery->preview_width, $this->config->gallery->preview_height);  // smaller dimension
		$minsize = $sizes[0];
		for ($k = 0; $k < count($sizes) && $mindim >= $sizes[$k]; $k++) {  // smaller than both width and height
			$minsize = $sizes[$k];
		}
		$preferred = array($minsize);
		$maxdim = max($this->config->gallery->preview_width, $this->config->gallery->preview_height);  // larger dimension
		for ($k = 0; $k < count($sizes) && $maxdim >= $sizes[$k]; $k++) {
			$preferred[] = $sizes[$k];
		}
		sort($preferred, SORT_REGULAR);
		$preferred = array_unique($preferred, SORT_REGULAR);

		// build URL query string to fetch list of photos in album
		$feedquery = array(
			'v' => '2.0',  // use Google Data Protocol v2.0
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
		$feedurl = $scheme.'//picasaweb.google.com/data/feed/api/user/'.$userid.'/albumid/'.$albumid.'?'.http_build_query($feedquery);

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
			$title = (string) $mediagroup->title;
			$summary = (string) $mediagroup->description;

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
				if ($this->config->gallery->preview_width >= $curwidth && $this->config->gallery->preview_height >= $curheight && ($curwidth > $thumbwidth || $curheight > $thumbheight)) {
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
					'width',
					'height',
					'title',
					'summary'
				),
				array(
					$folderparams->id,
					$imageurl,
					$time,
					$width,
					$height,
					$title,
					$summary
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

			$viewid = $this->getView($folderparams->id);
			return $viewid;
		} elseif ($imagedata === false) {  // retrieval failure
			throw new SIGPlusRemoteException($url);
		}

		// update folder entry with last modified date
		SIGPlusLogging::appendStatus('<a href="'.$url.'">Remote image</a> was last changed on <code>'.$folderparams->time.'</code>.');
		$folderid = $this->insertFolder($url, $folderparams);

		$metadata = null;
		$width = null;
		$height = null;

		// create temporary image file and extract metadata
		if ($imagepath = tempnam(JPATH_CACHE, 'sigplus')) {
			if (file_put_contents($imagepath, $imagedata)) {
				SIGPlusLogging::appendStatus('Image data has been saved to temporary file <code>'.$imagepath.'</code>.');

				// extract image metadata from file
				$metadata = new SIGPlusImageMetadata($imagepath);

				// image size
				$imagedims = getimagesize($imagepath);
				if ($imagedims !== false) {
					$width = $imagedims[0];
					$height = $imagedims[1];
				}
			}
			unlink($imagepath);  // "tempnam", if succeeds, always creates the file
		}

		// insert image data into database
		$imageid = SIGPlusDatabase::replaceSingle(  // deletes rows related via foreign key constraints
			'#__sigplus_image',
			array('folderid','fileurl','filename','filetime','width','height'),
			array($folderid, $url, basename($url), $folderparams->time, $width, $height)
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

	/**
	* Maps an image folder to a full file system path.
	* @param {string} $entry A simple directory entry (file or folder).
	*/
	private function getImageGalleryPath($entry) {
		$root = $this->config->base_folder;
		if (!is_absolute_path($this->config->base_folder)) {
			$root = JPATH_ROOT.DS.$root;
		}
		if ($entry) {
			return $root.DS.str_replace('/', DS, $entry);  // replace '/' with platform-specific directory separator
		} else {
			return $root;
		}
	}

	/**
	* Get an image label with placeholder and default value substitutions.
	*/
	private function getSubstitutedLabel($text, $default, $template, $filename, $index, $total) {
		// use default text if no text is explicitly given
		if (!isset($text) && isset($default)) {
			$text = $default;
		}

		// replace placeholders for file name, current image number and total image count with actual values in template
		if (isset($text) && isset($template)) {
			$text = str_replace(array('{$text}','{$filename}','{$current}','{$total}'), array($text, $filename, (string) ($index+1), (string) $total), $template);
		}

		return $text;
	}

	/**
	* Get an image label with placeholder and default substitutions as plain text with double quote escapes.
	*/
	private function getLabel($text, $default, $template, $url, $index, $total) {
		return $this->getSubstitutedLabel($text, $default, $template, basename($url), $index, $total);
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
	*/
	public function makeURL($url) {
		if (is_absolute_path($url)) {
			if (strpos($url, JPATH_CACHE.DS) === 0) {  // file is inside cache folder
				$path = substr($url, strlen(JPATH_CACHE.DS));
				$url = JURI::base(true).'/cache/'.pathurlencode($path);
			} elseif (strpos($url, $this->config->base_folder.DS) === 0) {  // file is inside base folder
				$path = substr($url, strlen($this->config->base_folder.DS));
				$url = $this->config->base_url.'/'.pathurlencode($path);
			} else {
				return false;
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
			return false;
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
				$db->nameQuote('fileurl').','.PHP_EOL.
				$db->nameQuote('filename').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->nameQuote('folderid').' = f.'.$db->nameQuote('folderid').PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->nameQuote('folderid').' = h.'.$db->nameQuote('ancestorid').PHP_EOL.
			'WHERE '.$db->nameQuote('folderurl').' = '.$db->quote($source).PHP_EOL.
				'AND '.$db->nameQuote('imageid').' = '.$imageid.$depthcond
		);
		$row = $db->loadRow();
		if ($row) {
			list($fileurl, $filename) = $row;
			if (is_absolute_path($fileurl)) {
				// return image as HTTP payload
				$size = getimagesize($fileurl);
				if ($size !== false) {
					header('Content-Type: '.$size['mime']);
				}
				$filesize = fsx::filesize($fileurl);
				if ($filesize !== false) {
					header('Content-Length: '.$filesize);
				}
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				@fsx::readfile($fileurl);
			} else {
				// redirect to image URL
				header('Location: '.$fileurl);
			}
			return true;
		} else {
			SIGPlusLogging::appendStatus('Image to download is not found in gallery database.');
			return false;
		}
	}

	/**
	* Generates image thumbnails with alternate text, title and lightbox pop-up activation on mouse click.
	* This method is typically called by the class plgContentSIGPlus, which represents the sigplus Joomla plug-in.
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

		// set gallery identifier
		$galleryid = $this->getUniqueGalleryId($curparams->id);

		// show current set of parameters for image galleries
		SIGPlusLogging::appendCodeBlock('Local gallery parameters for "'.$galleryid.'" are:', print_r($curparams, true));

		// instantiate image generator
		$generator = null;
		if (is_url_http($imagesource) ) {  // test for Picasa galleries
			$source = $imagesource;
			SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from URL: <code>'.$source.'</code>');
			if (preg_match('"^https?://picasaweb.google.com/data/feed/(?:api|base)/user/([^/?#]+)/albumid/([^/?#]+)"', $source)) {
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
			if (strpos($source, '*') !== false) {  // contains wildcard character
				// replace "*" and "?" with LIKE expression equivalents "%" and "_" in file name component of path
				$pattern = SIGPlusDatabase::sqlpattern(basename($source));

				// remove file name component of path
				$source = dirname($source);
			}

			// set up gallery populator
			if (is_dir($source)) {
				SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from folder: <code>'.$source.'</code>');
				$generator = new SIGPlusLocalGallery($config);
			} elseif (is_file($source)) {
				SIGPlusLogging::appendStatus('Generating gallery "'.$galleryid.'" from file: <code>'.$source.'</code>');
				$generator = new SIGPlusLocalImage($config);
				$curparams->maxcount = 1;
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
		$db->setQuery('SELECT '.$db->nameQuote('folderid').', '.$db->nameQuote('foldertime').', '.$db->nameQuote('entitytag').' FROM '.$db->nameQuote('#__sigplus_folder').' WHERE '.$db->nameQuote('folderurl').' = '.$db->quote($source));
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

		// add depth condition
		if ($curparams->depth >= 0) {
			$depthcond = ' AND depthnum <= '.$curparams->depth;
		} else {
			$depthcond = '';
		}

		// build and execute SQL query
		$viewid = (int) $viewid;
		$db->setQuery(
			'SELECT'.PHP_EOL.
				'i.'.$db->nameQuote('imageid').','.PHP_EOL.
				'IFNULL('.$db->nameQuote('watermark_fileurl').', '.$db->nameQuote('fileurl').') AS '.$db->nameQuote('url').','.PHP_EOL.
				$db->nameQuote('width').','.PHP_EOL.
				$db->nameQuote('height').','.PHP_EOL.
				'IFNULL(i.'.$db->nameQuote('title').','.PHP_EOL.
					'('.PHP_EOL.
						'SELECT c.'.$db->nameQuote('title').PHP_EOL.
						'FROM '.$db->nameQuote('#__sigplus_foldercaption').' AS c'.PHP_EOL.
						'WHERE'.PHP_EOL.
							'i.'.$db->nameQuote('filename').' LIKE c.'.$db->nameQuote('pattern').' AND '.PHP_EOL.
							'i.'.$db->nameQuote('folderid').' = c.'.$db->nameQuote('folderid').PHP_EOL.
						'ORDER BY c.'.$db->nameQuote('priority').' LIMIT 1'.PHP_EOL.
					')'.PHP_EOL.
				') AS '.$db->nameQuote('title').','.PHP_EOL.
				'IFNULL(i.'.$db->nameQuote('summary').','.PHP_EOL.
					'('.PHP_EOL.
						'SELECT c.'.$db->nameQuote('summary').PHP_EOL.
						'FROM '.$db->nameQuote('#__sigplus_foldercaption').' AS c'.PHP_EOL.
						'WHERE'.PHP_EOL.
							'i.'.$db->nameQuote('filename').' LIKE c.'.$db->nameQuote('pattern').' AND '.PHP_EOL.
							'i.'.$db->nameQuote('folderid').' = c.'.$db->nameQuote('folderid').PHP_EOL.
						'ORDER BY c.'.$db->nameQuote('priority').' LIMIT 1'.PHP_EOL.
					')'.PHP_EOL.
				') AS '.$db->nameQuote('summary').','.PHP_EOL.
				$db->nameQuote('preview_fileurl').','.PHP_EOL.
				$db->nameQuote('preview_width').','.PHP_EOL.
				$db->nameQuote('preview_height').','.PHP_EOL.
				$db->nameQuote('thumb_fileurl').','.PHP_EOL.
				$db->nameQuote('thumb_width').','.PHP_EOL.
				$db->nameQuote('thumb_height').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->nameQuote('folderid').' = f.'.$db->nameQuote('folderid').PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_hierarchy').' AS h'.PHP_EOL.
				'ON f.'.$db->nameQuote('folderid').' = h.'.$db->nameQuote('ancestorid').PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_imageview').' AS v'.PHP_EOL.
				'ON i.'.$db->nameQuote('imageid').' = v.'.$db->nameQuote('imageid').PHP_EOL.
			'WHERE'.PHP_EOL.
				$db->nameQuote('folderurl').' = '.$db->quote($source).' AND '.PHP_EOL.
				(isset($pattern) ? $db->nameQuote('fileurl').' LIKE '.$db->quote($pattern).' AND '.PHP_EOL : '').
				$db->nameQuote('viewid').' = '.$viewid.$depthcond.PHP_EOL.
			'ORDER BY '.$sortorder
		);
		$db->query();
		$total = $db->getNumRows();  // get number of images in gallery
		$rows = $db->loadRowList();

		// generate HTML code for each image
		if ($rows) {
			ob_start();  // start output buffering
			print '<!--[if gte IE 9]><!--><noscript class="sigplus-gallery"><!--<![endif]-->';  // downlevel-hidden conditional comment, browsers below IE9 ignore HTML inside, all other browsers interpret it
			print '<div id="'.$galleryid.'" class="'.$gallerystyle.'">';

			print '<ul>';
			$limit = $curparams->maxcount > 0 ? min($curparams->maxcount, count($rows)) : count($rows);
			for ($index = 0; $index < $limit; $index++) {  // no maximum preview image count set or current image index is within maximum limit
				print '<li>';
				$this->printGalleryItem($rows[$index]);
				print '</li>';
			}
			print '</ul>';

			if ($curparams->maxcount > 0 && $curparams->lightbox !== false) {  // if lightbox is disabled, user cannot navigate to images beyond maximum image count
				for (; $index < count($rows); $index++) {
					$this->printGalleryItem($rows[$index], 'display:none !important;');
				}
			}

			print '</div>';
			print '<!--[if gte IE 9]><!--></noscript><!--<![endif]-->';
			$body = ob_get_clean();  // fetch output buffer
		} else {
			$body = JText::_('SIGPLUS_GALLERY_EMPTY');
			$galleryid = null;
		}

		return $body;
	}

	private function printGalleryItem($row, $style = null) {
		$curparams = $this->paramstack->top();  // current gallery parameters

		list($imageid, $source, $width, $height, $title, $summary, $preview_url, $preview_width, $preview_height, $thumb_url, $thumb_width, $thumb_height) = $row;
		if ($style) {
			$style = ' style="'.$style.'"';
		}

		// translate paths into URLs
		$url = $this->makeURL($source);
		$preview_url = $this->makeURL($preview_url);
		$thumb_url = $this->makeURL($thumb_url);
		$download_url = $this->getImageDownloadUrl($imageid);

		if (SIGPLUS_CAPTION_CLIENT) {  // client-side template replacement
			$title = $title ? $title : $curparams->caption_title;
			$summary = $summary ? $summary : $curparams->caption_summary;
		} else {  // server-side template replacement
			$title = $this->getSubstitutedLabel($title, $curparams->caption_title, $curparams->caption_title_template, $url, $index, $total);
			$summary = $this->getSubstitutedLabel($summary, $curparams->caption_summary, $curparams->caption_summary_template, $url, $index, $total);
		}

		print '<a class="sigplus-image"'.$style.' href="'.$url.'">';
		print '<img src="'.htmlspecialchars($preview_url).'" width="'.$preview_width.'" height="'.$preview_height.'" alt="'.htmlspecialchars($title).'" />';
		print '<img class="sigplus-thumb" src="'.htmlspecialchars($thumb_url).'" width="'.$thumb_width.'" height="'.$thumb_height.'" alt="" />';
		print '</a>';
		print '<div class="sigplus-summary">'.$summary.'</div>';
		if ($download_url) {
			print '<a class="sigplus-download"'.$style.' href="'.htmlspecialchars($download_url).'"></a>';
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
				'#'.$id.' .captionplus-caption' => $captionrules
			);
			$instance->addStyles($selectors);
		}
	}

	public function addScripts($id = null) {
		if (isset($id)) {
			$curparams = $this->paramstack->top();  // current gallery parameters

			$instance = SIGPlusEngineServices::instance();
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

	public function setParameterObject(JRegistry $object) {
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