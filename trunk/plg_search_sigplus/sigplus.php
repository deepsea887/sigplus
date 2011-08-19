<?php
/**
* @file
* @brief    sigplus Image Gallery Plus image search plug-in
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

/**
* Triggered when the sigplus content plug-in is unavailable or there is a version mismatch.
*/
class SIGPlusSearchDependencyException extends Exception {
	/**
	* Creates a new exception instance.
	* @param {string} $key Error message language key.
	*/
    public function __construct() {
		$key = 'SIGPLUS_EXCEPTION_EXTENSION';
		$message = '['.$key.'] '.JText::_($key);  // get localized message text
		parent::__construct($message);
    }
}

/**
 * sigplus image search plug-in.
 */
class plgSearchSIGPlus extends JPlugin {
	private $limit = 50;
	private $core;

	public function __construct( &$subject, $config ) {
		parent::__construct( $subject, $config );
		$this->limit = (int) $this->params->get('search_limit');
		if ($this->limit < 1) {
			$this->limit = 50;
		}
	}

	/**
	* Metadata search method.
	* The SQL must return the following fields that are used in a common display
	* routine: href, title, section, created, text, browsernav
	* @param {string} $text Target search string
	* @param {string} $phrase Matching option [exact|any|all]
	* @param {string} $ordering Ordering option [newest|oldest|popular|alpha|category]
	* @param {mixed} $areas An array if the search it to be restricted to areas, null if search all
	*/
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null) {
		// load language file for internationalized labels and error messages
		$lang = JFactory::getLanguage();
		$lang->load('plg_search_sigplus', JPATH_ADMINISTRATOR);

		// skip if not searching inside image metadata
		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys(self::onContentSearchAreas()))) {
				return array();
			}
		}

		if (!isset($this->core)) {
			// load sigplus content plug-in
			if (!JPluginHelper::importPlugin('content', 'sigplus')) {
				throw new SIGPlusSearchDependencyException();
			}

			// load sigplus content plug-in parameters
			$plugin = JPluginHelper::getPlugin('content', 'sigplus');
			$params = new JParameter($plugin->params);

			// create configuration parameter objects
			$configuration = new SIGPlusConfigurationParameters();
			$configuration->service = new SIGPlusServiceParameters();
			$configuration->service->setParameters($params);
			$configuration->gallery = new SIGPlusGalleryParameters();
			$configuration->gallery->setParameters($params);

			if (SIGPLUS_LOGGING || $configuration->service->debug_server) {
				SIGPlusLogging::setService(new SIGPlusHTMLLogging());
			} else {
				SIGPlusLogging::setService(new SIGPlusNoLogging());
			}

			$this->core = new SIGPlusCore($configuration);
		}

		$db = JFactory::getDbo();

		// build SQL WHERE clause
		switch ($phrase) {
			case 'all':
			case 'any':
				$text = preg_replace('#\s+#', ' ', trim($text));  // collapse multiple spaces
				$words = explode(' ', $text);
				break;
			case 'exact':
			default:
				$words = array($text);
		}
		$wherewords = array();
		foreach ($words as $word) {
			// images whose metadata contain the given word
			$wherewords[] =
				'i.'.$db->nameQuote('imageid').' IN ('.PHP_EOL.
					'SELECT wi.'.$db->nameQuote('imageid').PHP_EOL.
					'FROM '.$db->nameQuote('#__sigplus_image').' AS wi'.PHP_EOL.
						'LEFT JOIN '.$db->nameQuote('#__sigplus_data').' AS wd'.PHP_EOL.
						'ON wi.'.$db->nameQuote('imageid').' = wd.'.$db->nameQuote('imageid').PHP_EOL.
					'WHERE'.PHP_EOL.
						'wi.'.$db->nameQuote('filename').' LIKE '.$db->quote('%'.$db->getEscaped($word, true).'%', false).' OR '.
						'wi.'.$db->nameQuote('title').' LIKE '.$db->quote('%'.$db->getEscaped($word, true).'%', false).' OR '.
						'wi.'.$db->nameQuote('summary').' LIKE '.$db->quote('%'.$db->getEscaped($word, true).'%', false).' OR '.
						'wd.'.$db->nameQuote('textvalue').' LIKE '.$db->quote('%'.$db->getEscaped($word, true).'%', false).PHP_EOL.
				')';
		}
		switch ($phrase) {
			case 'any':  // images at least one of whose metadata fields contain one of the words
				$implodephrase = 'OR';
				break;
			case 'all':  // images whose metadata fields contain all of the words
			case 'exact':
			default:
				$implodephrase = 'AND';
		}
		$where = '('.implode(PHP_EOL.$implodephrase.PHP_EOL, $wherewords).')';

		// build SQL ORDER BY clause
		$orderby = '';
		switch ($ordering) {
			case 'oldest':
				$orderby = 'filetime ASC';
				break;
			case 'newest':
				$orderby = 'filetime DESC';
				break;
			case 'category':
				$orderby = 'folderurl';
				break;
			case 'alpha':
			case 'popular':  // ignored
			default:
				$orderby = 'filename';
				break;
		}

		// build database query
		$query =
			'SELECT'.PHP_EOL.
				$db->nameQuote('fileurl').' AS url,'.PHP_EOL.
				$db->nameQuote('filename').','.PHP_EOL.
				$db->nameQuote('filetime').','.PHP_EOL.
				$db->nameQuote('width').','.PHP_EOL.
				$db->nameQuote('height').','.PHP_EOL.
				'IFNULL(i.'.$db->nameQuote('title').', f.'.$db->nameQuote('title').') AS '.$db->nameQuote('title').','.PHP_EOL.
				'IFNULL(i.'.$db->nameQuote('summary').', f.'.$db->nameQuote('summary').') AS '.$db->nameQuote('summary').PHP_EOL.
			'FROM '.$db->nameQuote('#__sigplus_image').' AS i'.PHP_EOL.
				'INNER JOIN '.$db->nameQuote('#__sigplus_folder').' AS f'.PHP_EOL.
				'ON i.'.$db->nameQuote('folderid').' = f.'.$db->nameQuote('folderid').PHP_EOL.
			'WHERE '.$where.PHP_EOL.
			'ORDER BY '.$orderby;
		$db->setQuery($query, 0, $this->limit);
		$rows = $db->loadAssocList();

		// fetch database results
		$results = array();
		if ($rows) {
			foreach($rows as $row) {
				if ($row['title']) {
					$title = $row['title'];
				} else {
					$title = $row['filename'];
				}

				// '<img src="'.$this->core->makeURL($row['preview_fileurl']).'" width="'.$row['preview_width'].'" height="'.$row['preview_height'].'" />'
				$results[] = (object) array(
					'href'        => $this->core->makeURL($row['url']),
					'text'        => '('.$row['width'].'x'.$row['height'].') '.htmlspecialchars($row['summary']),
					'title'       => html_entity_decode(strip_tags($title), ENT_QUOTES),
					'section'     => JText::_('SIGPLUS_IMAGES'),
					'created'     => $row['filetime'],
					'browsernav'  => '1'
				);
			}

			// include lightbox script only if there are image results
			$this->core->addLightboxScripts('.search-results > .result-title > a');
		}

		return $results;
	}

	/**
	 * @return {array} An array of search areas.
	 */
	public function onContentSearchAreas() {
		static $areas;
		if (!isset($areas)) {
			$areas = array(
				'sigplus' => JText::_('SIGPLUS_IMAGES')
			);
		}
		return $areas;
	}
}