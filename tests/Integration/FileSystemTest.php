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
use OCA\WorkflowOcr\Tests\TestUtils;
use OCA\WorkflowOcr\Wrapper\Filesystem;
use PHPUnit\Framework\TestCase;

class FileSystemTest extends TestCase {
	
	/** @var TestUtils */
	private $testUtils;

	protected function setUp() : void {
		parent::setUp();
		$this->testUtils = new TestUtils();
	}

	public function testInit() {
		$user = 'mytestuser';
		$pw = 'myuserspw';
		$path = '/mytestuser/files';
		/** @var \OCP\IUser */
		$userObject = null;

		try {
			$userObject = $this->testUtils->createUser($user, $pw);

			$fileSystem = new Filesystem();
			$fileSystem->init($user, $path);
			$this->assertTrue(\OC\Files\Filesystem::$loaded);
		} finally {
			if ($userObject && !$userObject->delete()) {
				throw new Exception("Could not delete user " . $user);
			}
		}
	}
}
