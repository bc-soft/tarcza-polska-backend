<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Audit\Service\AuditLogger;
use App\Identity\Entity\Operator;
use App\Reporting\Entity\ReportPhoto;
use App\Reporting\Service\PhotoStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Session-authenticated photo download for the Twig panel (same audit trail as the API). */
final class PhotoWebController extends AbstractController
{
    public function __construct(
        private readonly PhotoStorage $storage,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/command/photos/{id}', name: 'command_photo_file', methods: ['GET'])]
    public function show(#[CurrentUser] Operator $operator, ReportPhoto $photo): Response
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

        return $response;
    }
}
