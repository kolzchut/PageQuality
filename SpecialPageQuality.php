<?php
use MediaWiki\Extension\ArticleContentArea\ArticleContentArea;

class SpecialPageQuality extends SpecialPage {

	protected $subpage = null;

	function __construct( $name = 'PageQuality', $restriction = 'viewpagequality') {
		parent::__construct( $name, $restriction );
	}

	function execute( $subpage ) {
		$linkDefs = [
			'pq_reports' => 'Special:PageQuality/reports',
			'pq_history' => 'Special:PageQuality/history',
			'pq_settings' => 'Special:PageQuality/settings',
		];
		$links = [];
		foreach ( $linkDefs as $name => $page ) {
			$title = Title::newFromText( $page );
			$links[] = $this->getLinkRenderer()->makeLink( $title, $this->msg( $name ) );
		}
		$linkStr = $this->getContext()->getLanguage()->pipeList( $links );
		$this->getOutput()->setSubtitle( $linkStr );

		$this->subpage = $subpage;
		if ( $subpage == "report" ) {
			$this->showReport();
		} else if ( $subpage == "settings" ) {
			$this->showSettings();
		} else if ( $subpage == "history" ) {
			$this->showChangeHistoryForm();
			$this->showChangeHistory();
		} else if ( $subpage == "reports" || $subpage == "" ) {
			$this->showStatistics();
		} else if ( strpos( $subpage, "reports" ) !== false ) {
			$this->showListReport( substr($subpage, strrpos($subpage, '/') + 1) );
		}
	}

	function getGeneralSettingTab( $save_link, $saved_settings_values ) {
		$class_type = "General";
		foreach( PageQualityScorer::$general_settings as $type => $data ) {
			if ( !empty( $data['dependsOnExtension'] ) && !ExtensionRegistry::getInstance()->isLoaded( $data['dependsOnExtension'] ) ) {
				continue;
			}
			$value = $data['default'];
			if ( array_key_exists( $type, $saved_settings_values ) ) {
				$value = $saved_settings_values[$type];
			}
			if ( array_key_exists( 'data_type', $data ) && $data['data_type'] == "list" ) {
				$settings_html .= '
					<div class="form-group">
						<label for="'. $type .'">'. $this->msg( $data['name'] ) . ' ' . $this->msg( 'pq_settings_list_field_help' )->escaped() . '</label>
						<textarea name="'. $type .'" class="form-control" placeholder="'. $data['default'] .'">'. $value .'</textarea>
					</div>
				';
			} else {
				$settings_html = '
					<div class="form-group">
						<label for="'. $type .'">'. $this->msg( $data['name'] ) .'</label>
						<input name="'. $type .'" type="text" class="form-control" placeholder="'. $data['default'] .'" value='. $value .'>
					</div>
				';
			}
		}
		$tabsContent = '
			<div id="settings_list" style="margin-top:10px;">
					'. $settings_html .'
			</div>';


		return new OOUI\TabPanelLayout( 'pq-settings-section-' . $class_type, [
			'label' => $class_type,
			'content' => new OOUI\FieldsetLayout( [
				'classes' => [ 'mw-prefs-section-fieldset' ],
				'id' => "pq-settings-$class_type",
				'label' => $class_type,
				'items' => [
					new OOUI\Widget( [
						'content' => new OOUI\HtmlSnippet( $tabsContent )
					] ),
				],
			] ),
			'expanded' => false,
			'framed' => true,
		] );

	}
	function showSettings() {
		global $wgScript;

		if ( $this->subpage === 'settings' ) {
			$this->mRestriction = 'configpagequality';
			$this->checkPermissions();;
		}

		$this->getOutput()->enableOOUI();
		$this->getOutput()->setPageTitle( $this->msg( 'pq_settings_title' ) );

		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_REPLICA );

