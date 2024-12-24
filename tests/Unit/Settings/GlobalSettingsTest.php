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

namespace OCA\WorkflowOcr\Tests\Unit\Settings;

use OCA\WorkflowOcr\Settings\GlobalSettings;
use Test\TestCase;

class GlobalSettingsTest extends TestCase {
	/** @var GlobalSettings */
	private $adminSettings;

	protected function setUp(): void {
		parent::setUp();
		$this->adminSettings = new GlobalSettings();
	}

	public function testGetSection() {
		$this->assertEquals('workflow', $this->adminSettings->getSection());
	}

	public function testGetPriority() {
		$this->assertEquals(75, $this->adminSettings->getPriority());
	}

	public function testGetForm() {
		$templateResponse = $this->adminSettings->getForm();
		$templates = array_filter(scandir('./templates'), fn ($file) => is_file("./templates/$file"));
		$this->assertEquals(1, count($templates));
		$templateFileName = './templates/' . $templates[array_keys($templates)[0]];
		$templateNameWithoutExtension = pathinfo($templateFileName)['filename'];
		$this->assertEquals($templateNameWithoutExtension, $templateResponse->getTemplateName());
	}
}
