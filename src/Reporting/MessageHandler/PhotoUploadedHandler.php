<?php

declare(strict_types=1);

namespace App\Reporting\MessageHandler;

use App\Intelligence\Service\PhotoAnalyzerInterface;
use App\Reporting\Message\PhotoUploaded;
use App\Reporting\Repository\ReportPhotoRepository;
use App\Reporting\Service\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class PhotoUploadedHandler
{
    public function __construct(
        private ReportPhotoRepository $photos,
        private PhotoStorage $storage,
        private PhotoAnalyzerInterface $analyzer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PhotoUploaded $event): void
    {
        $photo = $this->photos->find(Uuid::fromString($event->photoId));
        if (null === $photo || $photo->isAnalyzed()) {
            return;
        }

        if (!$this->analyzer->isEnabled()) {
            $photo->markSkipped('skipped:'.$this->analyzer->name());
            $this->em->flush();
            $this->logger->notice('Photo {id} not analysed: analyzer {name} has no credentials', ['id' => $event->photoId, 'name' => $this->analyzer->name()]);

            return;
        }

        $analysis = $this->analyzer->analyze($this->storage->read($photo), $photo->getMime(), $photo->getReport()->getType());
        $photo->recordAnalysis($analysis, $this->analyzer->name());
        $this->em->flush();

        $this->logger->info('Photo {id} analysed by {name}: relevant={relevant} matchesType={matches} unsafe={unsafe}', [
            'id' => $event->photoId,
            'name' => $this->analyzer->name(),
            'relevant' => $analysis->relevant,
            'matches' => $analysis->matchesType,
            'unsafe' => $analysis->unsafe,
        ]);
    }
}
