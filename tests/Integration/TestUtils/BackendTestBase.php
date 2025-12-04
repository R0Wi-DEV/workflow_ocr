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

use CurlHandle;
use DomainException;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Operation;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCA\WorkflowOcr\Tests\Integration\Notification\AppFake;
use OCA\WorkflowOcr\Wrapper\CommandWrapper;
use OCP\AppFramework\App;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\Log\IFileBased;
use OCP\Log\ILogFactory;
use OCP\WorkflowEngine\IManager;
use Psr\Container\ContainerInterface;
use Test\TestCase;

abstract class BackendTestBase extends TestCase {
	private IConfig $config;
	private ScopeContext $context;
	private Manager $workflowEngineManager;
	private $oldLogLevel;
	private $operationClass = Operation::class;
	private array $uploadedFiles = [];

	protected array $filesToDelete = [];
	protected ContainerInterface $container;
	protected AppFake $notificationReceiverApp;

	protected function setUp(): void {
		parent::setUp();

		$app = new App(Application::APP_NAME);
		$this->container = $app->getContainer();
		$this->config = $this->container->get(IConfig::class);
		$this->workflowEngineManager = $this->container->get(Manager::class);
		$this->context = new ScopeContext(IManager::SCOPE_ADMIN);
		$this->notificationReceiverApp = $this->container->get(AppFake::class);
		$this->notificationReceiverApp->resetNotifications();

		$this->setNextcloudLogLevel();
		$this->deleteOperation();
	}

	protected function tearDown(): void {
		$this->restoreNextcloudLogLevel();
		$this->deleteTestFilesIfExist();
		$this->deleteOperation();
		parent::tearDown();
	}

	// -----------------------------------------------------
	// Tests which can run on both local and remote backend
	// -----------------------------------------------------

	protected function runTestWorkflowOcrSkipsNotificationOnInvalidPdf() {
		$localFile = 'document-signed.pdf';
		$this->addOperation('application/pdf', json_encode(['skipNotificationsOnInvalidPdf' => true]));
		$this->uploadTestFile($localFile);
		$this->runOcrBackgroundJob();

		$signatureErrorLogs = $this->getDigitalSignatureErrorLogs();

		$this->assertEquals(0, count($signatureErrorLogs), 'Expected no digital signature error logs');
	}

	protected function runTestWorkflowOcrSendsErrorNotificationOnInvalidPdf() {
		$localFile = 'document-signed.pdf';
		$this->addOperation('application/pdf', json_encode(['skipNotificationsOnInvalidPdf' => false]));
		$this->uploadTestFile($localFile);
		$dateBeforeJobRun = time();
		$this->runOcrBackgroundJob();
		$dateAfterRun = time();

		$signatureErrorLogs = $this->getDigitalSignatureErrorLogs();

		$this->assertEquals(1, count($signatureErrorLogs), 'Expected 1 digital signature error log');
		$notifications = $this->notificationReceiverApp->getNotifications();
		$filteredNotifications = array_filter(
			$notifications,
			function ($notification) use ($dateBeforeJobRun, $dateAfterRun) {
				$notificationTime = $notification->getDateTime()->getTimestamp();
				return $notification->getApp() === 'workflow_ocr'
					&& $notification->getSubject() === 'ocr_error'
					&& str_contains($notification->getSubjectParameters()['message'], 'OCR not possible:')
					&& str_contains($notification->getSubjectParameters()['message'], 'DigitalSignatureError')
					&& $notificationTime >= $dateBeforeJobRun
					&& $notificationTime <= $dateAfterRun;
			}
		);
		$this->assertCount(1, $filteredNotifications, 'Expected 1 notification for digital signature error');
	}

	protected function runTestWorkflowOcrSkipsNotificationOnEncryptedPdf() {
		$localFile = 'document-encrypted.pdf';
		$this->addOperation('application/pdf', json_encode(['skipNotificationsOnEncryptedPdf' => true]));
		$this->uploadTestFile($localFile);
		$this->runOcrBackgroundJob();

		$encryptedPdfErrorLogs = $this->getEncryptedPdfErrorLogs();

		$this->assertEquals(0, count($encryptedPdfErrorLogs), 'Expected no encrypted PDF error logs');
		$this->assertCount(0, $this->notificationReceiverApp->getNotifications(), 'Expected no notifications for encrypted PDF error');
	}

