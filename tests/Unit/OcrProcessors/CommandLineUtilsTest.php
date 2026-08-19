<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Robin Windey <ro.windey@gmail.com>
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

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\CommandLineUtils;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CommandLineUtilsTest extends TestCase {
	/** @var IOcrBackendInfoService|MockObject */
	private $ocrBackendInfoService;
	/** @var LoggerInterface|MockObject */
	private $logger;
	private CommandLineUtils $commandLineUtils;

	protected function setUp(): void {
		$this->ocrBackendInfoService = $this->createMock(IOcrBackendInfoService::class);
		$this->ocrBackendInfoService->method('isRemoteBackend')->willReturn(false);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->commandLineUtils = new CommandLineUtils($this->ocrBackendInfoService, $this->logger);
	}

	public function testValidLanguagesAreIncludedInCommandline(): void {
		$settings = new WorkflowSettings('{"languages":["eng","deu"]}');
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		$this->assertStringContainsString('--language eng+deu', $result);
	}

	/**
	 * Defense-in-depth regression test for the OS command injection reported via
	 * `languages`: even if a WorkflowSettings instance somehow carries a malicious
	 * language value (e.g. a workflow stored before input validation was added),
	 * CommandLineUtils must never let it reach the shell command string.
	 */
	public function testMaliciousLanguageValueIsFilteredOutOfCommandline(): void {
		$settings = $this->createMock(WorkflowSettings::class);
		$settings->method('getLanguages')->willReturn(['eng', 'x ; id > /tmp/pwned ; echo z']);
		$settings->method('getOcrMode')->willReturn(WorkflowSettings::OCR_MODE_SKIP_TEXT);
		$settings->method('getRemoveBackground')->willReturn(false);
		$settings->method('getCustomCliArgs')->willReturn('');
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		$this->assertStringContainsString('--language eng', $result);
		$this->assertStringNotContainsString(';', $result);
		$this->assertStringNotContainsString('id >', $result);
	}
}
