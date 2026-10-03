<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Audit\Dto\AuditSearchCriteria;
use App\Audit\Repository\AuditLogRepository;
use App\Audit\Service\AuditLogger;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Append-only audit trail browser (admins). */
#[Route('/command/audit')]
#[IsGranted('ROLE_ADMIN')]
final class AuditWebController extends AbstractCommandController
{
    #[Route('', name: 'command_audit_list', methods: ['GET'])]
    public function list(Request $request, AuditLogRepository $audit): Response
    {
        $criteria = AuditSearchCriteria::fromStrings(
            $request->query->getString('actor'),
            $request->query->getString('action'),
            $request->query->getString('subject'),
            $request->query->getInt('limit', 200),
        );

        return $this->render('command/audit_list.html.twig', [
            'entries' => $audit->search($criteria),
            'criteria' => $criteria,
            'actions' => $audit->distinctActions(),
            'labels' => AuditLogger::LABELS,
            'last24h' => $audit->countByActionSince(new DateTimeImmutable('-24 hours')),
        ]);
    }
}
