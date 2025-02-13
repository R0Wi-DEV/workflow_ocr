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

namespace OCA\WorkflowOcr\Tests\Unit\Wrapper;

include_once __DIR__ . '/../../../../../build/stubs/app_api.php';

use OCA\AppAPI\PublicFunctions;
use OCA\WorkflowOcr\Wrapper\AppApiWrapper;
use OCP\Http\Client\IResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AppApiWrapperTest extends TestCase {
	private $container;
	private $publicFunctions;
	private $appApiWrapper;

	protected function setUp(): void {
		$this->container = $this->createMock(ContainerInterface::class);
		$this->publicFunctions = $this->createMock(PublicFunctions::class);
		$this->container->method('get')->willReturn($this->publicFunctions);
		$this->appApiWrapper = new AppApiWrapper($this->container);
	}

	public function testExAppRequest(): void {
		$response = $this->createMock(IResponse::class);
		$this->publicFunctions->expects($this->once())
			->method('exAppRequest')
			->with('appId', 'route')
			->willReturn($response);

		$result = $this->appApiWrapper->exAppRequest('appId', 'route');

		$this->assertInstanceOf(IResponse::class, $result);
		$this->assertEquals($response, $result);
	}

	public function testExAppRequestWithParams(): void {
		$response = $this->createMock(IResponse::class);
		$params = ['key' => 'value'];
		$options = ['option' => 'value'];
		$request = $this->createMock(IRequest::class);

		$this->publicFunctions->expects($this->once())
			->method('exAppRequest')
			->with('appId', 'route', 'userId', 'POST', $params, $options, $request)
			->willReturn($response);

		$result = $this->appApiWrapper->exAppRequest('appId', 'route', 'userId', 'POST', $params, $options, $request);

		$this->assertInstanceOf(IResponse::class, $result);
		$this->assertEquals($response, $result);
	}

	public function testGetExApp(): void {
		$appData = ['name' => 'appName'];
		$this->publicFunctions->expects($this->once())
			->method('getExApp')
			->with('appName')
			->willReturn($appData);

		$result = $this->appApiWrapper->getExApp('appName');

		$this->assertIsArray($result);
		$this->assertEquals($appData, $result);
	}
}
