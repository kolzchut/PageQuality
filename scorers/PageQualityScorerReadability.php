<?php

class PageQualityScorerReadability extends PageQualityScorer {

	/**
	 * @inheritdoc
	 */
	public static $checksList = [
		"blocked_expressions" => [
			"name" => "pag_scorer_stop_words",
			"description" => "pag_scorer_stop_words_desc",
			"check_type" => "do_not_exist",
			"data_type" => "list",
			"severity" => PageQualityScorer::YELLOW,
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
			"description" => "pag_scorer_para_len_max_desc",
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
			"description" => "pag_scorer_sentence_len_max_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 30
		],
		"list_items_per_level_max" => [
			"name" => "pag_scorer_list_items_per_level_max",
			"description" => "pag_scorer_list_items_per_level_max_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::RED,
			"default" => 10
		],
	];
	/** @var array */
	public $response = [];

	/**
	 * @inheritDoc
	 */
	public function calculatePageScore() {
		$blocked_expressions = self::getSetting( "blocked_expressions" );
		if ( !empty( $blocked_expressions ) ) {
			foreach ( $blocked_expressions as $blocked_expression ) {
				if ( trim( $blocked_expression ) === '' ) {
					continue;
				}
				$offset = 0;
				while ( ( $offset = strpos( strip_tags( self::getText() ), $blocked_expression, $offset ) ) !== false ) {
					$cut_off_start_offset = max( 0, $offset - 30 );
					if ( strpos( strip_tags( self::getText() ), " ", $cut_off_start_offset ) !== false ) {
						$cut_off_start_offset = strpos( strip_tags( self::getText() ), " ", $cut_off_start_offset );
					}

					$this->response[ 'blocked_expressions' ][] = [
						"score" => self::getCheckList()[ 'blocked_expressions' ][ 'severity' ],
						"example" => substr_replace(
							substr(
								strip_tags( self::getText() ),
								$cut_off_start_offset,
								$cut_off_start_offset + strlen( $blocked_expression ) + 30
							),
							"<b>" . $blocked_expression . "</b>",
							$offset - $cut_off_start_offset,
							strlen( $blocked_expression )
						)
					];
					$offset += strlen( $blocked_expression );
				}
			}
		}

		$dom = self::loadDOM( self::$text );
		$pNodes = $dom->getElementsByTagName( 'html' );
		foreach ( $pNodes as $pNode ) {
			$this->recurseDomNodes( $pNode );
		}
		return $this->response;
	}

	/**
	 * @param string $text
	 *
	 * @return DOMDocument|null
	 */
	public static function loadDOM( string $text ): ?DOMDocument {
		$text = strip_tags( $text,
			[ 'p', 'table', 'tr', 'th', 'td', 'div', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5' ]
		);
		return parent::loadDOM( $text );
	}

	/**
	 * @param DOMNode $pNode
	 *
	 * @return void
	 */
	public function recurseDomNodes( $pNode ) {
		if ( !$pNode instanceof DOMText && ( $pNode->tagName == "ul" || $pNode->tagName == "ol" ) ) {
			if ( $pNode->hasChildNodes() ) {
				$count_of_li = 0;
				foreach ( $pNode->childNodes as $childNode ) {
					if ( !$childNode instanceof DOMText && $childNode->tagName == "li" ) {
						$count_of_li++;
					}
				}
				if ( $count_of_li > self::getSetting( "list_items_per_level_max" ) ) {
					$this->response['list_items_per_level_max'][] = [
						"score" => self::getCheckList()['list_items_per_level_max']['severity'],
						"example" => mb_substr( $pNode->textContent, 0, 50 )
					];
				}
			}
		}
		if ( $pNode->hasChildNodes() && count( $pNode->childNodes ) > 0 ) {
			foreach ( $pNode->childNodes as $childNode ) {
				$this->recurseDomNodes( $childNode );
			}
		} elseif ( $pNode->hasChildNodes() && $pNode->firstChild->hasChildNodes() ) {
			foreach ( $pNode->firstChild->childNodes as $childNode ) {
				$this->recurseDomNodes( $childNode );
			}
		} else {
			if ( empty( trim( $pNode->nodeValue ) ) ) {
				return;
			}
			if ( stripos( $pNode->parentNode->getAttribute( 'class' ), "emphasis-item-text" ) === false ) {
				$this->evaluateParagraphs( $pNode->nodeValue );
			}
		}
	}

	/**
	 * @param string $str
	 *
	 * @return void
	 */
	public function evaluateParagraphs( string $str ) {
		$sentences = preg_split( '/(?<=[.?!])\s+(?=[a-z])/i', $str );
		foreach ( $sentences as $sentence ) {
			$wc = self::str_word_count_utf8( $sentence );

			if ( $wc > self::getSetting( "sentence_length_max" ) ) {
				$this->response['sentence_length_max'][] = [
					"score" => self::getCheckList()['sentence_length_max']['severity'],
					"example" => mb_substr( $sentence, 0, 50 )
				];
			} elseif ( $wc > self::getSetting( "sentence_length" ) ) {
				$this->response['sentence_length'][] = [
					"score" => self::getCheckList()['sentence_length']['severity'],
					"example" => mb_substr( $sentence, 0, 50 )
				];
			}
		}

		if ( count( $sentences ) == 1 ) {
			return;
		}

		$wc = self::str_word_count_utf8( $str );
		if ( $wc > self::getSetting( "para_length_max" ) ) {
			$this->response['para_length_max'][] = [
				"score" => self::getCheckList()['para_length_max']['severity'],
				"example" => mb_substr( $str, 0, 50 )
			];
		} elseif ( $wc > self::getSetting( "para_length" ) ) {
			$this->response['para_length'][] = [
				"score" => self::getCheckList()['para_length']['severity'],
				"example" => mb_substr( $str, 0, 50 )
			];
		}
	}
}
