<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Robin Windey <ro.windey@gmail.com>
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

use OC\Files\Node\File;
use OCA\Files_Versions\Versions\IMetadataVersionBackend;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

// With PHPUnit 10 use
// https://docs.phpunit.de/en/10.5/test-doubles.html#createmockforintersectionofinterfaces
class VersionBackendMock implements IVersionBackend, IMetadataVersionBackend {
	public function __construct(
		private $createMock,
	) {
	}
	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}
	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		return [];
	}
	public function createVersion(IUser $user, FileInfo $file) {
	}
	public function rollback(IVersion $version) {
	}
	public function read(IVersion $version) {
	}
	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File {
		return $this->createMock->call(File::class);
	}
	public function setMetadataValue(Node $node, int $revision, string $key, string $value): void {
	}
}
