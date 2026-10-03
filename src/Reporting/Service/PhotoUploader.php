<?php

declare(strict_types=1);

namespace App\Reporting\Service;

use App\Incident\Enum\IncidentEventType;
use App\Incident\Service\IncidentTimeline;
use App\Reporting\Entity\Report;
use App\Reporting\Entity\ReportPhoto;
use App\Reporting\Exception\UnsupportedImageException;
use App\Reporting\Message\PhotoUploaded;
use App\Reporting\Repository\ReportPhotoRepository;
use App\Shared\Api\ApiProblemException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Accepts a citizen photo for a report: validates, strips metadata, stores, records history, queues analysis.
 */
final readonly class PhotoUploader
{
    public const int MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    public const int MAX_PHOTOS_PER_REPORT = 3;

    public function __construct(
        private ImageSanitizer $sanitizer,
        private PhotoStorage $storage,
        private ReportPhotoRepository $photos,
        private IncidentTimeline $timeline,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function upload(Report $report, UploadedFile $file): ReportPhoto
    {
        if (!$file->isValid()) {
            throw new ApiProblemException(400, 'bad_request', 'Upload failed: '.$file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new ApiProblemException(413, 'payload_too_large', \sprintf('Photo larger than %d MB', self::MAX_UPLOAD_BYTES >> 20));
        }
        if ($this->photos->countForReport($report) >= self::MAX_PHOTOS_PER_REPORT) {
            throw new ApiProblemException(409, 'photo_limit', \sprintf('At most %d photos per report', self::MAX_PHOTOS_PER_REPORT));
        }

        $bytes = (string) file_get_contents($file->getPathname());
        try {
            $sanitized = $this->sanitizer->sanitize($bytes);
        } catch (UnsupportedImageException $e) {
            throw new ApiProblemException(415, 'unsupported_media_type', $e->getMessage());
        }

        $photoId = Uuid::v7();
        $path = \sprintf('%s/%s/%s.jpg', $report->getCreatedAt()->format('Y/m'), $report->getId()->toRfc4122(), $photoId->toRfc4122());
        $this->storage->write($path, $sanitized->jpegBytes);

        $photo = new ReportPhoto($report, $path, 'image/jpeg', $sanitized->width, $sanitized->height, $sanitized->byteLength(), $sanitized->sha256);
        $this->em->persist($photo);

        $incident = $report->getIncident();
        if (null !== $incident) {
            $this->timeline->record($incident, IncidentEventType::PhotoAttached, [
                'reportId' => $report->getId()->toRfc4122(),
                'photoId' => $photo->getId()->toRfc4122(),
            ]);
        }
        $this->em->flush();

        $this->bus->dispatch(new PhotoUploaded($photo->getId()->toRfc4122()));

        return $photo;
    }
}
