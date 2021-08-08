<?php

use MediaWiki\MediaWikiServices;

class PageQualityApi extends ApiBase {

	public function addResultValues($code, $value) {
		$result = $this->getResult();
		if ($code == 'success') {
			$result->addValue( 'result', $code, $value, ApiResult::OVERRIDE);
		} else if ($code == 'failed' && array_key_exists('failed', $this->getResult()->getData()['result'])) {
			return;
		} else if ( is_array($value) ) {
			// $warnings = (array) $this->getResult()->getResultData()['result'][$code];
			// $warnings = array_merge( $warnings, $value );
			// dieq( $value, $this->getResult()->getResultData() );
			$result->addValue( 'result', $code, $value, ApiResult::OVERRIDE);
		} else {
			$result->addValue( 'result', $code, $value);
		}
	}

	public function execute() {
		global $wgUser;

		if ( $this->getMain()->getVal('pq_action') == "fetch_report_html" ) {
			$page_id = $this->getMain()->getVal('page_id');
			$title = Title::newFromId( $page_id );
			$html = SpecialPageQuality::getPageQualityReportHtml( $page_id );

			$this->addResultValues( "title", $this->msg( 'pq_page_quality_report_for_title' )->params( $title->getFullText() ) );
			$this->addResultValues( "html", $html );
			$this->getResult()->addValue( "result", "success", "success" );
		}
	}
}
