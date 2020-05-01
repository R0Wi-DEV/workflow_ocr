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
use thiagoalessio\TesseractOCR\TesseractOCR;

class PdfOcrProcessor implements IOcrProcessor {
    /** @var IPdfParser */
    private $pdfParser;
    /** @var IImagick */
    private $imagick;
    /** @var ITesseractOcr */
    private $tesseract;

    public function __construct(IPdfParser $pdfParser, IImagick $imagick, ITesseractOcr $tesseract) {
        $this->pdfParser = $pdfParser;    
        $this->imagick = $imagick;
        $this->tesseract = $tesseract;
    }

    public function ocrFile(string $fileContent): string {
        if ($this->checkContainsText($fileContent)) {
            throw new OcrNotPossibleException('Pdf contains text');
        }
        
        $this->imagick->setOption('density', '300');
        $this->imagick->readImageBlob($fileContent);
        $this->imagick->setImageFormat("png");
        $data = $this->imagick->getImageBlob();
        $size = $this->imagick->getImageLength();

        $pdf = $this->tesseract
            ->lang(['deu']) // TODO config
            ->imageData($data, $size)
            ->configFile('pdf')
            ->run();

        return $pdf;
    }

    private function checkContainsText(string $pdfContent) : bool {
        $pdf = $this->pdfParser->parseContent($pdfContent);

        $pages = $pdf->getPages();

        foreach ($pages as $page) {
            $txt = $page->getText();
            if (!empty($txt) && !empty(trim($txt))) {
                return true;
            }
        }

        return false;
    }
}
