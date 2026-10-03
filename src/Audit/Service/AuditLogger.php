<?php

declare(strict_types=1);

namespace App\Audit\Service;

use App\Audit\Entity\AuditLogEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuditLogger
{
    public const string VIEW_SENSITIVE = 'view_sensitive';
    public const string PUBLISH_ALERT = 'publish_alert';
    public const string RESOLVE_INCIDENT = 'resolve_incident';
    public const string ADD_SOURCE = 'add_external_source';

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(UserInterface $actor, string $action, string $subjectType, string $subjectId, array $context = []): void
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
        $this->em->persist(new AuditLogEntry($actor->getUserIdentifier(), $action, $subjectType, $subjectId, $context, $ip));
        $this->em->flush();
    }
}
