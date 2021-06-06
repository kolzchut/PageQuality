<?php

abstract class PageQualityScorer{
	const YELLOW = 1;
	const RED = 2;

	abstract public static function getChecksList( );
	abstract public function calculatePageScore( $text );

	public static $registered_classes = [];

	public static function loadAllScoreres() {
		foreach ( glob( __DIR__ . "/scorers/*.php") as $filename ) {
		    include_once $filename;
			self::$registered_classes[] = basename($filename, '.php');
		}
	}

	public static function getAllScorers( ) {
		if ( empty( self::$registered_classes ) ) {
			self::loadAllScoreres();
		}
		return self::$registered_classes;
	}

	public static function getAllChecksList( ) {
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