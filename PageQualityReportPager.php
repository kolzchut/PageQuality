<?php

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\ArticleContentArea\ArticleContentArea;
use MediaWiki\Extension\ArticleType\ArticleType;

class PageQualityReportPager extends TablePager {

	/** @var LinkRenderer */
	private $linkRenderer;
	private $report_type;
	private $opts;

	public function __construct( IContextSource $context, LinkRenderer $linkRenderer, FormOptions $opts, $report_type ) {
		parent::__construct( $context );

		$this->opts = $opts;
		$this->report_type = $report_type;
		$this->linkRenderer = $linkRenderer;
	}

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

		foreach( $all_checklist as $type => $type_data ) {
			$headers[$type] = $type_data['name'];
		}
		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	public function formatValue( $name, $value ) {
	}

	public function formatValueMy( $name, $value, $page_stats ) {
		$formatted = "";

		$row = $this->mCurrentRow;

		if ( array_key_exists( $name, $page_stats ) ) {
			return $page_stats[$name];
		}
		switch ( $name ) {
			case 'pagename':
				$formatted = $this->linkRenderer->makeKnownLink( Title::newFromId( $row->page_id ) );
				break;
			case 'timestamp':
				if ( !empty( $row->timestamp ) ) {
					$formatted = (new DateTime())->setTimestamp( $row->timestamp )->format( 'j M y' );
				}
				break;
			case 'score':
				if ( !empty( $row->new_score ) ) {
					$formatted = $row->new_score;
				} else {
					$formatted = $row->score;
				}
				break;
			case 'old_score':
				$formatted = $row->old_score;
				break;
			// case 'score_old':
			// 	$formatted = $row->score_old;
			// 	break;
			case 'status':
				$status = $row->score > PageQualityScorer::getSetting( "red" ) ? "red" : ( $row->score > 0 ? "yellow" : "green" );
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
     */
    public function formatRow( $row ) {
            $this->mCurrentRow = $row; // In case formatValue etc need to know
            $s = Html::openElement( 'tr', $this->getRowAttrs( $row ) ) . "\n";
            $fieldNames = $this->getFieldNames();

			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select(
				"pq_issues",
				'*',
				['page_id' => $row->page_id],
				__METHOD__
			);
			$page_stats = [];
			foreach( $res as $issue_row ) {
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


	public function getQueryInfo() {
		$info = [
			'tables' => [ 'pq_score', 'pq_score_log' ],
			'fields' => [ 'pq_score.page_id', 'pq_score.score', 'old_score', 'timestamp', 'MAX(timestamp)'],
			'conds' => [],
			'join_conds' => [ "pq_score_log" => ["LEFT JOIN", ["pq_score.page_id = pq_score_log.page_id AND pq_score.score = pq_score_log.new_score"] ] ],
			'options' => ["GROUP BY" => "pq_score.page_id"]
		];
		if ( \ExtensionRegistry::getInstance()->isLoaded ( 'ArticleContentArea' ) && !empty( $this->opts->getValue( 'article_content_type' ) ) ) {
			$info = array_merge_recursive( $info, ArticleContentArea::getJoin( $this->opts->getValue( 'article_content_type' ), "pq_score.page_id" ) );
		}
		if ( \ExtensionRegistry::getInstance()->isLoaded ( 'ArticleType' ) && !empty( $this->opts->getValue( 'article_type' ) ) ) {
			$info = array_merge_recursive( $info, ArticleType::getJoin( $this->opts->getValue( 'article_type' ), "pq_score.page_id" ) );
		}

		$from_ts = 0;
		$to_ts = wfTimestamp();
		if ( !empty( $this->opts->getValue( 'from_date' ) ) ) {
			$from_ts = DateTime::createFromFormat( 'Y-m-d', $this->opts->getValue('from_date') )->setTime(0, 0)->getTimestamp();
			$info['conds'][] = "timestamp > " . $from_ts;
		}
		if ( !empty( $this->opts->getValue( 'to_date' ) ) ) {
			$to_ts = DateTime::createFromFormat( 'Y-m-d', $this->opts->getValue('to_date') )->setTime(0, 0)->modify( '+1 days' )->getTimestamp();
			$info['conds'][] = "timestamp < " . $to_ts;
		}

		switch ( $this->report_type ) {
			case "all":
				$info['conds'][] = "score > 1";
				break;
			case "red_all":
				$info['conds'][] = "score > " . PageQualityScorer::getSetting( "red" );
				break;
			case "yellow_all":
				$info['conds'][] = "score > 0";
				$info['conds'][] = "score <= " . PageQualityScorer::getSetting( "red" );
				break;
			case "declines":
				$info = [
					'tables' => [ 'pq_score_log AS pq_a', 'pq_score_log AS pq_b' ],
					'fields' => [ 'pq_a.page_id as page_id', 'pq_a.new_score AS score', 'pq_a.timestamp as timestamp', 'pq_b.old_score AS old_score', 'pq_b.timestamp as pq_bts'],
					'conds' => [ "pq_a.timestamp > $from_ts AND pq_a.timestamp < $to_ts", "pq_b.timestamp > $from_ts AND pq_b.timestamp < $to_ts", "pq_a.page_id = pq_b.page_id" ],
					'join_conds' => [ "pq_a" => ["LEFT JOIN", ["pq_a.page_id=pq_b.page_id"] ] ],
					'options' => [ 'ORDER BY' => 'timestamp ASC, pq_bts DESC', 'GROUP BY' => 'pq_a.page_id' ]
				];
				break;
			case "improvements":
				$info = [
					'tables' => [ 'pq_score_log AS pq_a', 'pq_score_log AS pq_b' ],
					'fields' => [ 'pq_a.page_id as page_id', 'pq_a.new_score AS score', 'pq_a.timestamp as timestamp', 'pq_b.old_score AS old_score', 'pq_b.timestamp as pq_bts'],
					'conds' => [ "pq_a.timestamp > $from_ts AND pq_a.timestamp < $to_ts", "pq_b.timestamp > $from_ts AND pq_b.timestamp < $to_ts", "pq_a.page_id = pq_b.page_id" ],
					'join_conds' => [ "pq_a" => ["LEFT JOIN", ["pq_a.page_id=pq_b.page_id"] ] ],
					'options' => [ 'ORDER BY' => 'timestamp ASC, pq_bts DESC', 'GROUP BY' => 'pq_a.page_id' ]
				];
				break;
			default:
				$info['tables'][] = 'pq_issues';
				$info['conds']['pq_type'] = $this->report_type;
				$info['join_conds']["pq_issues"] = ["LEFT JOIN", ["pq_score.page_id=pq_issues.page_id"] ];
		}
		return $info;
	}

	public function getDefaultSort() {
		return 'page_id';
	}

	public function isFieldSortable( $name ) {
		$sortable = false;
		if ( in_array( $name, [ 'score', 'old_score', 'timestamp' ] ) ) {
			$sortable = true;
		}
		return $sortable;
	}
}