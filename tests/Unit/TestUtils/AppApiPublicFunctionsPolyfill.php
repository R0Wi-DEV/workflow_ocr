<?php

declare(strict_types=1);

/**
 * Minimal polyfill for the app_api app's OCA\AppAPI\PublicFunctions, loaded only by
 * tests/bootstrap.standalone.php and only if nextcloud/ocp doesn't already provide it
 * (it doesn't -- app_api is a separate app, not part of the OCP/NCU core package).
 * Not used by phpunit.xml/phpunit.integration.xml, where the real class from a full
 * checkout is always used instead. Only used as a tests/Unit/Wrapper/AppApiWrapperTest.php
 * mock target -- lib/Wrapper/AppApiWrapper.php never sees this polyfill under either
 * suite, real or standalone, so the method bodies are never actually reached.
 *
 * Signature mirrors OCA\AppAPI\PublicFunctions (nextcloud/app_api, lib/PublicFunctions.php).
 */

namespace OCA\AppAPI;

use OCP\Http\Client\IResponse;
use OCP\IRequest;

class PublicFunctions {
	public function exAppRequest(
		string $appId,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): array|IResponse {
		throw new \RuntimeException('Polyfill stub -- the app_api app is not available in the standalone test suite.');
	}

	public function getExApp(string $appId): ?array {
		throw new \RuntimeException('Polyfill stub -- the app_api app is not available in the standalone test suite.');
	}
}
