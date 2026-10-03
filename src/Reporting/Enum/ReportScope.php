<?php

declare(strict_types=1);

namespace App\Reporting\Enum;

/**
 * How a report type is verified and shown.
 *
 *  Area : the problem has a footprint (power, water, roads, threats). Reports cluster by distance, the crowd is
 *         asked cell by cell and the incident is drawn as a polygon of H3 cells.
 *  Point: the problem is about one object (a fuel station, a shelter). Reports attach to that object, the crowd
 *         standing at the object and at neighbouring objects of the same kind is asked, and the result is a status
 *         pin per object, never an area.
 */
enum ReportScope: string
{
    case Area = 'area';
    case Point = 'point';
}
