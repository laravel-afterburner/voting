<?php

namespace Afterburner\Voting\Enums;

enum BallotType: string
{
    case Poll = 'poll';
    case Resolution = 'resolution';
    case Election = 'election';

    public function label(): string
    {
        return match ($this) {
            self::Poll => 'Poll',
            self::Resolution => 'Resolution',
            self::Election => 'Election',
        };
    }
}
