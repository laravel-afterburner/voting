<?php

namespace Afterburner\Voting\Enums;

enum VoteVisibility: string
{
    case Secret = 'secret';
    case VisibleAfterClose = 'visible_after_close';
    case VisibleRealtime = 'visible_realtime';

    public function label(): string
    {
        return match ($this) {
            self::Secret => 'Secret',
            self::VisibleAfterClose => 'Visible After Close',
            self::VisibleRealtime => 'Visible Realtime',
        };
    }
}
