<?php

declare(strict_types=1);

namespace App\Identity\Enum;

/**
 * Where the last known position of a device came from. Lets the backend weigh freshness
 * (a home address stays valid for days, a GPS fix does not) and skip refresh reminders
 * for devices that already report from the background.
 */
enum LocationSource: string
{
    /** Home address chosen during onboarding (coordinates only, never the address text). */
    case Home = 'home';
    /** GPS fix taken while the app was in the foreground (default). */
    case Gps = 'gps';
    /** Periodic update sent by the app from the background (standby mode). */
    case Background = 'background';
}
