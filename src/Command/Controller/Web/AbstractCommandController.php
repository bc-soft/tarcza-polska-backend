<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Command\Service\WebFormValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

/** Shared plumbing of the Twig panel: CSRF checks and DTO validation surfaced as flashes. */
abstract class AbstractCommandController extends AbstractController
{
    private WebFormValidator $forms;

    #[Required]
    public function setFormValidator(WebFormValidator $forms): void
    {
        $this->forms = $forms;
    }

    protected function assertCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    /** @return bool true when validation errors were flashed (caller should redirect back) */
    protected function flashErrors(object $dto): bool
    {
        $errors = $this->forms->errors($dto);
        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return [] !== $errors;
    }

    protected static function nullableString(Request $request, string $key): ?string
    {
        $value = trim($request->request->getString($key));

        return '' === $value ? null : $value;
    }
}
