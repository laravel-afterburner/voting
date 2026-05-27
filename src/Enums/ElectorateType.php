<?php

namespace Afterburner\Voting\Enums;

enum ElectorateType: string
{
    case AllMembers = 'all_members';
    case Council = 'council';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AllMembers => 'All Members',
            self::Council => 'Council',
            self::Custom => 'Custom',
        };
    }
}
