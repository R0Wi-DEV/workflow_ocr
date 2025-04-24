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

namespace OCA\WorkflowOcr\OcrProcessors\Local;

use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorBase;
use OCA\WorkflowOcr\Wrapper\ICommand;
use Psr\Log\LoggerInterface;

abstract class OcrMyPdfBasedProcessor extends OcrProcessorBase {
	public function __construct(
		private ICommand $command,
		protected LoggerInterface $logger,
		private ISidecarFileAccessor $sidecarFileAccessor,
		private ICommandLineUtils $commandLineUtils,
	) {
		parent::__construct($logger);
	}

	protected function doOcrProcessing($fileResource, string $fileName, WorkflowSettings $settings, GlobalSettings $globalSettings): array {
		$additionalCommandlineArgs = $this->getAdditionalCommandlineArgs($settings, $globalSettings);
		$sidecarFile = $this->sidecarFileAccessor->getOrCreateSidecarFile();
		$commandStr = 'ocrmypdf ' . $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings, $sidecarFile, $additionalCommandlineArgs) . ' - - || exit $? ; cat';

		$inputFileContent = stream_get_contents($fileResource);

		$this->command
			->setCommand($commandStr)
			->setStdIn($inputFileContent);

		$this->logger->debug('Running command: ' . $commandStr);

		$success = $this->command->execute();
		$errorOutput = $this->command->getError();
		$stdErr = $this->command->getStdErr();
		$exitCode = $this->command->getExitCode();

		if (!$success) {
			return [false, null, null, $exitCode, $errorOutput . ' ' . $stdErr];
		}

		if ($stdErr !== '' || $errorOutput !== '') {
			// Log warning if ocrmypdf wrote a warning to the stderr
			$this->logger->warning('OCRmyPDF succeeded with warning(s): {stdErr}, {errorOutput}', [
				'stdErr' => $stdErr,
				'errorOutput' => $errorOutput
			]);
		}

		$ocrFileContent = $this->command->getOutput();
		$recognizedText = $this->sidecarFileAccessor->getSidecarFileContent();

		return [true, $ocrFileContent, $recognizedText, $exitCode, null];
	}

	/**
	 * Can be overwritten in subclasses to provide additional parameters, which will be appended to ocrmypdf commandline.
	 * @param WorkflowSettings $settings
	 * @param GlobalSettings $globalSettings
	 * @return array of strings
	 */
	protected function getAdditionalCommandlineArgs(WorkflowSettings $settings, GlobalSettings $globalSettings): array {
		return [];
	}
}
