<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Helper;

use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class SidecarFileAccessor implements ISidecarFileAccessor {

	/** @var ITempManager */
	private $tempManager;

	/** @var LoggerInterface */
	private $logger;

	/** @var string */
	private $sidecarFilePath;

	public function __construct(ITempManager $tempManager, LoggerInterface $logger) {
		$this->tempManager = $tempManager;
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getOrCreateSidecarFile(): string|bool {
		if ($this->sidecarFilePath === null) {
			$this->sidecarFilePath = $this->tempManager->getTemporaryFile('sidecar');
			if (!$this->sidecarFilePath) {
				$this->logger->warning('Could not create temporary sidecar file');
			}
		}
		return $this->sidecarFilePath;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSidecarFileContent(): string {
		return $this->sidecarFilePath ? file_get_contents($this->sidecarFilePath) : '';
	}
}
