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


namespace OCA\WorkflowOcr\AppInfo;

use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorFactory;
use OCA\WorkflowOcr\Operation;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Service\OcrService;
use OCA\WorkflowOcr\Wrapper\FpdiWrapper;
use OCA\WorkflowOcr\Wrapper\IFpdi;
use OCA\WorkflowOcr\Wrapper\IImagick;
use OCA\WorkflowOcr\Wrapper\ImagickWrapper;
use OCA\WorkflowOcr\Wrapper\IPdfParser;
use OCA\WorkflowOcr\Wrapper\ITesseractOcr;
use OCA\WorkflowOcr\Wrapper\IView;
use OCA\WorkflowOcr\Wrapper\IWrapperFactory;
use OCA\WorkflowOcr\Wrapper\PdfParserWrapper;
use OCA\WorkflowOcr\Wrapper\TesseractOcrWrapper;
use OCA\WorkflowOcr\Wrapper\ViewWrapper;
use OCA\WorkflowOcr\Wrapper\WrapperFactory;
use OCP\WorkflowEngine\IManager;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends \OCP\AppFramework\App {

	const APP_NAME = "workflow_ocr";

	/**
	 * Application constructor.
	 */
	public function __construct() {
		parent::__construct(Application::APP_NAME);

		$this->registerWorkflow();
		$this->registerDependencies();
	}

	private function registerWorkflow() :void {
		\OC::$server->getEventDispatcher()->addListener(IManager::EVENT_NAME_REG_OPERATION, function (GenericEvent $event) {
			$operation = \OC::$server->query(Operation::class);
			$event->getSubject()->registerOperation($operation);
			\OC_Util::addScript(Application::APP_NAME, 'admin');
		});
	}

	private function registerDependencies() : void {
		$container = $this->getContainer();

		$container->registerAlias(IOcrService::class, OcrService::class);
		$container->registerAlias(IOcrProcessorFactory::class, OcrProcessorFactory::class);
		$container->registerAlias(IPdfParser::class, PdfParserWrapper::class);
		$container->registerAlias(IImagick::class, ImagickWrapper::class);
		$container->registerAlias(ITesseractOcr::class, TesseractOcrWrapper::class);
		$container->registerAlias(IView::class, ViewWrapper::class);
		$container->registerAlias(IFpdi::class, FpdiWrapper::class);
		$container->registerAlias(IWrapperFactory::class, WrapperFactory::class);
	}
}
