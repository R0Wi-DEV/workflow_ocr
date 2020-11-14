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

namespace OCA\WorkflowOcr\Tests\Unit\Wrapper;

use OCA\WorkflowOcr\Wrapper\CommandWrapper;
use PHPUnit\Framework\TestCase;

class CommandWrapperTest extends TestCase {
	public function testWrappingPositiveCommand() {
		$cmd = new CommandWrapper();
		$cmd->setCommand('cat')
			->setStdIn('hello');
		$this->assertTrue($cmd->execute());
		$this->assertEquals('hello', $cmd->getOutput());
		$this->assertEquals(0, $cmd->getExitCode());
	}

	public function testWrappingNegativeCommand() {
		$cmd = new CommandWrapper();
		$cmd->setCommand('echo hello 1>&2');
		$cmd->execute();
		$this->assertEquals('hello', $cmd->getStdErr());
		$this->assertEquals('', $cmd->getError());
	}
}
