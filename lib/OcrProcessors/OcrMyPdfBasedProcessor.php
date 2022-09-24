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
use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

abstract class OcrMyPdfBasedProcessor implements IOcrProcessor {
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
		'ru' => 'rus',
		'chi' => 'chi_sim',
		'est' => 'est',
		'slk' => 'slk'
	];

	/** @var ICommand */
	private $command;

	/** @var LoggerInterface */
	private $logger;

	/** @var ISidecarFileAccessor */
	private $sidecarFileAccessor;

	public function __construct(ICommand $command, LoggerInterface $logger, ISidecarFileAccessor $sidecarFileAccessor) {
		$this->command = $command;
		$this->logger = $logger;
		$this->sidecarFileAccessor = $sidecarFileAccessor;
	}

	public function ocrFile(File $file, WorkflowSettings $settings, GlobalSettings $globalSettings): OcrProcessorResult {
		$commandStr = 'ocrmypdf ' . $this->getCommandlineArgs($settings, $globalSettings) . ' - - | cat';

		$inputFileContent = $file->getContent();

		$this->command
			->setCommand($commandStr)
			->setStdIn($inputFileContent);

		$this->logger->debug('Running command: ' . $commandStr);

		$success = $this->command->execute();
		$errorOutput = $this->command->getError();
		$stdErr = $this->command->getStdErr();
		$exitCode = $this->command->getExitCode();

		if (!$success) {
			throw new OcrNotPossibleException('OCRmyPDF exited abnormally with exit-code ' . $exitCode . '. Message: ' . $errorOutput . ' ' . $stdErr);
		}

		if ($stdErr !== '' || $errorOutput !== '') {
			// Log warning if ocrmypdf wrote a warning to the stderr
			$this->logger->warning('OCRmyPDF succeeded with warning(s): {stdErr}, {errorOutput}', [
				'stdErr' => $stdErr,
				'errorOutput' => $errorOutput
			]);
		}

		$ocrFileContent = $this->command->getOutput();

		if (!$ocrFileContent) {
			throw new OcrNotPossibleException('OCRmyPDF did not produce any output');
		}

		$recognizedText = $this->sidecarFileAccessor->getSidecarFileContent();

		if (!$recognizedText) {
			$this->logger->info('Temporary sidecar file at \'{path}\' was empty', ['path' => $this->sidecarFileAccessor->getOrCreateSidecarFile()]);
		}

		$this->logger->debug("OCR processing was successful");

		return new OcrProcessorResult($ocrFileContent, "pdf", $recognizedText);
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


	private function getCommandlineArgs(WorkflowSettings $settings, GlobalSettings $globalSettings): string {
		// Default setting is quiet with skip-text
		$args = ['-q', '--skip-text'];

		// Language settings
		if ($settings->getLanguages()) {
			$langStr = Chain::create($settings->getLanguages())
				->map(function ($langCode) {
					return self::$langMapping[(string)$langCode] ?? null;
				})
				->filter(function ($l) {
					return $l !== null;
				})
				->join('+');
			$args[] = "-l $langStr";
		}

		// Remove background option (NOTE :: this is incompatible with redo-ocr, so if we
		// decide to make this configurable, make it exclusive against each other!)
		if ($settings->getRemoveBackground()) {
			$args[] = '--remove-background';
		}

		// Number of CPU's to be used
		$processorCount = intval($globalSettings->processorCount);
		if ($processorCount > 0) {
			$args[] = '-j ' . $processorCount;
		}

		// Save recognized text in tempfile
		$sidecarFilePath = $this->sidecarFileAccessor->getOrCreateSidecarFile();
		if ($sidecarFilePath) {
			$args[] = '--sidecar ' . $sidecarFilePath;
		}

		$resultArgs = array_merge($args, $this->getAdditionalCommandlineArgs($settings, $globalSettings));

		return implode(' ', $resultArgs);
	}
}
