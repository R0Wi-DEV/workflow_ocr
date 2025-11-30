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

use OCA\WorkflowOcr\Events\TextRecognizedEvent;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\Tests\Integration\TestUtils\BackendTestBase;
use OCA\WorkflowOcr\Tests\Integration\TestUtils\IntegrationTestApiClient;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\Attributes\Depends;

/**
 * Full test case for registering new OCR Workflow, uploading file and
 * processing it via local OCR (ocrmypdf CLI) backend.
 * @group DB
 */
class LocalBackendTest extends BackendTestBase {
	private IntegrationTestApiClient $apiClient;
	private IEventDispatcher $dispatcher;
	private array $capturedEvents = [];

	/** @var callable */
	private $eventListener;

	protected function setUp(): void {
		parent::setUp();

		$githubActionsJob = getenv('GITHUB_JOB');
		$githubActionsMatrixBackend = getenv('GITHUB_MATRIX_BACKEND');
		$isOcrMyPdfInstalled = $this->checkOcrMyPdfInstalled();
		$shouldRunOnCi = $githubActionsJob === 'github-php-integrationtests' && $githubActionsMatrixBackend == 'local';

		if ($shouldRunOnCi && !$isOcrMyPdfInstalled) {
			$this->fail('Running Github Actions Integrationtests but ocrmypdf CLI is not installed');
			return;
		}

		if (!$isOcrMyPdfInstalled) {
			$this->markTestSkipped('ocrmypdf is not installed');
			return;
		}

		if ($this->checkOcrBackendServiceInstalled()) {
			$this->markTestSkipped('OCR Backend service is installed, cannot use local backend (ocrmypdf)');
			return;
		}

		$this->dispatcher = $this->container->get(IEventDispatcher::class);
		$this->apiClient = $this->container->get(IntegrationTestApiClient::class);
		$this->overwriteService(IApiClient::class, $this->apiClient);

		$this->eventListener = function (TextRecognizedEvent $event) {
			$this->capturedEvents[] = $event;
		};
		$this->dispatcher->addListener(TextRecognizedEvent::class, $this->eventListener);
	}

	protected function tearDown(): void {
		parent::tearDown();
		if (isset($this->dispatcher) && isset($this->eventListener)) {
			$this->dispatcher->removeListener(TextRecognizedEvent::class, $this->eventListener);
		}
		$this->capturedEvents = [];
	}

	/**
	 * Test processing a file via ocrmypdf CLI.
	 */
	public function testWorkflowOcrLocalBackend(): void {
		$this->addOperation('application/pdf');
		$this->uploadTestFile('document-ready-for-ocr.pdf');
		$this->runOcrBackgroundJob();

		$this->assertEmpty($this->apiClient->getRequests(), 'Expected no OCR Backend Service requests');
		$this->assertEquals(1, count($this->capturedEvents), 'Expected 1 TextRecognizedEvent');
		$textRecognizedEvent = $this->capturedEvents[0];
		$this->assertInstanceOf(TextRecognizedEvent::class, $textRecognizedEvent, 'Expected TextRecognizedEvent instance');
		$this->assertEquals('This document is ready for OCR', trim($textRecognizedEvent->getRecognizedText()), 'Expected recognized text');
	}

	/**
	 * Test processing a file via ocrmypdf CLI where file already contains OCR and mode is set to "skip file".
	 */
	public function testWorkflowOcrLocalBackendSkipFile(): void {
		$this->addOperation('application/pdf', '{"ocrMode":' . WorkflowSettings::OCR_MODE_SKIP_FILE . '}');
		$this->uploadTestFile('document-has-ocr.pdf');
		$this->runOcrBackgroundJob();

		$this->assertEmpty($this->apiClient->getRequests(), 'Expected no OCR Backend Service requests');
		$this->assertEquals(0, count($this->capturedEvents), 'Expected no TextRecognizedEvent');
	}

