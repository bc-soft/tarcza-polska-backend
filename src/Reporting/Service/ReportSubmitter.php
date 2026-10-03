<?php

declare(strict_types=1);

namespace App\Reporting\Service;

use App\Identity\Entity\Device;
use App\Reporting\Dto\CreateReportRequest;
use App\Reporting\Entity\Report;
use App\Reporting\Message\ReportCreated;
use App\Reporting\Repository\ReportRepository;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ReportSubmitter
{
    public function __construct(
        private ReportRepository $reports,
        private H3 $h3,
        private MessageBusInterface $bus,
    ) {
    }

    public function submit(Device $device, CreateReportRequest $request): Report
    {
        $point = new Point($request->lat, $request->lng);
        $cell = $this->h3->cellFor($point);

        $report = new Report($device, $request->type, $point, $cell, $request->description);
        // A report is also a location signal for the device itself.
        $device->updateLocation($point, $cell);
        $this->reports->save($report, true);

        $this->bus->dispatch(new ReportCreated($report->getId()->toRfc4122()));

        return $report;
    }
}
