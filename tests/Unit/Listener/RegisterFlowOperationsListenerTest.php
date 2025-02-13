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

namespace OCA\WorkflowOcr\Tests\Unit\Listener;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Listener\RegisterFlowOperationsListener;
use OCA\WorkflowOcr\Operation;
use OCP\EventDispatcher\Event;
use OCP\Util;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;
use OCP\WorkflowEngine\Events\RegisterEntitiesEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCP\WorkflowEngine\IManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RegisterFlowOperationsListenerTest extends TestCase {
	/** @var ContainerInterface|MockObject */
	private $container;
	
	public function setUp() : void {
		parent::setUp();
		$this->container = $this->createMock(ContainerInterface::class);
	}

	#[DataProvider('dataProvider_NonRegisterOperationsEvent')]
	public function testDoesNothing_OnNonRegisterOpterationsEvent(callable $eventCallback) {
		/** @var Event */
		$event = $eventCallback($this);
		$this->container->expects($this->never())
			->method('get')
			->withAnyParameters();

		$listener = new RegisterFlowOperationsListener($this->container);

		$listener->handle($event);
	}

	public function testRegistersOperationClassAndScripts_OnRegisterOperationsEvent() {
		/** @var Operation|MockObject */
		$operationMock = $this->createMock(Operation::class);

		$this->container->expects($this->once())
			->method('get')
			->withAnyParameters()
			->willReturn($operationMock);

		/** @var IManager|MockObject */
		$manager = $this->createMock(IManager::class);
		$manager->expects($this->once())
			->method('registerOperation')
			->with($operationMock);

		$listener = new RegisterFlowOperationsListener($this->container);

		$listener->handle(new RegisterOperationsEvent($manager));

		$scripts = Util::getScripts();
		$appName = Application::APP_NAME;

		$scriptCount = 0;
		foreach ($scripts as $script) {
			if (strpos($script, "$appName/l10n/") !== false || strpos($script, "$appName/js/workflow_ocr-main") !== false) {
				$scriptCount++;
			}
		}
		
		$this->assertEquals(2, $scriptCount);
	}

	public static function dataProvider_NonRegisterOperationsEvent() {
		/** @var IManager */
		$managerCreator = fn (self $testClass) => $testClass->createMock(IManager::class);
		$arr = [
			[ fn (self $testClass) => new RegisterEntitiesEvent($managerCreator($testClass)) ],
			[ fn (self $testClass) => new RegisterChecksEvent($managerCreator($testClass)) ],
			[ fn () => new LoadSettingsScriptsEvent() ]
		];
		return $arr;
	}
}
