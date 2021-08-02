<?php

class PageQualityScorerEmphasisRules extends PageQualityScorer{

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


	public function calculatePageScore( $text ) {
		$response = [];

		// @todo load only actual page content. right now this will also load stuff like the "protectedpagewarning" message
		$dom = new DOMDocument('1.0', 'utf-8');
		// Unicode-compatibility - see https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $text );
		$count = 0;
		$emphasis_gov = false;
		$divNodes = $dom->getElementsByTagName('div');
	    for ($i = 0; $i < $divNodes->length; $i++) {
	        if ( stripos($divNodes->item($i)->getAttribute('class'), "emphasis-item") !== false
	        	&&
	        	stripos($divNodes->item($i)->getAttribute('class'), "emphasis-item-icon") === false
	        	&&
	        	stripos($divNodes->item($i)->getAttribute('class'), "emphasis-item-text") === false
	   		) {
	        	$count++;
				$wc = self::str_word_count_utf8( $divNodes->item($i)->nodeValue );
				if ( $wc >= $this->getSetting( "emphasis_line_length" ) ) {
					$response['emphasis_line_length'][] = [
						"score" => self::getCheckList()['emphasis_line_length']['severity'],
						"example" => mb_substr( $divNodes->item($i)->nodeValue, 0, 50)
					];
				} else if ( $wc >= $this->getSetting( "emphasis_line_length_min" ) ) {
					$response['emphasis_line_length_min'][] = [
						"score" => self::getCheckList()['emphasis_line_length_min']['severity'],
						"example" => mb_substr( $divNodes->item($i)->nodeValue, 0, 50)
					];
				}
		        if ( strpos( $divNodes->item($i)->getAttribute('class'), "emphasis-type-government" ) !== false ) {
					$emphasis_gov = true;
		        } else {
					$emphasis_gov = false;
		        }
	        }
	    }

		if ( $count < $this->getSetting( "emphasis_lines_min" ) ) {
			$response['emphasis_lines_min'][] = [
				"score" => self::getCheckList()['emphasis_lines_min']['severity'],
				"example" => wfMessage( "pq_occurance", $count )
			];
		}
		if ( $count >= $this->getSetting( "emphasis_lines_num" ) ) {
			$response['emphasis_lines_num'][] = [
				"score" => self::getCheckList()['emphasis_lines_num']['severity'],
				"example" => wfMessage( "pq_occurance", $count )
			];
		}
		if ( !$emphasis_gov ) {
			$response['emphasis_gov_symbol'][] = [
				"score" => self::getCheckList()['emphasis_gov_symbol']['severity'],
				"example" => wfMessage( "pq_occurance", 0 )
			];
		}
		return $response;
	}
}
