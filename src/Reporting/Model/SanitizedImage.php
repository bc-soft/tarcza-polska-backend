<?php

declare(strict_types=1);

namespace App\Reporting\Model;

/** Output of ImageSanitizer: a baseline JPEG with no metadata, bounded in size. */
final readonly class SanitizedImage
{
    public function __construct(
        public string $jpegBytes,
        public int $width,
        public int $height,
        public string $sha256,
        public string $sourceMime,
    ) {
    }

    public function byteLength(): int
    {
        return \strlen($this->jpegBytes);
    }
}
