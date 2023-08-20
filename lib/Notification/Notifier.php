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

namespace OCA\WorkflowOcr\Notification;

use OCA\WorkflowOcr\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {
	/** @var IFactory*/
	private $l10nFactory;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(IFactory $factory,
		IURLGenerator $urlGenerator,
		IRootFolder $rootFolder,
		LoggerInterface $logger) {
		$this->l10nFactory = $factory;
		$this->urlGenerator = $urlGenerator;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 * @return string
	 */
	public function getID(): string {
		return Application::APP_NAME;
	}

	/**
	 * Human readable name describing the notifier
	 * @return string
	 */
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_NAME)->t('Workflow OCR');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_NAME) {
			throw new \InvalidArgumentException();
		}

		// Currently we only support sending notifications for ocr_error
		$subject = $notification->getSubject();
		if ($subject !== 'ocr_error') {
			$this->logger->warning('Unsupported notification subject {subject}', ['subject' => $subject]);
			// Note:: AlreadyProcessedException has be be thrown before any call to $notification->set...
			// otherwise notification won't be removed from the database
			throw new AlreadyProcessedException();
		}

		$l = $this->l10nFactory->get(Application::APP_NAME, $languageCode);

		// Only add file info if we have some ...
		$richParams = false;
		if ($notification->getObjectType() === 'file' &&
			($fileId = $notification->getObjectId()) &&
			($uid = $notification->getUser())){
				$richParams = $this->tryGetRichParamForFile($uid, intval($fileId));
				if ($richParams !== false) {
					$notification->setRichSubject($l->t('Workflow OCR error for file {file}'), $richParams);
				}
		}
		
		// Fallback to generic error message without file link
		if ($richParams === false) {
			$notification->setParsedSubject($l->t('Workflow OCR error'));
		}

		$message = $notification->getSubjectParameters()['message'];
		$notification
			->setParsedMessage($message)
			->setIcon($this->urlGenerator->imagePath(Application::APP_NAME, 'app-dark.svg'));

		return $notification;
	}

	private function tryGetRichParamForFile(string $uid, int $fileId) : array | bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			/** @var File[] */
			$files = $userFolder->getById($fileId);
			/** @var File $file */
			$file = array_shift($files);
			if ($file === null) {
				$this->logger->warning('Could not find file with id {fileId} for user {uid}', ['fileId' => $fileId, 'uid' => $uid]);
				return false;
			}
			$relativePath = $userFolder->getRelativePath($file->getPath());
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage(), ['exception' => $th]);
			return false;
		}

		return [
			'file' => [
				'type' => 'file',
				'id' => $file->getId(),
				'name' => $file->getName(),
				'path' => $relativePath,
				'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $fileId])
			]
		];
	}
}
