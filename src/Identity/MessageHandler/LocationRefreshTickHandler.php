<?php

declare(strict_types=1);

namespace App\Identity\MessageHandler;

use App\Identity\Message\LocationRefreshTick;
use App\Identity\Service\LocationRefreshReminder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LocationRefreshTickHandler
{
    public function __construct(private LocationRefreshReminder $reminder)
    {
    }

    public function __invoke(LocationRefreshTick $tick): void
    {
        $this->reminder->run();
    }
}
