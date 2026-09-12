<?php

declare(strict_types=1);

namespace OCA\WorkflowOcr\Tests\Unit\TestUtils;

/**
 * Standalone replacement for Nextcloud's Test\TestCase::invokePrivate, which is only
 * available inside a full Nextcloud checkout. Reflection-based private/protected method
 * invocation, same call shape as the Nextcloud original so call sites don't need to
 * change beyond using this trait instead of extending Test\TestCase.
 */
trait InvokesPrivateMethods {
	/**
	 * @param array<mixed> $args
	 * @return mixed
	 */
	protected function invokePrivate(object $object, string $methodName, array $args = []) {
		$reflectionMethod = new \ReflectionMethod($object, $methodName);
		$reflectionMethod->setAccessible(true);
		return $reflectionMethod->invokeArgs($object, $args);
	}
}
