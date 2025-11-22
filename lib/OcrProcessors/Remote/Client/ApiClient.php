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

namespace OCA\WorkflowOcr\OcrProcessors\Remote\Client;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Wrapper\IAppApiWrapper;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ApiClient implements IApiClient {
	private const DEFAULT_TIMEOUT = 60;
	public function __construct(
		private IAppApiWrapper $appApiWrapper,
		private LoggerInterface $logger,
		private IGlobalSettingsService $globalSettingsService,
	) {
	}

	public function processOcr($file, string $fileName, string $ocrMyPdfParameters): OcrResult|ErrorResult {
		$settings = $this->globalSettingsService->getGlobalSettings();
		$timeout = $this->getTimeout($settings);
		$options = [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $file,
					'filename' => $fileName
				],
				[
					'name' => 'ocrmypdf_parameters',
					'contents' => $ocrMyPdfParameters
				]
			],
			'timeout' => $timeout
		];

		$response = $this->exAppRequest('/process_ocr', $options, 'POST');

		switch ($response->getStatusCode()) {
			case 200:
				$class = OcrResult::class;
				break;
			case 500:
				$class = ErrorResult::class;
				break;
			default:
				$this->logger->error('Unexpected response code', ['response' => $response, 'body' => $response->getBody()]);
				throw new RuntimeException('Unexpected response code');
		}

		return ObjectSerializer::deserialize(json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR), $class);
	}

	public function getLanguages(): array {
		$response = $this->exAppRequest('/installed_languages', null, 'GET', true);
		return json_decode($response->getBody(), true);
	}

	public function heartbeat(): bool {
		$response = $this->exAppRequest('/heartbeat', null, 'GET', false);
		return $response->getStatusCode() === 200;
	}

	private function exAppRequest(string $path, ?array $options, string $method, bool $throwIfResponseCodeNot200 = false): IResponse {
		$this->logger->debug('Executing request', ['path' => $path, 'options' => $options, 'method' => $method]);
		$response = $this->appApiWrapper->exAppRequest(
			Application::APP_BACKEND_NAME,
			$path,
			null,
			$method,
			[],
			$options ?? []
		);
		$this->logger->debug('Response received', ['path' => $path, 'response' => $response]);

		if (is_array($response) || ($throwIfResponseCodeNot200 && $response->getStatusCode() !== 200)) {
			$this->logger->error('Request failed', ['path' => $path, 'response' => $response]);
			throw new RuntimeException('Request failed');
		}

		return $response;
	}

	/**
	 * Parse timeout from GlobalSettings and return the effective timeout
	 */
	private function getTimeout(object $settings): int {
		$timeout = self::DEFAULT_TIMEOUT;
		if (isset($settings->timeout) && $settings->timeout !== null && $settings->timeout !== '') {
			$timeoutInt = (int)$settings->timeout;
			if ($timeoutInt > 0) {
				$timeout = $timeoutInt;
			}
		}
		return $timeout;
	}
}
