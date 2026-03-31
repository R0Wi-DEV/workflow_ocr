<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Robin Windey <ro.windey@gmail.com>
 *
 *  @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\WorkflowOcr\Migration;

use Closure;
use OCA\WorkflowOcr\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Converts the 'processorCount' and 'timeout' app-config values from the
 * legacy string type (VALUE_STRING = 4) to the correct integer type
 * (VALUE_INT = 8).  Nextcloud's IAppConfig refuses to call setValueInt() on
 * a key that was previously stored via setValueString(), so we must delete the
 * old rows and re-insert them with the right type.
 */
class Version1034Date20260331194630 extends SimpleMigrationStep {
	/** Integer keys in GlobalSettings that must be migrated. */
	private const INT_KEYS = ['processorCount', 'timeout'];

	public function __construct(
		private IDBConnection $db,
		private IAppConfig $appConfig,
	) {
	}

	public function name(): string {
		return 'convert GlobalSettings integer config values from string to int type';
	}

	public function description(): string {
		return 'Deletes processorCount and timeout app-config entries that were '
			. 'stored as VALUE_STRING and re-inserts them as VALUE_INT so that '
			. 'IAppConfig::setValueInt() no longer throws a type-conflict error.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		foreach (self::INT_KEYS as $key) {
			$this->migrateKey($key, $output);
		}
		return null;
	}

	private function migrateKey(string $key, IOutput $output): void {
		// Read the raw string value directly from the DB so we can preserve it.
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('configvalue', 'type')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_NAME)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeQuery();

		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			// Key not yet stored – nothing to migrate.
			return;
		}

		if ((int)$row['type'] === IAppConfig::VALUE_INT) {
			// Already the right type, nothing to do.
			return;
		}

		$intValue = is_numeric($row['configvalue']) ? (int)$row['configvalue'] : 0;

		// IAppConfig throws a type-conflict if the stored type differs from the
		// requested one, so we must remove the old entry before re-inserting.
		$this->appConfig->deleteKey(Application::APP_NAME, $key);
		$this->appConfig->setValueInt(Application::APP_NAME, $key, $intValue);

		$output->info(sprintf(
			"Migrated config key '%s' for app '%s' from string to int (value: %d).",
			$key,
			Application::APP_NAME,
			$intValue,
		));
	}
}
