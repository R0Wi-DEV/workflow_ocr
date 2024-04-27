<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\BackgroundJobs;

use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * Represents a QuedJob which processes
 * a OCR on a single file.
 */
class ProcessFileJob extends \OCP\BackgroundJob\QueuedJob {
	/** @var LoggerInterface */
	protected $logger;
	/** @var IOcrService */
	private $ocrService;
	/** @var INotificationService */
	private $notificationService;
	
	public function __construct(
		LoggerInterface $logger,
		IOcrService $ocrService,
		INotificationService $notificationService,
		ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->ocrService = $ocrService;
		$this->notificationService = $notificationService;
	}
	
	/**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
		$this->logger->debug('STARTED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);

		try {
			[$fileId, $uid, $settings] = $this->parseArguments($argument);
			$this->ocrService->runOcrProcess($fileId, $uid, $settings);
		} catch (\Throwable $ex) {
			$this->logger->error($ex->getMessage(), ['exception' => $ex]);
			$this->notificationService->createErrorNotification($uid, 'An error occured while executing the OCR process ('.$ex->getMessage().'). Please have a look at your servers logfile for more details.');
		}

		$this->logger->debug('ENDED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
	}

	/**
	 * @param mixed $argument
	 */
	private function parseArguments($argument) : array {
		if (!is_array($argument)) {
			throw new \InvalidArgumentException('Argument is no array in ' . self::class . ' method \'tryParseArguments\'.');
		}

		$jsonSettings = $argument['settings'];
		$settings = new WorkflowSettings($jsonSettings);
		$uid = $argument['uid'];
		$fileId = intval($argument['fileId']);

		return [
			$fileId,
			$uid,
			$settings
		];
	}
}
