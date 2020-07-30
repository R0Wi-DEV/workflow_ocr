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

interface IFpdi {
	public function setContent(string $pdfContent) : void;
	public function getPageCount(): int;
	public function closeStreams() : void;
	public function import(int $pageNumber) : string;
	public function getTemplateSize(string $tpl);
	public function AddPage($orientation='', $format='', $keepmargins=false, $tocpage=false);
	public function useImportedPage(string $pageId, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false);
	public function Output($name='doc.pdf', $dest='I');
	public function Close();
}
