<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\SetupChecks;

use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class OcrMyPdfCheck implements ISetupCheck {
	public function __construct(
		private IL10N $l10n,
		private ICommand $command,
		private IOcrBackendInfoService $ocrBackendInfoService,
		private IApiClient $apiClient,
	) {
	}

	public function getCategory(): string {
		return 'system';
	}

	public function getName(): string {
		return $this->l10n->t('Is OCRmyPDF installed');
	}

	public function run(): SetupResult {
		if ($this->ocrBackendInfoService->isRemoteBackend()) {
			return $this->apiClient->heartbeat() ?
				SetupResult::success($this->l10n->t('Workflow OCR Backend is installed.')) :
				SetupResult::warning($this->l10n->t('Workflow OCR Backend is installed but heartbeat failed.'));
		}
		$this->command->setCommand('ocrmypdf --version')->execute();
		if ($this->command->getExitCode() === 127) {
			return SetupResult::error($this->l10n->t('OCRmyPDF CLI is not installed.'), 'https://github.com/R0Wi-DEV/workflow_ocr?tab=readme-ov-file#backend');
		}
		if ($this->command->getExitCode() !== 0) {
			return SetupResult::error($this->l10n->t('OCRmyPDF CLI is not working correctly. Error was: %1$s', [$this->command->getError()]));
		}
		$versionOutput = $this->command->getOutput();
		return SetupResult::success($this->l10n->t('OCRmyPDF is installed and has version %1$s.', [$versionOutput]));
	}
}
