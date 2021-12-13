<?php

class PageQualityScorerCalloutsRules extends PageQualityScorer{

	public static $checksList = [
		// "callout_gaps" => [
		// 	"name" => "pag_scorer_callout_gaps",
		// 	"description" => "callout_gaps_desc",
		// 	"check_type" => "exist",
		// 	"severity" => PageQualityScorer::YELLOW,
		// ],
		// "callout_section_begin" => [
		// 	"name" => "pag_scorer_callout_section_begin",
		// 	"description" => "callout_section_begin_desc",
		// 	"check_type" => "exist",
		// 	"severity" => PageQualityScorer::YELLOW,
		// ],
		"callout_number" => [
			"name" => "pag_scorer_callout_number",
			"description" => "callout_number_desc",
			"check_type" => "exist",
			"severity" => PageQualityScorer::YELLOW,
		],
	];

	public function calculatePageScore() {
		$response = [];

		$count = 0;
		$divNodes = self::getDOM()->getElementsByTagName('div');
	    for ($i = 0; $i < $divNodes->length; $i++) {
	        if (stripos($divNodes->item($i)->getAttribute('class'), "wr-example") !== false) {
	        	$count++;
	        } else if (stripos($divNodes->item($i)->getAttribute('class'), "wr-tip") !== false) {
	        	$count++;
	        } else if (stripos($divNodes->item($i)->getAttribute('class'), "wr-please-note") !== false) {
	        	$count++;
	        } else if (stripos($divNodes->item($i)->getAttribute('class'), "wr-warning") !== false) {
	        	$count++;
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
