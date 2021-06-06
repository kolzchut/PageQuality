<?php


class SpecialPageQuality extends SpecialPage{

	function __construct() {
		parent::__construct( 'PageQuality' );
	}

	function execute( $subpage ) {
		$linkDefs = [
			'Settings' => 'Special:PageQuality/settings',
			'Maintenance' => 'Special:PageQuality/maintenance',
			'Reports' => 'Special:PageQuality/reports',
		];
		$links = [];
		foreach ( $linkDefs as $name => $page ) {
			$title = Title::newFromText( $page );
			$links[] = Linker::link( $title, $name );
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
		global $wgUser;

		if ( !in_array( 'sysop', $wgUser->getEffectiveGroups() ) ) {
			$this->getOutput()->addHTML( 'You do not have the necessary permissions to view this page.' );
			return;
		}
		$this->getOutput()->setPageTitle( "Page Quality Settings" );

		$panels = "";

		$html = '
		<div class="" style="">
			<ul id="tabs" class="nav nav-tabs" role="tablist">
		';

		foreach( PageQualityScorer::getAllScorers() as $scorer_class ) {
			$class_type = str_replace( "PageQualityScorer", "", $scorer_class );
			$html .= '
			  <li role="presentation" class="nav-item"><a class="nav-link active" aria-controls="'. $class_type .'" role="tab" href="#'. strtolower( $class_type ) .'" data-toggle="tabs">'. $class_type .'</a></li>
			';

			$settings_html = "";

			$all_checklist = $scorer_class::getCheckList();
			foreach( $all_checklist as $type => $check ) {
				$settings_html .= '
					<div class="form-group">
						<label for="'. $type . PageQualityScorer::YELLOW .'">'. $check['name'] .' - Yellow</label>
						<input type="text" class="form-control" id="" placeholder="'. $check[PageQualityScorer::YELLOW] .'">
					</div>
					<div class="form-group">
						<label for="'. $type . PageQualityScorer::RED .'">'. $check['name'] .' - Red</label>
						<input type="text" class="form-control" id="" placeholder="'. $check[PageQualityScorer::RED] .'">
					</div>
  				';
			}

			$panels .= '
			<div role="tabpanel" class="tab-pane active card-body" id="'. strtolower( $class_type ) .'">
				<div id="settings_list" style="margin-top:10px;">
					<form>
						'. $settings_html .'
						<button type="submit" class="btn btn-primary">Save</button>
					</form>
				</div>
			</div>';

		}

		$html .= '
			</ul>
			<div class="tab-content card panel-default">
				'. $panels .'
			</div>
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

		$this->getOutput()->setPageTitle( "Page Quality Report for: " . $title->getText() );

		PageQualityScorer::loadAllScoreres();

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			"pq_issues",
			'*',
			array( 'page_id' => $page_id ),
			__METHOD__
		);

		$responses = [];

		foreach( $res as $row ) {
			$responses[$row->pq_type][$row->score][] = [
				"example" => $row->example
			];
		}
		foreach( $responses as $type => $type_responses ) {
			foreach( $type_responses as $score => $responses ) {
				if ( $score == PageQualityScorer::YELLOW ) {
					$html .= '
						<div class="panel panel-danger">
						<div class="panel-heading">
							<span style="background:#ffeeba;font-weight:600;text-transform:uppercase;">'. count( $responses ) .' Warnings </span> - 
							<span style="font-weight:600;">'. PageQualityScorer::getAllChecksList()[$type]['name'] .' - Exceeds recommended limit of '. PageQualityScorer::getAllChecksList()[$type][PageQualityScorer::YELLOW] . '</span>
						</div>
					';
				} else {
					$html .= '
						<div class="panel panel-danger">
						<div class="panel-heading">
							<span style="background:#f5c6cb;color:#721c24;font-weight:600;text-transform:uppercase;">'. count( $responses ) .' Issues </span> - 
							<span style="font-weight:600;">'. PageQualityScorer::getAllChecksList()[$type]['name'] .' - Exceeds recommended limit of '. PageQualityScorer::getAllChecksList()[$type][PageQualityScorer::RED] . '</span>
						</div>
					';
				}
				$html .= '
				<div class="panel">
						<ul class="list-group">
				';
				foreach( $responses as $response ) {
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

		$this->getOutput()->addModules( 'ext.bootstrap' );
		$this->getOutput()->addHTML( $html );
	}
}