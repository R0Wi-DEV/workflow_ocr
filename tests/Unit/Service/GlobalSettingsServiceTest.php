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
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class GlobalSettingsServiceTest extends TestCase {
	/** @var IConfig|MockObject */
	private $config;

	/** @var GlobalSettingsService */
	private $globalSettingsService;

	public function setUp() : void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->globalSettingsService = new GlobalSettingsService($this->config);
	}

	public function testGetSettings_ReturnsCorrectSettings() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_NAME, 'processorCount', '')
			->willReturn('2');

		$settings = $this->globalSettingsService->getGlobalSettings();

		$this->assertInstanceOf(GlobalSettings::class, $settings);
		$this->assertEquals(2, $settings->processorCount);
	}

	public function testSetSettings_CallsConfigSetAppValue() {
		$settings = new GlobalSettings();
		$settings->processorCount = 2;

		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_NAME, 'processorCount', '2');

		$this->globalSettingsService->setGlobalSettings($settings);
	}
}
