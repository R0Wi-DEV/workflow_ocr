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

/**
 * Interface for a shell commandline.
 */
interface ICommand {
	/**
	 * @param string $command the command or full command string to execute,
	 * like 'gzip' or 'gzip -d'.  You can still call addArg() to add more
	 * arguments to the command. If $escapeCommand was set to true, the command
	 * gets escaped with escapeshellcmd().
	 * @return static for method chaining
	 */
	public function setCommand(string $command) : ICommand;
	
	/**
	 * @param string|resource $stdIn If set, the string will be piped to the
	 * command via standard input. This enables the same functionality as
	 * piping on the command line. It can also be a resource like a file
	 * handle or a stream in which case its content will be piped into the
	 * command like an input redirection.
	 * @return static for method chaining
	 */
	public function setStdIn(string $stdIn) : ICommand;
	
	/**
	 * Execute the command
	 *
	 * @return bool whether execution was successful. If `false`, error details
	 * can be obtained from getError(), getStdErr() and getExitCode().
	 */
	public function execute() : bool;
	
	/**
	 * @param bool $trim whether to `trim()` the return value. The default is `true`.
	 * @return string the command output (stdout). Empty if none.
	 */
	public function getOutput(bool $trim = true) : string;
	
	/**
	 * @param bool $trim whether to `trim()` the return value. The default is `true`.
	 * @return string the error message, either stderr or an internal message.
	 * Empty string if none.
	 */
	public function getError(bool $trim = true) : string;
	 
	/**
	 * @param bool $trim whether to `trim()` the return value. The default is `true`.
	 * @return string the stderr output. Empty if none.
	 */
	public function getStdErr(bool $trim = true) : string;
	
	/**
	 * @return int|null the exit code or null if command was not executed yet
	 */
	public function getExitCode();
}
