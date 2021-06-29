<?php

class PageQualityScorerReadability extends PageQualityScorer{

	public static $checksList = [
		"para_length" => [
			"name" => "pag_scorer_para_len",
			"description" => "pag_scorer_para_len_desc",
			"check_type" => "min",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 40
		],
		"para_length_max" => [
			"name" => "pag_scorer_para_len_max",
			"description" => "pag_scorer_para_len_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 60
		],
		"sentence_length" => [
			"name" => "pag_scorer_sentence_len",
			"description" => "pag_scorer_sentence_len_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 15
		],
		"sentence_length_max" => [
			"name" => "pag_scorer_sentence_len_max",
			"description" => "pag_scorer_sentence_len_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 30
		],
	];


	public function calculatePageScore( $text ) {
		$response = [];

		// @todo load only actual page content. right now this will also load stuff like the "protectedpagewarning" message
		$dom = new DOMDocument('1.0', 'utf-8');
		// Unicode-compatibility - see https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $text );
		$pNodes = $dom->getElementsByTagName('p');

		foreach ( $pNodes as $pNode ) {
			$wc = self::str_word_count_utf8( $pNode->nodeValue );

			$score = "green";
			if ( $wc >= $this->getSetting( "para_length_max" ) ) {
				$response['para_length_max'][] = [
					"score" => self::getCheckList()['para_length_max']['severity'],
					"example" => mb_substr( $pNode->nodeValue, 0, 50)
				];
			} else if ( $wc >= $this->getSetting( "para_length" ) ) {
				$response['para_length'][] = [
					"score" => self::getCheckList()['para_length']['severity'],
					"example" => mb_substr( $pNode->nodeValue, 0, 50)
				];
			}

			$sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $pNode->nodeValue);
			foreach( $sentences as $sentence ) {
				$wc = self::str_word_count_utf8( $sentence );

				if ( $wc >= $this->getSetting( "sentence_length_max" ) ) {
					$response['sentence_length_max'][] = [
						"score" => self::getCheckList()['sentence_length_max']['severity'],
						"example" => mb_substr( $sentence, 0, 50)
					];
				} else if ( $wc >= $this->getSetting( "sentence_length" ) ) {
					$response['sentence_length'][] = [
						"score" => self::getCheckList()['sentence_length']['severity'],
						"example" => mb_substr( $sentence, 0, 50)
					];
				}
			}
		}

		return $response;
	}
}
