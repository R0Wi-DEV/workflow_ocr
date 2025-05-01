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

use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;

/**
 * Full test case for registering new OCR Workflow, uploading file and
 * processing it via OCR Backend Service.
 * @group DB
 */
class OcrBackendServiceTest extends BackendTestBase {
	private IntegrationTestApiClient $apiClient;


	protected function setUp(): void {
		parent::setUp();

		$githubActionsJob = getenv('GITHUB_JOB');
		$githubActionsMatrixBackend = getenv('GITHUB_MATRIX_BACKEND');
		$isOcrBackendServiceInstalled = $this->checkOcrBackendServiceInstalled();
		$shouldRunOnCi = $githubActionsJob === 'github-php-integrationtests' && $githubActionsMatrixBackend == 'remote';

		if ($shouldRunOnCi && !$isOcrBackendServiceInstalled) {
			$this->fail('Running Github Actions Integrationtests but OCR Backend is not installed');
			return;
		}

		if (!$isOcrBackendServiceInstalled) {
			$this->markTestSkipped('OCR Backend is not installed');
			return;
		}

		$this->apiClient = $this->container->get(IntegrationTestApiClient::class);
		$this->apiClient->reset();
		$this->overwriteService(IApiClient::class, $this->apiClient);
	}

	public function testWorkflowOcrBackendService() {
		$this->addOperation('application/pdf');
		$this->uploadTestFile('document-ready-for-ocr.pdf');
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

	public function testWorkflowOcrBackendServiceSkipFile() {
		$this->addOperation('application/pdf', '{"ocrMode":' . WorkflowSettings::OCR_MODE_SKIP_FILE . '}');
		$this->uploadTestFile('document-has-ocr.pdf');
		$this->runOcrBackgroundJob();

		$requests = $this->apiClient->getRequests();
		$this->assertCount(1, $requests, 'Expected 1 OCR request');
		$request = $requests[0];
		$this->assertTrue($request['ocrMyPdfParameters'] === '', 'Expected OCR parameters in request');

		$responses = $this->apiClient->getResponses();
		$this->assertCount(1, $responses, 'Expected 1 OCR response');
		$this->assertTrue($responses[0] instanceof ErrorResult, 'Expected ErrorResult instance, type is: ' . get_class($responses[0]));
		/** @var ErrorResult */
		$ocrResult = $responses[0];
		$this->assertEquals($ocrResult->getOcrMyPdfExitCode(), 6, 'Expected ocrmypdf ExitCode 6');
	}
}
