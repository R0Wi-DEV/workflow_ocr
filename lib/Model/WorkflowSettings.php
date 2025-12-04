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
	public const OCR_MODE_SKIP_TEXT = 0;
	public const OCR_MODE_REDO_OCR = 1;
	public const OCR_MODE_FORCE_OCR = 2;
	public const OCR_MODE_SKIP_FILE = 3;

	/** @var array */
	private $languages = [];

	/** @var bool */
	private $removeBackground = false;

	/** @var int */
	private $ocrMode = self::OCR_MODE_SKIP_TEXT;

	/** @var array string */
	private $tagsToRemoveAfterOcr = [];

	/** @var array string */
	private $tagsToAddAfterOcr = [];

	/** @var bool */
	private $keepOriginalFileVersion = false;

	/** @var bool */
	private $keepOriginalFileDate = false;

	/** @var bool */
	private $sendSuccessNotification = false;

	/** @var string */
	private $customCliArgs = '';

	/** @var bool */
	private $createSidecarFile = false;

	/** @var bool */
	private $skipNotificationsOnInvalidPdf = false;

	/** @var bool */
	private $skipNotificationsOnEncryptedPdf = false;

	/**
	 * @param string $json The serialized JSON string used in frontend as input for the Vue component
	 */
	public function __construct(?string $json = null) {
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
	 * @return int
	 */
	public function getOcrMode(): int {
		return $this->ocrMode;
	}

	/**
	 * @return array
	 */
	public function getTagsToRemoveAfterOcr(): array {
		return $this->tagsToRemoveAfterOcr;
	}

	/**
	 * @return array
	 */
	public function getTagsToAddAfterOcr(): array {
		return $this->tagsToAddAfterOcr;
	}

	/**
	 * @return bool
	 */
	public function getKeepOriginalFileVersion(): bool {
		return $this->keepOriginalFileVersion;
	}

	/**
	 * @return bool
	 */
	public function getKeepOriginalFileDate(): bool {
		return $this->keepOriginalFileDate;
	}

	/**
	 * @return bool
	 */
	public function getSendSuccessNotification(): bool {
		return $this->sendSuccessNotification;
	}

	/**
	 * @return string
	 */
	public function getCustomCliArgs(): string {
		return $this->customCliArgs;
	}

	/**
	 * @return bool
	 */
	public function getCreateSidecarFile(): bool {
		return $this->createSidecarFile;
	}

	/**
	 * @return bool
	 */
	public function getSkipNotificationsOnInvalidPdf(): bool {
		return $this->skipNotificationsOnInvalidPdf;
	}

	/**
	 * @return bool
	 */
	public function getSkipNotificationsOnEncryptedPdf(): bool {
		return $this->skipNotificationsOnEncryptedPdf;
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
	private function setJson(?string $json = null) {
		if (!$json) {
			return;
		}
		$data = json_decode($json, true);
		if ($data === null) {
			throw new InvalidArgumentException('Invalid JSON: "' . $json . '"');
		}
		$this->setProperty($this->languages, $data, 'languages', fn ($value) => is_array($value));
		$this->setProperty($this->removeBackground, $data, 'removeBackground', fn ($value) => is_bool($value));
		$this->setProperty($this->ocrMode, $data, 'ocrMode', fn ($value) => is_int($value));
		$this->setProperty($this->tagsToRemoveAfterOcr, $data, 'tagsToRemoveAfterOcr', fn ($value) => is_array($value));
		$this->setProperty($this->tagsToAddAfterOcr, $data, 'tagsToAddAfterOcr', fn ($value) => is_array($value));
		$this->setProperty($this->keepOriginalFileVersion, $data, 'keepOriginalFileVersion', fn ($value) => is_bool($value));
		$this->setProperty($this->keepOriginalFileDate, $data, 'keepOriginalFileDate', fn ($value) => is_bool($value));
		$this->setProperty($this->sendSuccessNotification, $data, 'sendSuccessNotification', fn ($value) => is_bool($value));
		$this->setProperty($this->customCliArgs, $data, 'customCliArgs', fn ($value) => is_string($value));
		$this->setProperty($this->createSidecarFile, $data, 'createSidecarFile', fn ($value) => is_bool($value));
		$this->setProperty($this->skipNotificationsOnInvalidPdf, $data, 'skipNotificationsOnInvalidPdf', fn ($value) => is_bool($value));
		$this->setProperty($this->skipNotificationsOnEncryptedPdf, $data, 'skipNotificationsOnEncryptedPdf', fn ($value) => is_bool($value));
	}

	private function setProperty(array|bool|int|string & $property, array $jsonData, string $key, ?callable $dataCheck = null): void {
		if (array_key_exists($key, $jsonData) && ($dataCheck === null || $dataCheck($jsonData[$key]))) {
			$property = $jsonData[$key];
		}
	}
}
