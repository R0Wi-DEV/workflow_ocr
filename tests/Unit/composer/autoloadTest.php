<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\composer;

use OCA\WorkflowOcr\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;

class composerTest extends TestCase {
	public function testAutoloaderFileCanBeLoaded() {
		$app = new App(Application::APP_NAME);
		$container = $app->getContainer();
		/** @var IAppManager */
		$appManager = $container->get(IAppManager::class);
		$path = $appManager->getAppPath(Application::APP_NAME);
		$autoloaderFile = $path . '/composer/autoload.php';
		$this->assertTrue(file_exists($autoloaderFile));
		require $autoloaderFile;
	}
}
