<?php

use MediaWiki\MediaWikiServices;

class PageQualityHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		global $wgOut, $wgScript, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 ) {
			PageQualityScorer::loadAllScoreres();
			list( $score, $responses ) = PageQualityScorer::runAllScoreres( $wgOut->getHTML() );

			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'pq_issues',
				array( "page_id" => $wgOut->getTitle()->getArticleID() ),
				__METHOD__
			);

			foreach( $responses as $type => $type_responses ) {
				foreach( $type_responses as $response ) {
					$dbw->insert(
						"pq_issues",
						[
							"page_id" => $wgOut->getTitle()->getArticleID(),
							"pq_type" => $type,
							"score" => $response['score'],
							"example" => $response['example']
						],
						__METHOD__,
						array( 'IGNORE' )
					);
				}
			}

			$wgOut->setIndicators( [
				"pq_status" =>
					'<a class="page_quality_show" data-page_id="'.$wgOut->getTitle()->getArticleID().'">' . $out->msg( 'pq_quality_score_link' )->escaped() . ' <span class="badge">'. $score .'</span></a>
					'
			]);
			$out->addModules( 'ext.page_quality' );
			$out->addModules( 'ext.jquery_confirm' );
		}
	}


	function onLoadExtensionSchemaUpdate( $updater ) {
		$updater->addExtensionTable( 'pq_settings',
		        __DIR__ . '/page_quality.sql', true );
		return true;
	}

}
