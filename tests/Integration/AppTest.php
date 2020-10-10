<?php
/**
 * Nextcloud - Files_PhotoSpheres
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @copyright Robin Windey 2020
 */

namespace OCA\WorkflowOcr\Tests\Integration;

use OC\AppFramework\Bootstrap\Coordinator;
use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Operation;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use \PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

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

	public function testOperationClassRegistered() {
		$this->runBootstrapRegistrations();
		$operation = $this->container->get(Operation::class);
		$this->assertInstanceOf(Operation::class, $operation);
	}

	private function runBootstrapRegistrations() {
		$bootstrapCoordinator = $this->container->get(Coordinator::class);
		
		// HACK:: reset registrations and simulate request start
		$reflectionClass = new ReflectionClass(Coordinator::class);
		$regContextProp = $reflectionClass->getProperty('registrationContext');
		$regContextProp->setAccessible(true);
		$regContextProp->setValue($bootstrapCoordinator, null);
		
		$bootstrapCoordinator->runRegistration();
	}
}
