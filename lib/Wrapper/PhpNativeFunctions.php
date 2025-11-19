<?php

declare(strict_types=1);

namespace OCA\WorkflowOcr\Wrapper;

class PhpNativeFunctions implements IPhpNativeFunctions {
	public function streamGetContents($handle) {
		return stream_get_contents($handle);
	}

	public function fopen(string $filename, string $mode) {
		return fopen($filename, $mode);
	}
}
