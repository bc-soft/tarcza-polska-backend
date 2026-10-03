<?php

declare(strict_types=1);

namespace App\Simulation\MessageHandler;

use App\Simulation\Message\SimulatedCrowdTick;
use App\Simulation\Service\SimulatedCrowd;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SimulatedCrowdTickHandler
{
    public function __construct(private SimulatedCrowd $crowd)
    {
    }

    public function __invoke(SimulatedCrowdTick $tick): void
    {
        $this->crowd->answerPending();
    }
}
