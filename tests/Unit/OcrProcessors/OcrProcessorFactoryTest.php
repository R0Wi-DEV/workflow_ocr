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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\Local\PdfOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\Remote\WorkflowOcrRemoteProcessor;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class OcrProcessorFactoryTest extends TestCase {
	/** @var ContainerInterface */
	private $appContainer;

	/** @var IOcrBackendInfoService|MockObject */
	private $ocrBackendInfoService;

	protected function setUp() : void {
		parent::setUp();
		$app = new Application();
		$this->appContainer = $app->getContainer();
		$this->ocrBackendInfoService = $this->createMock(IOcrBackendInfoService::class);
	}

	public function testReturnsLocalPdfProcessor() {
		$factory = new OcrProcessorFactory($this->appContainer, $this->ocrBackendInfoService);
		$processor = $factory->create('application/pdf');
		$this->assertInstanceOf(PdfOcrProcessor::class, $processor);
	}

	public function testReturnsRemotePdfProcessor() {
		$this->ocrBackendInfoService->method('isRemoteBackend')->willReturn(true);
		$factory = new OcrProcessorFactory($this->appContainer, $this->ocrBackendInfoService);
		$processor = $factory->create('application/pdf');
		$this->assertInstanceOf(WorkflowOcrRemoteProcessor::class, $processor);
	}

	public function testThrowsNotFoundExceptionOnInvalidMimeType() {
		$this->expectException(OcrProcessorNotFoundException::class);
		$factory = new OcrProcessorFactory($this->appContainer, $this->ocrBackendInfoService);
		$factory->create('no/mimetype');
	}

	#[DataProvider('dataProvider_mimeTypes')]
	public function testOcrProcessorsAreNotCached($mimetype) {
		// Related to BUG #43

		$factory = new OcrProcessorFactory($this->appContainer, $this->ocrBackendInfoService);
		$processor1 = $factory->create($mimetype);
		$processor2 = $factory->create($mimetype);
		$this->assertFalse($processor1 === $processor2);
	}

	#[DataProvider('dataProvider_mimeTypes')]
	public function testPdfCommandNotCached($mimetype) {
		// Related to BUG #43

		$factory = new OcrProcessorFactory($this->appContainer, $this->ocrBackendInfoService);
		$processor1 = $factory->create($mimetype);
		$processor2 = $factory->create($mimetype);
		$cmd1 = $this->getCommandObject($processor1);
		$cmd2 = $this->getCommandObject($processor2);
		$sidecar1 = $this->getSidecarFileAccessorObject($processor1);
		$sidecar2 = $this->getSidecarFileAccessorObject($processor2);

		$this->assertFalse($cmd1 === $cmd2);
		$this->assertFalse($sidecar1 === $sidecar2);
	}
	public static function dataProvider_mimeTypes() {
		$mimetypes = [];
		$mapping = (new \ReflectionClass(OcrProcessorFactory::class))->getProperty('localMapping')->getValue();
		foreach ($mapping as $mimetype => $className) {
			$mimetypes[] = [$mimetype];
		}
		return $mimetypes;
	}

	private function getCommandObject(IOcrProcessor $ocrProcessor) {
		return $this->getPrivateFieldBubbled($ocrProcessor, 'command');
	}

	private function getSidecarFileAccessorObject(IOcrProcessor $ocrProcessor) {
		return $this->getPrivateFieldBubbled($ocrProcessor, 'sidecarFileAccessor');
	}

	private function getPrivateFieldBubbled(IOcrProcessor $ocrProcessor, $fieldName) {
		$reflection = new \ReflectionClass($ocrProcessor);
		while (!$reflection->hasProperty($fieldName)) {
			$reflection = $reflection->getParentClass();
		}
		$property = $reflection->getProperty($fieldName);
		$property->setAccessible(true);
		return $property->getValue($ocrProcessor);
	}
}
