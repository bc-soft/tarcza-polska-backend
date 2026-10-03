<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use App\Alerting\Entity\Alert;
use App\Alerting\View\AlertView;
use App\Identity\Entity\Device;
use App\Identity\Enum\LocationSource;
use App\Identity\View\DeviceProfileView;
use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Model\TimelineEntry;
use App\Incident\View\IncidentPublicView;
use App\Incident\View\IncidentTimelineView;
use App\Reporting\Entity\Report;
use App\Reporting\Enum\ReportType;
use App\Reporting\View\ReportStatusView;
use App\Shared\Api\ApiExceptionListener;
use App\Shared\Api\ApiProblemException;
use App\Shared\Api\GeoJson;
use App\Shared\Geo\Point;
use App\Shelter\Entity\Shelter;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\View\ShelterView;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Entity\VerificationWave;
use App\Verification\View\VerificationQuestionView;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * The payloads the citizen app parses must match docs/openapi.json (swagger_parser generates the Dart
 * client from it). Each view is rendered from in-memory entities and checked against its schema.
 * No database needed.
 */
final class CitizenApiContractTest extends KernelTestCase
{
    private const string CELL = '891e24aa0b3ffff';

    /** @var array<string, mixed> */
    private static array $spec;
    private static OpenApiSchemaValidator $validator;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        /** @var ApiDocGenerator $generator */
        $generator = self::getContainer()->get('nelmio_api_doc.generator');
        /** @var array<string, mixed> $spec */
        $spec = json_decode($generator->generate()->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        self::$spec = $spec;
        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = $spec['components']['schemas'];
        self::$validator = new OpenApiSchemaValidator($schemas);
    }