	protected function runTestWorkflowOcrSendsErrorNotificationOnEncryptedPdf() {
		$localFile = 'document-encrypted.pdf';
		$this->addOperation('application/pdf', json_encode(['skipNotificationsOnEncryptedPdf' => false]));
		$this->uploadTestFile($localFile);
		$dateBeforeJobRun = time();
		$this->runOcrBackgroundJob();
		$dateAfterRun = time();

		$encryptedPdfErrorLogs = $this->getEncryptedPdfErrorLogs();

		$this->assertEquals(1, count($encryptedPdfErrorLogs), 'Expected 1 encrypted PDF error log');
		$notifications = $this->notificationReceiverApp->getNotifications();
		$filteredNotifications = array_filter(
			$notifications,
			function ($notification) use ($dateBeforeJobRun, $dateAfterRun) {
				$notificationTime = $notification->getDateTime()->getTimestamp();
				return $notification->getApp() === 'workflow_ocr'
					&& $notification->getSubject() === 'ocr_error'
					&& str_contains($notification->getSubjectParameters()['message'], 'OCR not possible:')
					&& str_contains($notification->getSubjectParameters()['message'], 'EncryptedPdfError')
					&& $notificationTime >= $dateBeforeJobRun
					&& $notificationTime <= $dateAfterRun;
			}
		);
		$this->assertCount(1, $filteredNotifications, 'Expected 1 notification for encrypted PDF error');
	}

	private function getDigitalSignatureErrorLogs(): array {
		return array_filter(
			$this->readLogEntries(),
			function ($entry) {
				return $entry->app === 'workflow_ocr'
					&& $entry->level === 3
					&& str_contains($entry->message, 'OCR not possible:')
					&& str_contains($entry->message, 'DigitalSignatureError')
					&& strtotime($entry->time) > (time() - 30); // only consider logs from last 30s
			}
		);
	}

	private function getEncryptedPdfErrorLogs(): array {
		return array_filter(
			$this->readLogEntries(),
			function ($entry) {
				return $entry->app === 'workflow_ocr'
					&& $entry->level === 3
					&& str_contains($entry->message, 'OCR not possible:')
					&& str_contains($entry->message, 'EncryptedPdfError')
					&& strtotime($entry->time) > (time() - 30); // only consider logs from last 30s
			}
		);
	}

	// -----------------------------------------------------

	protected function setNextcloudLogLevel() : void {
		$this->oldLogLevel = $this->config->getSystemValue('loglevel', 3);
		$this->config->setSystemValue('loglevel', 0);
	}

	protected function restoreNextcloudLogLevel() : void {
		$this->config->setSystemValue('loglevel', $this->oldLogLevel);
	}

	protected function runOcrBackgroundJob() {
		/** @var IJoblist */
		$jobList = $this->container->get(IJobList::class);
		$job = $jobList->getNext(false, [ProcessFileJob::class]);
		$this->assertNotNull($job, 'Expected one background job');
		$job->start($jobList);
	}

	protected function checkOcrBackendServiceInstalled() : bool {
		$ocrBackendInfoService = $this->container->get(IOcrBackendInfoService::class);
		return $ocrBackendInfoService->isRemoteBackend();
	}

	protected function checkOcrMyPdfInstalled() : bool {
		$command = new CommandWrapper();
		$command->setCommand('ocrmypdf --version')->execute();
		return $command->getExitCode() === 0;
	}

	protected function addOperation(string $mimeType, string $operationJson = '') {
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
					"value":"' . $mimeType . '",
					"invalid":false
				}
			],
			"operation":"' . str_replace('"', '\"', $operationJson) . '",
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

	protected function uploadTestFile(string $testFile) {
		$localFile = __DIR__ . '/../testdata/' . $testFile;
		$this->uploadedFiles[] = $localFile;
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

	protected function downloadFile(string $fileName) : string {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getNextcloudWebdavUrl() . basename($fileName));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		return $this->executeCurl($ch);
	}

	protected function readLogEntries(int $limit = 50, int $offset = 0) : array {
		$logFactory = $this->container->get(ILogFactory::class);
		/** @var IFileBased */
		$logReader = $logFactory->get('file');
		return $logReader->getEntries($limit, $offset);
	}

	private function deleteTestFilesIfExist() {
		foreach ($this->uploadedFiles as $localFile) {
			$this->deleteFileIfExists($localFile);
		}
		$this->uploadedFiles = [];

		foreach ($this->filesToDelete as $fileName) {
			$this->deleteFileIfExists($fileName);
		}
		$this->filesToDelete = [];
	}

	private function deleteFileIfExists(string $fileName) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->getNextcloudWebdavUrl() . basename($fileName));
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

		return $result;
	}

	private function deleteOperation() {
		// Clear managers cache and operations to ensure "deleteOperation" works
		$reflection = new \ReflectionClass($this->workflowEngineManager);

		$operationsByScopeProperty = $reflection->getProperty('operationsByScope');
		$operationsByScope = $operationsByScopeProperty->getValue($this->workflowEngineManager);
		$operationsByScope->clear();

		$operationsProperty = $reflection->getProperty('operations');
		$operationsProperty->setValue($this->workflowEngineManager, []);

		$operations = $this->workflowEngineManager->getOperations($this->operationClass, $this->context);
		foreach ($operations as $operation) {
			try {
				$this->workflowEngineManager->deleteOperation($operation['id'], $this->context);
			} catch (DomainException) {
				// ignore
			}
		}
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
}
