<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2702Date20230908170345 extends SimpleMigrationStep {
	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'delete old notifications';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Delete old notifications which could not be processed #221';
	}

	/**
	 * {@inheritDoc}
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		try {
			$this->deleteNonDeliverableNotifications();
		} catch(\Throwable $e) {
			$output->warning('Could not delete non-deliverable notifications: ' . $e->getMessage() . '. Please see https://github.com/R0Wi-DEV/workflow_ocr/issues/221.');
		}

		return null;
	}

	private function deleteNonDeliverableNotifications() {
		/*
		 *	See https://github.com/R0Wi-DEV/workflow_ocr/issues/221
		 * 	We need to delete notifications which could not be delivered
		 *  (for example because the file has been deleted in the meantime)
		 *  because they are not deleted automatically. This is due to the fact
		 *  that in our Notifier we set characteristics of the notification
		 *  too early, which will lead to the problem, that the
		 *  thrown AlreadyProcessedException won't let the framework delete
		 *  the notification (the framework itself doesn't find any matching
		 *  notifications). Therefore, such a notification will be processed
		 *  over and over again.
		 */
		$builder = $this->db->getQueryBuilder();
		$builder->delete('notifications')
			->where($builder->expr()->eq('app', $builder->createNamedParameter(Application::APP_NAME)))
			->andWhere($builder->expr()->eq('subject', $builder->createNamedParameter('ocr_error')))
			->executeStatement();
	}
}
