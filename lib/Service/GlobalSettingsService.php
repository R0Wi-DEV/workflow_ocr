<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

namespace OCA\WorkflowOcr\Service;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCP\IAppConfig;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class GlobalSettingsService
 *
 * Helper for writing and reading global settings stored in NC config.
 * @package OCA\WorkflowOcr\Service
 */
class GlobalSettingsService implements IGlobalSettingsService {

	public function __construct(
		private IAppConfig $config,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getGlobalSettings() : GlobalSettings {
		$settings = new GlobalSettings();

		foreach ($this->getProperties($settings) as $prop) {
			$key = $prop->getName();
			$type = $prop->getType();
			if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
				$intValue = $this->config->getValueInt(Application::APP_NAME, $key, 0);
				// 0 is used as the "not configured" sentinel: none of the integer settings
				// have a meaningful zero value, so 0 → null (not set).
				$settings->$key = $intValue > 0 ? $intValue : null;
			} else {
				$settings->$key = $this->config->getValueString(Application::APP_NAME, $key);
			}
		}

		return $settings;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	public function setGlobalSettings(GlobalSettings $settings) : void {
		foreach ($this->getProperties($settings) as $prop) {
			$key = $prop->getName();
			$value = $settings->$key;
			$type = $prop->getType();
			$isNullable = $type !== null && $type->allowsNull();

			if ($isNullable && $value === null) {
				$this->config->deleteKey(Application::APP_NAME, $key);
			} elseif ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
				$this->config->setValueInt(Application::APP_NAME, $key, (int)$value);
			} else {
				$this->config->setValueString(Application::APP_NAME, $key, (string)($value ?? ''));
			}
		}
	}

	private function getProperties(GlobalSettings $settings) : array {
		$reflect = new ReflectionClass($settings);
		return $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
	}
}
