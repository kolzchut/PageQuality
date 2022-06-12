<?php
/**
 * Background job to update the page quality scores
 *
 * @ingroup PageQuality
 * @author Nischay Nahata
 */

class PageQualiyRefreshJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params
	 */
	public function __construct( $title, array $params = [] ) {
		parent::__construct( 'updatePageQuality', $title, $params );
	}

	/**
	 * Run a CargoPopulateTable job.
	 *
	 * @return bool success
	 */
	public function run() {
		if ( $this->title === null ) {
			$this->error = "updatePageQuality: Invalid title";
			return false;
		}

		PageQualityScorer::runScorerForPage( $this->title, "", true );

		return true;
	}
}
