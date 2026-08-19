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
	/**
	 * Allow-list for tesseract/ocrmypdf language codes (e.g. 'eng', 'chi_sim', 'script/Latin').
	 * The language values are concatenated into the ocrmypdf command line, which is executed
	 * as a shell string, so anything not matching this pattern must never reach it. This is
	 * enforced again here (in addition to WorkflowSettings validation) as defense in depth,
	 * e.g. for workflows that were stored before this validation was introduced.
	 */
	private const LANGUAGE_CODE_REGEX = '/^[A-Za-z][A-Za-z0-9_\/]{0,31}$/';

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
		$languages = $this->filterValidLanguages($settings->getLanguages());
		if ($languages) {
			$langStr = implode('+', $languages);
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
		$processorCount = $globalSettings->processorCount ?? 0;
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

	/**
	 * Filters out any language value that does not match the allow-listed language
	 * code pattern. This value is concatenated into a shell command, so entries that
	 * don't look like a valid tesseract language code must never be passed through.
	 *
	 * @param array $languages
	 * @return array
	 */
	private function filterValidLanguages(array $languages): array {
		return array_values(array_filter($languages, function ($language) {
			if (!is_string($language) || preg_match(self::LANGUAGE_CODE_REGEX, $language) !== 1) {
				$this->logger->warning('Ignoring invalid OCR language value: ' . var_export($language, true));
				return false;
			}
			return true;
		}));
	}
}
