<?php

declare(strict_types=1);

namespace App\Reporting\Service;

use App\Fuel\Enum\FuelType;
use App\Identity\Entity\Device;
use App\Reporting\Dto\CreateReportRequest;
use App\Reporting\Entity\Report;
use App\Reporting\Enum\ReportScope;
use App\Reporting\Enum\ReportType;
use App\Reporting\Message\ReportCreated;
use App\Reporting\Repository\ReportRepository;
use App\Shared\Api\ApiProblemException;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiRef;
use App\Shared\Poi\PoiRegistry;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Accepts a report. Point-scoped types (fuel shortage, shelter issue) are bound to one object: the one the
 * app named (poiId) or the nearest one within the kind's snap radius. Without an object there is no report.
 */
final readonly class ReportSubmitter
{
    public function __construct(
        private ReportRepository $reports,
        private PoiRegistry $pois,
        private H3 $h3,
        private MessageBusInterface $bus,
    ) {
    }

    public function submit(Device $device, CreateReportRequest $request): Report
    {
        $point = new Point($request->lat, $request->lng);
        $cell = $this->h3->cellFor($point);

        $report = new Report($device, $request->type, $point, $cell, $request->description);

        if (ReportScope::Point === $request->type->scope()) {
            $report->attachPoi($this->resolvePoi($request->type, $request->poiId, $point));
        }
        if (ReportType::FuelShortage === $request->type) {
            $report->setFuelTypes($this->fuelTypes($request));
        }

        // A report is also a location signal for the device itself.
        $device->updateLocation($point, $cell);
        $this->reports->save($report, true);

        $this->bus->dispatch(new ReportCreated($report->getId()->toRfc4122()));

        return $report;
    }

    private function resolvePoi(ReportType $type, ?string $poiId, Point $point): PoiRef
    {
        /** @var PoiKind $kind */
        $kind = $type->poiKind();
        $locator = $this->pois->locator($kind);

        if (null !== $poiId) {
            $poi = $locator->find(Uuid::fromString($poiId));
            if (null === $poi) {
                throw new ApiProblemException(422, 'poi_not_found', \sprintf('Unknown %s "%s"', $kind->value, $poiId));
            }

            return $poi;
        }

        $poi = $locator->nearest($point, $kind->snapRadiusMeters());
        if (null === $poi) {
            throw new ApiProblemException(422, 'poi_required', \sprintf('No %s within %d m of the given position; pass poiId of the %s the report is about', $kind->label(), $kind->snapRadiusMeters(), mb_strtolower($kind->label())));
        }

        return $poi;
    }

    /** @return list<FuelType> */
    private function fuelTypes(CreateReportRequest $request): array
    {
        // The payload mapper already turned the strings into FuelType cases (unknown values are a 422 upstream).
        $types = [];
        foreach ($request->fuelTypes ?? [] as $type) {
            if (!\in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        if ([] === $types) {
            throw new ApiProblemException(422, 'validation_failed', 'fuelTypes is required for fuel_shortage reports');
        }

        return $types;
    }
}
