<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\BackgroundJobs;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use \OCP\Files\File;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\Files\FileInfo;
use OCP\Files\Node;

/**
 * Represents a QuedJob which processes
 * a OCR on a single file.
 */
class ProcessFileJob extends \OC\BackgroundJob\QueuedJob {

	/** @var ILogger */
	protected $logger;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IOcrService */
	private $ocrService;
	/** @var IViewFactory */
	private $viewFactory;
	/** @var IFilesystem */
	private $filesystem;
	
	public function __construct(
		ILogger $logger,
		IRootFolder $rootFolder,
		IOcrService $ocrService,
		IViewFactory $viewFactory,
		IFilesystem $filesystem) {
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->ocrService = $ocrService;
		$this->viewFactory = $viewFactory;
		$this->filesystem = $filesystem;
	}
	
	/**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
		$this->logger->debug('Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
		
		list($success, $filePath) = $this->parseArguments($argument);
		if (!$success) {
			return;
		}

		try {
			$this->runInternal($filePath);
		} catch (\Throwable $ex) {
			$this->logger->logException($ex);
		}
	}

	/**
	 * @param mixed $argument
	 */
	private function parseArguments($argument) : array {
		$filePath = $argument['filePath'];

		if (!isset($filePath)) {
			$this->logger->warning('Variable \'filePath\' not set in ' . self::class . ' method \'parseArguments\'.');
		}

		return [
			isset($filePath),
			$filePath
		];
	}

	/**
	 * @param string $filePath  The file to be processed
	 */
	private function runInternal(string $filePath) : void {
		$this->initFileSystem($filePath);
		
		try {
			/** @var File */
			$node = $this->rootFolder->get($filePath);
		} catch (NotFoundException $ex) {
			$this->logger->warning('Could not process file \'' . $filePath . '\'. File was not found');
			return;
		}

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			$this->logger->info('Skipping process for \'' . $filePath . '\'. It is not a file');
			return;
		}
		try {
			$ocrFile = $this->ocrFile($node);
		} catch (OcrNotPossibleException $ocrNpEx) {
			$this->logger->info('OCR for file ' . $node->getPath() . ' not possible. Message: ' . $ocrNpEx->getMessage());
			return;
		} catch (OcrProcessorNotFoundException $ocrProcNfEx) {
			$this->logger->info('OCR processor not found for mimetype ' . $node->getMimeType());
			return;
		}

		$dirPath = dirname($filePath);
		$filePath = basename($filePath);

		// Create new file or file-version with OCR-file
		$view = $this->viewFactory->create($dirPath);
		$view->file_put_contents($filePath, $ocrFile);
	}

	private function initFileSystem(string $filePath) : void {
		$pathSegments = explode('/', $filePath, 4);
		$user = $pathSegments[1];
		$rootFolder = '/' . $pathSegments[1] . '/files';
		$this->filesystem->init($user, $rootFolder);
	}

	private function ocrFile(File $file) : string {
		return $this->ocrService->ocrFile($file->getMimeType(), $file->getContent());
	}
}
