<?php


class SpecialPageQuality extends SpecialPage{

	function __construct() {
		parent::__construct( 'PageQuality' );
	}

	function execute( $subpage ) {
		$linkDefs = [
			'pq_settings' => 'Special:PageQuality/settings',
			'pq_maintenance' => 'Special:PageQuality/maintenance',
			'pq_reports' => 'Special:PageQuality/reports',
		];
		$links = [];
		foreach ( $linkDefs as $name => $page ) {
			$title = Title::newFromText( $page );
			$links[] = Linker::link( $title, $this->msg( $name ) );
		}
		$linkStr = $this->getContext()->getLanguage()->pipeList( $links );
		$this->getOutput()->setSubtitle( $linkStr );

		if ( $subpage == "report" ) {
			$this->showReport();
		} else if ( $subpage == "settings" ) {
			$this->showSettings();
		}
	}

	function showSettings() {
		global $wgUser, $wgScript;

		if ( !in_array( 'sysop', $wgUser->getEffectiveGroups() ) ) {
			$this->getOutput()->addHTML( 'You do not have the necessary permissions to view this page.' );
			return;
		}
		$this->getOutput()->setPageTitle( $this->msg( 'pq_settings_title' ) );

		$panels = "";

		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_REPLICA );

		if ( $this->getRequest()->getVal('save') ==  1 ) {
			foreach( PageQualityScorer::getAllScorers() as $scorer_class ) {
				$all_checklist = $scorer_class::getCheckList();
				foreach( $all_checklist as $type => $check ) {
					if ( $this->getRequest()->getVal( $type ) ) {
						$dbw->delete(
							'pq_settings',
							array( 'setting' => $type ),
							__METHOD__
						);
						$dbw->insert(
							'pq_settings',
							array( 'setting' => $type, 'value' => $this->getRequest()->getVal( $type ) ),
							__METHOD__
						);
					}
				}
			}
		}

		$saved_settings_values = PageQualityScorer::getSettingValues();

		$save_link = $wgScript . '?title=Special:PageQuality/settings&save=1';
		$html = '
		<div class="" style="">
			<form action="' . $save_link . '" method="post">
		';

		// $html = '
		// 	<ul id="tabs" class="nav nav-tabs" role="tablist">
		// ';

		foreach( PageQualityScorer::getAllScorers() as $scorer_class ) {
			$class_type = str_replace( "PageQualityScorer", "", $scorer_class );
			// $html .= '
			//   <li role="presentation" class="nav-item"><a class="nav-link active" data-toggle="tab" aria-controls="'. strtolower( $class_type ) .'" role="tab" href="#'. strtolower( $class_type ) .'" data-toggle="tabs">'. $class_type .'</a></li>
			// ';

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
				$settings_html .= '
					<div class="form-group">
						<label for="'. $type .'">'. $this->msg( $data['name'] ) .'</label>
						<input name="'. $type .'" type="text" class="form-control" placeholder="'. $data['default'] .'" value='. $value .'>
					</div>
  				';
			}
			if ( empty( $settings_html ) ) {
				continue;
			}

			$panels .= '
			<div class="card" id="'. strtolower( $class_type ) .'" style="margin-bottom:20px;">
				<div class="card-header" style="">
					'. $class_type .'
				</div>
  				<div class="card-body" style="">
						'. $settings_html .'
				</div>
			</div>';

		}

		// $html .= '
		// 	</ul>';
		$html .= '
				'. $panels .'
				<button type="submit" class="btn btn-primary">Save</button>
			</form>
		</div>
		';

		$checksListAll = PageQualityScorer::getAllChecksList();

		$this->getOutput()->addHTML( $html );
		$this->getOutput()->addModules( 'ext.bootstrap' );
		$this->getOutput()->addModules( 'ext.page_quality.special' );

	}

	function showReport() {
		$html = "";


		$page_id = $this->getRequest()->getVal('page_id');
		$title = Title::newFromId( $page_id );

		$this->getOutput()->setPageTitle( $this->msg( 'pq_page_quality_report_for_title' ) . " " . $title->getText() );

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
				$message = wfMessage( "page_scorer_exceeds" ) . $limit;
				if ( $all_checklist[$type]['check_type'] == "min" ) {
					$message = wfMessage( "page_scorer_minimum" ) . $limit;
				} else if ( $all_checklist[$type]['check_type'] == "exist" ) {
					$message = wfMessage( "page_scorer_existence" );
				}
				$html .= '
					<div class="panel panel-danger">
					<div class="panel-heading">
						<span style="background:#f5c6cb;color:#721c24;font-weight:600;text-transform:uppercase;">'. count( $score_responses ) .' Issues</span> - 
						<span style="font-weight:600;">'. wfMessage( PageQualityScorer::getAllChecksList()[$type]['name'] ) .' - '. $message .'</span>
					</div>
				';
				$html .= '
				<div class="panel">
						<ul class="list-group">
				';
				foreach( $score_responses as $response ) {
					$html .= '
							 <li class="">
							    ' . $response['example'] . '...
							  </li>
					';
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