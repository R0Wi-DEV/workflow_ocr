<?php

declare(strict_types=1);

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\OcrProcessors\OcrProcessorBase;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// If the Imagick extension is not available in the test environment, provide
// a lightweight stub to emulate the methods used by the class under test.
if (!class_exists('Imagick')) {
	class Imagick {
		public const ALPHACHANNEL_REMOVE = 1;
		public const LAYERMETHOD_FLATTEN = 2;

		private bool $hasAlpha = false;

		public function readImageFile($resource, $fileName) {
			$contents = stream_get_contents($resource);
			rewind($resource);
			$this->hasAlpha = strpos($contents, 'has_alpha') !== false;
		}

		public function getImageAlphaChannel() {
			return $this->hasAlpha ? 1 : 0;
		}

		public function setImageAlphaChannel($flag) {
			// noop for stub
		}

		public function mergeImageLayers($method) {
			// noop for stub
		}

		public function getImageBlob() {
			return $this->hasAlpha ? 'flattened_blob' : '';
		}

		public function clear() {
		}

		public function destroy() {
		}
	}
}

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;

class TestOcrProcessor extends OcrProcessorBase {
	protected function doOcrProcessing($fileResource, string $fileName, $settings, $globalSettings): array {
		// Return the preprocessed file bytes so tests can inspect preprocessing result
		$contents = '';
		if (is_resource($fileResource)) {
			rewind($fileResource);
			$contents = stream_get_contents($fileResource);
		}
		return [true, $contents, 'recognized', 0, ''];
	}
}

class OcrProcessorBaseTest extends TestCase {
	public function testRemoveAlphaChannelFromImage_RemovesAlphaAndReturnsNewStream(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$processor = new TestOcrProcessor($logger);

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

	public function testRemoveAlphaChannelFromImage_NoAlpha_ReturnsOriginalResource(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$processor = new TestOcrProcessor($logger);

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
