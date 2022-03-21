<?php

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;

class PageQualityReportPager extends TablePager {
	/** @var LinkRenderer */
	private $linkRenderer;
	private $report_type;
	private $addl_conds;

	public function __construct( IContextSource $context, LinkRenderer $linkRenderer, $report_type, $addl_conds ) {
		parent::__construct( $context );

		$this->addl_conds = $addl_conds;
		$this->report_type = $report_type;
		$this->linkRenderer = $linkRenderer;
	}

	public function getFieldNames() {
		static $headers = null;

		PageQualityScorer::loadAllScoreres();
		$all_checklist = PageQualityScorer::getAllChecksList();

		$headers = [
			'pagename' => 'pq_report_pagename',
			'page_score' => 'pq_report_page_score',
			// 'score_old' => 'pq_report_page_score_old',
			'page_status' => 'pq_report_page_status',
			'page_status' => 'pq_report_page_status',
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
			case 'page_score':
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
			case 'page_status':
				$status = $row->score > PageQualityScorer::getSetting( "red" ) ? "red" : ( $row->score > 0 ? "yellow" : "green" );
				$formatted = $this->msg( 'pq_report_page_status_' . $status )->escaped();
				break;
			default:
				$formatted = "";
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
			'joins_conds' => [ "pq_score_log" => ["LEFT JOIN", ["pq_score.page_id=pq_score_log.page_id AND pq_score.score=pq_score_log.new_score"] ] ],
			'options' => ["GROUP BY" => "pq_score.page_id"]
		];
		switch ( $this->report_type ) {
			case "red_all":
				$info['conds'][] = "score > " . PageQualityScorer::getSetting( "red" );
				break;
			case "yellow_all":
				$info['conds'][] = "score > 0";
				$info['conds'][] = "score <= " . PageQualityScorer::getSetting( "red" );
				break;
			case "declines":
				$info['fields'][] = 'new_score';
				$info['conds'][] = "new_score >= " . PageQualityScorer::getSetting( "red" );
				$info['conds'][] = "old_score < " . PageQualityScorer::getSetting( "red" );
				break;
			case "improvements":
				$info['fields'][] = 'new_score';
				$info['conds'][] = "new_score < " . PageQualityScorer::getSetting( "red" );
				$info['conds'][] = "old_score >= " . PageQualityScorer::getSetting( "red" );
				break;
			default:
				$info['tables'][] = 'pq_issues';
				$info['conds']['pq_type'] = $this->report_type;
				$info['joins_conds']["pq_issues"] = ["LEFT JOIN", ["pq_score.page_id=pq_issues.page_id"] ];
		}
		if ( !empty( $this->addl_conds ) ) {
			$info['conds'] = array_merge( $info['conds'], $this->addl_conds );
		}
		return $info;
	}

	public function getDefaultSort() {
		return 'pq_score.page_id';
	}

	public function isFieldSortable( $name ) {
		return true;
	}
}