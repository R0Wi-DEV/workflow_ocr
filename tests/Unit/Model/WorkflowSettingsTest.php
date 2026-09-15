<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkflowSettingsTest extends TestCase {
	#[DataProvider('dataProvider_testConstruction')]
	public function testWorkflowSettingsConstruction(string $json, bool $expectedRemoveBackground, array $expectedLangSettings) {
		$workflowSettings = new WorkflowSettings($json);
		$this->assertEquals($expectedRemoveBackground, $workflowSettings->getRemoveBackground());
		$this->assertEquals($expectedLangSettings, $workflowSettings->getLanguages());
	}

	public function testWorkflowSettingsConstructorThrowsInvalidArgumentExceptionOnInvalidJson() {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid JSON: "{"');
		new WorkflowSettings('{');
	}

	public function testCreateSidecarFileDefaultsToFalse() {
		$workflowSettings = new WorkflowSettings('{}');
		$this->assertFalse($workflowSettings->getCreateSidecarFile());
	}

	public function testCreateSidecarFileCanBeSetToTrue() {
		$workflowSettings = new WorkflowSettings('{"createSidecarFile":true}');
		$this->assertTrue($workflowSettings->getCreateSidecarFile());
	}

	public function testCreateSidecarFileCanBeSetToFalse() {
		$workflowSettings = new WorkflowSettings('{"createSidecarFile":false}');
		$this->assertFalse($workflowSettings->getCreateSidecarFile());
	}

	public function testSkipNotificationsOnInvalidPdfDefaultsToFalse() {
		$workflowSettings = new WorkflowSettings('{}');
		$this->assertFalse($workflowSettings->getSkipNotificationsOnInvalidPdf());
	}

	public function testSkipNotificationsOnInvalidPdfCanBeSetToTrue() {
		$workflowSettings = new WorkflowSettings('{"skipNotificationsOnInvalidPdf":true}');
		$this->assertTrue($workflowSettings->getSkipNotificationsOnInvalidPdf());
	}

	public function testSkipNotificationsOnInvalidPdfCanBeSetToFalse() {
		$workflowSettings = new WorkflowSettings('{"skipNotificationsOnInvalidPdf":false}');
		$this->assertFalse($workflowSettings->getSkipNotificationsOnInvalidPdf());
	}

	public function testSkipNotificationsOnEncryptedPdfDefaultsToFalse() {
		$workflowSettings = new WorkflowSettings('{}');
		$this->assertFalse($workflowSettings->getSkipNotificationsOnEncryptedPdf());
	}

	public function testSkipNotificationsOnEncryptedPdfCanBeSetToTrue() {
		$workflowSettings = new WorkflowSettings('{"skipNotificationsOnEncryptedPdf":true}');
		$this->assertTrue($workflowSettings->getSkipNotificationsOnEncryptedPdf());
	}

	public function testSkipNotificationsOnEncryptedPdfCanBeSetToFalse() {
		$workflowSettings = new WorkflowSettings('{"skipNotificationsOnEncryptedPdf":false}');
		$this->assertFalse($workflowSettings->getSkipNotificationsOnEncryptedPdf());
	}

	public static function dataProvider_testConstruction() {
		return [
			[
				'{"removeBackground":true,"languages":["eng","deu","spa","fra","ita"],"keepOriginalFileVersion":false}',
				true,
				['eng', 'deu', 'spa', 'fra', 'ita']
			],
			[
				'{"languages":["chi_sim","script/Latin"]}',
				false,
				['chi_sim', 'script/Latin']
			]
		];
	}

	#[DataProvider('dataProvider_testMaliciousLanguagesAreRejected')]
	public function testLanguagesContainingShellMetacharactersAreRejected(string $json) {
		// The whole 'languages' value must be rejected as soon as a single entry doesn't
		// look like a valid language code, since these values end up being concatenated
		// into a shell command.
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid value for setting \'languages\'');
		new WorkflowSettings($json);
	}

	#[DataProvider('dataProvider_testInvalidCustomCliArgsAreRejected')]
	public function testInvalidCustomCliArgsAreRejected(string $json) {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid value for setting \'customCliArgs\'');
		new WorkflowSettings($json);
	}

	public static function dataProvider_testInvalidCustomCliArgsAreRejected() {
		return [
			// Control characters (newline, carriage return, NUL, ...) have no place in a
			// commandline and could be used to obfuscate payloads
			[json_encode(['customCliArgs' => "--dpi 300\nid"])],
			[json_encode(['customCliArgs' => "--dpi 300\rid"])],
			[json_encode(['customCliArgs' => "--dpi 300\0id"])],
			// Way too long
			[json_encode(['customCliArgs' => str_repeat('a', 4097)])],
			// Wrong type
			['{"customCliArgs": 42}'],
			['{"customCliArgs": ["--dpi", "300"]}'],
		];
	}

	#[DataProvider('dataProvider_testValidCustomCliArgsAreAccepted')]
	public function testValidCustomCliArgsAreAccepted(string $customCliArgs) {
		$workflowSettings = new WorkflowSettings(json_encode(['customCliArgs' => $customCliArgs]));
		$this->assertEquals($customCliArgs, $workflowSettings->getCustomCliArgs());
	}

	public static function dataProvider_testValidCustomCliArgsAreAccepted() {
		return [
			[''],
			['--dpi 300'],
			['--output-type pdf'],
			['--rotate-pages-threshold 8'],
			['--title "My Document"'],
			[str_repeat('a', 4096)],
		];
	}

	public function testInvalidOcrModeIsRejected() {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid value for setting \'ocrMode\'');
		new WorkflowSettings('{"ocrMode": 42}');
	}

	public function testNonObjectJsonIsRejected() {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid JSON: "false"');
		new WorkflowSettings('false');
	}

	public static function dataProvider_testMaliciousLanguagesAreRejected() {
		return [
			['{"languages":["eng","x ; id > /tmp/pwned ; echo z"]}'],
			['{"languages":["eng","x && id"]}'],
			['{"languages":["eng","x | id"]}'],
			['{"languages":["eng","$(id)"]}'],
			['{"languages":["eng","`id`"]}'],
			['{"languages":["eng "]}'],
			['{"languages":[123]}'],
		];
	}

	/**
	 * Non-strict mode is used to deserialize already stored settings (e.g. when loading a
	 * workflow's settings for OCR processing). A value which fails validation must not abort
	 * construction there: the property should silently fall back to its default instead.
	 */
	public function testNonStrictModeFallsBackToDefaultOnInvalidCustomCliArgs() {
		$workflowSettings = new WorkflowSettings('{"customCliArgs": 42}', false);
		$this->assertEquals('', $workflowSettings->getCustomCliArgs());
	}

	public function testNonStrictModeFallsBackToDefaultOnInvalidOcrMode() {
		$workflowSettings = new WorkflowSettings('{"ocrMode": 42}', false);
		$this->assertEquals(WorkflowSettings::OCR_MODE_SKIP_TEXT, $workflowSettings->getOcrMode());
	}

	public function testNonStrictModeFallsBackToDefaultOnMaliciousLanguages() {
		$workflowSettings = new WorkflowSettings('{"languages":["eng","$(id)"]}', false);
		$this->assertEquals([], $workflowSettings->getLanguages());
	}

	public function testNonStrictModeStillAppliesValidValues() {
		$workflowSettings = new WorkflowSettings('{"customCliArgs": "--dpi 300", "ocrMode": ' . WorkflowSettings::OCR_MODE_FORCE_OCR . '}', false);
		$this->assertEquals('--dpi 300', $workflowSettings->getCustomCliArgs());
		$this->assertEquals(WorkflowSettings::OCR_MODE_FORCE_OCR, $workflowSettings->getOcrMode());
	}

	public function testNonStrictModeInvokesCallbackForEveryInvalidValue() {
		$seen = [];
		new WorkflowSettings(
			'{"customCliArgs": 42, "ocrMode": 42}',
			false,
			function (string $key, $value) use (&$seen) {
				$seen[$key] = $value;
			}
		);
		$this->assertEquals(['customCliArgs' => 42, 'ocrMode' => 42], $seen);
	}

	public function testNonStrictModeDoesNotInvokeCallbackForValidValues() {
		$called = false;
		new WorkflowSettings(
			'{"customCliArgs": "--dpi 300"}',
			false,
			function () use (&$called) {
				$called = true;
			}
		);
		$this->assertFalse($called);
	}

	public function testNonStrictModeStillThrowsOnNonObjectJson() {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid JSON: "false"');
		new WorkflowSettings('false', false);
	}
}
