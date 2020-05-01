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

namespace OCA\WorkflowOcr\Wrapper;

use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractOcrWrapper implements ITesseractOcr {
    /** @var TesseractOCR */
    private $wrappedTesseract;

    public function __construct() {
        $this->wrappedTesseract = new TesseractOCR();
    }

    public function configFile(string $config) : ITesseractOcr {
        $this->wrappedTesseract->configFile($config);
        return $this;
    }

    function lang(array $langs) : ITesseractOcr {
		call_user_func_array([$this->wrappedTesseract, 'lang'], array_map('trim', $langs));
        return $this;
    }

    function imageData(string $data, int $size) : ITesseractOcr {
        $this->wrappedTesseract->imageData($data, $size);
        return $this;
    }
    
    function run() : string {
        return $this->wrappedTesseract->run();
    }
}
