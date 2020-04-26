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

use Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use \OC\Files\Node\File;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Represents a QuedJob which processes 
 * a OCR on a single file.
 */
class ProcessFileJob extends \OC\BackgroundJob\QueuedJob {

    /** @var ILogger */
	protected $logger;
	/** @var IRootFolder */
    private $rootFolder;
    
    /**
	 * ProcessFileJob constructor.
	 *
	 * @param ILogger $logger
     * @param IRootFolder $rootFolder
	 */
	public function __construct(ILogger $logger, IRootFolder $rootFolder) {
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
    }
    
    /**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
        $this->logger->debug('Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
        
        $filePath = $argument['filePath'];

        if (!isset($filePath)){
            $this->logger->warning('Variable \'filePath\' not set in ' . self::class . ' method \'run\'.');
            return;
        }

        $this->initFileSystem($filePath);

        try {
            /** @var File */
            $node = $this->rootFolder->get($filePath);
        }
        catch(NotFoundException $ex) {
            $this->logger->warning('Could not process file \'' . $filePath . '\'. File was not found.action-container');
            return;
        }

        if (!$node instanceof File) {
            $this->logger->info('Skipping process for \'' . $filePath . '\'. It is not a file.');
            return;
        }
        
        $pdf = $this->ocrFile($node);
            
        $dirPath = dirname($filePath);
        $filePath = basename($filePath);

        $view = new \OC\Files\View($dirPath);
        $view->file_put_contents($filePath, $pdf);
    }

    private function initFileSystem(string $filePath) : void {
        $pathSegments = explode('/', $filePath, 4);
        \OC\Files\Filesystem::init($pathSegments[1], '/' . $pathSegments[1] . '/files');
    }

    private function ocrFile(File $file) : string {
        $filePath = $file->getPath();
        try {
            // TODO :: check file mime type
            return $this->ocrPdf($file);
        }
        catch(Exception $ex) {
            $this->logger->logException($ex, ['message' => 'Could not OCR file \'' . $filePath . '\'']);
            return null;
        }
    }

    private function ocrPdf(File $file) : string {
        $img = new \Imagick();
		$img->setOption('density', '300');
		$img->readImageBlob($file->getContent());
		$img->setImageFormat("png");
		$data = $img->getImageBlob();
		$size = $img->getImageLength();

		$pdf = (new TesseractOCR())
			->lang('deu')
			->imageData($data, $size)
			->pdf()
            ->run();   

        return $pdf;
    }
}