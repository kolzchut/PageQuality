<?php

class PageQualityScorerReadability extends PageQualityScorer{

	public static $checksList = [
		"blocked_expressions" => [
			"name" => "pag_scorer_stop_words",
			"description" => "pag_scorer_stop_words_desc",
			"check_type" => "do_not_exist",
			"data_type" => "list",
			"severity" => PageQualityScorer::RED,
			"default" => ""
		],
		"para_length" => [
			"name" => "pag_scorer_para_len",
			"description" => "pag_scorer_para_len_desc",
			"check_type" => "max",
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


	public function calculatePageScore() {
		$response = [];

		$blocked_expressions = explode( PHP_EOL, self::getSetting( "blocked_expressions" ) );
		foreach( $blocked_expressions as $blocked_expression ) {
			$offset = 0;
			while( ( $offset = strpos( strip_tags( self::getText() ), trim( $blocked_expression ), $offset ) ) !== false ) {
				$cut_off_start_offset = max( 0, $offset - 30 );
				if ( strpos( strip_tags( self::getText() ), " ", $cut_off_start_offset ) !== false ) {
					$cut_off_start_offset = strpos( strip_tags( self::getText() ), " ", $cut_off_start_offset );
				}

				$response['blocked_expressions'][] = [
					"score" => self::getCheckList()['blocked_expressions']['severity'],
					"example" => str_replace( $blocked_expression, "<b>" . $blocked_expression . "</b>", substr( strip_tags( self::getText() ), $cut_off_start_offset, $cut_off_start_offset + strlen( $blocked_expression ) + 30 ) )
				];
				$offset += strlen( $blocked_expression );
			}
		}


		$pNodes = self::getDOM()->getElementsByTagName('p');
		foreach ( $pNodes as $pNode ) {
			$wc = self::str_word_count_utf8( $pNode->nodeValue );

			$score = "green";
			if ( $wc >= self::getSetting( "para_length_max" ) ) {
				$response['para_length_max'][] = [
					"score" => self::getCheckList()['para_length_max']['severity'],
					"example" => mb_substr( $pNode->nodeValue, 0, 50)
				];
			} else if ( $wc >= self::getSetting( "para_length" ) ) {
				$response['para_length'][] = [
					"score" => self::getCheckList()['para_length']['severity'],
					"example" => mb_substr( $pNode->nodeValue, 0, 50)
				];
			}

			$sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $pNode->nodeValue);
			foreach( $sentences as $sentence ) {
				$wc = self::str_word_count_utf8( $sentence );

				if ( $wc >= self::getSetting( "sentence_length_max" ) ) {
					$response['sentence_length_max'][] = [
						"score" => self::getCheckList()['sentence_length_max']['severity'],
						"example" => mb_substr( $sentence, 0, 50)
					];
				} else if ( $wc >= self::getSetting( "sentence_length" ) ) {
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
