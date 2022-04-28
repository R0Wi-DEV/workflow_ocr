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

namespace OCA\WorkflowOcr\Model;

use InvalidArgumentException;

class WorkflowSettings {

	/** @var array */
	private $languages = [];

	/** @var bool */
	private $removeBackground = false;

	/**
	 * @param string $json The serialized JSON string used in frontend as input for the Vue component
	 */
	public function __construct(string $json = null) {
		$this->setJson($json);
	}

	/**
	 * @return array
	 */
	public function getLanguages(): array {
		return $this->languages;
	}

	/**
	 * @return bool
	 */
	public function getRemoveBackground(): bool {
		return $this->removeBackground;
	}

	/**
	 * Checks if a new WorkflowSettings object can be constructed from the given JSON string
	 * @param string $json The serialized JSON string used in frontend as input for the Vue component
	 * @return bool True if the JSON string is valid, false otherwise
	 */
	public static function canConstruct(string $json): bool {
		$settings = new WorkflowSettings();
		try {
			$settings->setJson($json);
		} catch (InvalidArgumentException $e) {
			return false;
		}
		return true;
	}

	/**
	 * @return void
	 */
	private function setJson(string $json = null) {
		if (!$json) {
			return;
		}
		$data = json_decode($json, true);
		if ($data === null) {
			throw new InvalidArgumentException('Invalid JSON: "' . $json . '"');
		}
		if (array_key_exists('languages', $data) && is_array($data['languages'])) {
			$this->languages = $data['languages'];
		}
		if (array_key_exists('removeBackground', $data) && is_bool($data['removeBackground'])) {
			$this->removeBackground = $data['removeBackground'];
		}
	}
}
