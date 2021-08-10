<?php

abstract class PageQualityScorer{
	const YELLOW = 1;
	const RED = 2;

	abstract public function calculatePageScore();

	public static $registered_classes = [];
	public static $settings = [];
	public static $checksList = [];
	public static $text = null;
	public static $dom = null;

	/***
	 * @link https://www.php.net/manual/en/function.str-word-count.php#107363
	 *
	 * This simple utf-8 word count function (it only counts)
	 * is a bit faster then the one with preg_match_all
	 * about 10x slower then the built-in str_word_count
	 *
	 * If you need the hyphen or other code points as word-characters
	 * just put them into the [brackets] like [^\p{L}\p{N}\'\-]
	 * If the pattern contains utf-8, utf8_encode() the pattern,
	 * as it is expected to be valid utf-8 (using the u modifier).
	 **/

	// Jonny 5's simple word splitter
	function str_word_count_utf8($str) {
		return count(preg_split('~[^\p{L}\p{N}\']+~u',$str));
	}

	public static function getCheckList() {
		return static::$checksList;
	}

	public static function loadAllScoreres() {
		foreach ( glob( __DIR__ . "/scorers/*.php") as $filename ) {
			include_once $filename;
			self::$registered_classes[] = basename($filename, '.php');
		}
	}

	public function getSetting( $type ) {
		if ( array_key_exists($type, self::getSettingValues()) ) {
			return self::$settings[$type];
		}
		return self::getCheckList()[$type]['default'];
	}

	public static function getAllScorers( ) {
		if ( empty( self::$registered_classes ) ) {
			self::loadAllScoreres();
		}
		return self::$registered_classes;
	}

	public static function getSettingValues() {
		if ( !empty( self::$settings ) ) {
			return self::$settings;
		}
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			"pq_settings",
			'*',
			array( true ),
			__METHOD__
		);

		self::$settings = [];
		foreach( $res as $row ) {
			self::$settings[$row->setting] = $row->value;
		}
		return self::$settings;
	}

	/**
	 * @param string $text
	 */
	public static function loadDOM( $text ) {
		// @todo load only actual page content. right now this will also load stuff like the "protectedpagewarning" message
		$dom = new DOMDocument('1.0', 'utf-8');
		// Unicode-compatibility - see https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $text );
		$dom->preserveWhiteSpace = false;
		self::$dom = $dom;
		self::$text = $text;
		self::removeIgnoredElements();

		return self::$dom;
	}

	/**
	 * @return string|null
	 */
	protected static function getText() {
		return self::$text;
	}

	/**
	 * @return DOMDocument|null
	 */
	protected static function getDOM() {
		return self::$dom;
	}

	public static function getAllChecksList() {
		$all_checklist = [];
		foreach( self::$registered_classes as $scorer_class ) {
			$all_checklist += $scorer_class::getCheckList();
		}
		return $all_checklist;
	}

	public static function runAllScoreres( $text ) {
		self::loadDOM( $text );
		$responses = [];
		foreach( self::$registered_classes as $scorer_class ) {
			$scorer_obj = new $scorer_class();
			$responses += $scorer_obj->calculatePageScore();
		}

		$score = 0;
		foreach( $responses as $type => $type_responses ) {
			foreach( $type_responses as $response ) {
				$score += $response['score'];
			}
		}
		return [ $score, $responses ];
	}

	protected static function getElementsByClassName( DOMDocument $dom, $className ) {
		$xpath = new DOMXpath( $dom );
		$expression = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]';
		return $xpath->query( $expression );
	}

	protected static function removeIgnoredElements () {
		$ignoredElements = self::getElementsByClassName( self::getDOM(), 'pagequality-ignore' );
		foreach ( $ignoredElements as $element ) {
			$element->parentNode->removeChild( $element );
		}
	}

}
