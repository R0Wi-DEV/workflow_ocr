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

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\ApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Wrapper\IAppApiWrapper;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ApiClientTest extends TestCase {
	private ApiClient $apiClient;
	private $appApiWrapper;
	private $logger;
	private $globalSettingsService;

	protected function setUp(): void {
		$this->appApiWrapper = $this->createMock(IAppApiWrapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->globalSettingsService = $this->createMock(IGlobalSettingsService::class);

		$settings = new GlobalSettings();
		$this->globalSettingsService->method('getGlobalSettings')->willReturn($settings);

		$this->apiClient = new ApiClient($this->appApiWrapper, $this->logger, $this->globalSettingsService);
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

	public function testProcessOcrUsesConfiguredTimeout(): void {
		$settings = new GlobalSettings();
		$settings->timeout = '120';

		$globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$globalSettingsService->method('getGlobalSettings')->willReturn($settings);

		$apiClient = new ApiClient($this->appApiWrapper, $this->logger, $globalSettingsService);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode(['result' => 'success']));

		$this->appApiWrapper->expects($this->once())
			->method('exAppRequest')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(function ($options) {
					return isset($options['timeout']) && $options['timeout'] === 120;
				})
			)
			->willReturn($response);

		$apiClient->processOcr('file_content', 'file.pdf', 'parameters');
	}

	public function testProcessOcrUsesDefaultTimeoutWhenNotConfigured(): void {
		$settings = new GlobalSettings();

		$globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$globalSettingsService->method('getGlobalSettings')->willReturn($settings);

		$apiClient = new ApiClient($this->appApiWrapper, $this->logger, $globalSettingsService);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode(['result' => 'success']));

		$this->appApiWrapper->expects($this->once())
			->method('exAppRequest')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(function ($options) {
					return isset($options['timeout']) && $options['timeout'] === 60;
				})
			)
			->willReturn($response);

		$apiClient->processOcr('file_content', 'file.pdf', 'parameters');
	}

	public function testGetTimeoutUsesDefaultWhenNotSet(): void {
		$settings = new GlobalSettings();

		$apiClient = $this->createMock(ApiClient::class);

		// instantiate a real ApiClient to test private method via reflection
		$appApiWrapper = $this->getMockBuilder('OCA\\WorkflowOcr\\Wrapper\\IAppApiWrapper')->disableOriginalConstructor()->getMock();
		$logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();
		$globalSettingsService = $this->getMockBuilder('OCA\\WorkflowOcr\\Service\\IGlobalSettingsService')->getMock();

		$realClient = new ApiClient($appApiWrapper, $logger, $globalSettingsService);

		$ref = new \ReflectionClass($realClient);
		$method = $ref->getMethod('getTimeout');
		$method->setAccessible(true);

		$this->assertEquals(60, $method->invoke($realClient, $settings));
	}

	public function testGetTimeoutParsesValuesCorrectly(): void {
		$appApiWrapper = $this->getMockBuilder('OCA\\WorkflowOcr\\Wrapper\\IAppApiWrapper')->disableOriginalConstructor()->getMock();
		$logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();
		$globalSettingsService = $this->getMockBuilder('OCA\\WorkflowOcr\\Service\\IGlobalSettingsService')->getMock();

		$realClient = new ApiClient($appApiWrapper, $logger, $globalSettingsService);
		$ref = new \ReflectionClass($realClient);
		$method = $ref->getMethod('getTimeout');
		$method->setAccessible(true);

		$settings = new GlobalSettings();
		$settings->timeout = '';
		$this->assertEquals(60, $method->invoke($realClient, $settings));

		$settings->timeout = '120';
		$this->assertEquals(120, $method->invoke($realClient, $settings));

		$settings->timeout = '0';
		$this->assertEquals(60, $method->invoke($realClient, $settings));

		$settings->timeout = '-10';
		$this->assertEquals(60, $method->invoke($realClient, $settings));

		$settings->timeout = '3600';
		$this->assertEquals(3600, $method->invoke($realClient, $settings));
	}
}
