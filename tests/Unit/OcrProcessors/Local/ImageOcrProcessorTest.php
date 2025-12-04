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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors\Local;

use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\Local\ImageOcrProcessor;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ImageOcrProcessorTest extends TestCase {
	/** @var \OCA\WorkflowOcr\Wrapper\IPhpNativeFunctions|MockObject */
	private $phpNative;
	public function testOcrFileSetsImageDpi() {
		/** @var ICommand|MockObject $command */
		$command = $this->createMock(ICommand::class);
		/** @var LoggerInterface|MockObject $logger */
		$logger = $this->createMock(LoggerInterface::class);
		/** @var File|MockObject $file */
		$file = $this->createMock(File::class);
		/** @var ISidecarFileAccessor|MockObject $fileReader */
		$sidecarFileAccessor = $this->createMock(ISidecarFileAccessor::class);
		/** @var ICommandLineUtils|MockObject $commandLineUtils */
		$commandLineUtils = $this->createMock(ICommandLineUtils::class);
		$commandLineUtils->method('getCommandlineArgs')
			->willReturnCallback(fn ($settings, $globalSettings, $sidecarFile, $additionalCommandlineArgs) => implode(' ', $additionalCommandlineArgs));

		$phpNative = $this->createMock(\OCA\WorkflowOcr\Wrapper\IPhpNativeFunctions::class);
		$phpNative->method('fopen')->willReturnCallback(fn ($file, $mode) => fopen($file, $mode));
		$phpNative->method('streamGetContents')->willReturnCallback(fn ($h) => stream_get_contents($h));

		$processor = new ImageOcrProcessor($command, $logger, $sidecarFileAccessor, $commandLineUtils, $phpNative);

		$file->method('fopen')
			->willReturnCallback(function ($mode) {
				$stream = fopen('php://temp', 'r+');
				fwrite($stream, 'content');
				rewind($stream);
				return $stream;
			});
		$file->expects($this->once())
			->method('getName')
			->willReturn('test.pdf');
		$command->expects($this->once())
			->method('setCommand')
			->with($this->stringContains(' --image-dpi 300 '))
			->willReturnSelf();
		$command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$command->expects($this->once())
			->method('getOutput')
			->willReturn('output');
		$command->expects($this->once())
			->method('getExitCode')
			->willReturn(0);

		$processor->ocrFile($file, new WorkflowSettings(), new GlobalSettings());
	}
}
