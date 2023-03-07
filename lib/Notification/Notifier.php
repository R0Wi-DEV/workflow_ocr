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

		$notification->setIcon($this->urlGenerator->imagePath(Application::APP_NAME, 'app-dark.svg'));
		$l = $this->l10nFactory->get(Application::APP_NAME, $languageCode);

		switch ($notification->getSubject()) {
			case 'ocr_error':
				$message = $notification->getSubjectParameters()['message'];
				$notification
					->setParsedSubject($l->t('Workflow OCR error'))
					->setParsedMessage($message);
				// Only add file info if we have one ...
				if ($notification->getObjectType() === 'file' && $notification->getObjectId()) {
					$richParams = $this->getRichParamForFile($notification);
					$notification->setRichSubject($l->t('Workflow OCR error for file {file}'), $richParams);
				}
				return $notification;
			default:
				throw new \InvalidArgumentException();
		}
	}

	private function getRichParamForFile(INotification $notification) : array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($notification->getUser());
			/** @var File[] */
			$files = $userFolder->getById($notification->getObjectId());
			/** @var File $file */
			$file = array_shift($files);
			$relativePath = $userFolder->getRelativePath($file->getPath());
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage(), ['exception' => $th]);
			throw new AlreadyProcessedException();
		}

		$richParams = [
			'file' => [
				'type' => 'file',
				'id' => $file->getId(),
				'name' => $file->getName(),
				'path' => $relativePath,
				'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $notification->getObjectId()])
			]
		];

		return $richParams;
	}
}
