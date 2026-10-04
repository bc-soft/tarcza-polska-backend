<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The root of the host is not an API resource: send browsers to the Command Center. */
final class HomeController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse('/command');
    }
}
