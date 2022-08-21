<?php

/**
 * Usage:
 *  php migrateTimestampToMWFormat.php
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

$maintClass = migrateTimestampToMWFormat::class;

class migrateTimestampToMWFormat extends Maintenance {

	/**
	 * A database replica DB object
	 *
	 * @var object
	 */
	public $dbr;

	/**
	 * List of article types to work on
	 *
	 * @var array
	 */
	public $articleType;

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( 20 );

		$this->addDescription( "Migrates all historical records to use the MW Timestamp Format." );
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
		$dbw = $this->getDB( DB_MASTER );

		if ( $this->hasOption( 'reset' ) ) {
			$this->getDB( DB_PRIMARY )->delete( 'pq_issues', '*' );
			$this->getDB( DB_PRIMARY )->delete( 'pq_score_log', '*' );
		}

		$basicQuery = $this->getBasicQuery();
		$startId = $this->getOption( 'startid', 0 );
		$totalNumRows = 0;

		$max_page_id = $dbr->selectField( 
			['page'],
			['MAX(page_id)'],
			[],
			__METHOD__,
		);
		$endId = intval( $max_page_id );
		while ( $startId < $endId ) {
			$query = $basicQuery;
			$query['conds'][] = 'page_id = ' . $dbr->addQuotes( $startId );

			$res = $dbr->select( $query['tables'],
				$query['fields'],
				$query['conds'],
				__METHOD__,
			);
			$startId++;
			if ( !$res->numRows() ) {
				continue;
			}
			$totalNumRows = $totalNumRows + $res->numRows();
			foreach ( $res as $row ) {
				$dbw->update(
					'pq_score_log',
					[
						'timestamp' => wfTimestamp( TS_MW, $row->timestamp )
					],
					[
						'id' => $row->id
					],
					__METHOD__,
					array( 'IGNORE' )
				);
			}

			$this->output( "Processed {$totalNumRows} titles, ending in {$startId}\n" );
		}

		$this->output( "\nDone.\n" );
	}

	private function getBasicQuery() {
		$dbr = $this->getDB( DB_REPLICA );

		$query = [];
		$query[ 'tables' ] = [ 'pq_score_log' ];
		$query[ 'fields' ]  = [ 'timestamp', 'id' ];

		return $query;
	}
}


require_once RUN_MAINTENANCE_IF_MAIN;
