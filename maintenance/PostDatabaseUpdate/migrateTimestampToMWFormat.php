<?php
namespace MediaWiki\Extensions\PageQuality\Maintenance\PostDatabaseUpdate;

use BatchRowIterator;
use LoggedUpdateMaintenance;

/**
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
	require_once __DIR__ . '/../../../../maintenance/Maintenance.php';
}

$maintClass = migrateTimestampToMWFormat::class;

/**
 * Run automatically with update.php, once
 */
class MigrateTimestampToMWFormat extends LoggedUpdateMaintenance {

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates(): bool {
		$dbw = $this->getDB( DB_PRIMARY );

		$iterator = new BatchRowIterator(
			$dbw,
			'pq_score_log',
			[ 'id' ],
			$this->getBatchSize()
		);
		$iterator->setFetchColumns( [ 'id', 'timestamp' ] );

		$processed = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$dbw->update(
					'pq_score_log',
					[ 'timestamp2' => wfTimestamp( TS_MW, $row->timestamp ) ],
					[ 'id' => $row->id ]
				);

				$processed += $dbw->affectedRows();
			}
		}

		$this->output( "Processed $processed PageQuality log records\n" );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey(): string {
		return __CLASS__;
	}

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Migrates all historical records to use the MW Timestamp Format." );
	}
}

require_once RUN_MAINTENANCE_IF_MAIN;
