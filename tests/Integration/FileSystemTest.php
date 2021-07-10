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

use OCA\WorkflowOcr\Wrapper\Filesystem;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class FileSystemTest extends TestCase {
	use UserTrait;

	private const USER = 'mytestuser';

	protected function setUp() : void {
		parent::setUp();
		$this->createUser(self::USER, 'pass');
		$this->loginAsUser(self::USER);
	}

	public function testInit() {
		$path = '/mytestuser/files';

		$fileSystem = new Filesystem();
		$fileSystem->init(self::USER, $path);
		$this->assertTrue(\OC\Files\Filesystem::$loaded);
	}
}
