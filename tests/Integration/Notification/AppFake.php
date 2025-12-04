<?php

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

namespace OCA\WorkflowOcr\Tests\Integration\Notification;

use OCP\IDBConnection;
use OCP\Notification\IApp;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

// We use a faked notification receiver app to keep track of any notifications created
class AppFake implements IApp {
	private $notifications = [];
	private $processed = [];

	private static $registered = false;

	public function __construct() {
		if (!self::$registered) {
			\OC::$server->get(IManager::class)->registerApp(AppFake::class);
			self::$registered = true;
		}
	}

	public function notify(INotification $notification): void {
		$this->notifications[] = $notification;
	}

	public function markProcessed(INotification $notification): void {
		$this->processed[] = $notification;
	}

	public function getCount(INotification $notification): int {
		return 0;
	}

	/**
	 * @return INotification[]
	 */
	public function getNotifications() : array {
		return $this->notifications;
	}

	/**
	 * @return INotification[]
	 */
	public function getProcessed() : array {
		return $this->processed;
	}

	public function resetNotifications() {
		$this->notifications = [];
		$dbConnection = \OC::$server->get(IDBConnection::class);
		try {
			if (!$dbConnection->tableExists('notifications')) {
				return;
			}
			$sql = $dbConnection->getQueryBuilder();
			$sql->delete('notifications')
				->where($sql->expr()->eq('app', $sql->createNamedParameter('workflow_ocr')));
			$sql->executeStatement();
		} finally {
			$dbConnection->close();
		}
	}
}
