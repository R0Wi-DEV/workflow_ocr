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

use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use OCA\WorkflowOcr\Wrapper\ICommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PdfOcrProcessorTest extends TestCase {
	/** @var ICommand|MockObject */
	private $command;

	/** @var LoggerInterface|MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->command = $this->createMock(ICommand::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testCallsCommandInterface() {
		$pdfBefore = 'someFileContent';
		$pdfAfter = 'someOcrFileContent';

		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with($pdfBefore)
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn($pdfAfter);
		
		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$result = $processor->ocrFile($pdfBefore);
		
		$this->assertEquals($pdfAfter, $result);
	}

	public function testThrowsOcrNotPossibleException() {
		$pdfBefore = 'someFileContent';

		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with($pdfBefore)
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

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$thrown = false;

		try {
			$processor->ocrFile($pdfBefore);
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
			->willReturn('error');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErr');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringStartsWith('OCRmyPDF succeeded with warning(s):'),
				$this->callback(function ($paramsArray) {
					return is_array($paramsArray) &&
							count($paramsArray) === 2 &&
							$paramsArray[0] === 'stdErr' &&
							$paramsArray[1] === 'error';
				}));

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent');
	}
}
