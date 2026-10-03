<?php

declare(strict_types=1);

namespace App\Intelligence\Service\Feed;

use App\Intelligence\Model\FeedDefinition;
use App\Intelligence\Model\FeedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Fetches and normalises one external feed. RSS/Atom today; a JSON API adapter for an operator
 * (e.g. an energy distributor's outage API) implements the same contract and is picked up automatically.
 */
#[AutoconfigureTag(self::TAG)]
interface FeedAdapterInterface
{
    public const string TAG = 'app.feed_adapter';

    public function supports(FeedDefinition $feed): bool;

    /** @return list<FeedItem> newest first, at most a few dozen items */
    public function fetch(FeedDefinition $feed): array;
}
