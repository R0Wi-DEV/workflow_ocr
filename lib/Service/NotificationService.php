<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

namespace OCA\WorkflowOcr\Service;

use OCA\WorkflowOcr\AppInfo\Application;
use OCP\Notification\IManager;

/*
* This class is used to create new NC notifications.
* They will be displayed later with the help of Nofification\Notifier.
*/
class NotificationService implements INotificationService {

	private IManager $notificationManager;

	public function __construct(IManager $notificationManager) {
		$this->notificationManager = $notificationManager;
	}

	public function createErrorNotification(?string $userId, string $message, int $fileId = null) {
		// We don't create unbound notifications
		if (!$userId) {
			return;
		}
		
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_NAME)
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setSubject('ocr_error', ['message' => $message]);

		if ($fileId) {
			$notification->setObject('file', strval($fileId));
		} else {
			$notification->setObject('ocr', 'ocr');
		}

		$this->notificationManager->notify($notification);
	}
}
