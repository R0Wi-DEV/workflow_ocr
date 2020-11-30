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

namespace OCA\WorkflowOcr\Tests\Integration;

use Exception;
use OC\Files\View;
use OCA\WorkflowOcr\Tests\TestUtils;
use OCA\WorkflowOcr\Wrapper\ViewWrapper;
use Test\TestCase;

/**
 * @group DB
 */
class ViewWrapperTest extends TestCase {
	
	/** @var TestUtils */
	private $testUtils;

	protected function setUp() : void {
		parent::setUp();
		$this->testUtils = new TestUtils();
	}

	/**
	 * @dataProvider dataProvider_FilePutContents
	 */
	public function testFilePutContents(string $filename, bool $expectedResult) {
		$user = 'mytestuser';
		$pw = 'myuserspw';
		$path = '/mytestuser/files';
		$content = 'hello world';

		/** @var \OCP\IUser */
		$userObject = null;

		try {
			$userObject = $this->testUtils->createUser($user, $pw);
			$this->loginAsUser($user);

			$viewWrapper = new ViewWrapper($path);

			$result = $viewWrapper->file_put_contents($filename, $content);
			$this->assertEquals($expectedResult, $result);

			// If we expect that we can write to the file we should
			// be able to read the file afterwards
			if ($expectedResult) {
				$ncView = new View($path);
				$readContent = $ncView->file_get_contents($filename);
				$this->assertEquals($content, $readContent);
			}
		} finally {
			if ($userObject) {
				$this->logout();
				if (!$userObject->delete()) {
					throw new Exception("Could not delete user " . $user);
				}
			}
		}
	}

	public function dataProvider_FilePutContents() {
		return [
			['testfile.txt', true],
			['this_is_invalid/..', false]
		];
	}
}
