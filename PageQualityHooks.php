<?php

use MediaWiki\MediaWikiServices;

class PageQualityHooks {

	public static function onPageSaveComplete( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) {
		global $wgOut, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 && PageQualityScorer::isPageScoreable( $wgOut->getTitle() ) ) {
			list( $score, $responses ) = PageQualityScorer::runScorerForPage( $wgOut->getTitle(), $wgOut->getHTML() );
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		global $wgOut, $wgTitle;

		if ( $wgTitle->getNamespace() == 0 && PageQualityScorer::isPageScoreable( $wgOut->getTitle() ) ) {
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

	/**
	 * LoadExtensionSchemaUpdate Hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdate
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdate( DatabaseUpdater $updater ) {
		$dir = __DIR__ . '/sql';

		$updater->addExtensionTable( 'pq_issues', "$dir/pq_issues.sql" );
		$updater->addExtensionTable( 'pq_settings', "$dir/pq_settings.sql" );
		$updater->addExtensionTable( 'pq_score_log', "$dir/pq_score_log.sql" );
		$updater->addExtensionField( 'pq_settings', 'value_blob', "$dir/pq_settings_patch_add_value_blob.sql" );
	}

}
