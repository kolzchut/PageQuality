<?php

class PageQualityScorerTableRules extends PageQualityScorer {

	/** @inheritdoc */
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

	/**
	 * @inheritDoc
	 */
	public function calculatePageScore() {
		$response = [];

		$tableNodes = self::getDOM()->getElementsByTagName( 'table' );
		for ( $i = 0; $i < $tableNodes->length; $i++ ) {
			$row_count = 0;
			$column_count = 0;
			foreach ( $tableNodes->item( $i )->getElementsByTagName( 'tr' ) as $tr_node ) {
				$row_count++;
				$column_count = max( $tr_node->getElementsByTagName( 'td' )->length, $column_count );
			}
			if ( $column_count > self::getSetting( "table_columns" ) ) {
				$response['table_columns'][] = [
					"score" => self::getCheckList()['table_columns']['severity'],
					"example" => wfMessage( "pq_occurance_table_columns", $column_count )
				];
			}
			if ( $row_count < self::getSetting( "table_rows" ) ) {
				$response['table_rows'][] = [
					"score" => self::getCheckList()['table_rows']['severity'],
					"example" => wfMessage( "pq_occurance_table_rows", $row_count )
				];
			}
		}
		return $response;
	}
}
