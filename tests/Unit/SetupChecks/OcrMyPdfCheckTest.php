<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\SetupChecks;

use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCA\WorkflowOcr\SetupChecks\OcrMyPdfCheck;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\IL10N;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OcrMyPdfCheckTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var ICommand|MockObject */
	private $command;
	/** @var IOcrBackendInfoService|MockObject */
	private $ocrBackendInfoService;
	/** @var IApiClient|MockObject */
	private $apiClient;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var OcrMyPdfCheck */
	private $ocrMyPdfCheck;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->command = $this->createMock(ICommand::class);
		$this->ocrBackendInfoService = $this->createMock(IOcrBackendInfoService::class);
		$this->apiClient = $this->createMock(IApiClient::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ocrMyPdfCheck = new OcrMyPdfCheck($this->l10n, $this->command, $this->ocrBackendInfoService, $this->apiClient, $this->logger);
	}

	public function testGetCategory(): void {
		$this->assertEquals('system', $this->ocrMyPdfCheck->getCategory());
	}

	public function testGetName(): void {
		$this->l10n->method('t')->willReturn('Is OCRmyPDF installed');
		$this->assertEquals('Is OCRmyPDF installed', $this->ocrMyPdfCheck->getName());
	}

	public function testRunOcrMyPdfNotInstalled(): void {
		$this->command->method('setCommand')->willReturnSelf();
		$this->command->method('execute')->willReturn(true);
		$this->command->method('getExitCode')->willReturn(127);

		$this->l10n->method('t')->willReturn('OCRmyPDF CLI is not installed.');

		$result = $this->ocrMyPdfCheck->run();
		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertEquals('OCRmyPDF CLI is not installed.', $result->getDescription());
	}

	public function testRunOcrMyPdfNotWorkingCorrectly(): void {
		$this->command->method('setCommand')->willReturnSelf();
		$this->command->method('execute')->willReturn(true);
		$this->command->method('getExitCode')->willReturn(1);
		$this->command->method('getError')->willReturn('Some error');

		$this->l10n->expects($this->once())->method('t')
			->with('OCRmyPDF CLI is not working correctly. Error was: %1$s', ['Some error'])
			->willReturn('OCRmyPDF CLI is not working correctly. Error was: Some error');

		$result = $this->ocrMyPdfCheck->run();
		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertEquals('OCRmyPDF CLI is not working correctly. Error was: Some error', $result->getDescription());
	}

	public function testRunOcrMyPdfInstalled(): void {
		$this->command->method('setCommand')->willReturnSelf();
		$this->command->method('execute')->willReturn(true);
		$this->command->method('getExitCode')->willReturn(0);
		$this->command->method('getOutput')->willReturn('12.0.0');

		$this->l10n->expects($this->once())->method('t')
			->with('OCRmyPDF is installed and has version %1$s.', ['12.0.0'])
			->willReturn('OCRmyPDF is installed and has version 12.0.0.');

		$result = $this->ocrMyPdfCheck->run();
		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::SUCCESS, $result->getSeverity());
		$this->assertEquals('OCRmyPDF is installed and has version 12.0.0.', $result->getDescription());
	}

	public function testRunOcrMyPdfInstalledViaRemoteBackend(): void {
		$this->ocrBackendInfoService->expects($this->once())->method('isRemoteBackend')->willReturn(true);
		$this->apiClient->expects($this->once())->method('heartbeat')->willReturn(true);
		$this->l10n->expects($this->once())->method('t')
			->with('Workflow OCR Backend is installed.')
			->willReturn('Workflow OCR Backend is installed.');

		$result = $this->ocrMyPdfCheck->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::SUCCESS, $result->getSeverity());
		$this->assertEquals('Workflow OCR Backend is installed.', $result->getDescription());
	}

	public function testRunOcrMyPdfInstalledVIaRemoteBackendHeartbeatFailed(): void {
		$this->ocrBackendInfoService->expects($this->once())->method('isRemoteBackend')->willReturn(true);
		$this->apiClient->expects($this->once())->method('heartbeat')->willReturn(false);
		$this->l10n->expects($this->once())->method('t')
			->with('Workflow OCR Backend is installed but heartbeat failed.')
			->willReturn('Workflow OCR Backend is installed but heartbeat failed.');

		$result = $this->ocrMyPdfCheck->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::WARNING, $result->getSeverity());
		$this->assertEquals('Workflow OCR Backend is installed but heartbeat failed.', $result->getDescription());
	}

	public function testRunOcrMyPdfInstalledViaRemoteBackendFailedCatchesException(): void {
		$this->ocrBackendInfoService->expects($this->once())->method('isRemoteBackend')->willReturn(true);
		$this->apiClient->expects($this->once())->method('heartbeat')->willThrowException(new \Exception('API error'));
		$this->l10n->expects($this->once())->method('t')
			->with('Workflow OCR Backend is not reachable. Error was: %1$s')
			->willReturn('Workflow OCR Backend is not reachable. Error was: %1$s');

		$result = $this->ocrMyPdfCheck->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertEquals('Workflow OCR Backend is not reachable. Error was: %1$s', $result->getDescription());
	}
}
