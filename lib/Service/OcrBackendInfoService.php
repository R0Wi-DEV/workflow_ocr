<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

namespace OCA\WorkflowOcr\Service;

use OCA\WorkflowOcr\Exception\CommandException;
use OCA\WorkflowOcr\Wrapper\ICommand;
use Psr\Log\LoggerInterface;

class OcrBackendInfoService implements IOcrBackendInfoService {
	/** @var ICommand */
	private $command;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(ICommand $command, LoggerInterface $logger) {
		$this->command = $command;
		$this->logger = $logger;
	}

	public function getInstalledLanguages() : array {
		$commandStr = 'tesseract --list-langs';
		$this->command->setCommand($commandStr);

		$success = $this->command->execute();
		$errorOutput = $this->command->getError();
		$stdErr = $this->command->getStdErr();
		$exitCode = $this->command->getExitCode();

		if (!$success) {
			throw new CommandException('Exited abnormally with exit-code ' . $exitCode . '. Message: ' . $errorOutput . ' ' . $stdErr, $commandStr);
		}

		if ($stdErr !== '' || $errorOutput !== '') {
			$this->logger->warning('Tesseract list languages succeeded with warning(s): {stdErr}, {errorOutput}', [
				'stdErr' => $stdErr,
				'errorOutput' => $errorOutput
			]);
		}

		$installedLangsStr = $this->command->getOutput();

		if (!$installedLangsStr) {
			throw new CommandException('No output produced', $commandStr);
		}

		$lines = explode("\n", $installedLangsStr);
		$arr = array_filter(
			array_slice($lines, 1), // Skip tesseract header line
			fn ($line) => $line !== 'osd' // Also skip "osd" (OSD is not a language)
		);
		return array_values($arr);
	}
}
