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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use PHPUnit\Framework\TestCase;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use OCA\WorkflowOcr\Wrapper\IFpdi;
use OCA\WorkflowOcr\Wrapper\IImagick;
use OCA\WorkflowOcr\Wrapper\IPdfParser;
use OCA\WorkflowOcr\Wrapper\ITesseractOcr;
use OCA\WorkflowOcr\Wrapper\IWrapperFactory;
use PHPUnit\Framework\MockObject\MockObject;
use \Smalot\PdfParser\Document;
use Smalot\PdfParser\Page;

class PdfOcrProcessorTest extends TestCase {
	/** @var MockObject|IPdfParser */
	private $pdfParser;
	/** @var MockObject|ITesseractOcr */
	private $tesseract;
	/** @var MockObject|IWrapperFactory */
	private $wrapperFactory;
	/** @var MockObject|IFpdi */
	private $fpdi;
	/** @var MockObject|IImagick */
	private $imagick;

	protected function setUp(): void {
		parent::setUp();

		$this->pdfParser = $this->createMock(IPdfParser::class);
		$this->tesseract = $this->createMock(ITesseractOcr::class);
		$this->wrapperFactory = $this->createMock(IWrapperFactory::class);
		$this->fpdi = $this->createMock(IFpdi::class);
		$this->imagick = $this->createMock(IImagick::class);
		$this->wrapperFactory->method('createFpdi')
				->withAnyParameters()
				->willReturn($this->fpdi);
		$this->wrapperFactory->method('createImagick')
				->with()
				->willReturn($this->imagick);
	}

	public function testThrowsOcrNotPossibleException_IfPdfContainsPagesWithTextOnly() {
		/*
			Setup fake PDF document with 2 pages containing text-layer
		*/
		$fakePdfDocument = $this->setUpFakePdfDocument('Page1Text', 'Page2Text');
		$this->pdfParser->expects($this->once())
			->method('parseContent')
			->with('someBinaryPdfContent')
			->willReturn($fakePdfDocument);

		$this->expectException(OcrNotPossibleException::class);
		$pdfProcessor = new PdfOcrProcessor($this->pdfParser, $this->tesseract, $this->wrapperFactory);
		$pdfProcessor->ocrFile('someBinaryPdfContent');
	}

	public function testSplitPdfIsDone() {
		/*
			Setup fake PDF document with 3 pages containing no text-layers
		*/
		$fakePdfDocument = $this->setUpFakePdfDocument('', '', '');
		$this->pdfParser->expects($this->once())
			->method('parseContent')
			->with('someBinaryPdfContent')
			->willReturn($fakePdfDocument);
		$this->fpdi->expects($this->once())
			->method('getPageCount')
			->with()
			->willReturn(3);
		$this->fpdi->method('getTemplatesize')
			->willReturn([
				'orientation' => 'someOrientation',
				'width' => 50,
				'height' => 50
			]);
		$this->fpdi->method('Output')
			->with(null, "S")
			->willReturn('someBinaryPdfContentOfOnePage');
		$this->fpdi->expects($this->atLeast(3))
			->method('import')
			->with($this->logicalOr($this->equalTo(1), $this->equalTo(2), $this->equalTo(3)));

		$pdfProcessor = new PdfOcrProcessor($this->pdfParser, $this->tesseract, $this->wrapperFactory);
		$pdfProcessor->ocrFile('someBinaryPdfContent');
	}

	public function testOcrIsCalledForEachPageWithoutText() {
		/*
			Setup fake PDF document with 3 pages. 2 without text-layer and one with text-layer.
		*/
		$fakePdfDocument = $this->setUpFakePdfDocument('', 'thisPageContainsText', '');
		$this->pdfParser->expects($this->once())
			->method('parseContent')
			->with('someBinaryPdfContent')
			->willReturn($fakePdfDocument);
		$this->fpdi->expects($this->once())
			->method('getPageCount')
			->with()
			->willReturn(3);
		$this->fpdi->method('getTemplatesize')
			->willReturn([
				'orientation' => 'someOrientation',
				'width' => 50,
				'height' => 50
			]);
		$this->fpdi->method('Output')
			->with(null, "S")
			->willReturn('someBinaryPdfContentOfOnePage');
		
		$imageBlob = 'someImageBlob';
		$imageSize = 16;
		$this->imagick->method('getImageBlob')
			 ->with()
			 ->willReturn($imageBlob);
		$this->imagick->method('getImageLength')
			 ->with()
			 ->willReturn($imageSize);

		// These methods are called for each page which is processed
		$this->tesseract->expects($this->exactly(2))
			->method('lang')
			->with(['deu', 'eng'])
			->willReturn($this->tesseract);
		$this->tesseract->expects($this->exactly(2))
			->method('imageData')
			->with($imageBlob, $imageSize)
			->willReturn($this->tesseract);
		$this->tesseract->expects($this->exactly(2))
			->method('configFile')
			->with('pdf')
			->willReturn($this->tesseract);
		$this->tesseract->expects($this->exactly(2))
			->method('run')
			->with();

		$pdfProcessor = new PdfOcrProcessor($this->pdfParser, $this->tesseract, $this->wrapperFactory);
		$pdfProcessor->ocrFile('someBinaryPdfContent');
	}

	private function setUpFakePdfDocument(...$pageTexts) : MockObject {
		$pageArray = [];
		foreach ($pageTexts as $pageText) {
			$fakePage = $this->createMock(Page::class);
			$fakePage->expects($this->once())
				->method('getText')
				->with()
				->willReturn($pageText);
			$pageArray[] = $fakePage;
		}

		$fakePdfDocument = $this->createMock(Document::class);
		$fakePdfDocument->expects($this->once())
			->method('getPages')
			->with()
			->willReturn($pageArray);
		$fakePdfDocument->expects($this->once())
			->method('getDetails')
			->with()
			->willReturn([]);

		return $fakePdfDocument;
	}
}
