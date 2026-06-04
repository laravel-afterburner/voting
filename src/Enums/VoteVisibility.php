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
            self::Secret => 'Confidential',
            self::VisibleAfterClose => 'Visible After Close',
            self::VisibleRealtime => 'Visible Realtime',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Secret => 'Individual choices stay hidden. After close, only totals are shown — not who voted for what.',
            self::VisibleAfterClose => 'Individual votes appear in results only after the ballot closes.',
            self::VisibleRealtime => 'Individual votes are visible in results while the ballot is still open.',
        };
    }

    public function isConfidential(): bool
    {
        return $this === self::Secret;
    }
}