	/**
	 * Test processing a file via ocrmypdf CLI where file already contains OCR and mode is set to "skip text".
	 */
	public function testWorkflowOcrLocalBackendSkipText(): void {
		$this->addOperation('application/pdf', '{"ocrMode":' . WorkflowSettings::OCR_MODE_SKIP_TEXT . '}');
		$this->uploadTestFile('document-has-ocr.pdf');
		$this->runOcrBackgroundJob();

		$this->assertEmpty($this->apiClient->getRequests(), 'Expected no OCR Backend Service requests');
		$this->assertEquals(1, count($this->capturedEvents), 'Expected no TextRecognizedEvent');

		// Ocrmypdf will "recognize" a special text for pages which already have OCR
		$textRecognizedEvent = $this->capturedEvents[0];
		$this->assertInstanceOf(TextRecognizedEvent::class, $textRecognizedEvent, 'Expected TextRecognizedEvent instance');
		$this->assertEquals('[OCR skipped on page(s) 1]', trim($textRecognizedEvent->getRecognizedText()), 'Expected recognized text');
	}

	public function testWorkflowOcrLocalBackendPngWithAlphaChannel(): void {
		$this->addOperation('image/png');
		$this->uploadTestFile('png-with-alpha-channel.png');
		$this->runOcrBackgroundJob();

		$this->assertEmpty($this->apiClient->getRequests(), 'Expected no OCR Backend Service requests');
		$this->assertEquals(1, count($this->capturedEvents), 'Expected 1 TextRecognizedEvent');
		$textRecognizedEvent = $this->capturedEvents[0];
		$this->assertInstanceOf(TextRecognizedEvent::class, $textRecognizedEvent, 'Expected TextRecognizedEvent instance');
		$this->assertEquals('PNG with alpha channel', trim($textRecognizedEvent->getRecognizedText()), 'Expected recognized text');
	}

	public function testWorkflowOcrLocalBackendRegularJpg(): void {
		$this->addOperation('image/png');
		$this->uploadTestFile('png-without-alpha-channel.png');
		$this->runOcrBackgroundJob();

		$this->assertEmpty($this->apiClient->getRequests(), 'Expected no OCR Backend Service requests');
		$this->assertEquals(1, count($this->capturedEvents), 'Expected 1 TextRecognizedEvent');
		$textRecognizedEvent = $this->capturedEvents[0];
		$this->assertInstanceOf(TextRecognizedEvent::class, $textRecognizedEvent, 'Expected TextRecognizedEvent instance');
		$this->assertEquals('PNG without alpha channel', trim($textRecognizedEvent->getRecognizedText()), 'Expected recognized text');
	}

	public function testWorkflowOcrCreatesSidecarFile(): void {
		$localFile = 'document-ready-for-ocr.pdf';
		$this->addOperation('application/pdf', json_encode(['createSidecarFile' => true]));
		$this->uploadTestFile($localFile);
		$this->runOcrBackgroundJob();

		$sidecarName = pathinfo($localFile, PATHINFO_FILENAME) . '.txt';
		$sidecarFileContent = $this->downloadFile($sidecarName);
		$this->filesToDelete[] = $sidecarName;

		$this->assertStringContainsString('This document is ready for OCR', $sidecarFileContent, 'Expected recognized text in sidecar file');
	}

	public function testWorkflowOcrSkipsNotificationOnInvalidPdf(): void {
		$this->runTestWorkflowOcrSkipsNotificationOnInvalidPdf();
	}

	#[Depends('testWorkflowOcrSkipsNotificationOnInvalidPdf')]
	public function testWorkflowOcrSendsErrorNotificationOnInvalidPdf(): void {
		$this->runTestWorkflowOcrSendsErrorNotificationOnInvalidPdf();
	}

	public function testWorkflowOcrSkipsNotificationOnEncryptedPdf(): void {
		$this->runTestWorkflowOcrSkipsNotificationOnEncryptedPdf();
	}

	#[Depends('testWorkflowOcrSkipsNotificationOnEncryptedPdf')]
	public function testWorkflowOcrSendsErrorNotificationOnEncryptedPdf(): void {
		$this->runTestWorkflowOcrSendsErrorNotificationOnEncryptedPdf();
	}
}
