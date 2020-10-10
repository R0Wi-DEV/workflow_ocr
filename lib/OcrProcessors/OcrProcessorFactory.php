<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\OcrProcessors;

use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use Psr\Container\ContainerInterface;

class OcrProcessorFactory implements IOcrProcessorFactory {
	private static $mapping = [
		'application/pdf' => PdfOcrProcessor::class
	];
	
	/** @var ContainerInterface */
	private $container;

	public function __construct(ContainerInterface $container) {
		$this->container = $container;
	}

	public function create(string $mimeType) : IOcrProcessor {
		if (!array_key_exists($mimeType, self::$mapping)) {
			throw new OcrProcessorNotFoundException($mimeType);
		}
		$className = self::$mapping[$mimeType];

		return $this->container->get($className);
	}
}
