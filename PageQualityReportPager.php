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
			'old_score' => 'pq_report_page_score_old',
			'old_status' => 'pq_report_page_status_old',
		];
		$headers["timestamp"] = "pq_report_page_score_timestamp";
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

		$formatted = '';
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'pagename':
				$formatted = $this->linkRenderer->makeKnownLink( Title::newFromRow( $row ) );
				break;
			case 'timestamp':
				if ( !empty( $value ) ) {
					$formatted = ( new DateTime() )->setTimestamp( wfTimestamp( TS_UNIX, $value ) )
						->format( 'j M y' );
				}
				break;
			case 'score':
				$score = !empty( $row->new_score ) ? $row->new_score : $row->score;
				$formatted = $score;
				break;
			case 'old_status':
			case 'status':
				$status = PageQualityScorer::getHumanReadableStatus( $value );
				$formatted = $this->msg( 'pq_report_page_status_' . $status )->escaped();
				break;
			default:
				$formatted = $value;
		}

		return $formatted;
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
				'pq_score.score', 'pq_score.status', 'old_score', 'pq_score_log.timestamp', 'MAX(pq_score_log.timestamp)'
			],
			'conds' => $this->getConditionLimitByDates( 'timestamp', $from_date, $to_date ),
			// @fixme this doesn't necessarily select the correct log entry. It should always select the max and min entry
			'join_conds' => [
				"pq_score_log" => [
					'LEFT JOIN',
					[ 'pq_score.page_id = pq_score_log.page_id', 'pq_score.score = pq_score_log.new_score' ]
				],
				'page' => [ 'INNER JOIN', [ 'pq_score.page_id = page.page_id' ] ]
			],
			'options' => [ 'GROUP BY' => "page.page_id" ]
		];

		switch ( $this->report_type ) {
			case "all":
				break;
			case "red_all":
				$info['conds']['pq_score.status'] = PageQualityScorer::RED;
				break;
			case "yellow_all":
				$info['conds']['pq_score.status'] = PageQualityScorer::YELLOW;
				break;
			case "green_all":
				$info['conds']['pq_score.status'] = PageQualityScorer::GREEN;
				break;
			default:
				$info['tables'][] = 'pq_issues';
				$info['join_conds']['pq_issues'] = [ 'JOIN', [ 'pq_score.page_id = pq_issues.page_id', 'pq_type' => $this->report_type ] ];
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
				'old_score' => 'log2.old_score', 'log2.timestamp',
				'new_status' => 'log1.new_status', 'old_status' => 'log2.old_status'
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
		$info['conds'] = isset( $info['conds'] ) ? array_merge( $info['conds'], $dateConditions ) : $dateConditions;

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
