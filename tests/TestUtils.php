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

use OCA\WorkflowOcr\AppInfo;
use OCP\AppFramework\App;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

class TestUtils {
	/** @var ContainerInterface */
	private $container;

	public function __construct() {
		$app = new App(AppInfo\Application::APP_NAME);
		$this->container = $app->getContainer();
	}

	public function createUser($user, $pw) : IUser {
		/** @var IUserManager */
		$userManager = $this->container->get(IUserManager::class);
		return $userManager->createUser($user, $pw);
	}

	public function initUserEnvironment(IUser $user) : void {
		$this->setUser($user);
	}

	public function shutDownUserEnvironment() : void {
		$this->setUser(null);
	}

	private function setUser($user) : void {
		/** @var IUserSession */
		$userSession = $this->container->get(IUserSession::class);
		$userSession->setUser($user);
	}
}
