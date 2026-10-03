<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Identity\Entity\Device;
use App\Reporting\Entity\Report;
use App\Reporting\Service\PhotoUploader;
use App\Reporting\View\ReportPhotoView;
use App\Shared\Api\ApiProblemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/reports')]
#[OA\Tag(name: 'Reports')]
final class ReportPhotoController
{
    public function __construct(private readonly PhotoUploader $uploader)
    {
    }

    #[Route('/{id}/photo', name: 'api_report_photo', methods: ['POST'])]
    #[OA\Post(summary: 'Attach a photo to my report (multipart/form-data, field "photo"; JPEG/PNG/WebP up to 10 MB). EXIF and GPS are stripped server-side; the photo is analysed asynchronously and visible only to operators.')]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['photo'], properties: [new OA\Property(property: 'photo', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 202, description: 'Stored, analysis queued', content: new OA\JsonContent(ref: '#/components/schemas/PhotoAccepted'))]
    #[OA\Response(response: 400, description: 'Missing "photo" field or broken upload (error.code: bad_request)')]
    #[OA\Response(response: 409, description: 'Report already has 3 photos (error.code: photo_limit)')]
    #[OA\Response(response: 413, description: 'File larger than 10 MB (error.code: payload_too_large)')]
    #[OA\Response(response: 415, description: 'Not a JPEG/PNG/WebP image (error.code: unsupported_media_type)')]
    #[OA\Response(response: 429, description: 'Too many uploads (error.code: too_many_requests)')]
    public function __invoke(
        #[CurrentUser]
        Device $device,
        Report $report,
        Request $request,
        RateLimiterFactory $photoUploadLimiter,
    ): JsonResponse {
        if ($report->getDevice()->getId()->toRfc4122() !== $device->getId()->toRfc4122()) {
            throw new ApiProblemException(404, 'not_found', 'Report not found');
        }
        $photoUploadLimiter->create($device->getUserIdentifier())->consume()->ensureAccepted();

        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(400, 'bad_request', 'Send the image as multipart/form-data field "photo"');
        }

        $photo = $this->uploader->upload($report, $file);

        return new JsonResponse(ReportPhotoView::accepted($photo), Response::HTTP_ACCEPTED);
    }
}
