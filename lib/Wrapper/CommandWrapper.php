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

namespace OCA\WorkflowOcr\Wrapper;

use mikehaertl\shellcommand\Command;

class CommandWrapper implements ICommand {
	/** @var Command */
	private $command;

	public function __construct() {
		$this->command = new Command();
	}

	/**
	 * @inheritdoc
	 */
	public function setCommand(string $command) : ICommand {
		$this->command->setCommand($command);
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function setStdIn(string $stdIn) : ICommand {
		$this->command->setStdIn($stdIn);
		return $this;
	}
	
	/**
	 * @inheritdoc
	 */
	public function execute() : bool {
		return (bool)$this->command->execute();
	}
	
	/**
	 * @inheritdoc
	 */
	public function getOutput(bool $trim = true) : string {
		return (string)$this->command->getOutput($trim);
	}
	
	/**
	 * @inheritdoc
	 */
	public function getError(bool $trim = true) : string {
		return (string)$this->command->getError($trim);
	}

	/**
	 * @inheritdoc
	 */
	public function getStdErr(bool $trim = true) : string {
		return (string)$this->command->getStdErr($trim);
	}
	
	/**
	 * @inheritdoc
	 */
	public function getExitCode() {
		return $this->command->getExitCode();
	}
}
