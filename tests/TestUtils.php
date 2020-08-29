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

namespace OCA\WorkflowOcr\Tests;

use OCP\AppFramework\IAppContainer;
use OCA\WorkflowOcr\AppInfo;
use OCP\AppFramework\App;
use OCP\IUser;
use OCP\IUserManager;

class TestUtils {

	/** @var IAppContainer */
	private $container;

	public function __construct() {
		$app = new App(AppInfo\Application::APP_NAME);
		$this->container = $app->getContainer();
	}

	public function createUser($user, $pw) : IUser {
		/** @var IUserManager */
		$userManager = $this->container->query(IUserManager::class);
		return $userManager->createUser($user, $pw);
	}
}
