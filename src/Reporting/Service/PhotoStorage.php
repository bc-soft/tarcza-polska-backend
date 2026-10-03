<?php

declare(strict_types=1);

namespace App\Reporting\Service;

use App\Reporting\Entity\ReportPhoto;
use League\Flysystem\FilesystemOperator;

/**
 * Thin wrapper over the "photos.storage" Flysystem disk so the rest of the module never sees paths or adapters.
 */
final readonly class PhotoStorage
{
    public function __construct(private FilesystemOperator $photosStorage)
    {
    }

    public function write(string $path, string $bytes): void
    {
        $this->photosStorage->write($path, $bytes, ['visibility' => 'private']);
    }

    /** @return resource */
    public function readStream(ReportPhoto $photo)
    {
        return $this->photosStorage->readStream($photo->getPath());
    }

    public function read(ReportPhoto $photo): string
    {
        return $this->photosStorage->read($photo->getPath());
    }

    public function exists(ReportPhoto $photo): bool
    {
        return $this->photosStorage->fileExists($photo->getPath());
    }

    /** Removes every stored photo (fixtures reset). Returns the number of files deleted. */
    public function purgeAll(): int
    {
        $deleted = 0;
        foreach ($this->photosStorage->listContents('', true) as $item) {
            if ($item->isFile()) {
                $this->photosStorage->delete($item->path());
                ++$deleted;
            }
        }

        return $deleted;
    }
}
