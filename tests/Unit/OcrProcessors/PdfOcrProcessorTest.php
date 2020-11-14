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

class PdfOcrProcessorTest extends TestCase {
	/** @var ICommand|MockObject */
	private $command;

	protected function setUp(): void {
		parent::setUp();

		$this->command = $this->createMock(ICommand::class);
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
		
		$processor = new PdfOcrProcessor($this->command);
		$result = $processor->ocrFile($pdfBefore);
		
		$this->assertEquals($pdfAfter, $result);
	}

	public function testThrowsOcrNotPossibleException() {
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
			->willReturn(false);
		$this->command->expects($this->never())
			->method('getOutput');
		$this->command->expects($this->once())
			->method('getError');
		$this->command->expects($this->once())
			->method('getExitCode');

		$processor = new PdfOcrProcessor($this->command);
		$thrown = false;

		try {
			$result = $processor->ocrFile($pdfBefore);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrNotPossibleException::class, $t);
		}
		
		$this->assertTrue($thrown);
	}
}
