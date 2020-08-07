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

use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Wrapper\IImagick;
use OCA\WorkflowOcr\Wrapper\IPdfParser;
use OCA\WorkflowOcr\Wrapper\ITesseractOcr;
use OCA\WorkflowOcr\Wrapper\IWrapperFactory;

class PdfOcrProcessor implements IOcrProcessor {
	/** @var IPdfParser */
	private $pdfParser;
	/** @var ITesseractOcr */
	private $tesseract;
	/** @var IWrapperFactory */
	private $wrapperFactory;

	public function __construct(IPdfParser $pdfParser, ITesseractOcr $tesseract, IWrapperFactory $wrapperFactory) {
		$this->pdfParser = $pdfParser;
		$this->tesseract = $tesseract;
		$this->wrapperFactory = $wrapperFactory;
	}

	public function ocrFile(string $fileContent): string {
		// Parse the content of the given file as PDF
		$pdfContent = $this->pdfParser->parseContent($fileContent);

		// Get metadata from parsed PDF
		$metadata = $this->getMetadata($pdfContent);

		// Get information of all PDF pages if they contain text or not
		$pagesTextInfo = $this->getPagesTextInfo($pdfContent);

		// Check if at least one page in PDF has no text
		$this->ensureCanOcrPdf($pagesTextInfo);

		// Split PDF into single pages
		$splitted = $this->splitPdf($fileContent);

		// OCR each single page PDF (if it does not contain text already)
		$this->ocrPages($splitted, $pagesTextInfo);

		// Merge results
		return $this->mergePdf($splitted, $metadata);
	}

	/*
	 * Returns an associative array (metadata name (string) => metadata value (string)) with PDF
	 * document information.
	 */
	private function getMetadata(&$pdfContent) : array {
		// Get metadata from PDF content
		$metadata = $pdfContent->getDetails();

		// Convert metadata either from Windows-1252 or Mac encoding to UTF-8
		return mb_convert_encoding($metadata, "UTF-8", "Windows-1252, Mac");
	}

	/**
	 * Returns an associative array (index (int) => containsText (bool)) with information, if the
	 * page contains text or not. Index starts a 1.
	 */
	private function getPagesTextInfo(&$pdfContent) : array {
		$tmpCnt = 1;
		$indices = [];
		$pages = $pdfContent->getPages();

		foreach ($pages as $page) {
			$txt = $page->getText();
			$indices[$tmpCnt++] = !empty($txt) && !empty(trim($txt));
		}

		return $indices;
	}

	private function ensureCanOcrPdf(array $pagesTextInfo) : void {
		$onePageWithoutText = false;

		foreach ($pagesTextInfo as $idx => $containsText) {
			if (!$containsText) {
				$onePageWithoutText = true;
				break;
			}
		}
		
		if (!$onePageWithoutText) {
			throw new OcrNotPossibleException('Pdf only contains pages with text');
		}
	}

	/**
	 * Splits PDF into associative array with 1-based index.
	 */
	private function splitPdf(string $pdfContent) : array {
		try {
			$fpdiWrapper = $this->wrapperFactory->createFpdi($pdfContent);
			$pagecount = $fpdiWrapper->getPageCount();
			$splitted = [];

			for ($i = 1; $i <= $pagecount; $i++) {
				$onePageFpdiWrapper = $this->wrapperFactory->createFpdi($pdfContent);
				$pageId = $onePageFpdiWrapper->import($i);
				$s = $onePageFpdiWrapper->getTemplatesize($pageId);
				$onePageFpdiWrapper->AddPage($s['orientation'], $s);
				$onePageFpdiWrapper->useImportedPage($pageId);

				try {
					$content = $onePageFpdiWrapper->Output(null, "S");
					$splitted[$i] = $content;
				} finally {
					$onePageFpdiWrapper->Close();
					$onePageFpdiWrapper->closeStreams();
				}
			}
		} finally {
			if (isset($fpdiWrapper)) {
				$fpdiWrapper->Close();
				$fpdiWrapper->closeStreams();
			}
		}

		return $splitted;
	}

	/**
	 * Process each PDF page with ocr algorithm except the pages which already
	 * contain a text layer.
	 */
	private function ocrPages(array &$splittedPdfPages, array $pagesTextInfo) : void {
		foreach ($splittedPdfPages as $i => $onePagePdf) {
			// Skip pages containing text
			if ($pagesTextInfo[$i] === true) {
				continue;
			}

			try {
				// Use Imagick to convert the pdf page to png
				$img = $this->wrapperFactory->createImagick();
				$img->setOption('density', '300');
				$img->readImageBlob($onePagePdf);
				$img->setImageFormat("png");

				$ocrPdf = $this->processSinglePageImagick($img);

				// Take original page format
				$original = $this->wrapperFactory->createFpdi($onePagePdf);
				$pageId = $original->import(1);
				$originalSize = $original->getTemplatesize($pageId);

				// Import single PDF page with ocr layer
				$withOcr = $this->wrapperFactory->createFpdi($ocrPdf);
				$pageIdOcr = $withOcr->import(1);
				$withOcr->AddPage($originalSize['orientation'], $originalSize);
				$withOcr->useImportedPage($pageIdOcr, 0, 0, $originalSize['width'], $originalSize['height'], false);

				// Overwrite original page with scanned one
				$splittedPdfPages[$i] = $withOcr->Output(null, "S");
			} finally {
				if (isset($img)) {
					$img->destroy();
				}
				if (isset($original)) {
					$original->Close();
					$original->closeStreams();
				}
				if (isset($withOcr)) {
					$withOcr->Close();
					$withOcr->closeStreams();
				}
			}
		}
	}

	private function processSinglePageImagick(IImagick $imagick) : string {
		$data = $imagick->getImageBlob();
		$size = $imagick->getImageLength();

		// Use Tesseract for ocr and converting image back to pdf
		$singlePagePdf = $this->tesseract
			->lang(['deu', 'eng']) // TODO make configurable?
			->imageData($data, $size)
			->configFile('pdf')
			->run();

		return $singlePagePdf;
	}

	/**
	 * Merges single page PDF array into one output PDF.
	 */
	private function mergePdf(array &$splitted, array $metadata) : string {
		try {
			$outputPdf = $this->wrapperFactory->createFpdi();

			foreach ($splitted as $i => $onePageOcrPdf) {
				$outputPdf->setContent($onePageOcrPdf);
				$pageId = $outputPdf->import(1);
				$s = $outputPdf->getTemplatesize($pageId);
				$outputPdf->AddPage($s['orientation'], $s);
				$outputPdf->useImportedPage($pageId);
			}

			// Set metadata in merged PDF
			// Note that Producer and CreationDate is overwritten by FPDF and ModDate is not set
			if (array_key_exists("Title", $metadata)) {
				$outputPdf->SetTitle($metadata['Title'], true);
			}
			if (array_key_exists("Author", $metadata)) {
				$outputPdf->SetAuthor($metadata['Author'], true);
			}
			if (array_key_exists("Subject", $metadata)) {
				$outputPdf->SetSubject($metadata['Subject'], true);
			}
			if (array_key_exists("Keywords", $metadata)) {
				$outputPdf->SetKeywords($metadata['Keywords'], true);
			}
			if (array_key_exists("Creator", $metadata)) {
				$outputPdf->SetCreator($metadata['Creator'], true);
			}

			// Output content of merged PDF as string
			$outputPdfContent = $outputPdf->Output(null, "S");
			return $outputPdfContent;
		} finally {
			if (isset($outputPdf)) {
				$outputPdf->Close();
				$outputPdf->closeStreams();
			}
		}
	}
}
