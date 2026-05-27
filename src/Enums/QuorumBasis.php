<?php

namespace Afterburner\Voting\Enums;

enum QuorumBasis: string
{
    case EligibleUnits = 'eligible_units';
    case EligibleUsers = 'eligible_users';

    public function label(): string
    {
        return match ($this) {
            self::EligibleUnits => 'Eligible Units',
            self::EligibleUsers => 'Eligible Users',
        };
    }
}
