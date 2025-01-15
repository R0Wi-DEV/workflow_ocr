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

namespace OCA\WorkflowOcr\Tests\Unit\Helper;

use OCA\WorkflowOcr\Helper\ProcessingFileAccessor;
use PHPUnit\Framework\TestCase;

class ProcessingFileAccessorTest extends TestCase {
	public function testSingleton() {
		$o1 = ProcessingFileAccessor::getInstance();
		$o2 = ProcessingFileAccessor::getInstance();

		$this->assertTrue($o1 === $o2);
	}
	
	public function testGetSet() {
		$o = ProcessingFileAccessor::getInstance();
		$o ->setCurrentlyProcessedFilePath('/someuser/files/somefile.pdf');
		$this->assertEquals('/someuser/files/somefile.pdf', $o->getCurrentlyProcessedFilePath());
		$o->setCurrentlyProcessedFilePath(null);
	}
}