    public function testEveryCitizenSuccessResponseHasASchema(): void
    {
        $missing = [];
        /** @var array<string, array<string, array<string, mixed>>> $paths */
        $paths = self::$spec['paths'];
        foreach ($paths as $path => $operations) {
            if (!str_starts_with($path, '/api/v1')) {
                continue;
            }
            foreach ($operations as $method => $operation) {
                /** @var array<string, array<string, mixed>> $responses */
                $responses = $operation['responses'];
                foreach ($responses as $code => $response) {
                    $code = (string) $code;
                    if (str_starts_with($code, '2') && '204' !== $code && !isset($response['content'])) {
                        $missing[] = \sprintf('%s %s -> %s', strtoupper($method), $path, $code);
                    }
                    if ((int) $code >= 400 && ($response['content']['application/json']['schema']['$ref'] ?? null) !== '#/components/schemas/ErrorResponse') {
                        $missing[] = \sprintf('%s %s -> %s lacks ErrorResponse', strtoupper($method), $path, $code);
                    }
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testDeviceProfile(): void
    {
        $fresh = new Device();
        $this->assertMatches('DeviceProfile', DeviceProfileView::toArray($fresh));

        $located = $this->device();
        $located->setLocationRefreshEnabled(false);
        $this->assertMatches('DeviceProfile', DeviceProfileView::toArray($located));
    }

    public function testIncidentViewAndFeature(): void
    {
        $detected = $this->incident();
        $this->assertMatches('IncidentView', IncidentPublicView::toArray($detected));
        $this->assertMatches('MapFeature', IncidentPublicView::toFeature($detected));

        $withArea = $this->incident();
        $withArea->setArea($this->polygon());
        $this->assertMatches('IncidentView', IncidentPublicView::toArray($withArea));
        $this->assertMatches('MapFeature', IncidentPublicView::toFeature($withArea));
    }

    public function testIncidentTimelineEntry(): void
    {
        $entries = [
            new TimelineEntry(IncidentEventType::Created, new DateTimeImmutable(), ['reports' => 1, 'cell' => self::CELL, 'ring' => 0]),
            new TimelineEntry(IncidentEventType::WaveStarted, new DateTimeImmutable(), ['ring' => 0, 'cells' => 7, 'devices' => 3]),
            new TimelineEntry(IncidentEventType::AreaChanged, new DateTimeImmutable(), ['positiveCells' => 4, 'negativeCells' => 2, 'unknownCells' => 5, 'yes' => 6, 'no' => 2]),
            new TimelineEntry(IncidentEventType::ConfidenceChanged, new DateTimeImmutable(), ['from' => 'likely', 'to' => 'high', 'score' => 0.612, 'reason' => 'verification_response']),
            new TimelineEntry(IncidentEventType::ResearchCompleted, new DateTimeImmutable(), ['hasSummary' => true]),
            new TimelineEntry(IncidentEventType::Resolved, new DateTimeImmutable(), ['resolution' => null]),
        ];
        foreach (IncidentTimelineView::list($entries) as $entry) {
            $this->assertMatches('IncidentTimelineEntry', $entry);
        }
    }

    public function testShelterViewAndFeature(): void
    {
        $bare = new Shelter('Schron Jeżyce', new Point(52.41, 16.9));
        $this->assertMatches('ShelterView', ShelterView::toArray($bare));
        $this->assertMatches('ShelterView', ShelterView::toArray($bare, 123.4));
        $this->assertMatches('MapFeature', ShelterView::toFeature($bare));

        $confirmed = new Shelter('Schron Wilda', new Point(52.38, 16.92));
        $confirmed->confirmStatus(ShelterStatus::Open);
        $this->assertMatches('ShelterView', ShelterView::toArray($confirmed));
    }

    public function testAlertViewAndFeature(): void
    {
        $alert = new Alert('Brak prądu', 'Awaria sieci', Alert::SEVERITY_WARNING, $this->polygon(), 'operator@example.org', new DateTimeImmutable('+2 hours'), $this->incident());
        $this->assertMatches('AlertView', AlertView::toArray($alert));
        $this->assertMatches('AlertView', AlertView::toArray($alert, false));
        $this->assertMatches('MapFeature', AlertView::toFeature($alert));
    }

    public function testMapFeatureCollectionMixesAllKinds(): void
    {
        $alert = new Alert('T', 'B', Alert::SEVERITY_INFO, $this->polygon(), 'op', new DateTimeImmutable('+1 hour'));
        $collection = GeoJson::collection([
            IncidentPublicView::toFeature($this->incident()),
            ShelterView::toFeature(new Shelter('S', new Point(52.4, 16.9))),
            AlertView::toFeature($alert),
        ]);

        $this->assertMatches('MapFeatureCollection', $collection);
        foreach ($collection['features'] as $feature) {
            self::assertSame($feature['id'], $feature['properties']['id'], 'Feature.id and properties.id must be the same uuid');
        }
    }

    public function testVerificationQuestionAndResult(): void
    {
        $wave = new VerificationWave($this->incident(), 0, [self::CELL], 90);
        $request = new VerificationRequest($wave, $this->device(), self::CELL, 'Czy masz prąd?');

        $this->assertMatches('VerificationQuestion', VerificationQuestionView::toArray($request));
        $this->assertMatches('VerificationResult', VerificationQuestionView::result($request));
    }

    public function testReportStatusAndAccepted(): void
    {
        $report = new Report($this->device(), ReportType::WaterOutage, new Point(52.4, 16.9), self::CELL, 'opis');
        $this->assertMatches('ReportAccepted', ReportStatusView::accepted($report));
        $this->assertMatches('ReportStatusView', ReportStatusView::toArray($report));

        $report->assignTo($this->incident());
        $this->assertMatches('ReportStatusView', ReportStatusView::toArray($report));

        $this->assertMatches('ReportTypeOption', ['value' => ReportType::RoadBlocked->value, 'label' => ReportType::RoadBlocked->label()]);
    }

    public function testErrorEnvelopes(): void
    {
        $violation = new ConstraintViolation('This value should be between -90 and 90.', null, [], null, 'lat', 123);
        $this->assertMatches('ErrorResponse', $this->errorBody(new ValidationFailedException(null, new ConstraintViolationList([$violation]))));
        $this->assertMatches('ErrorResponse', $this->errorBody(new RateLimitExceededException(new RateLimit(0, new DateTimeImmutable('+30 seconds'), false, 10))));
        $this->assertMatches('ErrorResponse', $this->errorBody(new ApiProblemException(410, 'verification_expired', 'expired')));
    }

    /** @param array<string, mixed> $payload */
    private function assertMatches(string $schema, array $payload): void
    {
        $violations = self::$validator->violations(['$ref' => '#/components/schemas/'.$schema], $payload);
        self::assertSame([], $violations, \sprintf("%s does not match:\n%s", $schema, json_encode($payload, \JSON_PRETTY_PRINT)));
    }

    /** @return array<string, mixed> */
    private function errorBody(Throwable $e): array
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/v1/x'), HttpKernelInterface::MAIN_REQUEST, $e);
        new ApiExceptionListener(new NullLogger())($event);

        /** @var array<string, mixed> */
        return json_decode((string) $event->getResponse()?->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function device(): Device
    {
        $device = new Device();
        $device->setPlatform('ios');
        $device->setPushToken('fcm-token');
        $device->updateLocation(new Point(52.4, 16.9), self::CELL, LocationSource::Home);

        return $device;
    }

    private function incident(): Incident
    {
        return new Incident(ReportType::PowerOutage, new Point(52.4, 16.9), self::CELL);
    }

    /** @return array<string, mixed> */
    private function polygon(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[16.9, 52.4], [16.91, 52.4], [16.91, 52.41], [16.9, 52.4]]]];
    }
}
