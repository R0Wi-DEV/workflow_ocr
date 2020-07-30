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

namespace OCA\WorkflowOcr\Wrapper;

use setasign\Fpdi\Tcpdf\Fpdi;

class FpdiWrapper extends Fpdi implements IFpdi {
    /** @var resource[] */
    private $streams = [];
    /** @var int */
    private $pageCount;

    public function __construct(string $pdfContent = '') {
        parent::__construct();

        if ($pdfContent !== '') {
            $this->setContent($pdfContent);
        }

        $this->setPrintFooter(false);
        $this->setPrintHeader(false);
    }

    public function setContent(string $pdfContent) : void {
        $stream = $this->createStream($pdfContent);
        $this->pageCount = $this->setSourceFile($stream);
    }

    public function getPageCount(): int {
        return $this->pageCount;
    }

    public function closeStreams() : void {
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
    }

    public function import(int $pageNumber) : string {
        return $this->importPage($pageNumber);
    }

    private function createStream(string $pdfContent) {
        $stream = fopen('php://temp', 'r+');

        if (!$stream) {
            throw new \Exception("Could not open PDF stream");
        }

        fwrite($stream, $pdfContent);
        rewind($stream);

        $this->streams[] = $stream;

        return $stream;
    }
}