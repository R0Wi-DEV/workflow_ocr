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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors\Local;

use OCA\WorkflowOcr\Exception\OcrAlreadyDoneException;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrResultEmptyException;
use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\CommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\Local\PdfOcrProcessor;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\Files\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PdfOcrProcessorTest extends TestCase {
	private const FILE_CONTENT_BEFORE = 'someFileContentBefore';
	private const FILE_CONTENT_AFTER = 'somePDFFileContentAfter';

	private $fileBeforeMimeType;
	private $ocrMyPdfOutput;

	/** @var File|MockObject */
	private $fileBefore;

	/** @var ICommand|MockObject */
	private $command;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var ISidecarFileAccessor|MockObject */
	private $sidecarFileAccessor;
	/** @var ICommandLineUtils|MockObject */
	private $commandLineUtils;
	/** @var IOcrBackendInfoService|MockObject */
	private $ocrBackendInfoService;
	/** @var WorkflowSettings */
	private $defaultSettings;
	/** @var GlobalSettings */
	private $defaultGlobalSettings;

	protected function setUp(): void {
		parent::setUp();

		$this->command = $this->createMock(ICommand::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->sidecarFileAccessor = $this->createMock(ISidecarFileAccessor::class);
		$this->ocrBackendInfoService = $this->createMock(IOcrBackendInfoService::class);
		$this->commandLineUtils = new CommandLineUtils($this->ocrBackendInfoService, $this->logger);

		$this->defaultSettings = new WorkflowSettings();
		$this->defaultGlobalSettings = new GlobalSettings();
		$this->fileBefore = $this->createMock(File::class);

		$this->fileBefore->method('getContent')
			->willReturn(self::FILE_CONTENT_BEFORE);
		$this->fileBefore->method('getMimeType')
			->willReturnCallback(fn () => $this->fileBeforeMimeType);

		$this->fileBeforeMimeType = 'application/pdf';
		$this->ocrMyPdfOutput = self::FILE_CONTENT_AFTER;
		$this->command->method('getOutput')
			->willReturnCallback(fn () => $this->ocrMyPdfOutput !== self::FILE_CONTENT_AFTER ? $this->ocrMyPdfOutput : self::FILE_CONTENT_AFTER);
		;
		$this->ocrBackendInfoService->method('isRemoteBackend')
			->willReturn(false);
	}

	public function testCallsCommandInterface() {
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with(self::FILE_CONTENT_BEFORE);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$result = $processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);

		$this->assertEquals(self::FILE_CONTENT_AFTER, $result->getFileContent());
	}

	public function testThrowsOcrNotPossibleException() {
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(false);
		$this->command->expects($this->never())
			->method('getOutput');
		$this->command->expects($this->once())
			->method('getError');
		$this->command->expects($this->once())
			->method('getExitCode');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$thrown = false;

		try {
			$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrNotPossibleException::class, $t);
		}

		$this->assertTrue($thrown);
	}

	public function testLogsWarningIfOcrMyPdfSucceedsWithWarningOutput() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('getErrorOutput');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErrOutput');
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'OCRmyPDF succeeded with warning(s): {stdErr}, {errorOutput}',
				$this->callback(function ($paramsArray) {
					return is_array($paramsArray)
							&& count($paramsArray) === 2
							&& $paramsArray['stdErr'] === 'stdErrOutput'
							&& $paramsArray['errorOutput'] === 'getErrorOutput';
				}));

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testThrowsErrorIfOcrFileWasEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('error');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErr');
		$this->ocrMyPdfOutput = '';
		$this->fileBefore->expects($this->once())
			->method('getPath')
			->willReturn('/admin/files/somefile.pdf');

		$thrown = false;
		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);

		try {
			$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrResultEmptyException::class, $t);
			$this->assertEquals('OCRmyPDF did not produce any output for file /admin/files/somefile.pdf', $t->getMessage());
		}

		$this->assertTrue($thrown);
	}

	public function testLanguageSettingsAreSetCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --language deu+eng - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"languages": ["deu", "eng"] }'), $this->defaultGlobalSettings);
	}

	public function testRemoveBackgroundFlagIsSetCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --remove-background - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"removeBackground": true }'), $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsNotSetIfGlobalSettingsDoesNotContainProcessorCount() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsSetCorrectlyFromGobalSettings() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --jobs 42 - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$this->defaultGlobalSettings->processorCount = 42;

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testLogsInfoIfSidecarFileContentWasEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('');

		$this->logger->expects($this->once())
			->method('info')
			->with($this->callback(function ($message) {
				return strpos($message, 'Temporary sidecar file at') !== false && strpos($message, 'was empty') !== false;
			}));

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testDoesNotLogInfoIfSidecarFileContentWasNotEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');

		$this->logger->expects($this->never())
			->method('info');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testAppliesSidecarParameterIfSidecarFileCanBeCreated() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --sidecar /tmp/sidecar.txt - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getOrCreateSidecarFile')
			->willReturn('/tmp/sidecar.txt');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	#[DataProvider('dataProvider_testAppliesOcrModeParameter')]
	public function testAppliesOcrModeParameter(int $simulatedOcrMode, string $expectedOcrMyPdfFlag) {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q' . $expectedOcrMyPdfFlag . '--sidecar /tmp/sidecar.txt - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getOrCreateSidecarFile')
			->willReturn('/tmp/sidecar.txt');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"ocrMode": ' . $simulatedOcrMode . '}'), $this->defaultGlobalSettings);
	}

	public function testRemoveBackgroundIsNotAppliedIfOcrModeIsRedoOcr() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --redo-ocr --sidecar /tmp/sidecar.txt - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getOrCreateSidecarFile')
			->willReturn('/tmp/sidecar.txt');
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->callback(function ($message) {
				return strpos($message, '--remove-background is incompatible with --redo-ocr') !== false;
			}));

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"ocrMode": ' . WorkflowSettings::OCR_MODE_REDO_OCR . ', "removeBackground": true}'), $this->defaultGlobalSettings);
	}

	public function testAppliesCustomCliArgsCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --sidecar /tmp/sidecar.txt --output-type pdf - - || exit $? ; cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getOrCreateSidecarFile')
			->willReturn('/tmp/sidecar.txt');

		$workflowSettings = new WorkflowSettings('{"customCliArgs": "--output-type pdf"}');
		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
		$processor->ocrFile($this->fileBefore, $workflowSettings, $this->defaultGlobalSettings);
	}

	public function testThrowsOcrAlreadyDoneExceptionIfErrorCodeIsEquals6() {
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(false);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('error');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErr');
		$this->command->expects($this->once())
			->method('getExitCode')
			->willReturn(6);

		$thrown = false;
		try {
			$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor, $this->commandLineUtils);
			$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrAlreadyDoneException::class, $t);
		}

		$this->assertTrue($thrown);
	}

	public static function dataProvider_testAppliesOcrModeParameter() {
		return [
			[WorkflowSettings::OCR_MODE_SKIP_TEXT, ' --skip-text '],
			[WorkflowSettings::OCR_MODE_REDO_OCR, ' --redo-ocr '],
			[WorkflowSettings::OCR_MODE_FORCE_OCR, ' --force-ocr '],
			[WorkflowSettings::OCR_MODE_SKIP_FILE, ' ']
		];
	}
}
