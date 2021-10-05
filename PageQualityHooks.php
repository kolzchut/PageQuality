<?php

use MediaWiki\MediaWikiServices;

class PageQualityHooks {

	public static function onPageSaveComplete( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) {
		if ( $wikiPage->getTitle()->getNamespace() === NS_MAIN && PageQualityScorer::isPageScoreable( $wikiPage->getTitle() ) ) {
			list( $score, $responses ) = PageQualityScorer::runScorerForPage( $wikiPage->getTitle() );
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if ( $out->getTitle()->getNamespace() === NS_MAIN && PageQualityScorer::isPageScoreable( $out->getTitle() ) ) {
			list( $score, $responses ) = PageQualityScorer::getScorForPage( $out->getTitle() );

			$out->setIndicators( [
				"pq_status" =>
					'<a class="page_quality_show" data-page_id="'.$out->getTitle()->getArticleID().'">' . $out->msg( 'pq_quality_score_link' )->escaped() . ' <span class="badge">'. $score .'</span></a>
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
		$updater->addExtensionField(
			'pq_settings',
			'value_blob',
			 __DIR__ . '/page_quality.sql'
		);
		return true;
	}

}
