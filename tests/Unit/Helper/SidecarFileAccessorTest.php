<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\Helper;

use OCA\WorkflowOcr\Helper\SidecarFileAccessor;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SidecarFileAccessorTest extends TestCase {

	/** @var ITempManager|MockObject */
	private $tempManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var SidecarFileAccessor */
	private $accessor;

	/** @var string */
	private $tmpFilePath;

	public function setUp(): void {
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->accessor = new SidecarFileAccessor($this->tempManager, $this->logger);
		$tmpFile = tmpfile();
		$this->tmpFilePath = stream_get_meta_data($tmpFile)['uri'];
		parent::setUp();
	}

	public function tearDown(): void {
		if (file_exists($this->tmpFilePath)) {
			unlink($this->tmpFilePath);
		}
		parent::tearDown();
	}

	public function testCreateAndReadSucceeds() {
		$ocrContent = 'testOCRContent';

		$this->tempManager->expects($this->once())
			->method('getTemporaryFile')
			->with('sidecar')
			->willReturn($this->tmpFilePath);

		file_put_contents($this->tmpFilePath, $ocrContent);

		$sidecarFilePath = $this->accessor->getOrCreateSidecarFile();
		$this->assertEquals($this->tmpFilePath, $sidecarFilePath);

		$sidecarFileContent = $this->accessor->getSidecarFileContent();
		$this->assertEquals($ocrContent, $sidecarFileContent);

		$sidecarFilePath2 = $this->accessor->getOrCreateSidecarFile();
		$this->assertEquals($this->tmpFilePath, $sidecarFilePath2);
	}

	public function testLogsWarningIfCreateFails() {
		$this->tempManager->expects($this->once())
			->method('getTemporaryFile')
			->with('sidecar')
			->willReturn(false);

		$this->logger->expects($this->once())
			->method('warning')
			->with('Could not create temporary sidecar file');

		$sidecarFilePath = $this->accessor->getOrCreateSidecarFile();
		$this->assertEquals(false, $sidecarFilePath);

		$sidecarFileContent = $this->accessor->getSidecarFileContent();
		$this->assertEquals('', $sidecarFileContent);
	}
}
