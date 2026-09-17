<?php

declare(strict_types=1);

/**
 * Minimal polyfill for the public interfaces of Nextcloud's built-in files_versions
 * app (OCA\Files_Versions\Versions\*), loaded only by tests/bootstrap.standalone.php
 * and only if nextcloud/ocp doesn't already provide them (it doesn't -- files_versions
 * is a separate bundled app, not part of the OCP/NCU core package the nextcloud/ocp
 * composer dependency ships). Not used by phpunit.xml/phpunit.integration.xml, where
 * the real Nextcloud classes from a full checkout are always used instead.
 *
 * Trimmed to the methods lib/Service/OcrService.php and
 * tests/Unit/Service/OcrServiceTest.php actually call -- not a complete reproduction
 * of the upstream API. Keep in sync with the real interfaces if OcrService.php ever
 * starts relying on more of them.
 */

namespace OCA\Files_Versions\Versions;

use OCP\Files\Node;
use OCP\IUser;

interface IVersionBackend {
	/** @return IVersion[] */
	public function getVersionsForFile(IUser $user, Node $file): array;
}

interface IVersionManager extends IVersionBackend {
}

interface IVersion {
	public function getBackend(): IVersionBackend;

	/** @return int|string */
	public function getRevisionId();

	public function getTimestamp(): int;
}

interface IMetadataVersion {
	public function getMetadataValue(string $key): ?string;
}

interface IMetadataVersionBackend {
	public function setMetadataValue(Node $node, int $revision, string $key, string $value): void;
}
