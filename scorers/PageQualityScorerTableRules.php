<?php

class PageQualityScorerTableRules extends PageQualityScorer{

	public static $checksList = [
		"table_columns" => [
			"name" => "pag_scorer_table_columns",
			"description" => "table_columns_desc",
			"check_type" => "max",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 3,
		],
		"table_rows" => [
			"name" => "pag_scorer_table_rows",
			"description" => "table_rows_desc",
			"check_type" => "min",
			"severity" => PageQualityScorer::YELLOW,
			"default" => 3,
		],
	];


	public function calculatePageScore( $text ) {
		$response = [];

		// @todo load only actual page content. right now this will also load stuff like the "protectedpagewarning" message
		$dom = new DOMDocument('1.0', 'utf-8');
		// Unicode-compatibility - see https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $text );

		$tableNodes = $dom->getElementsByTagName('table');
	    for ($i = 0; $i < $tableNodes->length; $i++) {
	    	$row_count = 0;
	    	$column_count = 0;
	    	foreach( $tableNodes->item($i)->getElementsByTagName('tr') as $tr_node ) {
	    		$row_count++;
	    		$column_count = max( $tr_node->getElementsByTagName('td')->length, $column_count );
	    	}
			if ( $column_count > $this->getSetting( "table_columns" ) ) {
				$response['table_columns'][] = [
					"score" => self::getCheckList()['table_columns']['severity'],
					"example" => wfMessage( "pq_occurance", $column_count )
				];
			}
			if ( $row_count < $this->getSetting( "table_rows" ) ) {
				$response['table_rows'][] = [
					"score" => self::getCheckList()['table_rows']['severity'],
					"example" => wfMessage( "pq_occurance", $row_count )
				];
			}
	    }
		return $response;
	}
}
