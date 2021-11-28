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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Service\OcrService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OcrServiceTest extends TestCase {
	
	/** @var IOcrProcessorFactory|MockObject */
	private $ocrProcessorFactory;
	/** @var IOcrProcessor|MockObject */
	private $ocrProcessor;
	/** @var IGlobalSettingsService|MockObject*/
	private $globalSettingsService;
	/** @var OcrService */
	private $ocrService;

	public function setUp() : void {
		parent::setUp();

		$this->globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$this->ocrProcessorFactory = $this->createMock(IOcrProcessorFactory::class);
		$this->ocrProcessor = $this->createMock(IOcrProcessor::class);

		$this->ocrService = new OcrService($this->ocrProcessorFactory, $this->globalSettingsService);
	}

	public function testCallsOcrProcessor_WithCorrectArguments() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings();
		$globalSettings = new GlobalSettings();

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->with($content, $settings, $globalSettings);

		$this->ocrService->ocrFile($mime, $content, $settings);
	}
}
