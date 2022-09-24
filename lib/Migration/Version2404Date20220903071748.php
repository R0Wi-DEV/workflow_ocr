<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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
use Exception;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2404Date20220903071748 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'migrate lang codes';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Execute migration of language codes towards tesseract langugage codes (e.g. deu instead of de)';
	}

	/**
	 * {@inheritDoc}
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// 'id' and new 'operation' value will be stored here
		$datasetsToMigrate = $this->getDatasetsToMigrate();
		$this->updateDatabase($datasetsToMigrate);

		return null;
	}

	private function getDatasetsToMigrate() : array {
		$langMapping = [
			'de' => 'deu',
			'en' => 'eng',
			'fr' => 'fra',
			'it' => 'ita',
			'es' => 'spa',
			'pt' => 'por',
			'ru' => 'rus',
			'chi' => 'chi_sim'
		];

		$builder = $this->db->getQueryBuilder();

		$ocrFlowOperations = $builder->select('id', 'operation')
			->from('flow_operations')
			->where($builder->expr()->eq('class', $builder->createNamedParameter('OCA\WorkflowOcr\Operation')))
			->executeQuery();

		$datasetsToMigrate = [];

		try {
			while ($row = $ocrFlowOperations->fetch()) {
				$workflowSettings = json_decode($row['operation'], true);
				$foundMapping = false;
				$newLangArr = [];
				$languagesArr = $workflowSettings['languages'];

				// Check if we need to migrate the languages code.
				// If yes, we have to regenerate the whole 'operation' string.
				foreach ($languagesArr as $existingLang) {
					if (array_key_exists($existingLang, $langMapping)) {
						$newLangArr[] = $langMapping[$existingLang];
						$foundMapping = true;
						continue;
					}
					$newLangArr[] = $existingLang;
				}

				if ($foundMapping) {
					$workflowSettings['languages'] = $newLangArr;
					$datasetsToMigrate[] = [
						'id' => $row['id'],
						'operation' => json_encode($workflowSettings)
					];
				}
			}
		} finally {
			$ocrFlowOperations->closeCursor();
		}

		return $datasetsToMigrate;
	}

	private function updateDatabase(array $datasetsToMigrate) : void {
		$this->db->beginTransaction();

		try {
			$builder = $this->db->getQueryBuilder();
			$builder->update('flow_operations')
				->set('operation', $builder->createParameter('operation'))
				->where($builder->expr()->eq('id', $builder->createParameter('id')));

			foreach ($datasetsToMigrate as $dataset) {
				$builder->setParameter('id', $dataset['id']);
				$builder->setParameter('operation', $dataset['operation']);
				$builder->executeStatement();
			}
		} catch (Exception $e) {
			$this->db->rollBack();
			throw $e;
		}

		$this->db->commit();
	}
}