		if ( $this->getRequest()->getVal('save') ==  1 ) {
			foreach( PageQualityScorer::getAllScorers() as $scorer_class ) {
				$all_checklist = $scorer_class::getCheckList();
				foreach( $all_checklist as $type => $check ) {
					$value_field = "value";
					if ( array_key_exists( 'data_type', $check ) && $check['data_type'] == "list" ) {
						$value_field = "value_blob";
					}
					$dbw->delete(
						'pq_settings',
						array( 'setting' => $type ),
						__METHOD__
					);
					if ( $this->getRequest()->getVal( $type ) ) {
						$dbw->insert(
							'pq_settings',
							array( 'setting' => $type, $value_field => $this->getRequest()->getVal( $type ) ),
							__METHOD__
						);
					}
				}
			}
			foreach( PageQualityScorer::$general_settings as $type => $data ) {
				if ( $this->getRequest()->getVal( $type ) ) {
					$value_field = "value";
					if ( array_key_exists( 'data_type', $check ) && $check['data_type'] == "list" ) {
						$value_field = "value_blob";
					}
					$dbw->delete(
						'pq_settings',
						array( 'setting' => $type ),
						__METHOD__
					);
					$dbw->insert(
						'pq_settings',
						array( 'setting' => $type, $value_field => $this->getRequest()->getVal( $type ) ),
						__METHOD__
					);
				}
			}
			if ( $this->getRequest()->getVal('regenerate_scores') == "yes" ) {
				$res = $dbr->select(
					"pq_issues",
					'*',
					[true],
					__METHOD__
				);
				$page_stats = [];
				$jobs = [];
				foreach( $res as $row ) {
					$page_stats[$row->page_id] = 1;
				}
				foreach( $page_stats as $page_id => $dummy_value ) {
					$jobs[] = new PageQualiyRefreshJob( Title::newFromId( $page_id ) );
				}
				JobQueueGroup::singleton()->push( $jobs );
			}
		}

		$saved_settings_values = PageQualityScorer::getSettingValues();

		$save_link = $wgScript . '?title=Special:PageQuality/settings&save=1';
		$tabPanels = [];

		$tabPanels[] = $this->getGeneralSettingTab( $save_link, $saved_settings_values );

		foreach ( PageQualityScorer::getAllScorers() as $scorer_class ) {
			$class_type = str_replace( "PageQualityScorer", "", $scorer_class );
			$settings_html = "";

			$all_checklist = $scorer_class::getCheckList();
			foreach( $all_checklist as $type => $data ) {
				if ( !array_key_exists( 'default', $data ) ) {
					continue;
				}
				$value = "";
				if ( array_key_exists( $type, $saved_settings_values ) ) {
					$value = $saved_settings_values[$type];
				}
				if ( array_key_exists( 'data_type', $data ) && $data['data_type'] == "list" ) {
					$settings_html .= '
						<div class="form-group">
							<label for="'. $type .'">'. $this->msg( $data['name'] ) . ' ' . $this->msg( 'pq_settings_list_field_help' )->escaped() . '</label>
							<textarea name="'. $type .'" class="form-control" placeholder="'. $data['default'] .'">'. $value .'</textarea>
						</div>
					';
				} else {
					$settings_html .= '
						<div class="form-group">
							<label for="'. $type .'">'. $this->msg( $data['name'] ) .'</label>
							<input name="'. $type .'" type="text" class="form-control" placeholder="'. $data['default'] .'" value='. $value .'>
						</div>
					';
				}
			}
			if ( empty( $settings_html ) ) {
				continue;
			}

			$tabsContent = '
				<div id="settings_list" style="margin-top:10px;">
						'. $settings_html .'
				</div>';


			$tabPanels[] = new OOUI\TabPanelLayout( 'pq-settings-section-' . $class_type, [
				'label' => $class_type,
				'content' => new OOUI\FieldsetLayout( [
					'classes' => [ 'mw-prefs-section-fieldset' ],
					'id' => "pq-settings-$class_type",
					'label' => $class_type,
					'items' => [
						new OOUI\Widget( [
							'content' => new OOUI\HtmlSnippet( $tabsContent )
						] ),
					],
				] ),
				'expanded' => false,
				'framed' => true,
			] );
		}

		$indexLayout = new OOUI\IndexLayout( [
			'infusable' => true,
			'expanded' => false,
			'autoFocus' => false,
			'classes' => [ 'pq-settings-tabs' ],
		] );
		$indexLayout->addTabPanels( $tabPanels );

