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

namespace OCA\WorkflowOcr\Tests\Integration\TestUtils;

use OCA\WorkflowOcr\OcrProcessors\Remote\Client\ApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Wrapper\IAppApiWrapper;
use Psr\Log\LoggerInterface;

/**
 * Recorder for ApiClient - tracks requests
 */
class IntegrationTestApiClient extends ApiClient {
	private array $requests = [];
	private array $responses = [];

	public function __construct(
		private IAppApiWrapper $appApiWrapper,
		private LoggerInterface $logger,
		private IGlobalSettingsService $globalSettingsService,
	) {
		parent::__construct($appApiWrapper, $logger, $globalSettingsService);
	}

	public function processOcr($file, string $fileName, string $ocrMyPdfParameters): OcrResult|ErrorResult {
		$this->requests[] = [
			'file' => $file,
			'fileName' => $fileName,
			'ocrMyPdfParameters' => $ocrMyPdfParameters
		];
		$result = parent::processOcr($file, $fileName, $ocrMyPdfParameters);
		$this->responses[] = $result;
		return $result;
	}

	public function getRequests(): array {
		return $this->requests;
	}

	public function getResponses(): array {
		return $this->responses;
	}

	public function reset(): void {
		$this->requests = [];
		$this->responses = [];
	}
}
