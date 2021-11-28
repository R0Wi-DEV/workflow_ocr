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
use OCP\IConfig;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class GlobalSettingsService
 *
 * Helper for writing and reading global settings stored in NC config.
 * @package OCA\WorkflowOcr\Service
 */
class GlobalSettingsService implements IGlobalSettingsService {
	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function getGlobalSettings() : GlobalSettings {
		$settings = new GlobalSettings();

		foreach ($this->getProperties($settings) as $prop) {
			$key = $prop->getName();
			$configValue = $this->config->getAppValue(Application::APP_NAME, $key);
			$settings->$key = $configValue;
		}

		return $settings;
	}

	/**
	 * @inheritDoc
	 */
	public function setGlobalSettings(GlobalSettings $settings) {
		foreach ($this->getProperties($settings) as $prop) {
			$key = $prop->getName();
			$value = $settings->$key;
			$this->config->setAppValue(Application::APP_NAME, $key, $value);
		}
	}

	private function getProperties(GlobalSettings $settings) : array {
		$reflect = new ReflectionClass($settings);
		return $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
	}
}
