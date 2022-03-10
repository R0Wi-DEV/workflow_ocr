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

namespace OCA\WorkflowOcr\OcrProcessors;

use Cocur\Chain\Chain;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\Wrapper\ICommand;
use Psr\Log\LoggerInterface;

class ImageOcrProcessor implements IOcrProcessor {
	/** @var array
	 * Mapping for VUE frontend lang settings.
	 * See also https://github.com/tesseract-ocr/tesseract/blob/main/doc/tesseract.1.asc#languages
	 */
	private static $langMapping = [
		'de' => 'deu',
		'en' => 'eng',
		'fr' => 'fra',
		'it' => 'ita',
		'es' => 'spa',
		'pt' => 'por',
		'ru' => 'rus'
	];

	/** @var ICommand */
	private $command;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(ICommand $command, LoggerInterface $logger) {
		$this->command = $command;
		$this->logger = $logger;
	}

	public function ocrFile(string $fileContent, WorkflowSettings $settings, GlobalSettings $globalSettings): string {
		$commandStr = 'convert - PDF:-';

		$this->command
			->setCommand($commandStr)
			->setStdIn($fileContent);

		$this->logger->debug('Running command: ' . $commandStr);

		$success = $this->command->execute();
		$errorOutput = $this->command->getError();
		$stdErr = $this->command->getStdErr();
		$exitCode = $this->command->getExitCode();

		if (!$success) {
			throw new OcrNotPossibleException('convert exited abnormally with exit-code ' . $exitCode . '. Message: ' . $errorOutput . ' ' . $stdErr);
		}

		if ($stdErr !== '' || $errorOutput !== '') {
			// Log warning if convert wrote a warning to the stderr
			$this->logger->warning('convert succeeded with warning(s): {stdErr}, {errorOutput}', [
				'stdErr' => $stdErr,
				'errorOutput' => $errorOutput
			]);
		}

		$ocrFileContent = $this->command->getOutput();

		if (!$ocrFileContent) {
			throw new OcrNotPossibleException('convert did not produce any output');
		}

		$this->logger->debug("convert was successful");

		return $ocrFileContent;
	}
}
