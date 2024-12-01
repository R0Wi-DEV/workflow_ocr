<?php

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

namespace OCA\WorkflowOcr\Tests\Integration;

use OC\AppFramework\Bootstrap\Coordinator;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Operation;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use Psr\Container\ContainerInterface;
use Test\TestCase;

/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class AppTest extends TestCase {
	/** @var ContainerInterface */
	private $container;

	/** @var IAppManager */
	private $appManager;

	protected function setUp() : void {
		parent::setUp();
		$app = new App(Application::APP_NAME);
		$this->container = $app->getContainer();
		$this->appManager = $this->container->get(IAppManager::class);
	}

	public function testAppInstalled() {
		$this->assertTrue($this->appManager->isInstalled(Application::APP_NAME));
	}

	/**
	 * @dataProvider trueFalseProvider
	 */
	public function testOperationClassRegistered(bool $lazy) {
		$this->runBootstrapRegistrations($lazy);
		$operation = $this->container->get(Operation::class);
		$this->assertInstanceOf(Operation::class, $operation);
	}

	public function testAppWorksWithNcAutoloader() {
		// We assume that the app is already loaded by our test bootstrapping.
		// 'Command' is one of the dependencies included by autoload.php
		$commandClassExists = class_exists('mikehaertl\shellcommand\Command');
		$this->assertTrue($commandClassExists);
	}

	public function trueFalseProvider() {
		return [
			[true],
			[false]
		];
	}

	private function runBootstrapRegistrations(bool $lazy) {
		/** @var Coordinator */
		$bootstrapCoordinator = $this->container->get(Coordinator::class);

		// HACK:: reset registrations and simulate request start
		$this->invokePrivate($bootstrapCoordinator, 'registrationContext', [null]);
		
		if ($lazy) {
			$bootstrapCoordinator->runLazyRegistration(Application::APP_NAME);
		} else {
			$bootstrapCoordinator->runInitialRegistration();
		}
	}
}
