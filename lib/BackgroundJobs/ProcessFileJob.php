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
use OC\User\NoUserException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use \OCP\Files\File;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Represents a QuedJob which processes 
 * a OCR on a single file.
 */
class ProcessFileJob extends \OC\BackgroundJob\QueuedJob {

    /** @var ILogger */
	protected $logger;
	/** @var IRootFolder */
    private $rootFolder;
    /** @var IUserManager */
    private $userManager;
    /** @var IUserSession */
    private $userSession;
    /** @var IOcrProcessorFactory */
    private $ocrProcessorFactory;
    
	public function __construct(
        ILogger $logger, 
        IRootFolder $rootFolder, 
        IUserManager $userManager, 
        IUserSession $userSession,
        IOcrProcessorFactory $ocrProcessorFactory) {
		$this->logger = $logger;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->ocrProcessorFactory = $ocrProcessorFactory;
    }
    
    /**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
        $this->logger->debug('Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
        
        list($success, $filePath, $uid) = $this->parseArguments($argument);
        if (!$success) {
            return;
        }

        try {
            $this->initUserEnvironment($uid);
            $this->runInternal($filePath, $uid);
        }
        catch(Exception $ex) {
            $this->logger->logException($ex);
        }
        finally {
            $this->shutdownUserEnvironment($uid);
        }  
    }

    /**
	 * @param mixed $argument
	 */
    private function parseArguments($argument) : array {
        $filePath = $argument['filePath'];
        $uid = $argument['uid'];

        if (!isset($filePath)){
            $this->logger->warning('Variable \'filePath\' not set in ' . self::class . ' method \'parseArguments\'.');
        }
        if (!isset($uid)) {
            $this->logger->warning('Variable \'uid\' not set in ' . self::class . ' method \'parseArguments\'.');
        }

        return [
            isset($filePath) && isset($uid),
            $filePath,
            $uid
        ];
    }

    /**
     * @param string $filePath  The file to be processed
     */
    private function runInternal(string $filePath) : void {
        try {
            /** @var File */
            $node = $this->rootFolder->get($filePath);
        }
        catch(NotFoundException $ex) {
            $this->logger->warning('Could not process file \'' . $filePath . '\'. File was not found.');
            return;
        }

        if (!$node instanceof File) {
            $this->logger->info('Skipping process for \'' . $filePath . '\'. It is not a file.');
            return;
        }
        try {
            $pdf = $this->ocrFile($node);
        }
        catch(OcrNotPossibleException $ocrNpEx) {
            $this->logger->info($ocrNpEx->getMessage());
            return;
        }
        catch(OcrProcessorNotFoundException $ocrProcNfEx) {
            $this->logger->info($ocrProcNfEx->getMessage());
            return;
        }

        $dirPath = dirname($filePath);
        $filePath = basename($filePath);

        $view = new \OC\Files\View($dirPath);
        $view->file_put_contents($filePath, $pdf);
    }

    /**
     * @param string $uid
     */
    private function shutdownUserEnvironment(string $uid) : void {
        $this->userSession->setUser(null);
    }

    /**
     * @param string $uid
     */
    private function initUserEnvironment(string $uid) : void {
        /** @var IUser */
        $user = $this->userManager->get($uid);
        if (!$user) {
            throw new NoUserException("User '$user' was not found");
        }
        $this->userSession->setUser($user);

        \OC\Files\Filesystem::init($uid, '/' . $uid . '/files');
    }

    private function ocrFile(File $file) : string {
        $ocrProcessor = $this->ocrProcessorFactory->create($file->getMimeType());
        return $ocrProcessor->ocrFile($file);
    }
}