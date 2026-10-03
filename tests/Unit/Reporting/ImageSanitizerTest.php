<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reporting;

use App\Reporting\Exception\UnsupportedImageException;
use App\Reporting\Service\ImageSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageSanitizerTest extends TestCase
{
    private ImageSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ImageSanitizer();
    }

    #[Test]
    public function largeJpegIsBoundedAndReencodedWithoutMetadata(): void
    {
        $result = $this->sanitizer->sanitize($this->jpeg(3200, 2000));

        self::assertSame(1600, $result->width);
        self::assertSame(1000, $result->height);
        self::assertSame('image/jpeg', $result->sourceMime);
        self::assertSame("\xFF\xD8", substr($result->jpegBytes, 0, 2), 'output is a JPEG');
        self::assertStringNotContainsString('Exif', $result->jpegBytes);
        self::assertSame(hash('sha256', $result->jpegBytes), $result->sha256);
        $info = getimagesizefromstring($result->jpegBytes);
        self::assertNotFalse($info);
        self::assertSame('image/jpeg', $info['mime']);
    }

    #[Test]
    public function smallPngIsKeptAtSizeAndFlattenedToJpeg(): void
    {
        $png = imagecreatetruecolor(300, 200);
        self::assertNotFalse($png);
        imagesavealpha($png, true);
        $transparent = imagecolorallocatealpha($png, 0, 0, 0, 127);
        self::assertNotFalse($transparent);
        imagefill($png, 0, 0, $transparent);
        ob_start();
        imagepng($png);
        $bytes = (string) ob_get_clean();

        $result = $this->sanitizer->sanitize($bytes);

        self::assertSame(300, $result->width);
        self::assertSame(200, $result->height);
        self::assertSame('image/png', $result->sourceMime);
        $decoded = imagecreatefromstring($result->jpegBytes);
        self::assertNotFalse($decoded);
        $rgb = imagecolorat($decoded, 10, 10);
        self::assertGreaterThan(0xF0F0F0, $rgb, 'transparent area became white, not black');
    }

    #[Test]
    public function rejectsNonImages(): void
    {
        $this->expectException(UnsupportedImageException::class);
        $this->sanitizer->sanitize('%PDF-1.4 definitely not an image');
    }

    #[Test]
    public function rejectsUnsupportedFormats(): void
    {
        $gif = imagecreatetruecolor(10, 10);
        self::assertNotFalse($gif);
        ob_start();
        imagegif($gif);
        $bytes = (string) ob_get_clean();

        $this->expectException(UnsupportedImageException::class);
        $this->sanitizer->sanitize($bytes);
    }

    /**
     * @param int<1, max> $w
     * @param int<1, max> $h
     */
    private function jpeg(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        self::assertNotFalse($img);
        $color = imagecolorallocate($img, 200, 30, 40);
        self::assertNotFalse($color);
        imagefilledrectangle($img, 0, 0, $w, $h, $color);
        ob_start();
        imagejpeg($img, null, 90);

        return (string) ob_get_clean();
    }
}
