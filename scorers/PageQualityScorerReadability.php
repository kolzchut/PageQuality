<?php

class PageQualityScorerReadability{

	public static $checksList = [
		"para_length" => [
			"name" => "Max words in a paragraph",
			"description" => "The maximum number of words a paragraph should have",
			PageQualityScorer::YELLOW => 40,
			PageQualityScorer::RED => 60
		],
		"sentence_length" => [
			"name" => "Max words in a sentence",
			"description" => "The maximum number of words a sentence should have",
			PageQualityScorer::YELLOW => 15,
			PageQualityScorer::RED => 30
		],
	];

	public static function getCheckList() {
		return self::$checksList;
	}

	public function calculatePageScore( $text ) {
		$response = [];

		$dom = new DOMDocument();
		$dom->loadHTML( $text );
		$pNodes = $dom->getElementsByTagName('p');

		foreach ( $pNodes as $pNode ) {
			$wc = str_word_count( $pNode->nodeValue );

			$score = "green";
			if ( $wc >= self::$checksList["para_length"][PageQualityScorer::RED] ) {
				$score = PageQualityScorer::RED;
			} else if ( $wc >= self::$checksList["para_length"][PageQualityScorer::YELLOW] ) {
				$score = PageQualityScorer::YELLOW;
			}
			if ( $score != "green" ) {
				$response['para_length'][] = [
					"score" => $score,
					"example" => substr( $pNode->nodeValue, 0, 50)
				];
			}


			$sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $pNode->nodeValue);
			foreach( $sentences as $sentence ) {
				$wc = str_word_count( $sentence );

				$score = "green";
				if ( $wc >= self::$checksList["sentence_length"][PageQualityScorer::RED] ) {
					$score = PageQualityScorer::RED;
				} else if ( $wc >= self::$checksList["sentence_length"][PageQualityScorer::YELLOW] ) {
					$score = PageQualityScorer::YELLOW;
				}
				if ( $score != "green" ) {
					$response['sentence_length'][] = [
						"score" => $score,
						"example" => substr( $sentence, 0, 50)
					];
				}
			}
		}

		return $response;
	}
}