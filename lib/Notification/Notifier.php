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
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {
	/** @var IFactory */
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
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_NAME, $languageCode);

		// Currently we only support sending notifications for ocr_error and ocr_success
		$subject = $notification->getSubject();
		switch ($subject) {
			case 'ocr_error':
				$parsedSubject = $l->t('Workflow OCR error');
				$richSubject = $l->t('Workflow OCR error for file {file}');
				break;
			case 'ocr_success':
				$parsedSubject = $l->t('Workflow OCR success');
				$richSubject = $l->t('Workflow OCR success for file {file}');
				break;
			default:
				$this->logger->warning('Unsupported notification subject {subject}', ['subject' => $subject]);
				// Note:: AlreadyProcessedException has to be thrown before any call to $notification->set...
				// otherwise notification won't be removed from the database
				throw new AlreadyProcessedException();
		}

		// Only add file info if we have some ...
		$richParams = null;
		if ($notification->getObjectType() === 'file'
			&& ($fileId = $notification->getObjectId())
			&& ($uid = $notification->getUser())) {
			// Note:: This might throw an AlreadyProcessedException if the file doesn't exist anymore.
			// It has to be thrown before any call to $notification->set... otherwise the notification
			// won't be removed from the database.
			$richParams = $this->tryGetRichParamForFile($uid, intval($fileId));
		}

		if ($richParams !== null) {
			$notification->setRichSubject($richSubject, $richParams);
		} else {
			// Fallback to generic error message without file link
			$notification->setParsedSubject($parsedSubject);
		}

		// If caller sends a message, use it, otherwise use the parsed subject
		$subjectParams = $notification->getSubjectParameters();
		$message = isset($subjectParams['message']) ? $subjectParams['message'] : $parsedSubject;

		$notification
			->setParsedMessage($message)
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_NAME, 'app-dark.svg')));

		return $notification;
	}

	/**
	 * Tries to build the rich notification parameters pointing to the file the
	 * notification was created for.
	 *
	 * @return array|null The rich parameters or `null` if they could not be determined
	 *                    because of an unexpected error.
	 * @throws AlreadyProcessedException If the file cannot be found for the given user
	 *                                   anymore. In that case the notification is obsolete
	 *                                   and gets removed from the database instead of being
	 *                                   re-rendered on every notification poll (see #382).
	 */
	private function tryGetRichParamForFile(string $uid, int $fileId) : ?array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			/** @var Node|null $file */
			$file = $userFolder->getFirstNodeById($fileId);
			$relativePath = $file !== null ? $userFolder->getRelativePath($file->getPath()) : null;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage(), ['exception' => $th]);
			return null;
		}

		if ($file === null) {
			// Nothing unusual: the user might have deleted the file (or moved it to the
			// trashbin) after the OCR process has finished. Since we cannot render a link
			// to the file anymore, the notification is dropped. This also prevents the
			// message from being logged over and over again, because the notifications app
			// re-renders every stored notification on each poll.
			$this->logger->debug('Could not find file with id {fileId} for user {uid}, discarding obsolete notification', ['fileId' => $fileId, 'uid' => $uid]);
			// Note:: The caller (prepare()) must not call $notification->set... before invoking this
			// method, otherwise the notification won't be removed from the database once this
			// exception is thrown.
			throw new AlreadyProcessedException();
		}

		return [
			'file' => [
				'type' => 'file',
				'id' => strval($file->getId()),
				'name' => $file->getName(),
				'path' => $relativePath,
				'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $fileId])
			]
		];
	}
}
