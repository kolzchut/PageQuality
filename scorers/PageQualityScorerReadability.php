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
				$wc = self::str_word_count_utf8( $sentence );

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
