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

namespace OCA\WorkflowOcr\Tests\Integration;

use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Operation;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCP\AppFramework\App;
use OCP\WorkflowEngine\IManager;
use Psr\Container\ContainerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class OcrBackendServiceTest extends TestCase {
	private const USER = 'admin';
	private const PASS = 'admin';

	private ContainerInterface $container;
	private Manager $manager;
	private IntegrationTestApiClient $apiClient;
	private ScopeContext $context;
	private $operationClass = Operation::class;

	protected function setUp(): void {
		parent::setUp();

		$app = new App(Application::APP_NAME);
		$this->container = $app->getContainer();
		$this->manager = $this->container->get(Manager::class);
		$this->apiClient = $this->container->get(IntegrationTestApiClient::class);
		$this->context = new ScopeContext(IManager::SCOPE_ADMIN);

		$this->overwriteService(IApiClient::class, $this->apiClient);

		if (!$this->checkOcrBackendInstalled()) {
			$this->markTestSkipped('OCR Backend is not installed');
			return;
		}

		$this->deleteTestFileIfExists();
		$this->deleteOperation();
	}

	protected function tearDown(): void {
		if (!$this->checkOcrBackendInstalled()) {
			return;
		}

		$this->deleteTestFileIfExists();
		$this->deleteOperation();
		parent::tearDown();
	}

	/**
	 * Full test case for registering new OCR Workflow, uploading file and
	 * processing it via OCR Backend Service.
	 */
	public function testWorkflowOcrBackendService() {
		$this->addOperation();
		$this->uploadTestFile();
		$this->runNextcloudCron();

		$requests = $this->apiClient->getRequests();
		$this->assertCount(1, $requests);
		$this->assertTrue(strpos($requests[0]['fileName'], 'document-ready-for-ocr.pdf') >= 0);
		$this->assertTrue($requests['ocrMyPdfParameters'] === '--skip-text');

		$responses = $this->apiClient->getResponses();
		$this->assertCount(1, $responses);
		$this->assertTrue($responses[0] instanceof OcrResult);
		/** @var OcrResult */
		$ocrResult = $responses[0];
		$this->assertEquals($requests[0]['fileName'], $ocrResult->getFileName());
		$this->assertEquals('application/pdf', $ocrResult->getContentType());
		$this->assertTrue(strpos($ocrResult->getRecognizedText(), 'This document is ready for OCR') >= 0);
	}

	private function addOperation() {
		$name = '';
		$checks = [
			0 =>
			 [
			 	'class' => 'OCA\\WorkflowEngine\\Check\\FileMimeType',
			 	'operator' => 'is',
			 	'value' => 'application/pdf',
			 	'invalid' => false,
			 ]
		];
		$operation = '';
		$entity = "OCA\WorkflowEngine\Entity\File";
		$events = [
			0 => '\\OCP\\Files::postCreate',
		];
		$operation = $this->manager->addOperation($this->operationClass, $name, $checks, $operation, $this->context, $entity, $events);
		$this->clearApcu();
	}

	private function deleteOperation() {
		$operations = $this->manager->getOperations($this->operationClass, $this->context);
		foreach ($operations as $operation) {
			$this->manager->deleteOperation($operation['id'], $this->context);
		}
		
	}

	private function uploadTestFile() {
		$webdav_url = 'http://localhost/remote.php/dav/files/' . self::USER . '/';
		$local_file = __DIR__ . '/testdata/document-ready-for-ocr.pdf';
		$file = fopen($local_file, 'r');

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $webdav_url . basename($local_file));
		curl_setopt($ch, CURLOPT_USERPWD, self::USER . ':' . self::PASS);
		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_INFILE, $file);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($local_file));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);

		if (curl_errno($ch)) {
			$this->fail('Error: ' . curl_error($ch));
		}

		curl_close($ch);
		fclose($file);
	}

	private function deleteTestFileIfExists() {
		$webdav_url = 'http://localhost/remote.php/dav/files/' . self::USER . '/';
		$local_file = __DIR__ . '/testdata/document-ready-for-ocr.pdf';

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $webdav_url . basename($local_file));
		curl_setopt($ch, CURLOPT_USERPWD, self::USER . ':' . self::PASS);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);

		if (curl_errno($ch)) {
			$this->fail('Error: ' . curl_error($ch));
		}

		curl_close($ch);
	}

	private function runNextcloudCron() {
		global $argv;
		$argv = [];
		require __DIR__ . '/../../../../cron.php';
	}

	private function clearApcu() {
		if (function_exists('apcu_clear_cache')) {
			apcu_clear_cache();
		}
	}

	private function checkOcrBackendInstalled() : bool {
		$ocrBackendInfoService = $this->container->get(IOcrBackendInfoService::class);
		return $ocrBackendInfoService->isRemoteBackend();
	}
}
