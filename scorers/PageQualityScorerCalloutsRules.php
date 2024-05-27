<?php

class PageQualityScorerCalloutsRules extends PageQualityScorer {

	/** @inheritdoc */
	public static $checksList = [
		"callout_gaps" => [
			"name" => "pag_scorer_callout_gaps",
			"description" => "callout_gaps_desc",
			"check_type" => "do_not_exist",
			"severity" => PageQualityScorer::YELLOW,
		],
		"callout_section_begin" => [
			"name" => "pag_scorer_callout_section_begin",
			"description" => "callout_section_begin_desc",
			"check_type" => "do_not_exist",
			"severity" => PageQualityScorer::YELLOW,
		],
		"callout_number" => [
			"name" => "pag_scorer_callout_number",
			"description" => "callout_number_desc",
			"check_type" => "exist",
			"severity" => PageQualityScorer::YELLOW,
		],
	];

	/**
	 * @inheritDoc
	 */
	public function calculatePageScore() {
		$response = [];

		$count = 0;
		$divNodes = self::getDOM()->getElementsByTagName( 'div' );
		for ( $i = 0; $i < $divNodes->length; $i++ ) {
			$class = $divNodes->item( $i )->getAttribute( 'class' );
			if (
				stripos( $class, "wr-example" ) !== false ||
				stripos( $class, "wr-tip" ) !== false ||
				stripos( $class, "wr-please-note" ) !== false ||
				stripos( $class, "wr-warning" ) !== false
			) {
				$count++;
				$previousNode = $divNodes->item( $i )->previousSibling;
				while ( $previousNode instanceof DOMText ) {
					$previousNode = $previousNode->previousSibling;
				}

				if ( !$previousNode ) {
					return $response;
				}

				if ( in_array( $previousNode->tagName, [ "h1", "h2", "h3", "h4" ] ) ) {
					$response['callout_section_begin'][] = [
						"score" => self::getCheckList()['callout_section_begin']['severity'],
						"example" => $divNodes->item( $i )->textContent
					];
				}
				$class = $previousNode->getAttribute( 'class' );
				if (
					stripos( $class, "wr-example" ) !== false ||
					stripos( $class, "wr-tip" ) !== false ||
					stripos( $class, "wr-please-note" ) !== false ||
					stripos( $class, "wr-warning" ) !== false
				) {
					$response['callout_gaps'][] = [
						"score" => self::getCheckList()['callout_gaps']['severity'],
						"example" => $divNodes->item( $i )->textContent
					];
				}
			}
		}
		if ( !$count ) {
			$response['callout_number'][] = [
				"score" => self::getCheckList()['callout_number']['severity'],
				"example" => null
			];
		}
		return $response;
	}
}
