<?php

use MediaWiki\MediaWikiServices;

class PageQualityHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		global $wgOut, $wgScript, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 ) {
			list( $score, $responses ) = PageQualityScorer::runScorerForPage( $wgOut->getTitle(), $wgOut->getHTML() );

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
		$updater->addExtensionTable( 'pq_score_log',
		        __DIR__ . '/page_quality.sql', true );
		return true;
	}

}
