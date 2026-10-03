<?php

declare(strict_types=1);

namespace App\Reporting\Message;

/** Domain event: a sanitized photo was stored; analyse it asynchronously. */
final readonly class PhotoUploaded
{
    public function __construct(public string $photoId)
    {
    }
}
