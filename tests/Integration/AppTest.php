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
 * @copyright Robin Windey 2019
 */

namespace OCA\WorkflowOcr\Tests\Integration\Controller;

use OCA\WorkflowOcr\AppInfo;

use OCP\AppFramework\App;
use \PHPUnit\Framework\TestCase;

/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class AppTest extends TestCase {
	private $container;

	public function setUp() {
		parent::setUp();
		$app = new App(AppInfo\Application::APP_NAME);
		$this->container = $app->getContainer();
	}

	public function testAppInstalled() {
		$appManager = $this->container->query('OCP\App\IAppManager');
		$this->assertTrue($appManager->isInstalled(AppInfo\Application::APP_NAME));
	}
}
