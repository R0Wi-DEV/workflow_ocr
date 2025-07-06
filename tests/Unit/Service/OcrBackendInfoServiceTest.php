<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OCA\WorkflowOcr\Exception\CommandException;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\Service\OcrBackendInfoService;
use OCA\WorkflowOcr\Wrapper\IAppApiWrapper;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class OcrBackendInfoServiceTest extends TestCase {
	/** @var ICommand|MockObject */
	private $command;
	/** @var IApiClient|MockObject */
	private $apiClient;
	/** @var IAppManager|MockObject */
	private $appManager;
	/** @var IAppApiManager|MockObject */
	private $appApiWrapper;
	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var OcrBackendInfoService */
	private $service;

	protected function setUp() : void {
		$this->command = $this->createMock(ICommand::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->apiClient = $this->createMock(IApiClient::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appApiWrapper = $this->createMock(IAppApiWrapper::class);
		$this->service = new OcrBackendInfoService($this->command, $this->apiClient, $this->appManager, $this->appApiWrapper, $this->logger);
		parent::setUp();
	}

	#[DataProvider('dataProviderInstalledLangs')]
	public function testGetInstalledLanguagesReturnsNonAssociativeArray(string $simulatedCliResult, array $expectedArray) : void {
		// Testcase for https://github.com/R0Wi/workflow_ocr/issues/140#issuecomment-1245310080

		$this->command->expects($this->once())
			->method('setCommand')
			->with('tesseract --list-langs');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn($simulatedCliResult);

		$result = $this->service->getInstalledLanguages();

		$this->assertEquals($expectedArray, $result);
	}

	public function testGetInstalledLanguagesThrowsIfCommandExecuteReturnsFalse() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('tesseract --list-langs');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(false);
		$this->command->expects($this->never())
			->method('getOutput');

		$this->expectException(CommandException::class);
		$this->service->getInstalledLanguages();
	}

	#[DataProvider('dataProviderStdErrAndErrOutput')]
	public function testGetInstalledLanguagesLogsWarningIfCommandStrErrOrErrOutputWasNotEmpty(string $stdErr, string $errorOutput) : void {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('tesseract --list-langs');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('SomeCliResult');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn($stdErr);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn($errorOutput);
		$logged = false;
		$this->logger->expects($this->once())
			->method('warning')
			->willReturnCallback(function ($message, $paramsArr) use ($stdErr, $errorOutput, &$logged) {
				$this->assertEquals('Tesseract list languages succeeded with warning(s): {stdErr}, {errorOutput}', $message);
				if (!empty($stdErr)) {
					$logged = $paramsArr['stdErr'] === $stdErr;
				}
				if (!empty($errorOutput)) {
					$logged = $paramsArr['errorOutput'] === $errorOutput;
				}
			});

		$this->service->getInstalledLanguages();

		$this->assertTrue($logged);
	}

	public function testGetInstalledLanguagesThrowsIfCliDidNotProduceAnyOutput() : void {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('tesseract --list-langs');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('');
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('');

		$this->expectException(CommandException::class);
		$this->service->getInstalledLanguages();
	}

	public function testIsRemoteBackendReturnsTrueIfRemoteBackendIsInstalledViaAppApi() {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('app_api')
			->willReturn(true);
		$this->appApiWrapper->expects($this->once())
			->method('getExApp')
			->with('workflow_ocr_backend')
			->willReturn(['enabled' => true]);

		$result = $this->service->isRemoteBackend();

		$this->assertTrue($result);
	}

	#[DataProvider('dataProviderDependencyInjectionExceptions')]
	public function testIsRemoteBackendReturnsFalseIfBackendAppIsNotInstalled(callable $exceptionCallback) {
		$exception = $exceptionCallback($this);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('app_api')
			->willReturn(true);
		$this->appApiWrapper->expects($this->once())
			->method('getExApp')
			->with('workflow_ocr_backend')
			->willThrowException($exception);

		$result = $this->service->isRemoteBackend();

		$this->assertFalse($result);
	}

	public function testGetInstalledLanguagesFromRemoteBackend() {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('app_api')
			->willReturn(true);
		$this->appApiWrapper->expects($this->once())
			->method('getExApp')
			->with('workflow_ocr_backend')
			->willReturn(['enabled' => true]);

		$this->apiClient->expects($this->once())
			->method('getLanguages')
			->willReturn(['eng', 'deu', 'chi']);

		$result = $this->service->getInstalledLanguages();

		$this->assertEquals(['eng', 'deu', 'chi'], $result);
	}

	public static function dataProviderInstalledLangs() {
		return [
			["List of available languages (4):\neng\ndeu\nosd\nchi", ['eng','deu','chi']]
		];
	}

	public static function dataProviderStdErrAndErrOutput() {
		return [
			['someStdErrMessage', ''],
			['', 'someErrorOutput']
		];
	}

	public static function dataProviderDependencyInjectionExceptions() {
		return [
			[fn (self $testClass) => $testClass->createMock(ContainerExceptionInterface::class)],
			[fn (self $testClass) => $testClass->createMock(NotFoundExceptionInterface::class)]
		];
	}
}
