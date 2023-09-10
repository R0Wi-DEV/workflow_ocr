<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Integration\Composer;

use OCP\Security\ISecureRandom;
use OCP\Server;
use Test\TestCase;

class AutoloaderTest extends TestCase {
	/** @var null|string */
	private $testClass = null;

	private static function getClassPath(string $class): string {
		return __DIR__ . '/../../../lib/' . $class . '.php';
	}

	protected function tearDown(): void {
		parent::tearDown();

		if ($this->testClass !== null && file_exists(self::getClassPath($this->testClass))) {
			unlink(self::getClassPath($this->testClass));
		}
	}

	public function testLoadDynamicClass(): void {
		$rand = Server::get(ISecureRandom::class);
		$className = ucfirst($rand->generate(10, ISecureRandom::CHAR_LOWER));
		$namespace = "OCA\\WorkflowOcr";

		file_put_contents(self::getClassPath($className), <<<FILE
<?php
namespace $namespace;
class $className {}
FILE);
		$this->testClass = $className;

		$this->assertTrue(class_exists($namespace . '\\' . $className));
	}
}
