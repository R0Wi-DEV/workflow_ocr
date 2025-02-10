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

use CurlHandle;
use DomainException;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;
use OCA\WorkflowOcr\Operation;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCP\AppFramework\App;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\WorkflowEngine\IManager;
use Psr\Container\ContainerInterface;
use Test\TestCase;

/**
 * Full test case for registering new OCR Workflow, uploading file and
 * processing it via OCR Backend Service.
 * @group DB
 */
class OcrBackendServiceTest extends TestCase {
	private ContainerInterface $container;
	private Manager $workflowEngineManager;
	private IntegrationTestApiClient $apiClient;
	private ScopeContext $context;
	private IConfig $config;

	private $operationClass = Operation::class;
	private $oldLogLevel;

	protected function setUp(): void {
		parent::setUp();

		$app = new App(Application::APP_NAME);
		$this->container = $app->getContainer();
		$this->workflowEngineManager = $this->container->get(Manager::class);
		$this->apiClient = $this->container->get(IntegrationTestApiClient::class);
		$this->config = $this->container->get(IConfig::class);
		$this->context = new ScopeContext(IManager::SCOPE_ADMIN);

		$this->overwriteService(IApiClient::class, $this->apiClient);

		$githubActionsJob = getenv('GITHUB_JOB');
		$isOcrBackendInstalled = $this->checkOcrBackendInstalled();

		if ($githubActionsJob === 'github-php-integrationtests' && !$isOcrBackendInstalled) {
			$this->fail('Running Github Actions Integrationtests but OCR Backend is not installed');
			return;
		}

		if (!$isOcrBackendInstalled) {
			$this->markTestSkipped('OCR Backend is not installed');
			return;
		}

		$this->setNextcloudLogLevel();
		$this->deleteTestFileIfExists();
		$this->deleteOperation();
	}

	protected function tearDown(): void {
		if (!$this->checkOcrBackendInstalled()) {
			return;
		}

		$this->deleteTestFileIfExists();
		$this->deleteOperation();
		$this->restoreNextcloudLogLevel();
		
		parent::tearDown();
	}

	public function testWorkflowOcrBackendService() {
		$this->addOperation();
		$this->uploadTestFile();
		$this->runOcrBackgroundJob();

		$requests = $this->apiClient->getRequests();
		$this->assertCount(1, $requests, 'Expected 1 OCR request');
		$request = $requests[0];
		$this->assertTrue(strpos($request['fileName'], 'document-ready-for-ocr.pdf') >= 0, 'Expected filename in request');
		$this->assertTrue($request['ocrMyPdfParameters'] === '--skip-text', 'Expected OCR parameters in request');

		$responses = $this->apiClient->getResponses();
		$this->assertCount(1, $responses, 'Expected 1 OCR response');
		$this->assertTrue($responses[0] instanceof OcrResult, 'Expected OcrResult instance, type is: ' . get_class($responses[0]));
		/** @var OcrResult */
		$ocrResult = $responses[0];
		$this->assertEquals($requests[0]['fileName'], $ocrResult->getFileName(), 'Expected filename in response to be equal to request');
		$this->assertEquals('application/pdf', $ocrResult->getContentType(), 'Expected content type in response');
		$this->assertTrue(strpos($ocrResult->getRecognizedText(), 'This document is ready for OCR') >= 0, 'Expected recognized text in response');
	}

	private function addOperation() {
		// NOTE :: we're creating the workflow operation via
		// REST API because if we'd use the manager directly, we'd
		// face some issues because of caching etc (test ist running
		// in another process than webserver ...)
		$url = $this->getNextcloudOcsApiUrl() . 'apps/workflowengine/api/v1/workflows/global?format=json';
		$json = '
		{
			"id":-1,
			"class":"' . str_replace('\\', '\\\\', $this->operationClass) . '",
			"entity":"OCA\\\\WorkflowEngine\\\\Entity\\\\File",
			"events":["\\\\OCP\\\\Files::postCreate"],
			"name":"",
			"checks":[
				{
					"class":"OCA\\\\WorkflowEngine\\\\Check\\\\FileMimeType",
					"operator":"is",
					"value":"application/pdf",
					"invalid":false
				}
			],
			"operation":"",
			"valid":true
		}';
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'OCS-APIREQUEST: true'
		]);

		$this->executeCurl($ch);
	}

	private function deleteOperation() {
		$operations = $this->workflowEngineManager->getOperations($this->operationClass, $this->context);
		foreach ($operations as $operation) {
			try {
				$this->workflowEngineManager->deleteOperation($operation['id'], $this->context);
			} catch (DomainException) {
				// ignore
			}
		}
		
	}

	private function uploadTestFile() {
		$localFile = $this->getTestFileReadyForOcr();
		$file = fopen($localFile, 'r');

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->getNextcloudWebdavUrl() . basename($localFile));
		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_INFILE, $file);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$this->executeCurl($ch);
		fclose($file);
	}

	private function deleteTestFileIfExists() {
		$localFile = $this->getTestFileReadyForOcr();

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->getNextcloudWebdavUrl() . basename($localFile));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$this->executeCurl($ch, [404]);
	}

	private function executeCurl(CurlHandle $ch, array $allowedNonSuccessResponseCodes = []) : string|bool {
		curl_setopt($ch, CURLOPT_USERPWD, $this->getNextcloudCredentials());

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			$this->fail('cURL Error: ' . curl_error($ch));
		}

		$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($responseCode >= 400 && !in_array($responseCode, $allowedNonSuccessResponseCodes)) {
			$responseBody = curl_multi_getcontent($ch);
			$this->fail('cURL HTTP Error ' . $responseCode . ': ' . $responseBody);
		}

		curl_close($ch);

		return $result;
	}

	private function runOcrBackgroundJob() {
		/** @var IJoblist */
		$jobList = $this->container->get(IJobList::class);
		$job = $jobList->getNext(false, [ProcessFileJob::class]);
		$this->assertNotNull($job);
		$job->start($jobList);
	}

	private function checkOcrBackendInstalled() : bool {
		$ocrBackendInfoService = $this->container->get(IOcrBackendInfoService::class);
		return $ocrBackendInfoService->isRemoteBackend();
	}

	private function getNextcloudWebdavUrl() : string {
		$port = getenv('NEXTCLOUD_PORT') ?: '80';
		$user = getenv('NEXTCLOUD_USER') ?: 'admin';
		return 'http://localhost:' . $port . '/remote.php/dav/files/' . $user . '/';
	}

	private function getNextcloudOcsApiUrl() : string {
		$port = getenv('NEXTCLOUD_PORT') ?: '80';
		return 'http://localhost:' . $port . '/ocs/v2.php/';
	}

	private function getNextcloudCredentials() : string {
		$user = getenv('NEXTCLOUD_USER') ?: 'admin';
		$pass = getenv('NEXTCLOUD_PASS') ?: 'admin';
		return $user . ':' . $pass;
	}

	private function getTestFileReadyForOcr() : string {
		return __DIR__ . '/testdata/document-ready-for-ocr.pdf';
	}

	private function setNextcloudLogLevel() : void {
		$this->oldLogLevel = $this->config->getSystemValue('loglevel', 3);
		$this->config->setSystemValue('loglevel', 0);
	}

	private function restoreNextcloudLogLevel() : void {
		$this->config->setSystemValue('loglevel', $this->oldLogLevel);
	}
}
