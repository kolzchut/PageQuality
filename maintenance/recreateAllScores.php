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

$maintClass = recreateAllScores::class;

class recreateAllScores extends Maintenance {

	/**
	 * A database replica DB object
	 *
	 * @var object
	 */
	public $dbr;

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Recreate page quality scores for all pages." );
	}

	public function execute() {
		$this->dbr = $this->getDB( DB_REPLICA );
		$startId = 0;
		while ( true ) {
			$res = $dbr->select( 'page',
				[ 'page_id', 'page_namespace', 'page_title' ],
				[ 'page_id > ' . $dbr->addQuotes( $startId ) ],
				__METHOD__,
				[
					'LIMIT' => 20,
					'ORDER BY' => 'page_id'

				]
			);
			if ( !$res->numRows() ) {
				break;
			}
			foreach ( $res as $row ) {
				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
				PageQualityScorer::runScorerForPage( $title, true );
				$startId = $row->page_id;
			}
		}
	}

}

require_once RUN_MAINTENANCE_IF_MAIN;
