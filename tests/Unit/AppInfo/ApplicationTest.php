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

namespace OCA\WorkflowOcr\Tests\Unit\AppInfo;

use Exception;
use OCA\WorkflowOcr\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IBootContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
	
	public function testBootDoesNothingOnBootContext() {
		/** @var IBootContext|MockObject */
		$bootContext = $this->createMock(IBootContext::class);
		$bootContext->expects($this->never())
			->method($this->anything());
		
		$app = new Application();

		$app->boot($bootContext);
	}	

	public function testAutoloadExecutedOnBoot() {
		/** @var IBootContext|MockObject */
		$bootContext = $this->createMock(IBootContext::class);
		$app = new Application();

		$app->boot($bootContext);

		// PdfParser is one of the dependencies included by autoload.php
		$phpParserExists = class_exists('Smalot\PdfParser\Parser');
		$this->assertTrue($phpParserExists);
	}

	/**
	 * @dataProvider dataProvider_AutoLoadDoesNotExist
	 * @param $autoloadDirDoesNotExist Controls if we simulate that 'vendor' directory is missing or if we simulate that 'autoload.php' is missing
	 */
	public function testThrowsException_OnAutoloadNotFound(bool $autoloadDirDoesNotExist) {
		/** @var IBootContext|MockObject */
		$bootContext = $this->createMock(IBootContext::class);
		$app = new Application();
		
		$composerDirOriginal = Application::COMPOSER_DIR;
		$composerDirMoveTo = realpath($composerDirOriginal) . '_TMP';
		$autoloadOriginal = Application::COMPOSER_DIR . 'autoload.php';
		$autoloadMoveTo = $autoloadOriginal . '_TMP';

		if (!is_dir($composerDirOriginal) || !file_exists($autoloadOriginal)) {
			throw new \Exception('Composer dependencies must be installed to run this test');
		}

		/** @var \Exception */
		$ex = null;

		try{
			// Simulate non-existing composer dir by renaming the existing one
			if ($autoloadDirDoesNotExist) {
				rename($composerDirOriginal, $composerDirMoveTo);
			}
			// Simulate non-existing autoload.php
			else {
				rename($autoloadOriginal, $autoloadMoveTo);
			}
			
			$app->boot($bootContext);
		}
		catch(\Throwable $t){
			$ex = $t;
		}
		finally {
			if ($autoloadDirDoesNotExist && is_dir($composerDirMoveTo)) {
				rename($composerDirMoveTo, $composerDirOriginal);
			}
			else if (!$autoloadDirDoesNotExist && file_exists($autoloadMoveTo)) {
				rename($autoloadMoveTo, $autoloadOriginal);
			}
		}

		$this->assertInstanceOf(\Exception::class, $ex);
		$this->assertStringContainsString('Cannot include autoload', $ex->getMessage());
	}

	public function dataProvider_AutoLoadDoesNotExist() {
		return [
			[true],
			[false]
		];
	}
}
