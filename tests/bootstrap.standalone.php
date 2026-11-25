<?php

declare(strict_types=1);

/**
 * Bootstrap for the standalone unit-test suite (phpunit.standalone.xml).
 *
 * Unlike tests/bootstrap.php, this does NOT require a full Nextcloud checkout.
 * It only loads this app's own composer autoloader, which in turn provides:
 *  - OCA\WorkflowOcr\* (this app, via the PSR-4 mapping in composer.json)
 *  - OCP\* / NCU\* (via the "nextcloud/ocp" composer dependency)
 *  - PHPUnit\* and all other require-dev tooling
 *
 * Kept deliberately minimal: no \OC::$server, no DI container, no DB.
 *
 * Seven files that touch a real, non-trivial Nextcloud implementation (not just an
 * interface) are excluded from this suite (see phpunit.standalone.xml, group
 * "nextcloud-full") and run only under phpunit.xml inside a full Nextcloud checkout:
 * AppInfo/ApplicationTest.php, OcrProcessors/OcrProcessorFactoryTest.php and
 * composer/AutoloadTest.php (construct a real OCP\AppFramework\App, needing
 * \OC::$server and Nextcloud's internal DI container), Wrapper/ViewFactoryTest.php
 * (real OC\Files\View), BackgroundJobs/ProcessFileJobTest.php and
 * Listener/RegisterFlowOperationsListenerTest.php (call real OCP methods that
 * internally use OCP\Server::get()/\OC::$server), and Notification/NotifierTest.php
 * (real OC\Notification\Notification, whose validation logic is too much to safely
 * polyfill).
 */
require_once __DIR__ . '/../vendor/autoload.php';

// lib/Service/OcrService.php and tests/Unit/Service/OcrServiceTest.php reference
// Nextcloud's internal (non-OCP) OC\User\NoUserException. It's not guaranteed that
// nextcloud/ocp ships this legacy OC\ class (its stated scope is OCP/ + NCU/, not
// general OC\ internals). Polyfill it only if it isn't already autoloadable, so
// neither production code nor the test needs to change either way.
if (!class_exists(\OC\User\NoUserException::class)) {
	require_once __DIR__ . '/Unit/TestUtils/NoUserExceptionPolyfill.php';
}

// lib/Service/OcrService.php and tests/Unit/Service/OcrServiceTest.php also reference
// the built-in files_versions app's public interfaces (OCA\Files_Versions\Versions\*)
// and tests/Unit/Wrapper/AppApiWrapperTest.php mocks the app_api app's
// OCA\AppAPI\PublicFunctions -- both separate apps, not part of nextcloud/ocp's
// OCP/NCU scope. Polyfilled the same way, only if not already autoloadable.
if (!interface_exists(\OCA\Files_Versions\Versions\IVersionManager::class)) {
	require_once __DIR__ . '/Unit/TestUtils/FilesVersionsPolyfill.php';
}
if (!class_exists(\OCA\AppAPI\PublicFunctions::class)) {
	require_once __DIR__ . '/Unit/TestUtils/AppApiPublicFunctionsPolyfill.php';
}

// tests/Unit/Service/NotificationServiceTest.php mocks Nextcloud's internal (non-OCP)
// OC\Notification\Notification (createMock() needs the class to exist and to satisfy
// INotification's return-type declarations). Polyfilled the same way, only if not
// already autoloadable. tests/Unit/Notification/NotifierTest.php instantiates the real
// class (needing its actual validation logic) and stays excluded from this suite
// instead of relying on this polyfill -- see phpunit.standalone.xml.
if (!class_exists(\OC\Notification\Notification::class)) {
	require_once __DIR__ . '/Unit/TestUtils/NotificationPolyfill.php';
}
