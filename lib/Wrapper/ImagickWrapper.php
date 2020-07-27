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

class ImagickWrapper implements IImagick {
    /** @var \Imagick */
    private $wrappedImagick; 

    public function __construct() {
        $this->wrappedImagick = new \Imagick();
    }

    /**
     * @inheritdoc
     */
    public function setOption(string $key, string $value): void {
        $this->wrappedImagick->setOption($key, $value);
    }
    
    /**
     * @inheritdoc
     */
    public function readImageBlob(string $fileContent): void {
        $this->wrappedImagick->readImageBlob($fileContent);
    }
    
    /**
     * @inheritdoc
     */
    public function setImageFormat(string $targetFormat): void {
        $this->wrappedImagick->setImageFormat($targetFormat);
    }
    
    /**
     * @inheritdoc
     */
    public function getImageBlob(): string {
        return $this->wrappedImagick->getImageBlob();
    }
    
    /**
     * @inheritdoc
     */
    public function getImageLength(): int {
        return $this->wrappedImagick->getImageLength();
    }

    /**
     * @inheritdoc
     */
    public function getNumberImages(): int {
        return (int)$this->wrappedImagick->getNumberImages();
    }

    /**
     * @inheritdoc
     */
    public function clear(): void {
        $this->wrappedImagick->clear();
    }

    /**
     * @return mixed
     */
    public function current() {
        return $this->wrappedImagick->current();
    }
    
    /**
     * @return scalar
     */
    public function key() {
        return $this->wrappedImagick->key();
    }

    public function next() : void {
        $this->wrappedImagick->next();
    }

    public function rewind() : void {
        $this->wrappedImagick->rewind();
    }

    public function valid() : bool {
        return $this->wrappedImagick->valid();
    }

    public function destroy() : void {
        $this->wrappedImagick->destroy();
    }
}