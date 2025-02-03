<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Robin Windey <ro.windey@gmail.com>
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
namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors\Remote;

use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\WorkflowOcrRemoteProcessor;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkflowOcrRemoteProcessorTest extends TestCase {
	/** @var IApiClient|MockObject */
	private $apiClient;
	private $commandLineUtils;
	private $logger;
	private $file;
	private $workflowSettings;
	private $globalSettings;
	private $processor;

	protected function setUp(): void {
		$this->apiClient = $this->createMock(IApiClient::class);
		$this->commandLineUtils = $this->createMock(ICommandLineUtils::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->file = $this->createMock(File::class);
		$this->workflowSettings = $this->createMock(WorkflowSettings::class);
		$this->globalSettings = $this->createMock(GlobalSettings::class);

		$this->processor = new WorkflowOcrRemoteProcessor(
			$this->apiClient,
			$this->commandLineUtils,
			$this->logger
		);
	}

	public function testOcrFileSuccess(): void {
		$fileResource = fopen('php://memory', 'rb');
		$fileName = 'test.pdf';
		$ocrMyPdfParameters = 'ocrmypdfparam --param';
		$ocrResult = new OcrResult([
			'filename' => 'result.pdf',
			'contentType' => 'application/pdf',
			'recognizedText' => 'recognized text',
			'fileContent' => base64_encode('file content')
		]);

		$this->file->method('fopen')->willReturn($fileResource);
		$this->file->method('getName')->willReturn($fileName);
		$this->commandLineUtils->method('getCommandlineArgs')->willReturn($ocrMyPdfParameters);
		$this->apiClient->expects($this->once())
			->method('processOcr')
			->with($fileResource, $fileName, $ocrMyPdfParameters)
			->willReturn($ocrResult);

		$result = $this->processor->ocrFile($this->file, $this->workflowSettings, $this->globalSettings);

		$this->assertInstanceOf(OcrProcessorResult::class, $result);
		$this->assertEquals('file content', $result->getFileContent());
		$this->assertEquals('pdf', $result->getFileExtension());
		$this->assertEquals('recognized text', $result->getRecognizedText());
	}

	public function testOcrFileErrorResult(): void {
		$fileResource = fopen('php://memory', 'rb');
		$fileName = 'test.pdf';
		$ocrMyPdfParameters = 'param1';
		$errorResult = $this->createMock(ErrorResult::class);

		$this->file->method('fopen')->willReturn($fileResource);
		$this->file->method('getName')->willReturn($fileName);
		$this->commandLineUtils->method('getCommandlineArgs')->willReturn($ocrMyPdfParameters);
		$this->apiClient->method('processOcr')->willReturn($errorResult);

		$errorResult->method('getMessage')->willReturn('OCR failed');

		$this->expectException(OcrNotPossibleException::class);
		$this->expectExceptionMessage('OCR failed');

		$this->processor->ocrFile($this->file, $this->workflowSettings, $this->globalSettings);
	}
}
