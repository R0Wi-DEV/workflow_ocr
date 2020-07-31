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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use PHPUnit\Framework\TestCase;
use OCP\AppFramework\IAppContainer;

class OcrProcessorFactoryTest extends TestCase {
	/** @var IAppContainer */
	private $appContainer;

	protected function setUp() : void {
		parent::setUp();
		$app = new Application();
		$this->appContainer = $app->getContainer();
	}
	
	public function testReturnsPdfProcessor() {
		$factory = new OcrProcessorFactory($this->appContainer);
		$processor = $factory->create('application/pdf');
		$this->assertInstanceOf(PdfOcrProcessor::class, $processor);
	}

	public function testThrowsNotFoundExceptionOnInvalidMimeType() {
		$this->expectException(OcrProcessorNotFoundException::class);
		$factory = new OcrProcessorFactory($this->appContainer);
		$factory->create('no/mimetype');
	}
}
