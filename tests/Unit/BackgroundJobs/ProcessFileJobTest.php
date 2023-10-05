<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\BackgroundJobs;

use Exception;
use OC\BackgroundJob\JobList;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Exception\OcrResultEmptyException;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessFileJobTest extends TestCase {
	/** @var LoggerInterface|MockObject */
	private $logger;
	
	/** @var IOcrService|MockObject */
	private $ocrService;
	
	/** @var INotificationService|MockObject */
	private $notificationService;
	/** @var JobList */
	private $jobList;
	/** @var ProcessFileJob */
	private $processFileJob;


	public function setUp() : void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ocrService = $this->createMock(IOcrService::class);
		$this->notificationService = $this->createMock(INotificationService::class);

		$this->processFileJob = new ProcessFileJob(
			$this->logger,
			$this->ocrService,
			$this->notificationService,
			$this->createMock(ITimeFactory::class)
		);
		$this->processFileJob->setId(111);

		/** @var IConfig */
		$configMock = $this->createMock(IConfig::class);
		/** @var ITimeFactory */
		$timeFactoryMock = $this->createMock(ITimeFactory::class);
		/** @var MockObject|IDbConnection */
		$connectionMock = $this->createMock(IDBConnection::class);
		/** @var MockObject|IQueryBuilder */
		$queryBuilderMock = $this->createMock(IQueryBuilder::class);
		$expressionBuilderMock = $this->createMock(IExpressionBuilder::class);

		$queryBuilderMock->method('delete')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('set')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('update')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('expr')
			->withAnyParameters()
			->willReturn($expressionBuilderMock);
		$connectionMock->method('getQueryBuilder')
			->withAnyParameters()
			->willReturn($queryBuilderMock);

		$this->jobList = new JobList(
			$connectionMock,
			$configMock,
			$timeFactoryMock,
			$this->logger
		);

		$this->processFileJob->setArgument([
			'fileId' => 42,
			'uid' => 'admin',
			'settings' => '{}'
		]);
	}
	
	public function testCatchesException() {
		$exception = new Exception('someEx');
		$this->ocrService->method('runOcrProcess')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error')
			->with($exception->getMessage(), ['exception' => $exception]);

		$this->processFileJob->start($this->jobList);
	}
	
	/**
	 * @dataProvider dataProvider_InvalidArguments
	 */
	public function testLogsErrorAndDoesNothingOnInvalidArguments($argument, $errorMessagePart) {
		$this->processFileJob->setArgument($argument);
		$this->ocrService->expects($this->never())
			->method('runOcrProcess')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains($errorMessagePart), $this->callback(function ($loggerArgs) {
				return is_array($loggerArgs) && ($loggerArgs['exception'] instanceof \Exception);
			}));

		$this->processFileJob->start($this->jobList);
	}

	public function testNotFoundLogsErrorAndSendsNotification() {
		$this->ocrService->method('runOcrProcess')
			->willThrowException(new NotFoundException('File was not found'));
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('File was not found'), $this->callback(function ($subject) {
				return is_array($subject) && ($subject['exception'] instanceof NotFoundException);
			}));
		$this->notificationService->expects($this->once())
			->method('createErrorNotification')
			->with($this->stringContains('An error occured while executing the OCR process (') && $this->stringContains('File was not found'));

		$this->processFileJob->start($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_OcrExceptions
	 */
	public function testLogsError_OnOcrException(Exception $exception) {
		$this->ocrService->method('runOcrProcess')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error');

		$this->processFileJob->start($this->jobList);
	}

	public function testLogsNonOcrExceptionsFromOcrService() {
		$exception = new \Exception('someException');

		$this->ocrService->expects($this->once())
			->method('runOcrProcess')
			->willThrowException($exception);

		$this->logger->expects($this->once())
			->method('error');

		$this->processFileJob->start($this->jobList);
	}

	public function dataProvider_InvalidArguments() {
		$arr = [
			[null, "Argument is no array"],
			[['mykey' => 'myvalue'], "Undefined array key"]
		];
		return $arr;
	}

	public function dataProvider_OcrExceptions() {
		return [
			[new OcrNotPossibleException('Ocr not possible')],
			[new OcrProcessorNotFoundException('audio/mpeg')],
			[new OcrResultEmptyException('Ocr result was empty')]
		];
	}
}
