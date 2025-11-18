<?php

declare(strict_types=1);

namespace OCA\WorkflowOcr\Wrapper;

interface IPhpNativeFunctions {
	/**
	 * Wrapper around PHP's native stream_get_contents.
	 * Returns string on success or false on failure.
	 *
	 * @param resource $handle
	 * @return string|false
	 */
	public function streamGetContents($handle);

	/**
	 * Wrapper around PHP's native fopen.
	 * Returns a resource on success or false on failure.
	 *
	 * @param string $filename
	 * @param string $mode
	 * @return resource|false
	 */
	public function fopen(string $filename, string $mode);
}
