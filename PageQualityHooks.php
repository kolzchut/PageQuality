<?php

use MediaWiki\MediaWikiServices;

class PageQualityHooks {

	public static function onPageSaveComplete( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) {
		global $wgOut, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 ) {
			list( $score, $responses ) = PageQualityScorer::runScorerForPage( $wgOut->getTitle(), $wgOut->getHTML() );
		}
	}


	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		global $wgOut, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 ) {
			list( $score, $responses ) = PageQualityScorer::getScorForPage( $wgOut->getTitle() );

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
