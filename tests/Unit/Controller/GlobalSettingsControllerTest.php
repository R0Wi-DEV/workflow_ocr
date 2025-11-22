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
use OCA\WorkflowOcr\Controller\GlobalSettingsController;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class GlobalSettingsControllerTest extends TestCase {
	/** @var IGlobalSettingsService|MockObject */
	private $globalSettingsService;

	/** @var IRequest|MockObject */
	private $request;

	/** @var GlobalSettingsController */
	private $controller;

	public function setUp() : void {
		parent::setUp();
		$this->globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->controller = new GlobalSettingsController(Application::APP_NAME, $this->request, $this->globalSettingsService);
	}

	public function testGetSettings() {
		$settings = new GlobalSettings();
		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($settings);

		/** @var JSONResponse */
		$result = $this->controller->getGlobalSettings();

		$this->assertInstanceOf(JSONResponse::class, $result);
		$this->assertSame($settings, $result->getData());
	}

	public function testGetSettingsCatchesExceptions() {
		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willThrowException(new \Exception('test'));

		/** @var JSONResponse */
		$result = $this->controller->getGlobalSettings();

		$this->assertInstanceOf(JSONResponse::class, $result);
		$this->assertEquals(500, $result->getStatus());
		$this->assertEquals(['error' => 'test'], $result->getData());
	}

	public function testSetSettingsCallsService() {
		$settings = [
			'processorCount' => 42,
			'timeout' => 120,
		];

		$this->globalSettingsService->expects($this->once())
			->method('setGlobalSettings')
			->with($this->callback(function (GlobalSettings $settings) {
				return $settings->processorCount === 42 && $settings->timeout === 120;
			}));

		$this->controller->setGlobalSettings($settings);
	}

	public function testSetSettingsCatchesExceptions() {
		$settings = [
			'processorCount' => 42,
		];

		$this->globalSettingsService->expects($this->once())
			->method('setGlobalSettings')
			->willThrowException(new \Exception('test'));

		/** @var JSONResponse */
		$result = $this->controller->setGlobalSettings($settings);

		$this->assertInstanceOf(JSONResponse::class, $result);
		$this->assertEquals(500, $result->getStatus());
		$this->assertEquals(['error' => 'test'], $result->getData());
	}

	public function testSetSettingsWithoutTimeout() {
		$settings = [
			'processorCount' => 42,
		];

		$this->globalSettingsService->expects($this->once())
			->method('setGlobalSettings')
			->with($this->callback(function (GlobalSettings $settings) {
				return $settings->processorCount === 42 && $settings->timeout === null;
			}));

		$this->controller->setGlobalSettings($settings);
	}
}
