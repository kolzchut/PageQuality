<?php

/**
 * Usage:
 *  php recreateAllScores.php
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author Nischay Nahata
 * @ingroup Maintenance
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
	require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

$maintClass = RecreateAllScores::class;

class RecreateAllScores extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( 20 );

		$this->addDescription( "Recreate page quality scores for all pages." );
		$this->addOption(
			'startid',
			'The id to start from',
			false,
			true,
			's'
		);
		$this->addOption(
			'namespace',
			'Namespace constant to work on. Defaults to NS_MAIN',
			false,
			true,
		);
		$this->addOption(
			'articletype',
			'A comma-separated list of article types to work on. Depends on extension:ArticleType.',
			false,
			true
		);
		$this->addOption(
			'reset',
			'Delete all existing scores before starting again',
		);
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );

		if ( $this->hasOption( 'reset' ) ) {
			$this->getDB( DB_PRIMARY )->delete( 'pq_issues', '*' );
			$this->getDB( DB_PRIMARY )->delete( 'pq_score_log', '*' );
		}

		$basicQuery = $this->getBasicQuery();
		$startId = $this->getOption( 'startid', 0 );
		$totalNumRows = 0;

		while ( true ) {
			$query = $basicQuery;
			$query['conds'][] = 'page_id > ' . $dbr->addQuotes( $startId );

			$res = $dbr->select( $query['tables'],
				$query['fields'],
				$query['conds'],
				__METHOD__,
				$query['options'],
				$query['join_conds']
			);
			if ( !$res->numRows() ) {
				break;
			}
			$totalNumRows = $totalNumRows + $res->numRows();
			foreach ( $res as $row ) {
				$title = Title::newFromRow( $row );
				PageQualityScorer::runScorerForPage( $title, "", true );
				$startId = $row->page_id;
			}

			$this->output( "Processed {$totalNumRows} titles, ending in {$startId}\n" );
		}

		$this->output( "\nDone.\n" );
	}

	/**
	 * @return array
	 */
	private function getBasicQuery() {
		$dbr = $this->getDB( DB_REPLICA );

		$query = [];
		$query[ 'tables' ] = [ 'page' ];
		$query[ 'fields' ]  = [ 'page_id', 'page_namespace', 'page_title' ];
		$query[ 'options' ] = [
			'LIMIT' => $this->getBatchSize(),
			'ORDER BY' => 'page_id'
		];
		$query[ 'join_conds' ] = [];

		// Handle namespaces
		if ( $this->hasOption( 'namespace' ) && !defined( $this->getOption( 'namespace' ) ) ) {
			$this->fatalError( "Expected a namespace constant, `" . $this->getOption( 'namespace' ) . "` is unknown!" );
		}
		$namespace = $this->getOption( 'namespace' );
		if ( $namespace ) {
			$namespace = constant( $this->getOption( 'namespace' ) );
		} else {
			$namespace = NS_MAIN;
		}
		$query[ 'conds' ] = [
			'page_namespace = ' . $dbr->addQuotes( $namespace )
		];

		return $query;
	}
}

require_once RUN_MAINTENANCE_IF_MAIN;
