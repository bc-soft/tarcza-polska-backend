<?php

declare(strict_types=1);

namespace App\Simulation\Service;

use App\Simulation\Entity\SimulationScenario;
use App\Simulation\Repository\SimulationScenarioRepository;
use App\Verification\Enum\VerificationAnswer;
use App\Verification\Repository\VerificationRequestRepository;
use App\Verification\Service\VerificationResponder;
use Psr\Log\LoggerInterface;

/**
 * Makes virtual devices behave like a plausible crowd. Called every 10 s by the scheduler.
 */
final readonly class SimulatedCrowd
{
    private const int MIN_DELAY_SECONDS = 4;
    private const int MAX_PER_TICK = 60;

    public function __construct(
        private SimulationScenarioRepository $scenarios,
        private VerificationRequestRepository $requests,
        private VerificationResponder $responder,
        private LoggerInterface $logger,
    ) {
    }

    public function answerPending(): int
    {
        $scenarios = $this->scenarios->findActive();
        if ([] === $scenarios) {
            return 0;
        }

        $pending = $this->requests->findPendingSimulated(self::MIN_DELAY_SECONDS, self::MAX_PER_TICK);
        $answered = 0;

        foreach ($pending as $request) {
            // Simulate humans: not everybody answers every tick.
            if (mt_rand() / mt_getrandmax() > 0.6) {
                continue;
            }
            $location = $request->getDevice()->getLastLocation();
            if (null === $location) {
                continue;
            }

            $scenario = $this->matchScenario($scenarios, $request->getIncident()->getType()->value);
            $this->responder->respond($request, $this->decide($scenario, $location));
            ++$answered;
        }

        if ($answered > 0) {
            $this->logger->info('Simulated crowd answered {n} verification requests', ['n' => $answered]);
        }

        return $answered;
    }

    /** @param list<SimulationScenario> $scenarios */
    private function matchScenario(array $scenarios, string $type): ?SimulationScenario
    {
        foreach ($scenarios as $s) {
            if ($s->getType()->value === $type) {
                return $s;
            }
        }

        return null;
    }

    private function decide(?SimulationScenario $scenario, \App\Shared\Geo\Point $location): VerificationAnswer
    {
        $r = mt_rand() / mt_getrandmax();
        $unknownRate = $scenario?->getUnknownRate() ?? 0.2;
        if ($r < $unknownRate) {
            return VerificationAnswer::Unknown;
        }

        $problemHere = null !== $scenario && $scenario->contains($location);
        $accuracy = $scenario?->getAccuracy() ?? 0.5;
        $truthful = (mt_rand() / mt_getrandmax()) < $accuracy;
        $saysProblem = $truthful ? $problemHere : !$problemHere;

        // Questions are phrased "do you HAVE power?" for most types -> invert through the type flag.
        $type = $scenario?->getType();
        $yesMeansProblem = $type?->yesMeansProblemPresent() ?? false;

        return ($saysProblem === $yesMeansProblem) ? VerificationAnswer::Yes : VerificationAnswer::No;
    }
}
