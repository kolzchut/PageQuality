<?php

use MediaWiki\Extension\ArticleContentArea\ArticleContentArea;
use MediaWiki\Extension\ArticleType\ArticleType;
use MediaWiki\Linker\LinkRenderer;

class PageQualityReportPager extends TablePager {

	/** @var LinkRenderer */
	private LinkRenderer $linkRenderer;
	/** @var string */
	private string $report_type;
	/** @var FormOptions */
	private FormOptions $opts;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param FormOptions $opts
	 * @param string $report_type
	 */
	public function __construct(
		IContextSource $context, LinkRenderer $linkRenderer, FormOptions $opts, string $report_type
	) {
		$this->report_type = $report_type;

		parent::__construct( $context );

		$this->opts = $opts;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		static $headers = null;

		PageQualityScorer::loadAllScoreres();
		$all_checklist = PageQualityScorer::getAllChecksList();

		$headers = [
			'pagename' => 'pq_report_pagename',
			'score' => 'pq_report_page_score',
			// 'score_old' => 'pq_report_page_score_old',
			'status' => 'pq_report_page_status',
			"old_score" => "pq_report_page_score_old"
		];
		$headers["timestamp"] = "pq_report_page_score_timestamp";

		foreach ( $all_checklist as $type => $type_data ) {
			$headers[$type] = $type_data['name'];
		}
		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	/**
	 * It seems we totally ignore this function and use formatValueMy() in formatRow().
	 * That seems wrong, but what do I know.
	 * This function must still be implemented, even empty.
	 *
	 * @todo clean up this mess
	 *
	 * @inheritDoc
	 */
	public function formatValue( $name, $value ) {
	}

	/**
	 * Format a table cell. The return value should be HTML, but use an empty
	 * string not &#160; for empty cells. Do not include the <td> and </td>.
	 *
	 * The current result row is available as $this->mCurrentRow, in case you
	 * need more context.
	 *
	 * @param string $name The database field name
	 * @param string|null $value The value retrieved from the database
	 * @param array|null $page_stats
	 *
	 * @return string|null
	 */
	public function formatValueMy( string $name, ?string $value, ?array $page_stats ): ?string {
		$formatted = "";

		$row = $this->mCurrentRow;

		if ( array_key_exists( $name, $page_stats ) ) {
			return $page_stats[$name];
		}

		$score = !empty( $row->new_score ) ? $row->new_score : $row->score;

		switch ( $name ) {
			case 'pagename':
				$formatted = $this->linkRenderer->makeKnownLink( Title::newFromRow( $row ) );
				break;
			case 'timestamp':
				if ( !empty( $row->timestamp ) ) {
					$formatted = ( new DateTime() )->setTimestamp( wfTimestamp( TS_UNIX, $row->timestamp ) )
												   ->format( 'j M y' );
				}
				break;
			case 'score':
				$formatted = $score;
				break;
			case 'old_score':
				$formatted = $row->old_score;
				break;
			case 'status':
				$status = ( $score > PageQualityScorer::getSetting( "red" ) )
					? "red"
					: ( $score > 0 ? "yellow" : "green" );
				$formatted = $this->msg( 'pq_report_page_status_' . $status )->escaped();
				break;
			default:
				break;
		}

		return $formatted;
	}

	/**
	 * @stable to override
	 * @param stdClass $row
	 * @return string HTML
	 *
	 * @todo This is horrible! it does one DB query *per row*,, intead of selecting them all at once
	 */
	public function formatRow( $row ) {
			// Save the row, in case formatValue etc. need to know
			$this->mCurrentRow = $row;
			$s = Html::openElement( 'tr', $this->getRowAttrs( $row ) ) . "\n";
			$fieldNames = $this->getFieldNames();

			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select(
				"pq_issues",
				'*',
				[ 'page_id' => $row->page_id ],
				__METHOD__
			);
			$page_stats = [];
			foreach ( $res as $issue_row ) {
				$type = $issue_row->pq_type;
				if ( !array_key_exists( $type, $page_stats ) ) {
					$page_stats[$type] = 0;
				}
				$page_stats[$type]++;
			}

			foreach ( $fieldNames as $field => $name ) {
					$value = $row->$field ?? null;
					$formatted = strval( $this->formatValueMy( $field, $value, $page_stats ) );

					if ( $formatted == '' ) {
							$formatted = "\u{00A0}";
					}

					$s .= Html::rawElement( 'td', $this->getCellAttrs( $field, $value ), $formatted ) . "\n";
			}

			$s .= Html::closeElement( 'tr' ) . "\n";

			return $s;
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function getQueryInfo(): array {
		$from_date = $this->opts->getValue( 'from_date' );
		$to_date = $this->opts->getValue( 'to_date' );

		// We also join on the page table, so that deleted pages do not show
		// While doing that, we get enough fields for Title::newFromRow()
		$info = [
			'tables' => [ 'pq_score', 'pq_score_log', 'page' ],
			'fields' => [
				'page.page_id', 'page.page_namespace', 'page.page_title',
				'pq_score.score', 'old_score', 'pq_score_log.timestamp', 'MAX(pq_score_log.timestamp)'
			],
			'conds' => $this->getConditionLimitByDates( 'timestamp', $from_date, $to_date ),
			'join_conds' => [
				"pq_score_log" => [
					'LEFT JOIN',
					[ 'pq_score.page_id = pq_score_log.page_id', 'pq_score.score = pq_score_log.new_score' ]
				],
				'page' => [ 'INNER JOIN', [ 'pq_score.page_id = page.page_id' ] ]
			],
			'options' => [ 'GROUP BY' => "page.page_id" ]
		];

		$redScoreSetting = PageQualityScorer::getSetting( "red" );

		switch ( $this->report_type ) {
			case "all":
				$info['conds'][] = "score > 1";
				break;
			case "red_all":
				$info['conds'][] = "score > $redScoreSetting";
				break;
			case "yellow_all":
				$info['conds'][] = "score > 0";
				$info['conds'][] = "score <= $redScoreSetting";
				break;
			case "declines":
				$info = $this->getScoreLogQuery( $from_date, $to_date );
				$info['options']['HAVING'] = "new_score > $redScoreSetting AND old_score < $redScoreSetting";
				break;
			case "improvements":
				$info = $this->getScoreLogQuery( $from_date, $to_date );
				$info['options']['HAVING'] = "new_score < $redScoreSetting AND old_score > $redScoreSetting";
				break;
			default:
				$info['tables'][] = 'pq_issues';
				$info['conds']['pq_type'] = $this->report_type;
				$info['join_conds']['pq_issues'] = [ 'LEFT JOIN', 'pq_score.page_id = pq_issues.page_id' ];
		}

		if ( \ExtensionRegistry::getInstance()->isLoaded( 'ArticleContentArea' ) &&
			 !empty( $this->opts->getValue( 'article_content_type' ) )
		) {
			$info = array_merge_recursive(
				$info,
				ArticleContentArea::getJoin( $this->opts->getValue( 'article_content_type' ), 'page.page_id' )
			);
		}
		if ( \ExtensionRegistry::getInstance()->isLoaded( 'ArticleType' ) &&
			 !empty( $this->opts->getValue( 'article_type' ) )
		) {
			$info = array_merge_recursive(
				$info, ArticleType::getJoin( $this->opts->getValue( 'article_type' ), 'page.page_id' )
			);
		}

		return $info;
	}

	/**
	 * @param string|null $from_date
	 * @param string|null $to_date
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getScoreLogQuery( ?string $from_date = null, ?string $to_date = null ): array {
		$info = [
			'tables' => [ 'log1' => 'pq_score_log', 'log2' => 'pq_score_log', 'page' ],
			'fields' => [
				'page.page_id', 'page.page_namespace', 'page.page_title',
				'new_score' => 'log1.new_score', 'timestamp' => 'log1.timestamp',
				'old_score' => 'log2.old_score', 'log2.timestamp'
			],
			'join_conds' => [
				'log2' => [ 'LEFT JOIN', 'log1.page_id = log2.page_id' ],
				'page' => [ 'INNER JOIN', 'log1.page_id = page.page_id' ]
			],
			'options' => [
				'ORDER BY' => [ 'log1.timestamp ASC', 'log2.timestamp DESC' ],
				'GROUP BY' => 'page.page_id',
			]
		];

		$dateConditions = array_merge(
			$this->getConditionLimitByDates( 'log1.timestamp', $from_date, $to_date ),
			$this->getConditionLimitByDates( 'log2.timestamp', $from_date, $to_date )
		);
		$info['conds'] = array_merge( (array)$info['conds'], $dateConditions );

		return $info;
	}

	/**
	 * @param string $fieldName
	 * @param DateTime|string|null $from
	 * @param DateTime|string|null $to
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function getConditionLimitByDates( string $fieldName, $from = null, $to = null ): array {
		$db = $this->getDatabase();
		$conds = [];
		if ( !empty( $from ) ) {
			$conds[] = $fieldName . ' >= ' . $db->addQuotes( $db->timestamp( new DateTime( $from ) ) );
		}
		if ( !empty( $to ) ) {
			// Add 1 day, so we check for "any date before tomorrow"
			$to = $db->timestamp( new DateTime( $to . ' +1 day' ) );
			$conds[] = $fieldName . ' < ' . $db->addQuotes( $to );
		}

		return $conds;
	}

	/** @inheritDoc */
	public function getIndexField() {
		if ( in_array( $this->report_type, [ 'declines', 'improvements' ] ) ) {
			return [ 'time' => [ 'log1.timestamp ASC', 'log2.timestamp DESC' ] ];
		}

		return empty( $this->mSort ) ? 'score' : $this->mSort;
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort(): string {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldSortable( $field ): bool {
		return ( $field === 'score' );
	}
}
