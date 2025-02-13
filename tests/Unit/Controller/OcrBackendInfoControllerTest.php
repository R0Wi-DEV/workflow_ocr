<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\Controller;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Controller\OcrBackendInfoController;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OcrBackendInfoControllerTest extends TestCase {
	/** @var IOcrBackendInfoService|MockObject */
	private $ocrBackendInfoService;

	/** @var IRequest|MockObject */
	private $request;

	/** @var OcrBackendInfoController */
	private $controller;

	protected function setUp() : void {
		$this->ocrBackendInfoService = $this->createMock(IOcrBackendInfoService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->controller = new OcrBackendInfoController(Application::APP_NAME, $this->request, $this->ocrBackendInfoService);
		parent::setUp();
	}

	#[DataProvider('dataProviderInstalledLangsJson')]
	public function testGetInstalledLanguagesReturnsJsonArray(array $simulatedServiceResponse, string $expectedResultJson) : void {
		$this->ocrBackendInfoService->expects($this->once())
			->method('getInstalledLanguages')
			->willReturn($simulatedServiceResponse);
		$response = $this->controller->getInstalledLanguages();
		$this->assertEquals($expectedResultJson, $response->render());
	}

	public static function dataProviderInstalledLangsJson() {
		return [
			[['eng', 'deu', 'chi'], '["eng","deu","chi"]'],
			[['eng'], '["eng"]']
		];
	}
}
