<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Robin Windey <ro.windey@gmail.com>
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

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use Psr\Log\LoggerInterface;

class CommandLineUtils implements ICommandLineUtils {
	private static $ocrModeToCmdParameterMapping = [
		WorkflowSettings::OCR_MODE_SKIP_TEXT => '--skip-text',
		WorkflowSettings::OCR_MODE_REDO_OCR => '--redo-ocr',
		WorkflowSettings::OCR_MODE_FORCE_OCR => '--force-ocr',
		WorkflowSettings::OCR_MODE_SKIP_FILE => '' // This is the ocrmypdf default behaviour
	];

	public function __construct(
		private IOcrBackendInfoService $ocrBackendInfoService,
		private LoggerInterface $logger,
	) {
	}

	public function getCommandlineArgs(WorkflowSettings $settings, GlobalSettings $globalSettings, ?string $sidecarFile = null, array $additionalCommandlineArgs = []): string {
		$isLocalExecution = !$this->ocrBackendInfoService->isRemoteBackend();
		
		// Default setting is quiet
		$args = $isLocalExecution ? ['-q'] : [];

		// OCR mode ('--skip-text', '--redo-ocr', '--force-ocr' or empty)
		$args[] = self::$ocrModeToCmdParameterMapping[$settings->getOcrMode()];

		// Language settings
		if ($settings->getLanguages()) {
			$langStr = implode('+', $settings->getLanguages());
			$args[] = "--language $langStr";
		}

		// Remove background option (NOTE :: this is incompatible with redo-ocr, so
		// we have to make it exclusive against each other!)
		if ($settings->getRemoveBackground()) {
			if ($settings->getOcrMode() === WorkflowSettings::OCR_MODE_REDO_OCR) {
				$this->logger->warning('--remove-background is incompatible with --redo-ocr, ignoring');
			} else {
				$args[] = '--remove-background';
			}
		}

		// Number of CPU's to be used
		$processorCount = intval($globalSettings->processorCount);
		if ($processorCount > 0) {
			$args[] = '--jobs ' . $processorCount;
		}

		if ($isLocalExecution && $sidecarFile !== null) {
			// Save recognized text in tempfile
			$args[] = '--sidecar ' . $sidecarFile;
		}

		$resultArgs = array_filter(array_merge(
			$args,
			$additionalCommandlineArgs,
			[$this->escapeCustomCliArgs($settings->getCustomCliArgs())]
		), fn ($arg) => !empty($arg));

		return implode(' ', $resultArgs);
	}

	private function escapeCustomCliArgs(string $customCliArgs): string {
		$customCliArgs = str_replace('&&', '', $customCliArgs);
		$customCliArgs = str_replace(';', '', $customCliArgs);
		return $customCliArgs;
	}
}
