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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OCA\WorkflowOcr\Events\TextRecognizedEvent;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\Service\EventService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EventServiceTest extends TestCase {

    /** @var IEventDispatcher|MockObject */
    private $eventDispatcher;

    /** @var EventService */
    private $service;

    public function setUp() : void {
        $this->eventDispatcher = $this->createMock(IEventDispatcher::class);
        $this->service = new EventService($this->eventDispatcher);
        parent::setUp();
    }

    public function testTextRecognizedDispatchesEvent() {
        /** @var File|MockObject */
        $file = $this->createMock(File::class);
        $ocrResult = new OcrProcessorResult('content', 'pdf', 'recognizedText');
        $this->eventDispatcher->expects($this->once())
            ->method('dispatchTyped')
            ->with($this->callback(function (TextRecognizedEvent $event) use ($ocrResult, $file) {
                return $event->getResult() === $ocrResult && $event->getFile() === $file;
            }));

        $this->service->textRecognized($ocrResult, $file);
    }
}