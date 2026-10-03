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
    public const string RERUN_RESEARCH = 'rerun_research';
    public const string CREATE_SHELTER = 'create_shelter';
    public const string UPDATE_SHELTER = 'update_shelter';
    public const string SET_SHELTER_STATUS = 'set_shelter_status';
    public const string CREATE_OPERATOR = 'create_operator';
    public const string UPDATE_OPERATOR = 'update_operator';
    public const string RESET_PASSWORD = 'reset_password';

    /** Human readable labels for the Command Center. */
    public const array LABELS = [
        self::VIEW_SENSITIVE => 'Podgląd danych wrażliwych',
        self::PUBLISH_ALERT => 'Publikacja komunikatu',
        self::RESOLVE_INCIDENT => 'Zamknięcie incydentu',
        self::ADD_SOURCE => 'Dodanie źródła zewnętrznego',
        self::RERUN_RESEARCH => 'Ponowny research AI',
        self::CREATE_SHELTER => 'Utworzenie schronu',
        self::UPDATE_SHELTER => 'Edycja schronu',
        self::SET_SHELTER_STATUS => 'Zmiana statusu schronu',
        self::CREATE_OPERATOR => 'Utworzenie konta operatora',
        self::UPDATE_OPERATOR => 'Edycja konta operatora',
        self::RESET_PASSWORD => 'Reset hasła operatora',
    ];

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
