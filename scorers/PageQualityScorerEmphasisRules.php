<?php

class PageQualityScorerEmphasisRules extends PageQualityScorer {

	/** @inheritdoc */
	public static $checksList = [
		"emphasis_lines_min" => [
			"name" => "pag_scorer_emphasis_lines_min",
			"description" => "emphasis_lines_num_desc",
			"check_type" => "min",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 2,
		],
		"emphasis_lines_num" => [
			"name" => "pag_scorer_emphasis_lines_num",
			"description" => "emphasis_lines_num_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 5,
		],
		"emphasis_gov_symbol" => [
			"name" => "pag_scorer_emphasis_gov_symbol",
			"description" => "emphasis_gov_sym_desc",
			"check_type" => "exist",
			"severity" => PageQualityScorer::YELLOW,
			"disabled" => true
		],
		"emphasis_line_length_min" => [
			"name" => "pag_scorer_emphasis_line_length_min",
			"description" => "pag_scorer_emphasis_length_min_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 15,
		],
		"emphasis_line_length" => [
			"name" => "pag_scorer_emphasis_length",
			"description" => "pag_scorer_emphasis_length_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 30
		]
	];

	/**
	 * @inheritDoc
	 */
	public function calculatePageScore() {
		$response = [];
		$count = 0;
		$emphasis_gov = false;
		$divNodes = self::getDOM()->getElementsByTagName( 'div' );
		for ( $i = 0; $i < $divNodes->length; $i++ ) {
			if ( stripos( $divNodes->item( $i )->getAttribute( 'class' ), "emphasis-item" ) !== false
				&&
				stripos( $divNodes->item( $i )->getAttribute( 'class' ), "emphasis-item-icon" ) === false
				&&
				stripos( $divNodes->item( $i )->getAttribute( 'class' ), "emphasis-item-text" ) === false
			) {
				$count++;
				$wc = self::str_word_count_utf8( $divNodes->item( $i )->nodeValue );
				if ( $wc > self::getSetting( "emphasis_line_length" ) ) {
					$response['emphasis_line_length'][] = [
						"score" => self::getCheckList()['emphasis_line_length']['severity'],
						"example" => mb_substr( $divNodes->item( $i )->nodeValue, 0, 50 )
					];
				} elseif ( $wc > self::getSetting( "emphasis_line_length_min" ) ) {
					$response['emphasis_line_length_min'][] = [
						"score" => self::getCheckList()['emphasis_line_length_min']['severity'],
						"example" => mb_substr( $divNodes->item( $i )->nodeValue, 0, 50 )
					];
				}
				if ( self::getCheckList()['emphasis_gov_symbol']['disabled'] === true || strpos( $divNodes->item( $i )->getAttribute( 'class' ), "emphasis-type-government" ) !== false ) {
					$emphasis_gov = true;
				} else {
					$emphasis_gov = false;
				}
			}
		}

		if ( $count < self::getSetting( "emphasis_lines_min" ) ) {
			$response['emphasis_lines_min'][] = [
				"score" => self::getCheckList()['emphasis_lines_min']['severity'],
				"example" => wfMessage( "pq_occurance_emphasis_lines", $count )
			];
		}
		if ( $count > self::getSetting( "emphasis_lines_num" ) ) {
			$response['emphasis_lines_num'][] = [
				"score" => self::getCheckList()['emphasis_lines_num']['severity'],
				"example" => wfMessage( "pq_occurance_emphasis_lines", $count )
			];
		}
		if ( !$emphasis_gov ) {
			$response['emphasis_gov_symbol'][] = [
				"score" => self::getCheckList()['emphasis_gov_symbol']['severity'],
				"example" => null
			];
		}
		return $response;
	}
}
