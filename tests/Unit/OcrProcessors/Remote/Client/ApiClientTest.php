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
namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors\Remote\Client;

use OCA\WorkflowOcr\OcrProcessors\Remote\Client\ApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Wrapper\IAppApiWrapper;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ApiClientTest extends TestCase {
	private ApiClient $apiClient;
	private $appApiWrapper;
	private $logger;

	protected function setUp(): void {
		$this->appApiWrapper = $this->createMock(IAppApiWrapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->apiClient = new ApiClient($this->appApiWrapper, $this->logger);
	}

	public function testProcessOcrSuccess(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode(['result' => 'success']));

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$result = $this->apiClient->processOcr('file_content', 'file.pdf', 'parameters');
		$this->assertInstanceOf(OcrResult::class, $result);
	}

	public function testProcessOcrError(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(500);
		$response->method('getBody')->willReturn(json_encode(['error' => 'Internal Server Error']));

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$result = $this->apiClient->processOcr('file_content', 'file.pdf', 'parameters');
		$this->assertInstanceOf(ErrorResult::class, $result);
	}

	public function testProcessOcrUnexpectedResponse(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(404);
		$response->method('getBody')->willReturn('Not Found');

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$this->expectException(RuntimeException::class);
		$this->apiClient->processOcr('file_content', 'file.pdf', 'parameters');
	}

	public function testGetLanguages(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn(json_encode(['en', 'fr', 'de']));
		$response->method('getStatusCode')->willReturn(200);

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$languages = $this->apiClient->getLanguages();
		$this->assertIsArray($languages);
		$this->assertContains('en', $languages);
	}

	public function testGetLanguagesThrowsRuntimeExceptionOnResponseCodeNot200(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(500);

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$this->expectException(RuntimeException::class);
		$this->apiClient->getLanguages();
	}

	public function testHeartbeat(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$this->assertTrue($this->apiClient->heartbeat());
	}

	public function testHeartbeatFailure(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(500);

		$this->appApiWrapper->method('exAppRequest')->willReturn($response);

		$this->assertFalse($this->apiClient->heartbeat());
	}
}
