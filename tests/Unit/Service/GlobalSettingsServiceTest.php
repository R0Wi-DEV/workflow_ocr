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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Service\GlobalSettingsService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class GlobalSettingsServiceTest extends TestCase {
	/** @var IAppConfig|MockObject */
	private $config;

	/** @var GlobalSettingsService */
	private $globalSettingsService;

	public function setUp() : void {
		parent::setUp();
		$this->config = $this->createMock(IAppConfig::class);
		$this->globalSettingsService = new GlobalSettingsService($this->config);
	}

	public function testGetSettings_ReturnsCorrectSettings() {
		$this->config->expects($this->any())
			->method('getValueString')
			->willReturnMap([
				[Application::APP_NAME, 'processorCount', '2'],
				[Application::APP_NAME, 'timeout', '30'],
			]);

		$settings = $this->globalSettingsService->getGlobalSettings();

		$this->assertInstanceOf(GlobalSettings::class, $settings);
		$this->assertEquals(2, $settings->processorCount);
		$this->assertEquals(30, $settings->timeout);
	}

	public function testSetSettings_CallsConfigSetAppValue() {
		$settings = new GlobalSettings();
		$settings->processorCount = '2';
		$settings->timeout = '30';

		$this->config->expects($this->any())
			->method('setValueString')
			->willReturnCallback(
				function (string $appName, string $key, string $value) use ($settings) {
					if ($key === 'processorCount') {
						$this->assertEquals($settings->processorCount, $value);
					} elseif ($key === 'timeout') {
						$this->assertEquals($settings->timeout, $value);
					} else {
						$this->fail("Unexpected key: $key");
					}
					return true;
				}
			);

		$this->globalSettingsService->setGlobalSettings($settings);
	}
}
