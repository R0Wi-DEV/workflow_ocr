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

namespace OCA\WorkflowOcr\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use Test\TestCase;

class WorkflowSettingsTest extends TestCase {
	/**
	 * @dataProvider dataProvider_testConstruction
	 */
	public function testWorkflowSettingsConstruction(string $json, bool $expectedRemoveBackground, array $expectedLangSettings) {
		$workflowSettings = new WorkflowSettings($json);
		$this->assertEquals($expectedRemoveBackground, $workflowSettings->getRemoveBackground());
		$this->assertEquals($expectedLangSettings, $workflowSettings->getLanguages());
	}

	public function testWorkflowSettingsConstructorThrowsInvalidArgumentExceptionOnInvalidJson() {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid JSON: "{"');
		new WorkflowSettings('{');
	}

	public function dataProvider_testConstruction() {
		return [
			[
				'{"removeBackground":true,"languages":["eng","deu","spa","fra","ita"]}',
				true,
				['eng', 'deu', 'spa', 'fra', 'ita']
			]
		];
	}
}
