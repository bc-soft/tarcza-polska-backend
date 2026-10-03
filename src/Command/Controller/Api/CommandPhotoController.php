<?php

declare(strict_types=1);

namespace App\Command\Controller\Api;

use App\Audit\Service\AuditLogger;
use App\Identity\Entity\Operator;
use App\Incident\Entity\Incident;
use App\Reporting\Entity\ReportPhoto;
use App\Reporting\Repository\ReportPhotoRepository;
use App\Reporting\Service\PhotoStorage;
use App\Reporting\View\ReportPhotoView;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Citizen photos for operators. Files are never public: every download is authenticated and audit-logged.
 */
#[OA\Tag(name: 'Command')]
final class CommandPhotoController
{
    public function __construct(
        private readonly ReportPhotoRepository $photos,
        private readonly PhotoStorage $storage,
        private readonly AuditLogger $audit,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/api/command/incidents/{id}/photos', name: 'api_command_incident_photos', methods: ['GET'])]
    #[OA\Get(summary: 'Photos attached to the reports of an incident, newest first, with vision analysis')]
    #[OA\Parameter(name: 'includeUnsafe', in: 'query', description: '1 = include photos flagged by moderation', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Photos')]
    public function list(Incident $incident, Request $request): JsonResponse
    {
        $photos = $this->photos->findByIncident($incident, $request->query->getBoolean('includeUnsafe'));

        return new JsonResponse(array_map(
            fn (ReportPhoto $p) => ReportPhotoView::command($p, $this->urls->generate('api_command_photo_file', ['id' => $p->getId()->toRfc4122()])),
            $photos,
        ));
    }

    #[Route('/api/command/photos/{id}/file', name: 'api_command_photo_file', methods: ['GET'])]
    #[OA\Get(summary: 'Download the sanitized JPEG (audit-logged)')]
    #[OA\Response(response: 200, description: 'image/jpeg', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary')))]
    public function file(#[CurrentUser] Operator $operator, ReportPhoto $photo): Response
    {
        $this->audit->log($operator, AuditLogger::VIEW_SENSITIVE, 'photo', $photo->getId()->toRfc4122(), ['reportId' => $photo->getReport()->getId()->toRfc4122()]);

        $stream = $this->storage->readStream($photo);
        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', $photo->getMime());
        $response->headers->set('Content-Length', (string) $photo->getBytes());
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('Content-Disposition', 'inline; filename="'.$photo->getId()->toRfc4122().'.jpg"');

        return $response;
    }
}
