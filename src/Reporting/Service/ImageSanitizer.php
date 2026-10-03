<?php

declare(strict_types=1);

namespace App\Reporting\Service;

use App\Reporting\Exception\UnsupportedImageException;
use App\Reporting\Model\SanitizedImage;
use GdImage;

/**
 * Re-encodes any uploaded picture into a plain baseline JPEG: EXIF, GPS, ICC, XMP and thumbnails are gone,
 * the longer edge is capped, EXIF orientation is applied first so the picture is not rotated by the strip.
 */
final class ImageSanitizer
{
    public const int MAX_EDGE_PX = 1600;
    public const int JPEG_QUALITY = 85;
    public const array ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function sanitize(string $bytes): SanitizedImage
    {
        $info = @getimagesizefromstring($bytes);
        if (false === $info) {
            throw new UnsupportedImageException('The file is not a readable image');
        }
        $mime = $info['mime'];
        if (!\in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new UnsupportedImageException(\sprintf('Unsupported image type "%s"; send JPEG, PNG or WebP', $mime));
        }

        $image = @imagecreatefromstring($bytes);
        if (!$image instanceof GdImage) {
            throw new UnsupportedImageException('The image could not be decoded');
        }

        $image = $this->applyOrientation($image, 'image/jpeg' === $mime ? $this->readOrientation($bytes) : 1);
        $image = $this->bound($image);
        $image = $this->flatten($image);

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        return new SanitizedImage($jpeg, $width, $height, hash('sha256', $jpeg), $mime);
    }

    private function readOrientation(string $jpegBytes): int
    {
        if (!\function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($jpegBytes));
        if (!\is_array($exif)) {
            return 1;
        }

        return (int) ($exif['Orientation'] ?? 1);
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function bound(GdImage $image): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $longest = max($w, $h);
        if ($longest <= self::MAX_EDGE_PX) {
            return $image;
        }
        $scale = self::MAX_EDGE_PX / $longest;
        // Default (bilinear) interpolation: IMG_BICUBIC is not available on every GD build.
        $scaled = imagescale($image, (int) round($w * $scale), (int) round($h * $scale));
        if (!$scaled instanceof GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $scaled;
    }

    /** Transparent PNG/WebP pixels become white instead of black in the JPEG. */
    private function flatten(GdImage $image): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $canvas = imagecreatetruecolor($w, $h);
        if (!$canvas instanceof GdImage) {
            return $image;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        if (false !== $white) {
            imagefill($canvas, 0, 0, $white);
        }
        imagecopy($canvas, $image, 0, 0, 0, 0, $w, $h);
        imagedestroy($image);

        return $canvas;
    }
}
