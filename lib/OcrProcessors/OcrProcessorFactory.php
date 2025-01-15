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

namespace OCA\WorkflowOcr\OcrProcessors;

use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\OcrProcessors\Local\ImageOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\Local\PdfOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\Remote\WorkflowOcrRemoteProcessor;
use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class OcrProcessorFactory implements IOcrProcessorFactory {
	/**
	 * List of processors which are using a local CLI tool to perform OCR.
	 */
	private static $localMapping = [
		'application/pdf' => PdfOcrProcessor::class,
		'image/jpeg' => ImageOcrProcessor::class,
		'image/png' => ImageOcrProcessor::class
	];

	/**
	 * List of processors which are using a remote service (Workflow OCR Backend) to perform OCR.
	 */
	private static $remoteMapping = [
		'application/pdf' => WorkflowOcrRemoteProcessor::class,
		'image/jpeg' => WorkflowOcrRemoteProcessor::class,
		'image/png' => WorkflowOcrRemoteProcessor::class
	];

	public function __construct(
		private ContainerInterface $container,
		private IOcrBackendInfoService $ocrBackendInfoService,
	) {
	}

	public static function registerOcrProcessors(IRegistrationContext $context) : void {
		/*
		*	BUG #43: registerServiceAlias uses shared = false so every call to
		*	get() on the container interface will return a new instance. If we
		*	don't register this explicitly the instance will be cached as a kind of
		*	"singleton per request" which leads to problems regarding the reused Command object
		*	under the hood.
		*/
		$context->registerService(PdfOcrProcessor::class, fn (ContainerInterface $c) =>
			new PdfOcrProcessor(
				$c->get(ICommand::class),
				$c->get(LoggerInterface::class),
				$c->get(ISidecarFileAccessor::class),
				$c->get(ICommandLineUtils::class)), false);
		$context->registerService(ImageOcrProcessor::class, fn (ContainerInterface $c) =>
			new ImageOcrProcessor(
				$c->get(ICommand::class),
				$c->get(LoggerInterface::class),
				$c->get(ISidecarFileAccessor::class),
				$c->get(ICommandLineUtils::class)), false);
	}

	/** @inheritdoc */
	public function create(string $mimeType) : IOcrProcessor {
		$useRemoteBackend = $this->ocrBackendInfoService->isRemoteBackend();
		/** @var array */
		$mimeTypeToProcessorMapping = $useRemoteBackend ? self::$remoteMapping : self::$localMapping;

		if (!self::canCreate($mimeType, $mimeTypeToProcessorMapping)) {
			throw new OcrProcessorNotFoundException($mimeType, $useRemoteBackend);
		}
		$className = $mimeTypeToProcessorMapping[$mimeType];

		return $this->container->get($className);
	}

	private static function canCreate(string $mimeType, array $mimeTypeToProcessorMapping) : bool {
		return array_key_exists($mimeType, $mimeTypeToProcessorMapping);
	}
}
