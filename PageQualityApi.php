<?php

class PageQualityApi extends ApiBase {

	/**
	 * @param string $code
	 * @param string|array $value
	 *
	 * @return void
	 */
	public function addResultValues( string $code, $value ) {
		$result = $this->getResult();
		if ( $code == 'success' ) {
			$result->addValue( 'result', $code, $value, ApiResult::OVERRIDE );
		} elseif ( $code == 'failed' && array_key_exists( 'failed', $this->getResult()->getData()['result'] ) ) {
			return;
		} elseif ( is_array( $value ) ) {
			$result->addValue( 'result', $code, $value, ApiResult::OVERRIDE );
		} else {
			$result->addValue( 'result', $code, $value );
		}
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 */
	public function execute() {
		$this->checkUserRightsAny( 'viewpagequality' );

		if ( $this->getMain()->getVal( 'pq_action' ) == "fetch_report_html" ) {
			$page_id = $this->getMain()->getVal( 'page_id' );
			$title = Title::newFromId( $page_id );
			$html = SpecialPageQuality::getPageQualityReportHtml( $page_id );

			$this->addResultValues( "title",
				$this->msg( 'pq_page_quality_report_for_title' )->params( $title->getFullText() )
			);
			$this->addResultValues( "html", $html );
			$this->getResult()->addValue( "result", "success", "success" );
		}
	}
}
