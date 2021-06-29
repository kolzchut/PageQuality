<?php

abstract class PageQualityScorer{
	const YELLOW = 1;
	const RED = 2;

	abstract public function calculatePageScore( $text );

	public static $registered_classes = [];
	public static $settings = [];
	public static $checksList = [];

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

	public function getCheckList() {
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

	public static function getAllChecksList() {
		$all_checklist = [];
		foreach( self::$registered_classes as $scorer_class ) {
			$all_checklist += $scorer_class::getCheckList();
		}
		return $all_checklist;
	}

	public static function runAllScoreres( $text ) {
		$responses = [];
		foreach( self::$registered_classes as $scorer_class ) {
			$scorer_obj = new $scorer_class();
			$responses += $scorer_obj->calculatePageScore( $text );
		}

		$score = 0;
		foreach( $responses as $type => $type_responses ) {
			foreach( $type_responses as $response ) {
				$score += $response['score'];
			}
		}
		return [ $score, $responses ];
	}
}