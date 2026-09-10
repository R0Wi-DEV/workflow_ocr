<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

namespace OCA\WorkflowOcr\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

abstract class ControllerBase extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Executes the given function and wraps the result into a JSONResponse. Errors are
	 * logged server side only; the response contains a generic message to not leak any
	 * internal details (paths, configuration, ...) to the client.
	 */
	protected function tryExecute(callable $function) : JSONResponse {
		try {
			$result = $function();
			return new JSONResponse($result);
		} catch (\Throwable $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['error' => 'Internal server error'], 500);
		}
	}
}
