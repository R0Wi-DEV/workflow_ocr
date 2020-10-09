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

use OCA\WorkflowOcr\Listener\RegisterFlowOperationsListener;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorFactory;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Service\OcrService;
use OCA\WorkflowOcr\Wrapper\Filesystem;
use OCA\WorkflowOcr\Wrapper\FpdiWrapper;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IFpdi;
use OCA\WorkflowOcr\Wrapper\IImagick;
use OCA\WorkflowOcr\Wrapper\ImagickWrapper;
use OCA\WorkflowOcr\Wrapper\IPdfParser;
use OCA\WorkflowOcr\Wrapper\ITesseractOcr;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCA\WorkflowOcr\Wrapper\IWrapperFactory;
use OCA\WorkflowOcr\Wrapper\PdfParserWrapper;
use OCA\WorkflowOcr\Wrapper\TesseractOcrWrapper;
use OCA\WorkflowOcr\Wrapper\ViewFactory;
use OCA\WorkflowOcr\Wrapper\WrapperFactory;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

class Application extends App implements IBootstrap {
	public const COMPOSER_DIR = __DIR__ . '/../../vendor/';
	public const APP_NAME = "workflow_ocr";

	/**
	 * Application constructor.
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(Application::APP_NAME, $urlParams);
	}

	/**
	 * @inheritdoc
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(IOcrService::class, OcrService::class);
		$context->registerServiceAlias(IOcrProcessorFactory::class, OcrProcessorFactory::class);
		$context->registerServiceAlias(IPdfParser::class, PdfParserWrapper::class);
		$context->registerServiceAlias(IImagick::class, ImagickWrapper::class);
		$context->registerServiceAlias(ITesseractOcr::class, TesseractOcrWrapper::class);
		$context->registerServiceAlias(IViewFactory::class, ViewFactory::class);
		$context->registerServiceAlias(IFpdi::class, FpdiWrapper::class);
		$context->registerServiceAlias(IWrapperFactory::class, WrapperFactory::class);
		$context->registerServiceAlias(IFilesystem::class, Filesystem::class);

		$context->registerEventListener(RegisterOperationsEvent::class, RegisterFlowOperationsListener::class);
	}

	/**
	 * @inheritdoc
	 */
	public function boot(IBootContext $context): void {
		$this->requireAutoload();
	}

	private function requireAutoload() {
		if (is_dir(self::COMPOSER_DIR) && file_exists(self::COMPOSER_DIR . 'autoload.php')) {
			require_once self::COMPOSER_DIR . 'autoload.php';
		} else {
			throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}
	}
}
