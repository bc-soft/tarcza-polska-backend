<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\Device;
use App\Incident\Entity\Incident;
use App\Incident\Enum\CellState;
use App\Incident\Enum\IncidentResolution;
use App\Verification\Repository\VerificationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies ReputationPolicy to everyone who took part in a closed incident: reporters and answerers.
 * Simulated devices are skipped so demo crowds never drift.
 */
final readonly class ReputationUpdater
{
    public function __construct(
        private ReputationPolicy $policy,
        private VerificationRequestRepository $verifications,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{reporters: int, answerers: int} number of devices adjusted */
    public function applyResolution(Incident $incident, IncidentResolution $resolution): array
    {
        $touched = ['reporters' => 0, 'answerers' => 0];

        $reporterDelta = $this->policy->reporterDelta($resolution);
        if (0.0 !== $reporterDelta) {
            $seen = [];
            foreach ($incident->getReports() as $report) {
                $device = $report->getDevice();
                $key = $device->getUserIdentifier();
                if (isset($seen[$key]) || $device->isSimulated()) {
                    continue;
                }
                $seen[$key] = true;
                $device->adjustReputation($reporterDelta);
                ++$touched['reporters'];
            }
        }

        if (IncidentResolution::Expired !== $resolution) {
            $cells = $incident->getCells();
            foreach ($this->verifications->findAnsweredForIncident($incident) as $request) {
                $device = $request->getDevice();
                if ($device->isSimulated()) {
                    continue;
                }
                $cell = $cells->get($request->getH3Cell());
                $state = $cell?->getState() ?? CellState::Unknown;
                $answer = $request->getNormalisedAnswer();
                $delta = $this->policy->answerDelta(\in_array($answer, ['yes', 'no', 'unknown'], true) ? $answer : null, $state, $resolution);
                if (0.0 === $delta) {
                    continue;
                }
                $device->adjustReputation($delta);
                ++$touched['answerers'];
            }
        }

        $this->em->flush();
        $this->logger->info('Reputation updated after incident {id} closed as {resolution}: {reporters} reporters, {answerers} answerers', [
            'id' => $incident->getId()->toRfc4122(), 'resolution' => $resolution->value,
        ] + $touched);

        return $touched;
    }

    public function reputationOf(Device $device): float
    {
        return $device->getReputation();
    }
}
