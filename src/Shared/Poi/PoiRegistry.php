<?php

declare(strict_types=1);

namespace App\Shared\Poi;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Finds the locator / status updater for a POI kind. */
final class PoiRegistry
{
    /** @var array<string, PoiLocatorInterface> */
    private array $locators = [];

    /** @var array<string, PoiStatusUpdaterInterface> */
    private array $updaters = [];

    /**
     * @param iterable<PoiLocatorInterface>       $locators
     * @param iterable<PoiStatusUpdaterInterface> $updaters
     */
    public function __construct(
        #[AutowireIterator(PoiLocatorInterface::TAG)]
        iterable $locators,
        #[AutowireIterator(PoiStatusUpdaterInterface::TAG)]
        iterable $updaters,
    ) {
        foreach ($locators as $locator) {
            $this->locators[$locator->kind()->value] = $locator;
        }
        foreach ($updaters as $updater) {
            $this->updaters[$updater->kind()->value] = $updater;
        }
    }

    public function locator(PoiKind $kind): PoiLocatorInterface
    {
        return $this->locators[$kind->value] ?? throw new LogicException(\sprintf('No PoiLocator registered for %s', $kind->value));
    }

    public function updater(PoiKind $kind): PoiStatusUpdaterInterface
    {
        return $this->updaters[$kind->value] ?? throw new LogicException(\sprintf('No PoiStatusUpdater registered for %s', $kind->value));
    }
}
