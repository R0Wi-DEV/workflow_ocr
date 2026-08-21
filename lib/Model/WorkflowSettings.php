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

	/**
	 * Allow-list for tesseract/ocrmypdf language codes (e.g. 'eng', 'chi_sim', 'script/Latin').
	 * Used to reject anything that could be (ab)used as a shell metacharacter once the
	 * languages are concatenated into the ocrmypdf command line.
	 */
	private const LANGUAGE_CODE_REGEX = '/^[A-Za-z][A-Za-z0-9_\/]{0,31}$/';

	/**
	 * Maximum accepted length of the custom ocrmypdf CLI arguments.
	 */
	private const CUSTOM_CLI_ARGS_MAX_LENGTH = 4096;

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
	 * Validates the given JSON string and throws if it cannot be used to construct
	 * a new WorkflowSettings object.
	 * @param string $json The serialized JSON string used in frontend as input for the Vue component
	 * @throws InvalidArgumentException If the JSON string is invalid
	 * @return void
	 */
	public static function validate(string $json): void {
		$settings = new WorkflowSettings();
		$settings->setJson($json);
	}

	/**
	 * @return void
	 */
	private function setJson(?string $json = null) {
		if (!$json) {
			return;
		}
		$data = json_decode($json, true);
		if (!is_array($data)) {
			throw new InvalidArgumentException('Invalid JSON: "' . $json . '"');
		}
		$this->setProperty($this->languages, $data, 'languages', fn ($value) => self::isValidLanguagesArray($value));
		$this->setProperty($this->removeBackground, $data, 'removeBackground', fn ($value) => is_bool($value));
		$this->setProperty($this->ocrMode, $data, 'ocrMode', fn ($value) => self::isValidOcrMode($value));
		$this->setProperty($this->tagsToRemoveAfterOcr, $data, 'tagsToRemoveAfterOcr', fn ($value) => is_array($value));
		$this->setProperty($this->tagsToAddAfterOcr, $data, 'tagsToAddAfterOcr', fn ($value) => is_array($value));
		$this->setProperty($this->keepOriginalFileVersion, $data, 'keepOriginalFileVersion', fn ($value) => is_bool($value));
		$this->setProperty($this->keepOriginalFileDate, $data, 'keepOriginalFileDate', fn ($value) => is_bool($value));
		$this->setProperty($this->sendSuccessNotification, $data, 'sendSuccessNotification', fn ($value) => is_bool($value));
		$this->setProperty($this->customCliArgs, $data, 'customCliArgs', fn ($value) => self::isValidCustomCliArgs($value));
		$this->setProperty($this->createSidecarFile, $data, 'createSidecarFile', fn ($value) => is_bool($value));
		$this->setProperty($this->skipNotificationsOnInvalidPdf, $data, 'skipNotificationsOnInvalidPdf', fn ($value) => is_bool($value));
		$this->setProperty($this->skipNotificationsOnEncryptedPdf, $data, 'skipNotificationsOnEncryptedPdf', fn ($value) => is_bool($value));
	}

	/**
	 * Applies the value stored under $key to $property. Keys which are not part of the
	 * given JSON data keep their default value. A key which is present but doesn't pass
	 * its check is rejected with an exception instead of being silently dropped, so that
	 * the user gets a proper error message when saving the workflow and a malformed
	 * (or manipulated) setting never ends up being used with default values.
	 *
	 * @throws InvalidArgumentException If the value stored under $key is invalid
	 */
	private function setProperty(array|bool|int|string & $property, array $jsonData, string $key, ?callable $dataCheck = null): void {
		if (!array_key_exists($key, $jsonData)) {
			return;
		}

		$value = $jsonData[$key];

		if ($dataCheck !== null && !$dataCheck($value)) {
			throw new InvalidArgumentException('Invalid value for setting \'' . $key . '\'');
		}

		$property = $value;
	}

	/**
	 * Validates that $value is an array of strings, each matching the allow-listed
	 * language code pattern. This is a security relevant check: the language values
	 * end up being concatenated into a shell command (see CommandLineUtils), so any
	 * value not matching the allow-list must be rejected here.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function isValidLanguagesArray($value): bool {
		if (!is_array($value)) {
			return false;
		}
		foreach ($value as $language) {
			if (!is_string($language) || preg_match(self::LANGUAGE_CODE_REGEX, $language) !== 1) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validates that $value is one of the known OCR modes. An unknown mode would result
	 * in an undefined commandline parameter mapping (see CommandLineUtils).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function isValidOcrMode($value): bool {
		return is_int($value) && in_array($value, [
			self::OCR_MODE_SKIP_TEXT,
			self::OCR_MODE_REDO_OCR,
			self::OCR_MODE_FORCE_OCR,
			self::OCR_MODE_SKIP_FILE,
		], true);
	}

	/**
	 * Validates the custom ocrmypdf CLI arguments. The value is passed to the ocrmypdf
	 * commandline (see CommandLineUtils, where every argument is quoted if needed), so
	 * we only make sure here that it's a reasonably sized, single line string without
	 * any control characters.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function isValidCustomCliArgs($value): bool {
		return is_string($value)
			&& strlen($value) <= self::CUSTOM_CLI_ARGS_MAX_LENGTH
			&& preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
	}
}
