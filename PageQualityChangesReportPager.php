<?php

use MediaWiki\Extension\ArticleContentArea\ArticleContentArea;
use MediaWiki\Extension\ArticleType\ArticleType;
use MediaWiki\Linker\LinkRenderer;

class PageQualityChangesReportPager extends TablePager {

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
		if ( !in_array( $report_type, [ 'declines', 'improvements' ] ) ) {
			throw new ErrorPageError( 'pq_reports', 'pq_report_error_no_report' );
		}

		parent::__construct( $context );

		$this->opts = $opts;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		static $headers = null;

		if ( $headers == [] ) {
			$headers = [
				'pagename' => 'pq_report_pagename',
				'status' => 'pq_report_page_status',
				'old_status' => 'pq_report_page_status_old',
				'new_score' => 'pq_report_page_score',
				'old_score' => 'pq_report_page_score_old'
			];
			foreach ($headers as &$msg) {
				$msg = $this->msg($msg)->text();
			}
		}

		return $headers;
	}

	/**
	 * @inheritDoc
	 */
	public function formatValue( $name, $value ) {
		switch ( $name ) {
			case 'pagename':
				$formatted = $this->linkRenderer->makeKnownLink( Title::newFromRow( $this->mCurrentRow ) );
				break;
			case 'status':
			case 'old_status':
				$status = PageQualityScorer::getHumanReadableStatus( $value );
				$formatted = $this->msg( 'pq_report_page_status_' . $status )->escaped();
				break;
			default: $formatted = $value;
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
		$info = $this->getScoreLogQuery( $from_date, $to_date );

		$havingCond = ( $this->report_type === 'declines' ) ? "new_status > old_status" :  "new_status < old_status";
		$info['options']['HAVING'] = $havingCond;

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
			// @fixme this doesn't necessarily select the correct log entry. It should always select the max and min entry
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
			return [ 'time' => [ 'log1.timestamp ASC', 'log2.timestamp DESC' ] ];
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
