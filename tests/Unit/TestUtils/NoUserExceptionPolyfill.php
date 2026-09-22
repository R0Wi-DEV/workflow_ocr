<?php

declare(strict_types=1);

/**
 * Minimal polyfill for Nextcloud's internal OC\User\NoUserException, loaded only by
 * tests/bootstrap.standalone.php and only if nextcloud/ocp doesn't already provide the
 * real class. Not used by phpunit.xml/phpunit.integration.xml, where the real Nextcloud
 * class from a full checkout is always used instead.
 *
 * Keep in sync with the real class's public shape (extends \Exception) if Nextcloud
 * ever changes it in a way lib/Service/OcrService.php relies on.
 */

namespace OC\User;

class NoUserException extends \Exception {
}
