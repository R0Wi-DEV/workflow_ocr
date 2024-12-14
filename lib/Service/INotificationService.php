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

interface INotificationService {

	/**
	 * Create a new notification for the given user if the OCR process of the given file failed.
	 * @param string $userId The user ID of the user that should receive the notification.
	 * @param string $message The error message that should be displayed in the notification.
	 * @param int $fileId Optional file ID of the file that failed to OCR. If given, user can jump to the file via link.
	 */
	public function createErrorNotification(?string $userId, string $message, ?int $fileId = null);

	/**
	 * Create a new notification for the given user if the OCR process of the given file was successful.
	 * @param string $userId The user ID of the user that should receive the notification.
	 * @param string $message The success message that should be displayed in the notification.
	 * @param int $fileId Optional file ID of the file that was successfully OCR'd. If given, user can jump to the file via link.
	 */
	public function createSuccessNotification(?string $userId, ?int $fileId = null);
}
