<?php

declare(strict_types=1);

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;

use OCA\WorkflowOcr\OcrProcessors\Local\OcrMyPdfBasedProcessor;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorBase;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCA\WorkflowOcr\Wrapper\IPhpNativeFunctions;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BadReadStreamWrapper {
	public $context;
	public function stream_open($path, $mode, $options, &$opened_path) {
		return true;
	}

	public function stream_read($count) {
		return false; // simulate read failure
	}

	public function stream_eof() {
		return false; // not EOF, so read failure is treated as a read error
	}

	public function stream_stat() {
		return false;
	}
}

class TestOcrProcessor extends OcrProcessorBase {
	public function __construct($logger, IPhpNativeFunctions $phpNative) {
		parent::__construct($logger, $phpNative);
	}

	protected function doOcrProcessing($fileResource, string $fileName, $settings, $globalSettings): OcrProcessorResult {
		// Return the preprocessed file bytes so tests can inspect preprocessing result
		$contents = '';
		if (is_resource($fileResource)) {
			rewind($fileResource);
			$contents = stream_get_contents($fileResource);
		}
		return new OcrProcessorResult(true, $contents, 'recognized', 0, '');
	}
}

class TestableOcrMyPdfBasedProcessor extends OcrMyPdfBasedProcessor {
	public function __construct($command, $logger, $sidecar, $cmdUtils, $phpNative) {
		parent::__construct($command, $logger, $sidecar, $cmdUtils, $phpNative);
	}

	public function runCallToDoOcrProcessing($fileResource, string $fileName, $settings, $globalSettings): OcrProcessorResult {
		return $this->doOcrProcessing($fileResource, $fileName, $settings, $globalSettings);
	}

	protected function getAdditionalCommandlineArgs($settings, $globalSettings): array {
		return [];
	}
}

class OcrProcessorBaseTest extends TestCase {
	public function testRemoveAlphaChannelFromImage_RemovesAlphaAndReturnsNewStream(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$phpNative = $this->createMock(IPhpNativeFunctions::class);
		$phpNative->method('fopen')->willReturnCallback(fn ($file, $mode) => fopen($file, $mode));
		$phpNative->method('streamGetContents')->willReturnCallback(fn ($h) => stream_get_contents($h));

		$processor = new TestOcrProcessor($logger, $phpNative);

		$originalStream = $this->createPngStream(true);

		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('image/png');
		$file->method('fopen')->with('rb')->willReturn($originalStream);
		$file->method('getName')->willReturn('test.png');

		$settings = $this->createMock(WorkflowSettings::class);
		$globalSettings = $this->createMock(GlobalSettings::class);

		// Capture original bytes before ocrFile closes streams
		if (!is_resource($originalStream)) {
			$this->fail('createPngStream did not return a valid stream resource');
		}
		$originalBytes = stream_get_contents($originalStream);
		rewind($originalStream);

		$result = $processor->ocrFile($file, $settings, $globalSettings);

		$this->assertNotNull($result);
		$processed = $result->getFileContent();

		// Check PNG IHDR color type byte: 6 = RGBA (has alpha), 2 = RGB (no alpha)
		$this->assertGreaterThanOrEqual(26, strlen($originalBytes), 'PNG too short to inspect IHDR');
		$origColorType = ord($originalBytes[25]);
		$procColorType = ord($processed[25]);

		$this->assertEquals(6, $origColorType, 'Original should have RGBA color type');
		$this->assertNotEquals(6, $procColorType, 'Processed image should not have RGBA color type');
	}

	public function testDoOcrProcessingReturnsErrorIfInputFileCannotBeRead(): void {
		$logger = $this->createMock(LoggerInterface::class);

		// Create mocks for constructor dependencies
		$command = $this->createMock(ICommand::class);
		$sidecar = $this->createMock(ISidecarFileAccessor::class);
		$cmdUtils = $this->createMock(ICommandLineUtils::class);
		$phpNative = $this->createMock(IPhpNativeFunctions::class);

		// Mock that "stream_get_contents" fails
		$phpNative->method('streamGetContents')->willReturn(false);

		$processor = new TestableOcrMyPdfBasedProcessor($command, $logger, $sidecar, $cmdUtils, $phpNative);

		$settings = $this->createMock(WorkflowSettings::class);
		$globalSettings = $this->createMock(GlobalSettings::class);

		$badStream = fopen('php://temp', 'r');
		try {
			$result = $processor->runCallToDoOcrProcessing($badStream, 'unreadable.pdf', $settings, $globalSettings);
		} finally {
			if (is_resource($badStream)) {
				fclose($badStream);
			}
		}

		$this->assertInstanceOf(OcrProcessorResult::class, $result);
		$this->assertFalse($result->isSuccess(), 'Expected processing to indicate failure');
		$this->assertSame(-1, $result->getExitCode(), 'Expected exit code -1 for read failure');
		$this->assertStringContainsString('Failed to read file content', (string)$result->getErrorMessage());
	}

	public function testRemoveAlphaChannelFromImage_NoAlpha_ReturnsOriginalResource(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$phpNative = $this->createMock(IPhpNativeFunctions::class);
		$phpNative->method('fopen')->willReturnCallback(fn ($file, $mode) => fopen($file, $mode));
		$phpNative->method('streamGetContents')->willReturnCallback(fn ($h) => stream_get_contents($h));

		$processor = new TestOcrProcessor($logger, $phpNative);

		$originalStream = $this->createPngStream(false);

		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('image/png');
		$file->method('fopen')->with('rb')->willReturn($originalStream);
		$file->method('getName')->willReturn('test_no_alpha.png');

		$settings = $this->createMock(WorkflowSettings::class);
		$globalSettings = $this->createMock(GlobalSettings::class);

		// Capture original bytes before ocrFile closes streams
		rewind($originalStream);
		$originalBytes = stream_get_contents($originalStream);
		rewind($originalStream);

		$result = $processor->ocrFile($file, $settings, $globalSettings);

		$this->assertNotNull($result);
		$processed = $result->getFileContent();

		// For no-alpha images the preprocessed bytes should equal the original
		$this->assertEquals($originalBytes, $processed);
	}

	public function testThrowsRuntimeExceptionIfOpeningTempFails(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$phpNative = $this->createMock(IPhpNativeFunctions::class);

		// Simulate failure when opening the temporary stream for alpha removal
		$phpNative->method('fopen')->with('php://temp', 'r+')->willReturn(false);

		$processor = new TestOcrProcessor($logger, $phpNative);

		$originalStream = $this->createPngStream(true);

		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('image/png');
		$file->method('fopen')->with('rb')->willReturn($originalStream);
		$file->method('getName')->willReturn('test.png');

		$settings = $this->createMock(WorkflowSettings::class);
		$globalSettings = $this->createMock(GlobalSettings::class);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Failed to create temporary stream for alpha channel removal');

		$processor->ocrFile($file, $settings, $globalSettings);
	}

	private function createPngStream(bool $withAlpha) {
		$stream = fopen('php://temp', 'r+');
		$img = imagecreatetruecolor(10, 10);
		if ($withAlpha) {
			imagesavealpha($img, true);
			$trans_colour = imagecolorallocatealpha($img, 0, 0, 0, 127);
			imagefill($img, 0, 0, $trans_colour);
		} else {
			$white = imagecolorallocate($img, 255, 255, 255);
			imagefill($img, 0, 0, $white);
		}
		ob_start();
		imagepng($img);
		$png = ob_get_clean();
		imagedestroy($img);
		fwrite($stream, $png);
		rewind($stream);
		return $stream;
	}
}