		$form = new OOUI\PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'classes' => [ 'pq-settings-tabs-wrapper' ],
			'content' => $indexLayout
		] );

		$html = '
		<form action="' . $save_link . '" method="post">
			'. $form .'
				<input type="checkbox" id="regenerate_scores" name="regenerate_scores" value="yes">
				<label for="regenerate_scores"> '. $this->msg( "regenerate_scores_checkbox" ) .'</label><br>
				<button type="submit" class="btn btn-primary">' . $this->msg( 'pq_settings_submit' )->escaped() . '</button>
		</form>
		';

		$this->getOutput()->addHTML( $html );
		$this->getOutput()->addModules( 'ext.page_quality.special' );

	}

	function showListReport( $report_type ) {
		$valid_content_areas = [];
		if ( \ExtensionRegistry::getInstance()->isLoaded ( 'ArticleContentArea' ) ) {
			$valid_content_areas = ArticleContentArea::getValidContentAreas();
		}
		$formDescriptor = [
			'article_content_type' => [
				'type' => 'select',
				'name' => 'article_content_type',
				'label-message' => 'article_content_type',
				'options' => array_combine($valid_content_areas, $valid_content_areas),
			],
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'filter-legend' )
			->setSubmitTextMsg( 'pg_history_form_submit' )
			->prepareForm()
			->displayForm( false );

		$from_date = $this->getRequest()->getVal( 'from_date', 0 );
		$to_date = $this->getRequest()->getVal( 'to_date', 0 );
		$addl_conds = [];
		if ( !empty( $from_date ) ) {
			$addl_conds[] = "timestamp > $from_date";
		}
		if ( !empty( $to_date ) ) {
			$addl_conds[] = "timestamp < $to_date";
		}

		$opts = ( new FormOptions() );
		$opts->add( 'article_content_type', '' );
		$opts->fetchValuesFromRequest( $this->getRequest() );

		$pager = new PageQualityReportPager( $this->getContext(), $this->getLinkRenderer(), $opts, $report_type, $addl_conds );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );	
	}

	function showChangeHistoryForm() {
		$this->getOutput()->setPageTitle( $this->msg( 'pq_page_quality_history' ) );

		$from = $this->getRequest()->getVal( 'from_date', null );
		$to = $this->getRequest()->getVal( 'to_date', null );

		$formDescriptor = [
			'from_date' => [
				'type' => 'date',
				'name' => 'from_date',
				'label' => $this->msg( 'pg_history_form_from_date' ),
				'default' => $from,
			],
			'to_date' => [
				'type' => 'date',
				'name' => 'to_date',
				'label' => $this->msg( 'pg_history_form_to_date' ),
				'default' => $to,
			],
		];

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setFormIdentifier( 'filter_by_date' );
		$htmlForm
			->setSubmitText( $this->msg( 'pg_history_form_submit' ) )
			->setSubmitCallback( [ $this, 'showChangeHistory' ] )
			->prepareForm()
			->displayForm( false );
	}

	function showChangeHistory() {
		$from = $this->getRequest()->getVal( 'from_date', null );
		$to = $this->getRequest()->getVal( 'to_date', null );

		$from_date = 0;
		if ( !empty( $from ) ) {
			$from_date = DateTime::createFromFormat( 'Y-m-d', $from )->getTimestamp();
		}

		$to_date = wfTimestamp();
		if ( !empty( $to ) ) {
			$to_date = DateTime::createFromFormat( 'Y-m-d', $to )->getTimestamp();
		}

		if ( $to_date <= $from_date ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			"pq_score_log",
			'*',
			[ "timestamp > $from_date AND timestamp < $to_date" ],
			__METHOD__,
			array( 'ORDER BY' => 'timestamp ASC' ),
		);

		// We might have multiple entries for the same page within the selected time frame, the below code will ensure that only the latest log is considered in that case.
		$improvements = [];
		$declines = [];
		foreach( $res as $row ) {
			if ( $row->new_score > PageQualityScorer::getSetting( "red" ) && $row->old_score < PageQualityScorer::getSetting( "red" ) ) {
				$declines[$row->page_id] = 1;
//				$improvements[$row->page_id] = 0;
			} else if ( $row->new_score < PageQualityScorer::getSetting( "red" ) && $row->old_score > PageQualityScorer::getSetting( "red" ) ) {
				$improvements[$row->page_id] = 1;
//				$declines[$row->page_id] = 0;
			}
		}
		$html = '
			<table class="wikitable sortable">
			<tr>
				<th>
					' . $this->msg( 'pq_report_metric' )->escaped() . '
				</th>
				<th>
					' . $this->msg( 'pq_report_num_pages' )->escaped() . '
				</th>
			';
		$page = 'Special:PageQuality/reports/declines';
		$title = Title::newFromText( $page );
		$link = $this->getLinkRenderer()->makeLink( $title, array_sum( $declines ), [], [ 'from_date' => $from_date, 'to_date' => $to_date ] );

		$html .= '
			<tr>
				<td>
					'. $this->msg( 'declining_pages' )->escaped() . '
				</td>
				<td>
					'. $link .'
				</td>
			</tr>';

		$page = 'Special:PageQuality/reports/improvements';
		$title = Title::newFromText( $page );
		$link = $this->getLinkRenderer()->makeLink( $title, array_sum( $improvements ), [], [ 'from_date' => $from_date, 'to_date' => $to_date ] );

		$html .= '
			<tr>
				<td>
					' . $this->msg( 'improving_pages' )->escaped() . '
				</td>
				<td>
					'. $link .'
				</td>
			</tr>';

		$html .= '
			</table>
		';

		$this->getOutput()->addHTML( $html );
	}

	function showStatistics() {
		PageQualityScorer::loadAllScoreres();

		$this->getOutput()->setPageTitle( $this->msg( 'pq_page_quality_reports_dashboard' ) );

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			"pq_issues",
			'*',
			[true],
			__METHOD__
		);
		$scorer_stats = [];
		$page_stats = [];
		foreach( $res as $row ) {
			$type = $row->pq_type;
			$score = $row->score;
			$page_id = $row->page_id;
			if ( !array_key_exists( $page_id, $page_stats ) ) {
				$page_stats[$page_id] = [ 'score' => 0 ];
			}
			$page_stats[$page_id]['score'] += $score;
			if ( !array_key_exists( $type, $page_stats[$page_id] ) ) {
				$page_stats[$page_id][$type] = 0;
			}
			$page_stats[$page_id][$type]++;

			if ( !array_key_exists( $type, $scorer_stats ) ) {
				$scorer_stats[$type] = 0;
			}
			$scorer_stats[$type]++;
		}
		$red_page_count = count( array_filter( array_column($page_stats, "score"), function( $a ) { return $a > PageQualityScorer::getSetting( "red" ); } ) );
		$yellow_page_count = count( array_filter( array_column($page_stats, "score"), function( $a ) { return $a <= PageQualityScorer::getSetting( "red" ) && $a >0; } ) );

		$page = 'Special:PageQuality/reports/all';
		$title = Title::newFromText( $page );
		$link = $this->getLinkRenderer()->makeLink( $title, count( $page_stats ) );

		$html = '
			<table class="wikitable sortable">
			<tr>
				<th>
					' . $this->msg( 'pq_report_metric' )->escaped() . '
				</th>
				<th>
					' . $this->msg( 'pq_report_num_pages' )->escaped() . '
				</th>
			</tr>';

		$html .= '
			<tr>
				<td>
					' . $this->msg( 'total_scanned_pages' )->escaped() . '
				</td>
				<td>
					' . $link .'
				</td>
			</tr>';

		$page = 'Special:PageQuality/reports/red_all';
		$title = Title::newFromText( $page );
		$link = $this->getLinkRenderer()->makeLink( $title, $red_page_count );

		$html .= '
			<tr>
				<td>
					' . $this->msg( 'red_scanned_pages' )->escaped() . '
				</td>
				<td>
					'. $link .'
				</td>
			</tr>';

		$page = 'Special:PageQuality/reports/yellow_all';
		$title = Title::newFromText( $page );
		$link = $this->getLinkRenderer()->makeLink( $title, $yellow_page_count );

		$html .= '
			<tr>
				<td>
					'. $this->msg( 'yellow_scanned_pages' )->escaped() . '
				</td>
				<td>
					'. $link .'
				</td>
			</tr>
		';


		$html .= '
			<tr>
				<td>
					' . $this->msg( 'green_scanned_pages' )->escaped() . '
				</td>
				<td>
					'. ( count( $page_stats ) - $red_page_count - $yellow_page_count ) .'
				</td>
			</tr>
		';

		$all_checklist = PageQualityScorer::getAllChecksList();
		$col = array_column( $all_checklist, "severity" );
		array_multisort( $col, SORT_DESC, $all_checklist );
		foreach( $all_checklist as $type => $type_data ) {
			if ( array_key_exists( $type, $scorer_stats ) ) {
				$page = "Special:PageQuality/reports/$type";
				$title = Title::newFromText( $page );
				$link = $this->getLinkRenderer()->makeLink( $title, $scorer_stats[$type] );

				$html .= '
					<tr>
						<td>
							' . $this->msg( "scorer_type_count", $this->msg( $type_data['name'] ) )->escaped() . '
						</td>
						<td>
							'. $link .'
						</td>
					</tr>
				';
			}
		}
		$html .= '
			</table>
		';

		$this->getOutput()->addHTML( $html );
	}

	function showReport() {
		$html = "";


		$page_id = $this->getRequest()->getVal('page_id');
		$title = Title::newFromId( $page_id );

		$this->getOutput()->setPageTitle( $this->msg( 'pq_page_quality_report_for_title', $title->getText() ) );

		$this->getOutput()->addHTML( self::getPageQualityReportHtml( $page_id ) );
	}

	public static function getPageQualityReportHtml( $page_id ) {
		PageQualityScorer::loadAllScoreres();

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			"pq_issues",
			'*',
			array( 'page_id' => $page_id ),
			__METHOD__
		);

		$html = "";

		$responses = [];
		foreach( $res as $row ) {
			$responses[$row->pq_type][$row->score][] = [
				"example" => $row->example
			];
		}

		$saved_settings_values = PageQualityScorer::getSettingValues();
		$all_checklist = [];
		foreach( PageQualityScorer::getAllScorers() as $scorer_class ) {
			$all_checklist += $scorer_class::getCheckList();
		}

		foreach( $responses as $type => $type_responses ) {
			krsort( $type_responses );
			foreach( $type_responses as $score => $score_responses ) {
				$limit = 0;
				if ( array_key_exists( $type, $saved_settings_values )  ) {
					$limit = $saved_settings_values[$type];
				} else if ( array_key_exists( 'default', $all_checklist[$type] ) ) {
					$limit = $all_checklist[$type]['default'];
				}
				$message = wfMessage( "page_scorer_exceeds", $limit );
				if ( $all_checklist[$type]['check_type'] == "min" ) {
					$message = wfMessage( "page_scorer_minimum", $limit );
				} else if ( $all_checklist[$type]['check_type'] == "exist" ) {
					$message = wfMessage( "page_scorer_existence" );
				} else if ( $all_checklist[$type]['check_type'] == "do_not_exist" ) {
					$message = wfMessage( "page_scorer_inexistence" );
				}

				$panelTypeBySeverity = $all_checklist[$type]['severity'] === PageQualityScorer::RED ? 'panel-danger' : 'panel-warning';

				$html .= '
					<div class="panel ' . $panelTypeBySeverity . '">
					<div class="panel-heading">
						<span class="badge" data-raofz="15">' . count( $score_responses ) . '</span>&nbsp;
						<span class="sr-only">'. wfMessage( 'pq_num_issues' )->numParams( count( $score_responses ) ) . ' </span>
						<span>'. wfMessage( PageQualityScorer::getAllChecksList()[$type]['name'] ) .' - '. $message .'</span>
					</div>
				';
				$html .= '
				<div class="panel-body">
						<ul class="list-group">
				';
				foreach( $score_responses as $response ) {
					if ( !empty( $response[ 'example' ] ) ) {
						$html .= '
								 <li class="list-group-item">' .
						            trim( $response[ 'example' ] ) . '...' .
								  '</li>';
					}
				}
				$html .= '
						</ul>
					</div>
					</div>
				';
			}
		}
		return $html;
	}
}
